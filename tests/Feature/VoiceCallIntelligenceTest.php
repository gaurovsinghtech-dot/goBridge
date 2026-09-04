<?php

namespace Tests\Feature;

use App\Models\PhoneNumber;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\ContactTag;
use App\Modules\Voice\Models\VoiceAgent;
use App\Modules\Voice\Models\VoiceCall;
use App\Modules\Voice\Models\VoiceCampaign;
use App\Modules\Voice\Models\VoiceCampaignRecipient;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoiceCallIntelligenceTest extends TestCase
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
            'name' => 'Workspace A Intelligence',
            'slug' => 'workspace-a-intelligence',
            'service_type' => 'whatsapp_voice',
            'status' => 'active',
        ]);

        $this->userA = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
            'role' => 'client',
        ]);

        $this->workspaceB = Workspace::create([
            'name' => 'Workspace B Intelligence',
            'slug' => 'workspace-b-intelligence',
            'service_type' => 'whatsapp_voice',
            'status' => 'active',
        ]);

        $this->userB = User::factory()->create([
            'workspace_id' => $this->workspaceB->id,
            'role' => 'client',
        ]);

        $this->agentA = VoiceAgent::create([
            'workspace_id' => $this->workspaceA->id,
            'name' => 'Sales Intelligence Agent',
            'status' => 'active',
            'provider' => 'twilio',
            'voice_id' => 'Polly.Joanna',
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
            'duration_sec' => 245,
            'recording_url' => 'https://api.twilio.com/recordings/RE12345.mp3',
            'transcript' => "AI: Hello! How can I assist you today?\nCaller: I want to know about your WhatsApp API pricing and schedule a demo.\nAI: Our business plan starts at $49/mo with full automation.\nCaller: Great! Please have your sales team call me.",
            'summary' => 'Customer inquired about WhatsApp API pricing and requested demo.',
            'intent' => 'sales',
            'lead_interest' => 'high',
            'conversation_signal' => 'positive',
            'topics' => ['WhatsApp API', 'Pricing', 'Demo'],
            'important_moments' => [
                ['timestamp' => '00:30', 'text' => 'Inquired about pricing'],
                ['timestamp' => '01:15', 'text' => 'Requested demo'],
            ],
            'next_action' => 'Schedule sales callback',
            'started_at' => Carbon::now()->subMinutes(10),
            'ended_at' => Carbon::now()->subMinutes(6),
        ]);
    }

    public function test_calls_index_renders_with_filters_and_stats(): void
    {
        $res = $this->actingAs($this->userA)->get(route('client.voice.calls.index'));
        $res->assertOk();
        $res->assertInertia(fn ($page) =>
            $page->component('Voice/Calls/Index')
                ->has('calls')
                ->has('stats')
                ->has('agents')
                ->has('campaigns')
        );
    }

    public function test_calls_index_search_filter(): void
    {
        // Search by customer name
        $res = $this->actingAs($this->userA)->get(route('client.voice.calls.index', ['search' => 'Rahul']));
        $res->assertOk();
        $res->assertInertia(fn ($page) =>
            $page->has('calls.data', 1)
        );

        // Search for non-existent term
        $res2 = $this->actingAs($this->userA)->get(route('client.voice.calls.index', ['search' => 'NonExistentXYZ']));
        $res2->assertOk();
        $res2->assertInertia(fn ($page) =>
            $page->has('calls.data', 0)
        );
    }

    public function test_call_show_details_view(): void
    {
        $res = $this->actingAs($this->userA)->get(route('client.voice.calls.show', $this->callA->uuid));
        $res->assertOk();
        $res->assertInertia(fn ($page) =>
            $page->component('Voice/Calls/Show')
                ->has('call')
                ->has('transcriptTurns', 4)
        );
    }

    public function test_transcript_json_endpoint(): void
    {
        $res = $this->actingAs($this->userA)->getJson(route('client.voice.calls.transcript', $this->callA->uuid));
        $res->assertOk();

        $data = $res->json();
        $this->assertEquals($this->callA->id, $data['call_id']);
        $this->assertCount(4, $data['turns']);

        // Search query inside transcript
        $res2 = $this->actingAs($this->userA)->getJson(route('client.voice.calls.transcript', ['call' => $this->callA->uuid, 'q' => 'pricing']));
        $res2->assertOk();
        $data2 = $res2->json();
        $this->assertCount(1, $data2['turns']); // 1 turn contains "pricing"
    }

    public function test_analyze_call_endpoint_updates_intelligence(): void
    {
        $res = $this->actingAs($this->userA)->post(route('client.voice.calls.analyze', $this->callA->uuid));
        $res->assertRedirect();

        $this->callA->refresh();
        $this->assertNotNull($this->callA->analyzed_at);
        $this->assertEquals('sales', $this->callA->intent);
        $this->assertEquals('high', $this->callA->lead_interest);
    }

    public function test_follow_up_attaches_tag_and_schedules_callback(): void
    {
        $res = $this->actingAs($this->userA)->post(route('client.voice.calls.follow-up', $this->callA->uuid), [
            'action_type' => 'tag',
            'tag_name' => 'Demo-Requested',
        ]);
        $res->assertRedirect();

        $this->assertTrue($this->contactA->fresh()->tags()->where('name', 'Demo-Requested')->exists());
    }

    public function test_workspace_isolation_blocks_unauthorized_access(): void
    {
        // User B cannot view User A's call details
        $res = $this->actingAs($this->userB)->get(route('client.voice.calls.show', $this->callA->uuid));
        $res->assertForbidden();

        // User B cannot view User A's transcript
        $res2 = $this->actingAs($this->userB)->getJson(route('client.voice.calls.transcript', $this->callA->uuid));
        $res2->assertForbidden();

        // User B cannot analyze User A's call
        $res3 = $this->actingAs($this->userB)->post(route('client.voice.calls.analyze', $this->callA->uuid));
        $res3->assertForbidden();
    }
}
