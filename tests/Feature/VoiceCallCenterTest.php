<?php

namespace Tests\Feature;

use App\Models\PhoneNumber;
use App\Models\TwilioAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\Shared\Models\Contact;
use App\Modules\Voice\Models\VoiceAgent;
use App\Modules\Voice\Models\VoiceCall;
use App\Modules\Voice\Models\VoiceCampaign;
use App\Modules\Voice\Models\VoiceCampaignRecipient;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoiceCallCenterTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;
    private Workspace $workspaceA;
    private User $userB;
    private Workspace $workspaceB;
    private VoiceAgent $agentA;
    private Contact $contactA;
    private VoiceCall $activeCallA;
    private VoiceCall $handoffCallA;
    private VoiceCall $completedCallA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspaceA = Workspace::create([
            'name' => 'Workspace A Call Center',
            'slug' => 'workspace-a-call-center',
            'service_type' => 'whatsapp_voice',
            'status' => 'active',
        ]);

        $this->userA = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
            'role' => 'client',
        ]);

        $this->workspaceB = Workspace::create([
            'name' => 'Workspace B Call Center',
            'slug' => 'workspace-b-call-center',
            'service_type' => 'whatsapp_voice',
            'status' => 'active',
        ]);

        $this->userB = User::factory()->create([
            'workspace_id' => $this->workspaceB->id,
            'role' => 'client',
        ]);

        $this->agentA = VoiceAgent::create([
            'workspace_id' => $this->workspaceA->id,
            'name' => 'Inbound Support Specialist',
            'status' => 'active',
            'provider' => 'twilio',
            'voice_id' => 'Polly.Aditi',
            'language' => 'en-US',
            'tone' => 'professional',
            'human_transfer_number' => '+919876543210',
        ]);

        $this->contactA = Contact::create([
            'workspace_id' => $this->workspaceA->id,
            'first_name' => 'Rahul',
            'last_name' => 'Sharma',
            'phone_e164' => '+919876543211',
            'status' => 'active',
        ]);

        // 1. In-progress call
        $this->activeCallA = VoiceCall::create([
            'workspace_id' => $this->workspaceA->id,
            'voice_agent_id' => $this->agentA->id,
            'contact_id' => $this->contactA->id,
            'direction' => 'inbound',
            'provider' => 'twilio',
            'from_number' => '+919876543211',
            'to_number' => '+12125550199',
            'status' => 'in-progress',
            'duration_sec' => 124,
            'started_at' => Carbon::now()->subSeconds(124),
        ]);

        // 2. Active Handoff call
        $this->handoffCallA = VoiceCall::create([
            'workspace_id' => $this->workspaceA->id,
            'voice_agent_id' => $this->agentA->id,
            'contact_id' => $this->contactA->id,
            'direction' => 'inbound',
            'provider' => 'twilio',
            'from_number' => '+919876543211',
            'to_number' => '+12125550199',
            'status' => 'in-progress',
            'outcome' => 'human_handoff',
            'duration_sec' => 310,
            'started_at' => Carbon::now()->subSeconds(310),
        ]);

        // 3. Completed call
        $this->completedCallA = VoiceCall::create([
            'workspace_id' => $this->workspaceA->id,
            'voice_agent_id' => $this->agentA->id,
            'contact_id' => $this->contactA->id,
            'direction' => 'outbound',
            'provider' => 'twilio',
            'from_number' => '+12125550199',
            'to_number' => '+919876543211',
            'status' => 'completed',
            'outcome' => 'interested',
            'duration_sec' => 180,
            'started_at' => Carbon::now()->subMinutes(10),
            'ended_at' => Carbon::now()->subMinutes(7),
        ]);

        // Twilio provider account
        TwilioAccount::create([
            'workspace_id' => $this->workspaceA->id,
            'twilio_account_sid' => 'AC_test_123',
            'auth_token' => 'token_123',
            'status' => 'active',
        ]);
    }

    public function test_call_center_dashboard_renders_with_live_telemetry(): void
    {
        $res = $this->actingAs($this->userA)->get(route('client.voice.call-center'));
        $res->assertOk();
        $res->assertInertia(fn ($page) =>
            $page->component('Voice/CallCenter/Index')
                ->has('activeCalls')
                ->has('activeHandoffs')
                ->has('agents')
                ->has('queueSummary')
                ->has('todayStats')
                ->has('providers')
                ->has('alerts')
        );
    }

    public function test_live_feed_endpoint_returns_json_for_polling(): void
    {
        $res = $this->actingAs($this->userA)->getJson(route('client.voice.call-center.live-feed'));
        $res->assertOk();

        $data = $res->json();
        $this->assertArrayHasKey('activeCalls', $data);
        $this->assertArrayHasKey('activeHandoffs', $data);
        $this->assertArrayHasKey('todayStats', $data);
        $this->assertArrayHasKey('queueSummary', $data);

        // Verify active calls count is 2 (in-progress and handoff), completed is excluded from active
        $this->assertCount(2, $data['activeCalls']);

        // Verify active handoffs count is 1
        $this->assertCount(1, $data['activeHandoffs']);

        // Verify today's calls count is 3
        $this->assertEquals(3, $data['todayStats']['total_calls']);
    }

    public function test_provider_status_connected_when_configured(): void
    {
        $res = $this->actingAs($this->userA)->getJson(route('client.voice.call-center.live-feed'));
        $res->assertOk();

        $data = $res->json();
        $twilio = collect($data['providers'])->firstWhere('provider', 'twilio');
        $this->assertNotNull($twilio);
        $this->assertEquals('connected', $twilio['status']);
    }

    public function test_workspace_isolation_protects_call_center_data(): void
    {
        // User B should see 0 active calls and 0 handoffs
        $res = $this->actingAs($this->userB)->getJson(route('client.voice.call-center.live-feed'));
        $res->assertOk();

        $data = $res->json();
        $this->assertCount(0, $data['activeCalls']);
        $this->assertCount(0, $data['activeHandoffs']);
        $this->assertEquals(0, $data['todayStats']['total_calls']);
    }
}
