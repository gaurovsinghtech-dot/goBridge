<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Modules\AI\Models\AiChatbot;
use App\Modules\AI\Models\AiKnowledgeBase;
use App\Services\AI\AiAgentService;
use App\Services\AI\AiKnowledgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAgentStudioTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;
    private Workspace $workspaceA;
    private User $userB;
    private Workspace $workspaceB;
    private AiKnowledgeBase $kbA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspaceA = Workspace::create([
            'name' => 'Studio Workspace A',
            'slug' => 'studio-workspace-a',
            'status' => 'active',
        ]);

        $this->userA = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
            'role' => 'client',
        ]);

        $this->workspaceB = Workspace::create([
            'name' => 'Studio Workspace B',
            'slug' => 'studio-workspace-b',
            'status' => 'active',
        ]);

        $this->userB = User::factory()->create([
            'workspace_id' => $this->workspaceB->id,
            'role' => 'client',
        ]);

        $this->kbA = AiKnowledgeBase::create([
            'workspace_id' => $this->workspaceA->id,
            'name' => 'Primary Company Knowledge',
            'category' => 'company',
            'status' => 'active',
            'answer_policy' => 'strict_kb_only',
        ]);
    }

    public function test_ai_agents_dashboard_renders_with_agents_and_metrics(): void
    {
        AiChatbot::create([
            'workspace_id' => $this->workspaceA->id,
            'name' => 'Sales Growth Agent',
            'agent_type' => 'sales',
            'channels' => ['whatsapp', 'voice'],
            'status' => 'published',
            'ai_kb_id' => $this->kbA->id,
        ]);

        $res = $this->actingAs($this->userA)->get(route('client.ai-agents.index'));
        $res->assertOk();
        $res->assertInertia(fn ($page) =>
            $page->component('AI/Chatbots/Index')
                ->has('chatbots')
                ->has('stats')
                ->has('connectedChannels')
        );
    }

    public function test_ai_agent_creation_via_wizard_with_personality_and_objectives(): void
    {
        $payload = [
            'name' => 'Inbound Receptionist AI',
            'agent_type' => 'receptionist',
            'purpose' => 'Greet callers and route inquiries to appropriate teams.',
            'tone' => 'friendly',
            'response_style' => 'short',
            'emoji_style' => 'sometimes',
            'response_delay_mode' => 'natural',
            'response_delay_seconds' => 2,
            'languages' => ['en', 'hi'],
            'objectives' => ['answer_questions', 'collect_customer_info', 'schedule_callback'],
            'guardrails' => ['no_hallucinations', 'protect_internal_data', 'escalate_complaints'],
            'ai_kb_id' => $this->kbA->id,
            'channels' => ['whatsapp', 'voice'],
            'status' => 'draft',
            'human_handoff_enabled' => true,
            'human_handoff_message' => 'Connecting you with front desk.',
        ];

        $res = $this->actingAs($this->userA)->postJson(route('client.ai-agents.store'), $payload);
        $res->assertOk();
        $this->assertTrue($res->json('success'));

        $this->assertDatabaseHas('ai_chatbots', [
            'workspace_id' => $this->workspaceA->id,
            'name' => 'Inbound Receptionist AI',
            'agent_type' => 'receptionist',
            'tone' => 'friendly',
            'status' => 'draft',
            'version' => 1,
        ]);
    }

    public function test_system_prompt_builder_includes_guardrails_objectives_and_anti_hallucination(): void
    {
        $service = app(AiAgentService::class);

        $agent = AiChatbot::create([
            'workspace_id' => $this->workspaceA->id,
            'name' => 'Enterprise Sales AI',
            'agent_type' => 'sales',
            'purpose' => 'Qualify high-value enterprise prospects.',
            'tone' => 'formal',
            'response_style' => 'detailed',
            'emoji_style' => 'never',
            'languages' => ['en', 'es'],
            'objectives' => ['generate_leads', 'collect_customer_info', 'offer_demo'],
            'guardrails' => ['no_hallucinations', 'protect_system_prompt', 'no_unauthorized_promises'],
            'strict_knowledge_mode' => true,
            'fallback_reply' => 'I cannot verify that policy in my verified enterprise database.',
            'lead_qualification_fields' => ['company', 'budget', 'timeline'],
            'channels' => ['whatsapp'],
        ]);

        $prompt = $service->buildSystemPrompt($agent);

        $this->assertStringContainsString('Enterprise Sales AI', $prompt);
        $this->assertStringContainsString('FORMAL', $prompt);
        $this->assertStringContainsString('DO NOT use emojis', $prompt);
        $this->assertStringContainsString('CORE OBJECTIVES TO ACCOMPLISH:', $prompt);
        $this->assertStringContainsString('AI SAFETY & GUARDRAILS (STRICT):', $prompt);
        $this->assertStringContainsString('NEVER invent or speculate on pricing', $prompt);
        $this->assertStringContainsString('Target fields: Company, Budget, Timeline', $prompt);
        $this->assertStringContainsString('STRICT ANTI-HALLUCINATION ENFORCEMENT', $prompt);
        $this->assertStringContainsString('I cannot verify that policy in my verified enterprise database', $prompt);
    }

    public function test_ai_agent_knowledge_scoping_and_strict_rag_fallback(): void
    {
        $kbService = app(AiKnowledgeService::class);
        $agentService = app(AiAgentService::class);

        $salesDoc = $kbService->ingestFaq(
            $this->kbA,
            'What is the pricing for Growbridge Enterprise?',
            'Growbridge Enterprise is $299/mo with unlimited voice minutes and multi-tenant isolation.',
            'pricing',
            9
        );

        $agent = AiChatbot::create([
            'workspace_id' => $this->workspaceA->id,
            'name' => 'Sales Bot',
            'agent_type' => 'sales',
            'ai_kb_id' => $this->kbA->id,
            'strict_knowledge_mode' => true,
            'confidence_threshold' => 70,
            'channels' => ['whatsapp'],
        ]);

        // In-scope question
        $resKnown = $agentService->runPlaygroundTest($agent, 'What is the pricing for Growbridge Enterprise?');
        $this->assertTrue($resKnown['ok']);
        $this->assertFalse($resKnown['human_handoff']);
        $this->assertNotEmpty($resKnown['sources_used']);
        $this->assertStringContainsString('299', $resKnown['draft_response']);

        // Out-of-scope question
        $resUnknown = $agentService->runPlaygroundTest($agent, 'Who is the president of Mars colonization project in 2099?');
        $this->assertTrue($resUnknown['ok']);
        $this->assertTrue($resUnknown['is_unknown_fallback']);
    }

    public function test_ai_simulator_sandbox_provides_debug_info_without_sending_production_messages(): void
    {
        $agent = AiChatbot::create([
            'workspace_id' => $this->workspaceA->id,
            'name' => 'Test Sandbox Agent',
            'agent_type' => 'support',
            'ai_kb_id' => $this->kbA->id,
            'channels' => ['whatsapp', 'messenger'],
            'status' => 'testing',
        ]);

        $res = $this->actingAs($this->userA)->postJson(route('client.ai-agents.simulate', $agent->uuid), [
            'message' => 'I am very upset with your delayed delivery, connect me with a human manager immediately!',
        ]);

        $res->assertOk();
        $this->assertEquals('human_request', $res->json('detected_intent'));
        $this->assertTrue($res->json('human_handoff'));
        $this->assertArrayHasKey('latency_ms', $res->json());
        $this->assertArrayHasKey('tokens', $res->json());
    }

    public function test_ai_agent_validation_and_publishing_lifecycle(): void
    {
        // 1. Incomplete agent cannot be published
        $incomplete = AiChatbot::create([
            'workspace_id' => $this->workspaceA->id,
            'name' => '', // empty name
            'channels' => [], // no channels
            'status' => 'draft',
        ]);

        $resFail = $this->actingAs($this->userA)->postJson(route('client.ai-agents.publish', $incomplete->uuid));
        $resFail->assertStatus(422);

        // 2. Complete agent publishes successfully
        $complete = AiChatbot::create([
            'workspace_id' => $this->workspaceA->id,
            'name' => 'Live Customer Support Agent',
            'agent_type' => 'support',
            'purpose' => 'Support customers with orders and returns.',
            'channels' => ['whatsapp', 'email'],
            'system_prompt' => 'Support assistant instructions.',
            'status' => 'draft',
            'version' => 1,
        ]);

        $resPass = $this->actingAs($this->userA)->postJson(route('client.ai-agents.publish', $complete->uuid));
        $resPass->assertOk();
        $this->assertEquals('published', $complete->fresh()->status);
        $this->assertEquals(1, $complete->fresh()->published_version);
        $this->assertNotNull($complete->fresh()->published_at);

        // 3. Pause agent
        $resPause = $this->actingAs($this->userA)->postJson(route('client.ai-agents.pause', $complete->uuid));
        $resPause->assertOk();
        $this->assertEquals('paused', $complete->fresh()->status);
    }

    public function test_ai_agent_versioning_and_duplication(): void
    {
        $agent = AiChatbot::create([
            'workspace_id' => $this->workspaceA->id,
            'name' => 'Master Sales Bot',
            'agent_type' => 'sales',
            'channels' => ['whatsapp'],
            'system_prompt' => 'Sales instructions.',
            'status' => 'published',
            'version' => 2,
        ]);

        // Duplicate agent
        $resDup = $this->actingAs($this->userA)->postJson(route('client.ai-agents.duplicate', $agent->uuid));
        $resDup->assertOk();
        $this->assertTrue($resDup->json('success'));

        $this->assertDatabaseHas('ai_chatbots', [
            'workspace_id' => $this->workspaceA->id,
            'name' => 'Master Sales Bot (Copy)',
            'status' => 'draft',
            'version' => 1,
        ]);
    }

    public function test_workspace_isolation_protects_ai_agents(): void
    {
        $agentA = AiChatbot::create([
            'workspace_id' => $this->workspaceA->id,
            'name' => 'Confidential Sales Agent A',
            'channels' => ['whatsapp'],
            'status' => 'published',
        ]);

        // User B cannot access User A's agent
        $resShow = $this->actingAs($this->userB)->get(route('client.ai-agents.show', $agentA->uuid));
        $resShow->assertForbidden();

        // User B cannot publish User A's agent
        $resPublish = $this->actingAs($this->userB)->post(route('client.ai-agents.publish', $agentA->uuid));
        $resPublish->assertForbidden();

        // User B cannot delete User A's agent
        $resDelete = $this->actingAs($this->userB)->delete(route('client.ai-agents.destroy', $agentA->uuid));
        $resDelete->assertForbidden();
    }
}
