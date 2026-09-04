<?php

namespace Tests\Feature;

use App\Models\PhoneNumber;
use App\Models\TwilioAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\Leads\Models\Lead;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\ContactTag;
use App\Modules\Voice\Jobs\ProcessVoiceCampaignCallJob;
use App\Modules\Voice\Models\VoiceAgent;
use App\Modules\Voice\Models\VoiceCampaign;
use App\Modules\Voice\Models\VoiceCampaignRecipient;
use App\Modules\Voice\Services\SmartVoiceQueueService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SmartVoiceQueueTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;
    private Workspace $workspaceA;
    private User $userB;
    private Workspace $workspaceB;
    private VoiceAgent $agentA;
    private VoiceCampaign $campaignA;
    private Contact $contactHot;
    private Contact $contactOptOut;
    private SmartVoiceQueueService $queueService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspaceA = Workspace::create([
            'name' => 'Workspace A Smart Queue',
            'slug' => 'workspace-a-smart-queue',
            'service_type' => 'whatsapp_voice',
            'status' => 'active',
        ]);

        $this->userA = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
            'role' => 'client',
        ]);

        $this->workspaceB = Workspace::create([
            'name' => 'Workspace B Smart Queue',
            'slug' => 'workspace-b-smart-queue',
            'service_type' => 'whatsapp_voice',
            'status' => 'active',
        ]);

        $this->userB = User::factory()->create([
            'workspace_id' => $this->workspaceB->id,
            'role' => 'client',
        ]);

        $this->queueService = app(SmartVoiceQueueService::class);

        // Setup Agent & Campaign
        $this->agentA = VoiceAgent::create([
            'workspace_id' => $this->workspaceA->id,
            'name' => 'Smart Queue Agent',
            'status' => 'active',
            'provider' => 'twilio',
            'voice_id' => 'Polly.Aditi',
            'language' => 'en-US',
            'tone' => 'professional',
        ]);

        $this->campaignA = VoiceCampaign::create([
            'workspace_id' => $this->workspaceA->id,
            'name' => 'Smart Inbound Nurture',
            'type' => 'lead_followup',
            'voice_agent_id' => $this->agentA->id,
            'caller_id_number' => '+12125550188',
            'status' => 'running',
            'calling_days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
            'calling_start_time' => '00:00',
            'calling_end_time' => '23:59',
            'max_attempts' => 3,
            'compliance_confirmed' => true,
        ]);

        // Setup Hot Contact with Lead score 90
        $this->contactHot = Contact::create([
            'workspace_id' => $this->workspaceA->id,
            'first_name' => 'Amit',
            'last_name' => 'Verma',
            'phone_e164' => '+919876543215',
            'lead_score' => 90,
            'priority' => 'high',
            'status' => 'active',
        ]);

        // Setup Opted-Out Contact
        $this->contactOptOut = Contact::create([
            'workspace_id' => $this->workspaceA->id,
            'first_name' => 'DoNot',
            'last_name' => 'CallMe',
            'phone_e164' => '+919876543299',
            'marketing_opt_out' => true,
            'status' => 'active',
        ]);
    }

    public function test_smart_queue_dashboard_renders_with_stats(): void
    {
        $res = $this->actingAs($this->userA)->get(route('client.voice.queue.index'));
        $res->assertOk();
        $res->assertInertia(fn ($page) =>
            $page->component('Voice/Queue/Index')
                ->has('queueItems')
                ->has('stats')
                ->has('campaigns')
        );
    }

    public function test_eligibility_engine_excludes_opted_out_and_invalid_numbers(): void
    {
        // 1. Opted-out contact
        $res1 = $this->queueService->evaluateEligibility($this->contactOptOut, $this->campaignA);
        $this->assertFalse($res1['eligible']);
        $this->assertEquals('opted_out', $res1['reason']);

        // 2. Invalid phone number
        $invalidContact = Contact::create([
            'workspace_id' => $this->workspaceA->id,
            'first_name' => 'Bad',
            'last_name' => 'Phone',
            'phone_e164' => '123',
            'status' => 'active',
        ]);
        $res2 = $this->queueService->evaluateEligibility($invalidContact, $this->campaignA);
        $this->assertFalse($res2['eligible']);
        $this->assertEquals('invalid_phone', $res2['reason']);

        // 3. Eligible hot contact
        $res3 = $this->queueService->evaluateEligibility($this->contactHot, $this->campaignA);
        $this->assertTrue($res3['eligible']);
        $this->assertNull($res3['reason']);
    }

    public function test_priority_engine_scores_hot_leads_and_callbacks(): void
    {
        // Hot Lead (Score 90)
        $priorityHot = $this->queueService->calculatePriority($this->contactHot, $this->campaignA);
        $this->assertEquals('high', $priorityHot['level']);
        $this->assertEquals(90, $priorityHot['score']);
        $this->assertEquals('hot_lead', $priorityHot['reason']);

        // Callback Context (Score 100)
        $priorityCallback = $this->queueService->calculatePriority($this->contactHot, $this->campaignA, ['is_callback' => true]);
        $this->assertEquals('high', $priorityCallback['level']);
        $this->assertEquals(100, $priorityCallback['score']);
        $this->assertEquals('callback_requested', $priorityCallback['reason']);
    }

    public function test_reschedule_callback_promotes_priority(): void
    {
        $recipient = VoiceCampaignRecipient::create([
            'workspace_id' => $this->workspaceA->id,
            'voice_campaign_id' => $this->campaignA->id,
            'contact_id' => $this->contactHot->id,
            'phone_e164' => $this->contactHot->phone_e164,
            'status' => 'pending',
            'priority_level' => 'low',
            'priority_score' => 10,
        ]);

        $callbackTime = now()->addHours(3)->toDateTimeString();

        $res = $this->actingAs($this->userA)->post(route('client.voice.queue.callback', $recipient->id), [
            'callback_time' => $callbackTime,
            'notes' => 'Customer requested call in 3 hours.',
        ]);

        $res->assertRedirect();
        $recipient->refresh();

        $this->assertEquals('scheduled', $recipient->status);
        $this->assertTrue($recipient->is_callback);
        $this->assertEquals('high', $recipient->priority_level);
        $this->assertEquals(100, $recipient->priority_score);
        $this->assertEquals('callback_requested', $recipient->queue_reason);
    }

    public function test_dial_now_dispatches_instant_call_job(): void
    {
        Queue::fake();

        $recipient = VoiceCampaignRecipient::create([
            'workspace_id' => $this->workspaceA->id,
            'voice_campaign_id' => $this->campaignA->id,
            'contact_id' => $this->contactHot->id,
            'phone_e164' => $this->contactHot->phone_e164,
            'status' => 'pending',
        ]);

        $res = $this->actingAs($this->userA)->post(route('client.voice.queue.dial', $recipient->id));
        $res->assertRedirect();

        Queue::assertPushed(ProcessVoiceCampaignCallJob::class, function ($job) use ($recipient) {
            return $job->recipientId === $recipient->id;
        });

        $this->assertEquals('queued', $recipient->fresh()->status);
        $this->assertEquals(100, $recipient->fresh()->priority_score);
    }

    public function test_exclude_and_requeue_recipient(): void
    {
        $recipient = VoiceCampaignRecipient::create([
            'workspace_id' => $this->workspaceA->id,
            'voice_campaign_id' => $this->campaignA->id,
            'contact_id' => $this->contactHot->id,
            'phone_e164' => $this->contactHot->phone_e164,
            'status' => 'pending',
        ]);

        // Exclude
        $res = $this->actingAs($this->userA)->post(route('client.voice.queue.exclude', $recipient->id), [
            'reason' => 'opted_out',
        ]);
        $res->assertRedirect();
        $recipient->refresh();
        $this->assertEquals('skipped', $recipient->status);
        $this->assertEquals('opted_out', $recipient->exclusion_reason);

        // Re-enqueue
        $res2 = $this->actingAs($this->userA)->post(route('client.voice.queue.requeue', $recipient->id));
        $res2->assertRedirect();
        $recipient->refresh();
        $this->assertEquals('pending', $recipient->status);
        $this->assertNull($recipient->exclusion_reason);
    }

    public function test_atomic_queue_locking_prevents_duplicate_processing(): void
    {
        $recipient1 = VoiceCampaignRecipient::create([
            'workspace_id' => $this->workspaceA->id,
            'voice_campaign_id' => $this->campaignA->id,
            'contact_id' => $this->contactHot->id,
            'phone_e164' => $this->contactHot->phone_e164,
            'status' => 'pending',
            'priority_score' => 90,
        ]);

        $batch1 = $this->queueService->fetchAndLockNextBatch($this->workspaceA->id, 5);
        $this->assertCount(1, $batch1);
        $this->assertEquals($recipient1->id, $batch1->first()->id);
        $this->assertNotNull($recipient1->fresh()->locked_at);

        // Second worker tries to fetch the same batch immediately -> should be empty because it is locked
        $batch2 = $this->queueService->fetchAndLockNextBatch($this->workspaceA->id, 5);
        $this->assertCount(0, $batch2);
    }

    public function test_workspace_isolation_blocks_unauthorized_queue_actions(): void
    {
        $recipientA = VoiceCampaignRecipient::create([
            'workspace_id' => $this->workspaceA->id,
            'voice_campaign_id' => $this->campaignA->id,
            'contact_id' => $this->contactHot->id,
            'phone_e164' => $this->contactHot->phone_e164,
            'status' => 'pending',
        ]);

        // User B attempts to dial User A's recipient
        $res = $this->actingAs($this->userB)->post(route('client.voice.queue.dial', $recipientA->id));
        $res->assertForbidden();

        // User B attempts to exclude User A's recipient
        $res2 = $this->actingAs($this->userB)->post(route('client.voice.queue.exclude', $recipientA->id), [
            'reason' => 'blocked',
        ]);
        $res2->assertForbidden();
    }
}
