<?php

namespace Tests\Feature;

use App\Models\PhoneNumber;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\AI\Models\AiDailyStat;
use App\Modules\AI\Models\AiKnowledgeBase;
use App\Modules\AI\Models\AiUnknownQuestion;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Voice\Jobs\GenerateVoiceCallSummaryJob;
use App\Modules\Voice\Models\VoiceAgent;
use App\Modules\Voice\Models\VoiceCall;
use App\Services\AI\AiKnowledgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiVoiceAgentAndTwilioIntegrationTest extends TestCase
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
            'name' => 'Workspace A Tech',
            'slug' => 'workspace-a-tech',
            'service_type' => 'whatsapp_voice',
            'status' => 'active',
        ]);

        $this->userA = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
            'role' => 'client',
        ]);

        $this->workspaceB = Workspace::create([
            'name' => 'Workspace B Retail',
            'slug' => 'workspace-b-retail',
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
            'name' => 'Tech Hub Voice Knowledge',
            'category' => 'company',
            'status' => 'active',
        ]);

        $this->knowledgeService->ingestBusinessProfile($this->kbA, [
            'name' => 'Tech Hub Electronics',
            'business_hours' => '9:00 AM to 9:00 PM',
            'address' => '456 Cyber Tower, Bangalore',
        ]);

        // Setup Voice Agent
        $this->agentA = VoiceAgent::create([
            'workspace_id' => $this->workspaceA->id,
            'name' => 'Sales Assistant',
            'status' => 'active',
            'language' => 'en-US',
            'ai_kb_id' => $this->kbA->id,
            'greeting_message' => 'Hello! Welcome to Tech Hub. How can I help you today?',
            'human_transfer_number' => '+919876543210',
        ]);

        // Setup Phone Number
        $this->numberA = PhoneNumber::create([
            'workspace_id' => $this->workspaceA->id,
            'phone_number' => '+12125550199',
            'country' => 'US',
            'friendly_name' => 'Sales Hotline',
            'status' => 'active',
            'voice_enabled' => true,
            'assigned_ai_agent_id' => $this->agentA->id,
            'handoff_number' => '+919876543210',
            'fallback_action' => 'whatsapp_callback',
        ]);
    }

    public function test_voice_settings_page_renders_with_config(): void
    {
        $res = $this->actingAs($this->userA)->get(route('client.voice.settings.index'));
        $res->assertOk();
        $res->assertInertia(fn ($page) => 
            $page->component('Voice/Settings')
                ->has('twilioConfig')
                ->has('phoneNumbers')
                ->has('agents')
        );
    }

    public function test_voice_settings_update_saves_credentials_and_handoff_rules(): void
    {
        $res = $this->actingAs($this->userA)->post(route('client.voice.settings.update'), [
            'account_sid' => 'ACtestmockaccount00000000000000000',
            'auth_token' => 'secret_token_123456',
            'default_from_number' => '+12125550199',
            'human_transfer_number' => '+919876543210',
            'fallback_action' => 'whatsapp_callback',
            'call_recording' => true,
        ]);

        $res->assertRedirect();
        $res->assertSessionHas('success');
    }

    public function test_inbound_voice_webhook_creates_call_and_crm_contact(): void
    {
        $res = $this->post(route('webhooks.voice.incoming', ['provider' => 'twilio']), [
            'To' => '+12125550199',
            'From' => '+919800011122',
            'CallSid' => 'CA_test_call_sid_123',
        ]);

        $res->assertOk();
        $this->assertStringContainsString('<Response>', $res->getContent());
        $this->assertStringContainsString('Tech Hub', $res->getContent());
        $this->assertStringContainsString('<Gather', $res->getContent());

        // Check VoiceCall record created
        $call = VoiceCall::where('provider_call_id', 'CA_test_call_sid_123')->first();
        $this->assertNotNull($call);
        $this->assertEquals($this->workspaceA->id, $call->workspace_id);
        $this->assertEquals('inbound', $call->direction);
        $this->assertEquals('+919800011122', $call->from_number);

        // Check CRM Contact created
        $contact = Contact::where('workspace_id', $this->workspaceA->id)
            ->where('phone_e164', '+919800011122')
            ->first();
        $this->assertNotNull($contact);
        $this->assertEquals($contact->id, $call->contact_id);
    }

    public function test_speech_gather_answers_business_knowledge(): void
    {
        $call = VoiceCall::create([
            'workspace_id' => $this->workspaceA->id,
            'phone_number_id' => $this->numberA->id,
            'voice_agent_id' => $this->agentA->id,
            'from_number' => '+919800011122',
            'to_number' => '+12125550199',
            'status' => 'in-progress',
        ]);

        $res = $this->post(route('webhooks.voice.gather', [
            'provider' => 'twilio',
            'call_uuid' => $call->uuid,
        ]), [
            'SpeechResult' => 'What are your business hours?',
        ]);

        $res->assertOk();
        $content = $res->getContent();

        $this->assertStringContainsString('9:00 AM to 9:00 PM', $content);
        $this->assertStringContainsString('<Gather', $content);

        // Verify transcript appended
        $call->refresh();
        $this->assertStringContainsString('What are your business hours?', $call->transcript);
        $this->assertStringContainsString('9:00 AM to 9:00 PM', $call->transcript);
    }

    public function test_speech_gather_unknown_question_fallback(): void
    {
        $call = VoiceCall::create([
            'workspace_id' => $this->workspaceA->id,
            'phone_number_id' => $this->numberA->id,
            'voice_agent_id' => $this->agentA->id,
            'from_number' => '+919800011122',
            'to_number' => '+12125550199',
            'status' => 'in-progress',
        ]);

        $res = $this->post(route('webhooks.voice.gather', [
            'provider' => 'twilio',
            'call_uuid' => $call->uuid,
        ]), [
            'SpeechResult' => 'Who is the President of France?',
        ]);

        $res->assertOk();
        $this->assertStringContainsString("specific information in my business knowledge", $res->getContent());

        // Verify unknown question recorded for analytics
        $q = AiUnknownQuestion::where('workspace_id', $this->workspaceA->id)
            ->where('question', 'like', '%President of France%')
            ->first();
        $this->assertNotNull($q);
    }

    public function test_speech_gather_triggers_human_handoff_and_transfer_dial(): void
    {
        $call = VoiceCall::create([
            'workspace_id' => $this->workspaceA->id,
            'phone_number_id' => $this->numberA->id,
            'voice_agent_id' => $this->agentA->id,
            'from_number' => '+919800011122',
            'to_number' => '+12125550199',
            'status' => 'in-progress',
        ]);

        $res = $this->post(route('webhooks.voice.gather', [
            'provider' => 'twilio',
            'call_uuid' => $call->uuid,
        ]), [
            'SpeechResult' => 'I need to speak with a human manager please',
        ]);

        $res->assertOk();
        $content = $res->getContent();

        $this->assertStringContainsString('<Dial>+919876543210</Dial>', $content);
        $this->assertEquals('transferred', $call->fresh()->outcome);
        $this->assertEquals('Customer requested human representative', $call->fresh()->handoff_reason);
    }

    public function test_no_agent_available_fallback_when_transfer_number_missing(): void
    {
        $this->agentA->update(['human_transfer_number' => null]);
        $this->numberA->update(['handoff_number' => null]);

        $call = VoiceCall::create([
            'workspace_id' => $this->workspaceA->id,
            'phone_number_id' => $this->numberA->id,
            'voice_agent_id' => $this->agentA->id,
            'from_number' => '+919800011122',
            'to_number' => '+12125550199',
            'status' => 'in-progress',
        ]);

        $res = $this->post(route('webhooks.voice.gather', [
            'provider' => 'twilio',
            'call_uuid' => $call->uuid,
        ]), [
            'Digits' => '0',
        ]);

        $res->assertOk();
        $this->assertStringContainsString('WhatsApp', $res->getContent());
        $this->assertEquals('callback_requested', $call->fresh()->outcome);
    }

    public function test_call_summary_generation_and_crm_lead_qualification(): void
    {
        $contact = Contact::create([
            'workspace_id' => $this->workspaceA->id,
            'phone_e164' => '+919800011122',
            'first_name' => 'Aarav',
            'last_name' => 'Mehta',
            'status' => 'lead',
        ]);

        $call = VoiceCall::create([
            'workspace_id' => $this->workspaceA->id,
            'phone_number_id' => $this->numberA->id,
            'voice_agent_id' => $this->agentA->id,
            'contact_id' => $contact->id,
            'from_number' => '+919800011122',
            'to_number' => '+12125550199',
            'duration_sec' => 145,
            'transcript' => "Caller: What is the price of your enterprise automation package?\nAI: The price starts at ₹15,000 per month.",
            'status' => 'completed',
        ]);

        // Run summary job synchronously
        (new GenerateVoiceCallSummaryJob($call->id))->handle();

        $call->refresh();
        $this->assertNotEmpty($call->summary);
        $this->assertEquals(85, $call->lead_score);
        $this->assertEquals('qualified', $call->outcome);

        // Check CRM Contact updated with Voice Lead & Hot Lead tags
        $contact->refresh();
        $tagNames = $contact->tags->pluck('name')->toArray();
        $this->assertContains('Voice Lead', $tagNames);
        $this->assertContains('Hot Lead', $tagNames);

        // Check Unified Inbox conversation created
        $conv = Conversation::where('workspace_id', $this->workspaceA->id)
            ->where('contact_id', $contact->id)
            ->where('channel', 'phone')
            ->first();
        $this->assertNotNull($conv);

        // Check daily analytics recorded
        $stat = AiDailyStat::where('workspace_id', $this->workspaceA->id)
            ->where('channel', 'phone')
            ->first();
        $this->assertNotNull($stat);
        $this->assertEquals(1, $stat->conversations);
        $this->assertEquals(1, $stat->resolved);
    }

    public function test_call_history_page_renders(): void
    {
        $res = $this->actingAs($this->userA)->get(route('client.voice.calls.index'));
        $res->assertOk();
        $res->assertInertia(fn ($page) => 
            $page->component('Voice/Calls/Index')
                ->has('calls')
        );
    }

    public function test_workspace_isolation_protects_voice_resources(): void
    {
        // User B tries to view User A's call logs
        $callA = VoiceCall::create([
            'workspace_id' => $this->workspaceA->id,
            'from_number' => '+919800011122',
            'to_number' => '+12125550199',
            'status' => 'completed',
        ]);

        $res = $this->actingAs($this->userB)->get(route('client.voice.calls.index'));
        $res->assertOk();
        // User B sees 0 calls
        $this->assertCount(0, $res->inertiaProps()['calls']['data']);
    }
}
