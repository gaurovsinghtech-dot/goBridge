<?php

namespace Tests\Feature;

use App\Models\PhoneNumber;
use App\Models\TwilioAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\AI\Models\AiKnowledgeBase;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\ContactTag;
use App\Modules\Voice\Jobs\DispatchVoiceCampaignCallsJob;
use App\Modules\Voice\Jobs\ProcessVoiceCampaignCallJob;
use App\Modules\Voice\Models\VoiceAgent;
use App\Modules\Voice\Models\VoiceCampaign;
use App\Modules\Voice\Models\VoiceCampaignRecipient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AiVoiceCampaignsTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;
    private Workspace $workspaceA;
    private User $userB;
    private Workspace $workspaceB;
    private VoiceAgent $agentA;
    private PhoneNumber $numberA;
    private Contact $contactA1;
    private Contact $contactA2;
    private ContactTag $tagVip;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspaceA = Workspace::create([
            'name' => 'Workspace A Campaigns',
            'slug' => 'workspace-a-campaigns',
            'service_type' => 'whatsapp_voice',
            'status' => 'active',
        ]);

        $this->userA = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
            'role' => 'client',
        ]);

        $this->workspaceB = Workspace::create([
            'name' => 'Workspace B Campaigns',
            'slug' => 'workspace-b-campaigns',
            'service_type' => 'whatsapp_voice',
            'status' => 'active',
        ]);

        $this->userB = User::factory()->create([
            'workspace_id' => $this->workspaceB->id,
            'role' => 'client',
        ]);

        // Setup Agent & Number
        $this->agentA = VoiceAgent::create([
            'workspace_id' => $this->workspaceA->id,
            'name' => 'Outbound Qualification Agent',
            'status' => 'active',
            'provider' => 'twilio',
            'voice_id' => 'Polly.Joanna',
            'language' => 'en-US',
            'tone' => 'sales',
            'greeting_message' => 'Hello! This is Growbridge Connect calling regarding your inquiry.',
            'human_transfer_number' => '+919876543210',
        ]);

        $this->numberA = PhoneNumber::create([
            'workspace_id' => $this->workspaceA->id,
            'phone_number' => '+12125550199',
            'country' => 'US',
            'status' => 'active',
            'assigned_ai_agent_id' => $this->agentA->id,
        ]);

        // Setup Twilio Credentials
        TwilioAccount::create([
            'workspace_id' => $this->workspaceA->id,
            'twilio_account_sid' => 'AC1234567890abcdef',
            'auth_token' => 'secret_token_123',
            'status' => 'active',
            'metadata' => [
                'from_number' => '+12125550199',
            ],
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'https://api.twilio.com/*' => \Illuminate\Support\Facades\Http::response([
                'sid' => 'CA1234567890abcdef',
                'status' => 'queued',
            ], 200),
        ]);

        // Setup CRM Contacts & Tags
        $this->tagVip = ContactTag::create([
            'workspace_id' => $this->workspaceA->id,
            'name' => 'VIP Lead',
            'color' => '#f59e0b',
        ]);

        $this->contactA1 = Contact::create([
            'workspace_id' => $this->workspaceA->id,
            'first_name' => 'Rahul',
            'last_name' => 'Sharma',
            'phone_e164' => '+919876543211',
            'status' => 'active',
        ]);
        $this->contactA1->tags()->sync([$this->tagVip->id]);

        $this->contactA2 = Contact::create([
            'workspace_id' => $this->workspaceA->id,
            'first_name' => 'Priya',
            'last_name' => 'Patel',
            'phone_e164' => '+919876543212',
            'status' => 'active',
        ]);
    }

    public function test_voice_campaigns_index_screen_renders(): void
    {
        $res = $this->actingAs($this->userA)->get(route('client.voice.campaigns.index'));
        $res->assertOk();
        $res->assertInertia(fn ($page) =>
            $page->component('Voice/Campaigns/Index')
                ->has('campaigns')
                ->has('stats')
        );
    }

    public function test_voice_campaign_create_wizard_renders(): void
    {
        $res = $this->actingAs($this->userA)->get(route('client.voice.campaigns.create'));
        $res->assertOk();
        $res->assertInertia(fn ($page) =>
            $page->component('Voice/Campaigns/Create')
                ->has('agents')
                ->has('phoneNumbers')
                ->has('tags')
        );
    }

    public function test_store_voice_campaign_with_audience_and_rules(): void
    {
        Queue::fake();

        $res = $this->actingAs($this->userA)->post(route('client.voice.campaigns.store'), [
            'name' => 'VIP Follow-up Q3',
            'type' => 'lead_followup',
            'description' => 'Targeting warm VIP leads for demo scheduling.',
            'voice_agent_id' => $this->agentA->id,
            'phone_number_id' => $this->numberA->id,
            'caller_id_number' => '+12125550199',
            'timezone' => 'Asia/Kolkata',
            'calling_days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
            'calling_start_time' => '09:00',
            'calling_end_time' => '18:00',
            'max_attempts' => 3,
            'retry_delay_hours' => 24,
            'call_timeout_sec' => 30,
            'max_duration_sec' => 600,
            'concurrent_limit' => 2,
            'daily_limit' => 100,
            'compliance_confirmed' => true,
            'ai_disclosure_enabled' => true,
            'whatsapp_followup_enabled' => true,
            'audience_type' => 'tags',
            'selected_tags' => [$this->tagVip->id],
            'start_now' => true,
        ]);

        $res->assertRedirect();

        $campaign = VoiceCampaign::where('workspace_id', $this->workspaceA->id)
            ->where('name', 'VIP Follow-up Q3')
            ->first();

        $this->assertNotNull($campaign);
        $this->assertEquals('running', $campaign->status);
        $this->assertEquals(1, $campaign->total_contacts); // Only contactA1 has tagVip
        $this->assertEquals(1, $campaign->recipients()->count());

        Queue::assertPushed(DispatchVoiceCampaignCallsJob::class);
    }

    public function test_process_campaign_call_updates_crm_and_outcomes(): void
    {
        $campaign = VoiceCampaign::create([
            'workspace_id' => $this->workspaceA->id,
            'name' => 'Demo Campaign',
            'type' => 'lead_followup',
            'voice_agent_id' => $this->agentA->id,
            'phone_number_id' => $this->numberA->id,
            'caller_id_number' => '+12125550199',
            'status' => 'running',
            'max_attempts' => 3,
            'retry_delay_hours' => 24,
            'compliance_confirmed' => true,
            'total_contacts' => 1,
        ]);

        $recipient = VoiceCampaignRecipient::create([
            'workspace_id' => $this->workspaceA->id,
            'voice_campaign_id' => $campaign->id,
            'contact_id' => $this->contactA1->id,
            'phone_e164' => $this->contactA1->phone_e164,
            'contact_name' => 'Rahul Sharma',
            'status' => 'pending',
            'attempts_count' => 0,
            'max_attempts' => 3,
        ]);

        // Process call job
        $job = new ProcessVoiceCampaignCallJob($recipient->id);
        $job->handle(app(\App\Modules\Voice\Services\VoiceDriverManager::class));

        $recipient->refresh();
        $campaign->refresh();

        $this->assertNull($recipient->error_message, $recipient->error_message ?? '');
        $this->assertEquals('completed', $recipient->status);
        $this->assertEquals('interested', $recipient->call_outcome);
        $this->assertEquals('hot', $recipient->lead_score);
        $this->assertNotNull($recipient->voice_call_id);

        $this->assertEquals(1, $campaign->completed_calls);
        $this->assertEquals(1, $campaign->interested_calls);
        $this->assertEquals('completed', $campaign->status); // All recipients finished

        // Verify CRM Tags attached
        $this->assertTrue($this->contactA1->fresh()->tags()->where('name', 'Hot Lead')->exists());
    }

    public function test_pause_and_stop_voice_campaign(): void
    {
        $campaign = VoiceCampaign::create([
            'workspace_id' => $this->workspaceA->id,
            'name' => 'Pause Test Campaign',
            'voice_agent_id' => $this->agentA->id,
            'status' => 'running',
            'compliance_confirmed' => true,
        ]);

        // Pause
        $res = $this->actingAs($this->userA)->post(route('client.voice.campaigns.pause', $campaign->uuid));
        $res->assertRedirect();
        $this->assertEquals('paused', $campaign->fresh()->status);

        // Resume / Start
        $res2 = $this->actingAs($this->userA)->post(route('client.voice.campaigns.start', $campaign->uuid));
        $res2->assertRedirect();
        $this->assertEquals('running', $campaign->fresh()->status);

        // Stop
        $res3 = $this->actingAs($this->userA)->post(route('client.voice.campaigns.stop', $campaign->uuid));
        $res3->assertRedirect();
        $this->assertEquals('cancelled', $campaign->fresh()->status);
    }

    public function test_campaign_analytics_endpoint(): void
    {
        $campaign = VoiceCampaign::create([
            'workspace_id' => $this->workspaceA->id,
            'name' => 'Analytics Campaign',
            'voice_agent_id' => $this->agentA->id,
            'status' => 'completed',
            'total_contacts' => 10,
            'completed_calls' => 10,
            'answered_calls' => 8,
            'interested_calls' => 5,
            'qualified_calls' => 4,
            'compliance_confirmed' => true,
        ]);

        $res = $this->actingAs($this->userA)->getJson(route('client.voice.campaigns.analytics', $campaign->uuid));
        $res->assertOk();

        $data = $res->json();
        $this->assertEquals(10, $data['total_contacts']);
        $this->assertEquals(10, $data['completed_calls']);
        $this->assertEquals(80.0, $data['answer_rate']);
    }

    public function test_workspace_isolation_blocks_unauthorized_access(): void
    {
        $campaign = VoiceCampaign::create([
            'workspace_id' => $this->workspaceA->id,
            'name' => 'Private Campaign A',
            'voice_agent_id' => $this->agentA->id,
            'status' => 'draft',
            'compliance_confirmed' => true,
        ]);

        // User B cannot view User A's campaign
        $res = $this->actingAs($this->userB)->get(route('client.voice.campaigns.show', $campaign->uuid));
        $res->assertForbidden();

        // User B cannot start User A's campaign
        $res2 = $this->actingAs($this->userB)->post(route('client.voice.campaigns.start', $campaign->uuid));
        $res2->assertForbidden();
    }
}
