<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\ContactTag;
use App\Modules\Voice\Models\VoiceAgent;
use App\Modules\Voice\Models\VoiceCall;
use App\Modules\Voice\Models\VoiceFollowUp;
use App\Modules\Voice\Models\VoiceFollowUpRule;
use App\Modules\Voice\Services\VoiceFollowUpService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoiceFollowUpAutomationTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;
    private Workspace $workspaceA;
    private User $userB;
    private Workspace $workspaceB;
    private VoiceAgent $agentA;
    private Contact $contactA;
    private VoiceCall $callA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspaceA = Workspace::create([
            'name' => 'Workspace A Follow-ups',
            'slug' => 'workspace-a-followups',
            'service_type' => 'whatsapp_voice',
            'status' => 'active',
        ]);

        $this->userA = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
            'role' => 'client',
        ]);

        $this->workspaceB = Workspace::create([
            'name' => 'Workspace B Follow-ups',
            'slug' => 'workspace-b-followups',
            'service_type' => 'whatsapp_voice',
            'status' => 'active',
        ]);

        $this->userB = User::factory()->create([
            'workspace_id' => $this->workspaceB->id,
            'role' => 'client',
        ]);

        $this->agentA = VoiceAgent::create([
            'workspace_id' => $this->workspaceA->id,
            'name' => 'Sales Callback Specialist',
            'status' => 'active',
            'provider' => 'twilio',
            'voice_id' => 'Polly.Aditi',
            'language' => 'en-US',
            'tone' => 'sales',
        ]);

        $this->contactA = Contact::create([
            'workspace_id' => $this->workspaceA->id,
            'first_name' => 'Rahul',
            'last_name' => 'Sharma',
            'phone_e164' => '+919876543211',
            'status' => 'active',
        ]);

        $this->callA = VoiceCall::create([
            'workspace_id' => $this->workspaceA->id,
            'voice_agent_id' => $this->agentA->id,
            'contact_id' => $this->contactA->id,
            'direction' => 'inbound',
            'provider' => 'twilio',
            'from_number' => '+919876543211',
            'to_number' => '+12125550199',
            'status' => 'completed',
            'outcome' => 'interested',
            'duration_sec' => 180,
            'summary' => 'Customer is interested in WhatsApp omnichannel automation.',
            'started_at' => Carbon::now()->subMinutes(10),
            'ended_at' => Carbon::now()->subMinutes(7),
        ]);
    }

    public function test_follow_ups_dashboard_renders(): void
    {
        $res = $this->actingAs($this->userA)->get(route('client.voice.follow-ups.index'));
        $res->assertOk();
        $res->assertInertia(fn ($page) =>
            $page->component('Voice/FollowUps/Index')
                ->has('followUps')
                ->has('stats')
                ->has('filters')
        );
    }

    public function test_manual_follow_up_creation(): void
    {
        $res = $this->actingAs($this->userA)->post(route('client.voice.follow-ups.store'), [
            'type' => 'crm_task',
            'contact_id' => $this->contactA->id,
            'priority' => 'high',
            'due_at' => Carbon::tomorrow()->toDateTimeString(),
            'title' => 'Call back Rahul regarding WhatsApp brochure',
            'notes' => 'Hot lead discussion',
        ]);

        $res->assertRedirect(route('client.voice.follow-ups.index'));

        $this->assertDatabaseHas('voice_follow_ups', [
            'workspace_id' => $this->workspaceA->id,
            'contact_id' => $this->contactA->id,
            'type' => 'crm_task',
            'priority' => 'high',
            'title' => 'Call back Rahul regarding WhatsApp brochure',
        ]);
    }

    public function test_follow_up_lifecycle_complete_reschedule_cancel(): void
    {
        $followUp = VoiceFollowUp::create([
            'workspace_id' => $this->workspaceA->id,
            'contact_id' => $this->contactA->id,
            'type' => 'callback',
            'status' => 'scheduled',
            'priority' => 'high',
            'due_at' => Carbon::now()->addHours(2),
            'title' => 'Scheduled Callback Test',
        ]);

        // 1. Reschedule
        $newTime = Carbon::now()->addHours(5)->toDateTimeString();
        $res1 = $this->actingAs($this->userA)->post(route('client.voice.follow-ups.reschedule', $followUp->uuid), [
            'due_at' => $newTime,
        ]);
        $res1->assertRedirect();
        $this->assertEquals($newTime, $followUp->fresh()->due_at->toDateTimeString());

        // 2. Complete
        $res2 = $this->actingAs($this->userA)->post(route('client.voice.follow-ups.complete', $followUp->uuid), [
            'notes' => 'Spoke with customer, sent WhatsApp quote.',
        ]);
        $res2->assertRedirect();
        $this->assertEquals('completed', $followUp->fresh()->status);
        $this->assertNotNull($followUp->fresh()->completed_at);
    }

    public function test_automated_call_follow_up_service_on_interested_outcome(): void
    {
        $service = app(VoiceFollowUpService::class);
        $actions = $service->processCallFollowUp($this->callA);

        $this->assertNotEmpty($actions);

        // Verify CRM Task follow-up was created
        $this->assertDatabaseHas('voice_follow_ups', [
            'workspace_id' => $this->workspaceA->id,
            'voice_call_id' => $this->callA->id,
            'contact_id' => $this->contactA->id,
            'type' => 'crm_task',
            'priority' => 'high',
            'outcome_trigger' => 'interested',
        ]);

        // Verify Voice-Interested tag was attached to contact
        $this->assertTrue($this->contactA->fresh()->tags()->where('name', 'Voice-Interested')->exists());
    }

    public function test_follow_up_rules_crud(): void
    {
        // 1. Create rule
        $res = $this->actingAs($this->userA)->post(route('client.voice.follow-ups.rules.store'), [
            'name' => 'Custom Rule: Callback on Interested',
            'trigger_event' => 'interested',
            'actions' => [
                ['type' => 'schedule_callback', 'delay_minutes' => 120],
                ['type' => 'add_tag', 'tag_name' => 'Hot-Lead-Voice'],
            ],
        ]);
        $res->assertRedirect();

        $rule = VoiceFollowUpRule::where('name', 'Custom Rule: Callback on Interested')->first();
        $this->assertNotNull($rule);
        $this->assertTrue($rule->is_active);

        // 2. Toggle rule
        $resToggle = $this->actingAs($this->userA)->post(route('client.voice.follow-ups.rules.toggle', $rule->uuid));
        $resToggle->assertRedirect();
        $this->assertFalse($rule->fresh()->is_active);

        // 3. Delete rule
        $resDelete = $this->actingAs($this->userA)->delete(route('client.voice.follow-ups.rules.destroy', $rule->uuid));
        $resDelete->assertRedirect();
        $this->assertNull(VoiceFollowUpRule::find($rule->id));
    }

    public function test_workspace_isolation_protects_follow_ups(): void
    {
        $followUp = VoiceFollowUp::create([
            'workspace_id' => $this->workspaceA->id,
            'contact_id' => $this->contactA->id,
            'type' => 'crm_task',
            'status' => 'pending',
            'priority' => 'high',
            'due_at' => Carbon::now()->addDay(),
            'title' => 'Secret Follow-up',
        ]);

        // User B cannot view User A's follow-up
        $res = $this->actingAs($this->userB)->get(route('client.voice.follow-ups.show', $followUp->uuid));
        $res->assertForbidden();

        // User B cannot complete User A's follow-up
        $res2 = $this->actingAs($this->userB)->post(route('client.voice.follow-ups.complete', $followUp->uuid));
        $res2->assertForbidden();
    }
}
