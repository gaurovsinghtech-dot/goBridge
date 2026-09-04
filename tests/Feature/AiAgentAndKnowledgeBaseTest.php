<?php

namespace Tests\Feature;

use App\Models\Crm\CrmPipeline;
use App\Models\Crm\CrmPipelineStage;
use App\Models\User;
use App\Modules\AI\Models\AiChatbot;
use App\Modules\AI\Models\AiKbChunk;
use App\Modules\AI\Models\AiKbDocument;
use App\Modules\AI\Models\AiKnowledgeBase;
use App\Modules\Shared\Models\Contact;
use App\Services\AI\AiAgentService;
use App\Services\AI\AiKnowledgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiAgentAndKnowledgeBaseTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected $workspace;
    protected $client;
    protected AiAgentService $agentService;
    protected AiKnowledgeService $knowledgeService;

    protected function setUp(): void
    {
        parent::setUp();

        $ctx = $this->createWorkspaceContext();
        $this->user = $ctx['user'];
        $this->workspace = $ctx['workspace'];
        $this->client = $ctx['client'];

        $this->agentService = app(AiAgentService::class);
        $this->knowledgeService = app(AiKnowledgeService::class);
    }

    public function test_ai_agent_creation_and_template_hydration(): void
    {
        // 1. Create from template: Sales Assistant
        $salesAgent = $this->agentService->createFromTemplate(
            $this->workspace->id,
            'sales_assistant',
            ['name' => 'Growbridge Top Sales Closer']
        );

        $this->assertDatabaseHas('ai_chatbots', [
            'id' => $salesAgent->id,
            'name' => 'Growbridge Top Sales Closer',
            'agent_type' => 'sales_assistant',
            'status' => 'active',
        ]);
        $this->assertContains('whatsapp', $salesAgent->channels);
        $this->assertContains('update_lead', $salesAgent->tools_enabled);

        // 2. Create from template: Customer Support
        $supportAgent = $this->agentService->createFromTemplate(
            $this->workspace->id,
            'customer_support'
        );

        $this->assertEquals('customer_support', $supportAgent->agent_type);
        $this->assertTrue($supportAgent->strict_knowledge_mode);
        $this->assertEquals(75, $supportAgent->confidence_threshold);
    }

    public function test_knowledge_base_document_chunking_and_publishing(): void
    {
        // 1. Create Knowledge Base
        $kb = AiKnowledgeBase::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Growbridge Product & Pricing Knowledge',
            'category' => 'pricing',
            'status' => 'active',
        ]);

        // 2. Ingest Text Document
        $textDoc = $this->knowledgeService->ingestText(
            $kb,
            'Enterprise Pricing 2026',
            "Growbridge Connect Enterprise Plan costs $499 per month for up to 50 team members.\n\nIt includes unlimited WhatsApp messaging, custom AI agents, AI voice dialer via Twilio Voice, and dedicated SLA.",
            'pricing',
            7
        );

        $this->assertContains($textDoc->status, ['ready', 'indexed']);
        $this->assertGreaterThan(0, $textDoc->tokens);
        $this->assertGreaterThan(0, $textDoc->chunks()->count());

        // 3. Ingest Structured FAQ Collection
        $faqDoc = $this->knowledgeService->ingestFaqs(
            $kb,
            [
                ['q' => 'Does Growbridge support Instagram DM automation?', 'a' => 'Yes, Growbridge Connect provides full Instagram DM and Story reply automation.'],
                ['q' => 'What is the refund policy?', 'a' => 'Growbridge Connect offers a 14-day money-back guarantee on all annual plans.'],
            ],
            'Omnichannel FAQ'
        );

        $this->assertContains($faqDoc->status, ['ready', 'indexed']);
        $this->assertEquals(2, $kb->documents()->count());
    }

    public function test_hybrid_knowledge_retrieval_and_priority_weighting(): void
    {
        $kb = AiKnowledgeBase::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Company Docs',
            'category' => 'company',
            'status' => 'active',
        ]);

        $this->knowledgeService->ingestText(
            $kb,
            'Official Pricing',
            'The starter plan is $49/mo, growth plan is $149/mo, and enterprise is $499/mo.',
            'pricing',
            9
        );

        $this->knowledgeService->ingestText(
            $kb,
            'General Info',
            'Growbridge Connect is the modern marketing automation platform.',
            'general',
            3
        );

        $results = $this->knowledgeService->search($kb, 'How much does the enterprise pricing plan cost?', 5);

        $this->assertNotEmpty($results);
        $this->assertEquals('Official Pricing', $results[0]['title']);
        $this->assertStringContainsString('$499/mo', $results[0]['content']);
    }

    public function test_strict_knowledge_mode_and_hallucination_prevention(): void
    {
        $kb = AiKnowledgeBase::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Strict Policy KB',
            'category' => 'policies',
            'status' => 'active',
        ]);

        $this->knowledgeService->ingestText(
            $kb,
            'Return Policy',
            'Items can be returned within 30 days of purchase in original packaging.',
            'policies',
            8
        );

        // Out of domain query in strict mode
        $results = $this->knowledgeService->search(
            $kb,
            'What is the weather forecast in Tokyo today?',
            5,
            true // strict mode
        );

        $this->assertEmpty($results);
    }

    public function test_entity_extraction_and_autonomous_lead_qualification(): void
    {
        $pipeline = CrmPipeline::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Sales Pipeline',
            'is_default' => true,
        ]);

        $stage = CrmPipelineStage::create([
            'workspace_id' => $this->workspace->id,
            'pipeline_id' => $pipeline->id,
            'name' => 'Qualified Leads',
            'position' => 2,
        ]);

        $contact = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Dev',
            'email' => 'dev@startup.io',
            'phone_e164' => '+919876543210',
            'lead_score' => 0,
        ]);

        $agent = $this->agentService->createFromTemplate($this->workspace->id, 'lead_qualification');

        $message = "We need 50 seats for our Delhi branch with a $5000 budget immediately.";

        $entities = $this->agentService->extractEntities($message);
        $this->assertEquals(50, $entities['quantity'] ?? null);
        $this->assertNotEmpty($entities['budget'] ?? null);
        $this->assertNotEmpty($entities['timeline'] ?? null);
        $this->assertEquals('Delhi', $entities['location'] ?? null);

        // Run qualification
        $qualification = $this->agentService->qualifyLead($contact, $entities, 'sales', $agent);

        $this->assertTrue($qualification['is_qualified']);
        $this->assertGreaterThanOrEqual(80, $qualification['total_score']);
        $this->assertEquals($qualification['total_score'], $contact->fresh()->lead_score);
        $this->assertEquals($stage->id, $contact->fresh()->stage_id);
    }

    public function test_ai_confidence_evaluation_and_smart_human_handoff(): void
    {
        $contact = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Alex',
            'email' => 'alex@acme.corp',
        ]);

        $agent = $this->agentService->createFromTemplate(
            $this->workspace->id,
            'customer_support',
            [
                'human_handoff_user_id' => $this->user->id,
                'confidence_threshold' => 75,
            ]
        );

        // 1. Complaint detection
        $complaintIntent = $this->agentService->detectIntent('I am extremely angry and want an immediate refund for bad service!');
        $this->assertEquals('complaint', $complaintIntent['intent']);

        // 2. Human Request detection
        $humanIntent = $this->agentService->detectIntent('Please connect me with a real human agent or manager');
        $this->assertEquals('human_request', $humanIntent['intent']);

        // 3. Trigger handoff
        $handoffResult = $this->agentService->triggerHumanHandoff($agent, $contact, 'customer_request');
        $this->assertTrue($handoffResult['handoff']);
        $this->assertEquals($this->user->id, $contact->fresh()->assigned_user_id);
    }

    public function test_ai_agent_playground_and_knowledge_testing(): void
    {
        $kb = AiKnowledgeBase::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'FAQ Base',
            'status' => 'active',
        ]);

        $this->knowledgeService->ingestText(
            $kb,
            'WhatsApp Pricing FAQ',
            'Growbridge Connect offers unlimited WhatsApp messaging with official Meta Cloud API integration.',
            'pricing',
            8
        );

        $agent = $this->agentService->createFromTemplate(
            $this->workspace->id,
            'sales_assistant',
            ['ai_kb_id' => $kb->id]
        );

        $testResult = $this->agentService->runPlaygroundTest(
            $agent,
            'What is the pricing for WhatsApp integration?'
        );

        $this->assertTrue($testResult['ok']);
        $this->assertEquals('pricing', $testResult['detected_intent']);
        $this->assertNotEmpty($testResult['knowledge_used']);
        $this->assertFalse($testResult['human_handoff']);
        $this->assertStringContainsString('WhatsApp', $testResult['draft_response']);
    }

    public function test_ai_agent_and_knowledge_base_rest_api_v1(): void
    {
        Sanctum::actingAs($this->user, ['ai:read', 'ai:write']);

        // 1. POST /api/v1/ai/knowledge-bases
        $kbRes = $this->postJson('/api/v1/ai/knowledge-bases', [
            'name' => 'API Created Knowledge Base',
            'category' => 'company',
        ]);
        $kbRes->assertStatus(201);
        $kbId = $kbRes->json('data.id');

        // 2. POST /api/v1/ai/knowledge-bases/{id}/documents (Direct text)
        $docRes = $this->postJson("/api/v1/ai/knowledge-bases/{$kbId}/documents", [
            'source_type' => 'text',
            'title' => 'API Document',
            'content' => 'Growbridge Connect API endpoints are secured with Sanctum personal access tokens.',
            'category' => 'company',
        ]);
        $docRes->assertStatus(201);

        // 3. POST /api/v1/ai/knowledge-bases/{id}/search
        $searchRes = $this->postJson("/api/v1/ai/knowledge-bases/{$kbId}/search", [
            'query' => 'Sanctum tokens API',
        ]);
        $searchRes->assertOk();
        $this->assertNotEmpty($searchRes->json('results'));

        // 4. POST /api/v1/ai/agents
        $agentRes = $this->postJson('/api/v1/ai/agents', [
            'name' => 'API Assistant',
            'template_key' => 'sales_assistant',
            'ai_kb_id' => $kbId,
        ]);
        $agentRes->assertStatus(201);
        $agentId = $agentRes->json('data.id');

        // 5. POST /api/v1/ai/agents/{id}/activate & pause
        $actRes = $this->postJson("/api/v1/ai/agents/{$agentId}/activate");
        $actRes->assertOk();
        $this->assertEquals('active', $actRes->json('status'));

        // 6. POST /api/v1/ai/agents/{id}/test
        $testRes = $this->postJson("/api/v1/ai/agents/{$agentId}/test", [
            'message' => 'Tell me about your Sanctum tokens API',
        ]);
        $testRes->assertOk();
        $this->assertTrue($testRes->json('ok'));
    }
}
