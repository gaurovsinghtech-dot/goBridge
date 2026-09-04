<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Modules\AI\Models\AiChatbot;
use App\Modules\AI\Models\AiKnowledgeBase;
use App\Services\AI\AiKnowledgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAgentPlaygroundTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;
    private Workspace $workspaceA;
    private User $userB;
    private Workspace $workspaceB;
    private AiChatbot $agentA;
    private AiKnowledgeBase $kbA;
    private AiKnowledgeService $knowledgeService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspaceA = Workspace::create([
            'name' => 'Workspace A Tech',
            'slug' => 'workspace-a-tech',
            'status' => 'active',
        ]);

        $this->userA = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
            'role' => 'client',
        ]);

        $this->workspaceB = Workspace::create([
            'name' => 'Workspace B Retail',
            'slug' => 'workspace-b-retail',
            'status' => 'active',
        ]);

        $this->userB = User::factory()->create([
            'workspace_id' => $this->workspaceB->id,
            'role' => 'client',
        ]);

        $this->knowledgeService = app(AiKnowledgeService::class);

        // Setup Knowledge Base & Agent for Workspace A
        $this->kbA = AiKnowledgeBase::create([
            'workspace_id' => $this->workspaceA->id,
            'name' => 'Tech Store Knowledge',
            'category' => 'company',
            'status' => 'active',
        ]);

        // Ingest business info
        $this->knowledgeService->ingestBusinessProfile($this->kbA, [
            'name' => 'Tech Hub Electronics',
            'business_hours' => '9:00 AM – 9:00 PM',
            'address' => '456 Cyber Tower, Bangalore',
            'return_policy' => '10 days easy replacement',
        ]);

        // Ingest product
        $this->knowledgeService->ingestProduct($this->kbA, [
            'name' => 'Wireless Bluetooth Earbuds',
            'price' => '1499',
            'currency' => 'INR',
            'description' => 'Noise-cancelling wireless earbuds with 30-hour battery life.',
        ]);

        // Create AI Agent for Workspace A
        $this->agentA = AiChatbot::create([
            'workspace_id' => $this->workspaceA->id,
            'name' => 'Sales & Support Assistant',
            'purpose' => 'Qualify leads and answer product/pricing questions',
            'agent_type' => 'sales',
            'status' => 'draft',
            'enabled' => false,
            'ai_kb_id' => $this->kbA->id,
            'response_mode' => 'auto_reply',
            'confidence_threshold' => 70,
            'strict_knowledge_mode' => false,
            'human_handoff_enabled' => true,
            'human_handoff_message' => "Certainly. I'm connecting you with our human team.",
            'system_prompt' => 'You are a helpful sales assistant.',
        ]);
    }

    public function test_playground_dashboard_page_renders_with_agents(): void
    {
        $res = $this->actingAs($this->userA)->get(route('client.ai.playground.index'));
        $res->assertOk();
        $res->assertInertia(fn ($page) => 
            $page->component('AI/Playground/Index')
                ->has('chatbots')
                ->has('knowledgeBases')
        );
    }

    public function test_simulated_question_answering_using_business_knowledge(): void
    {
        $res = $this->actingAs($this->userA)->postJson(route('client.ai.playground.test'), [
            'ai_agent_id' => $this->agentA->id,
            'message' => 'What are your business hours?',
            'channel' => 'whatsapp',
        ]);

        $res->assertOk();
        $res->assertJsonStructure([
            'ok',
            'question',
            'draft_response',
            'confidence',
            'sources_used',
            'latency_sec',
            'tokens',
            'is_test_mode',
        ]);

        $data = $res->json();
        $this->assertTrue($data['is_test_mode']);
        $this->assertStringContainsString('9:00 AM – 9:00 PM', $data['draft_response']);
        $this->assertNotEmpty($data['sources_used']);
    }

    public function test_simulated_product_price_inquiry(): void
    {
        $res = $this->actingAs($this->userA)->postJson(route('client.ai.playground.test'), [
            'ai_agent_id' => $this->agentA->id,
            'message' => 'What is the price of Wireless Bluetooth Earbuds?',
            'channel' => 'instagram',
        ]);

        $res->assertOk();
        $data = $res->json();

        $this->assertStringContainsString('1499', $data['draft_response']);
        $this->assertStringContainsString('Wireless Bluetooth Earbuds', $data['draft_response']);
    }

    public function test_simulated_human_handoff_trigger(): void
    {
        $res = $this->actingAs($this->userA)->postJson(route('client.ai.playground.test'), [
            'ai_agent_id' => $this->agentA->id,
            'message' => 'I want to speak to a human representative please',
        ]);

        $res->assertOk();
        $data = $res->json();

        $this->assertTrue($data['human_handoff']);
        $this->assertStringContainsString('human', strtolower($data['handoff_reason']));
        $this->assertStringContainsString('connecting you with our human team', $data['draft_response']);
    }

    public function test_unknown_general_trivia_does_not_hallucinate(): void
    {
        $res = $this->actingAs($this->userA)->postJson(route('client.ai.playground.test'), [
            'ai_agent_id' => $this->agentA->id,
            'message' => 'Who is the Prime Minister of Canada?',
        ]);

        $res->assertOk();
        $data = $res->json();

        $this->assertTrue($data['is_unknown_fallback']);
        $this->assertStringContainsString("I don't have that information", $data['draft_response']);
    }

    public function test_feedback_submission_on_ai_response(): void
    {
        $res = $this->actingAs($this->userA)->postJson(route('client.ai.playground.feedback'), [
            'ai_agent_id' => $this->agentA->id,
            'question' => 'Do you ship to Mumbai?',
            'answer' => 'Yes, standard delivery takes 2-4 days.',
            'rating' => 'good',
            'improvement_notes' => 'Answer is accurate and concise.',
        ]);

        $res->assertOk();
        $this->assertTrue($res->json('ok'));
    }

    public function test_checklist_and_activation_of_ai_agent(): void
    {
        $this->assertEquals('draft', $this->agentA->status);

        $res = $this->actingAs($this->userA)->post(route('client.ai.playground.activate', $this->agentA));
        $res->assertRedirect();
        $res->assertSessionHas('success');

        $this->assertEquals('active', $this->agentA->fresh()->status);
        $this->assertTrue((bool) $this->agentA->fresh()->enabled);
    }

    public function test_workspace_isolation_prevents_unauthorized_agent_testing(): void
    {
        // User B tries to test User A's agent
        $res = $this->actingAs($this->userB)->postJson(route('client.ai.playground.test'), [
            'ai_agent_id' => $this->agentA->id,
            'message' => 'Test message',
        ]);

        $res->assertNotFound();

        // User B tries to activate User A's agent
        $resAct = $this->actingAs($this->userB)->post(route('client.ai.playground.activate', $this->agentA));
        $resAct->assertForbidden();
    }
}
