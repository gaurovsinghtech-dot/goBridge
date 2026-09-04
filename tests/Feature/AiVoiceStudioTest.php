<?php

namespace Tests\Feature;

use App\Models\PhoneNumber;
use App\Models\TwilioAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\AI\Models\AiKnowledgeBase;
use App\Modules\Voice\Models\VoiceAgent;
use App\Services\AI\AiKnowledgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiVoiceStudioTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;
    private Workspace $workspaceA;
    private User $userB;
    private Workspace $workspaceB;
    private VoiceAgent $agentA;
    private PhoneNumber $numberA;
    private AiKnowledgeBase $kbA;
    private AiKnowledgeService $knowledgeService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspaceA = Workspace::create([
            'name' => 'Workspace A Studio',
            'slug' => 'workspace-a-studio',
            'service_type' => 'whatsapp_voice',
            'status' => 'active',
        ]);

        $this->userA = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
            'role' => 'client',
        ]);

        $this->workspaceB = Workspace::create([
            'name' => 'Workspace B Studio',
            'slug' => 'workspace-b-studio',
            'service_type' => 'whatsapp_voice',
            'status' => 'active',
        ]);

        $this->userB = User::factory()->create([
            'workspace_id' => $this->workspaceB->id,
            'role' => 'client',
        ]);

        $this->knowledgeService = app(AiKnowledgeService::class);

        // Setup Knowledge Base
        $this->kbA = AiKnowledgeBase::create([
            'workspace_id' => $this->workspaceA->id,
            'name' => 'Tech Corp Knowledge',
            'category' => 'company',
            'status' => 'active',
        ]);

        $this->knowledgeService->ingestBusinessProfile($this->kbA, [
            'name' => 'Tech Corp Solutions',
            'business_hours' => '9:00 AM – 6:00 PM Monday to Friday',
            'pricing' => 'Enterprise package is ₹25,000/mo',
        ]);

        // Setup Voice Agent
        $this->agentA = VoiceAgent::create([
            'workspace_id' => $this->workspaceA->id,
            'name' => 'Sales Studio Agent',
            'status' => 'draft',
            'provider' => 'twilio',
            'voice_id' => 'Polly.Aditi',
            'language' => 'en-US',
            'tone' => 'professional',
            'greeting_message' => 'Hello! Welcome to Tech Corp. How can I help you?',
            'ai_kb_id' => $this->kbA->id,
            'human_transfer_number' => '+919876543210',
            'max_duration_sec' => 600,
        ]);

        // Setup Phone Number
        $this->numberA = PhoneNumber::create([
            'workspace_id' => $this->workspaceA->id,
            'phone_number' => '+12125550188',
            'country' => 'US',
            'status' => 'active',
            'assigned_ai_agent_id' => $this->agentA->id,
        ]);
    }

    public function test_voice_studio_screen_renders_with_config_and_voices(): void
    {
        $res = $this->actingAs($this->userA)->get(route('client.ai.voice-studio.index'));
        $res->assertOk();
        $res->assertInertia(fn ($page) =>
            $page->component('AI/VoiceStudio/Index')
                ->has('agents')
                ->has('selectedAgent')
                ->has('supportedVoices')
                ->has('phoneNumbers')
                ->has('knowledgeBases')
                ->has('checklist')
        );
    }

    public function test_save_voice_agent_updates_configuration(): void
    {
        $res = $this->actingAs($this->userA)->post(route('client.ai.voice-studio.save'), [
            'id' => $this->agentA->id,
            'name' => 'Executive Voice Assistant',
            'description' => 'Qualify VIP sales leads.',
            'status' => 'draft',
            'provider' => 'twilio',
            'voice_id' => 'Polly.Joanna',
            'language' => 'en-US',
            'tone' => 'friendly',
            'greeting_message' => 'Hello! How may I assist your business today?',
            'ai_kb_id' => $this->kbA->id,
            'human_transfer_number' => '+919876543210',
            'max_duration_sec' => 900,
            'phone_number_id' => $this->numberA->id,
            'call_flow' => [
                'objective' => 'sales',
                'objective_description' => 'Convert inbound queries into qualified meetings.',
                'response_style' => 'short',
                'allow_interruption' => true,
                'ask_one_question' => true,
                'confirm_important_info' => true,
                'max_ai_turns' => 30,
                'recording_enabled' => true,
                'recording_notice' => 'This call is recorded for quality.',
                'handoff_triggers' => ['customer_request', 'complaint'],
                'fallback_action' => 'whatsapp_callback',
            ],
            'working_hours' => [
                'schedule' => [
                    ['day' => 'Monday', 'enabled' => true, 'start' => '09:00', 'end' => '18:00'],
                    ['day' => 'Tuesday', 'enabled' => true, 'start' => '09:00', 'end' => '18:00'],
                ],
                'outside_action' => 'whatsapp_callback',
            ],
        ]);

        $res->assertRedirect();
        $this->agentA->refresh();

        $this->assertEquals('Executive Voice Assistant', $this->agentA->name);
        $this->assertEquals('Polly.Joanna', $this->agentA->voice_id);
        $this->assertEquals('friendly', $this->agentA->tone);
        $this->assertEquals(900, $this->agentA->max_duration_sec);
        $this->assertEquals('sales', $this->agentA->call_flow_json['objective']);
    }

    public function test_activation_blocked_when_provider_not_connected(): void
    {
        // No TwilioAccount exists yet for workspaceA
        $res = $this->actingAs($this->userA)->post(route('client.ai.voice-studio.activate', $this->agentA->uuid));

        $res->assertRedirect();
        $res->assertSessionHas('error');
        $this->assertEquals('draft', $this->agentA->fresh()->status);
    }

    public function test_activation_succeeds_when_all_checklist_items_valid(): void
    {
        // Connect Twilio
        TwilioAccount::create([
            'workspace_id' => $this->workspaceA->id,
            'twilio_account_sid' => 'AC1234567890abcdef',
            'auth_token' => 'secret_token_123',
            'status' => 'active',
        ]);

        $res = $this->actingAs($this->userA)->post(route('client.ai.voice-studio.activate', $this->agentA->uuid));

        $res->assertRedirect();
        $res->assertSessionHas('success');
        $this->assertEquals('active', $this->agentA->fresh()->status);
    }

    public function test_pause_voice_agent(): void
    {
        $this->agentA->update(['status' => 'active']);

        $res = $this->actingAs($this->userA)->post(route('client.ai.voice-studio.pause', $this->agentA->uuid));

        $res->assertRedirect();
        $res->assertSessionHas('success');
        $this->assertEquals('paused', $this->agentA->fresh()->status);
    }

    public function test_voice_simulator_answers_from_knowledge_base(): void
    {
        $res = $this->actingAs($this->userA)->postJson(route('client.ai.voice-studio.simulate', $this->agentA->uuid), [
            'message' => 'What are your business hours?',
        ]);

        $res->assertOk();
        $data = $res->json();

        $this->assertTrue($data['success']);
        $this->assertFalse($data['is_handoff']);
        $this->assertStringContainsString('9:00 AM', $data['response']);
    }

    public function test_voice_simulator_detects_handoff_request(): void
    {
        $res = $this->actingAs($this->userA)->postJson(route('client.ai.voice-studio.simulate', $this->agentA->uuid), [
            'message' => 'I want to talk to an agent please',
        ]);

        $res->assertOk();
        $data = $res->json();

        $this->assertTrue($data['success']);
        $this->assertTrue($data['is_handoff']);
        $this->assertStringContainsString('+919876543210', $data['response']);
    }

    public function test_workspace_isolation_blocks_access_to_other_workspaces(): void
    {
        // User B tries to view User A's voice agent in Studio
        $res = $this->actingAs($this->userB)->get(route('client.ai.voice-studio.show', $this->agentA->uuid));
        $res->assertForbidden();

        // User B tries to activate User A's voice agent
        $res2 = $this->actingAs($this->userB)->post(route('client.ai.voice-studio.activate', $this->agentA->uuid));
        $res2->assertForbidden();
    }
}
