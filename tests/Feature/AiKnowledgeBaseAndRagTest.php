<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Modules\AI\Models\AiChatbot;
use App\Modules\AI\Models\AiKbChunk;
use App\Modules\AI\Models\AiKbDocument;
use App\Modules\AI\Models\AiKnowledgeBase;
use App\Modules\AI\Models\AiUnknownQuestion;
use App\Services\AI\AiKnowledgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AiKnowledgeBaseAndRagTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;
    private Workspace $workspaceA;
    private User $userB;
    private Workspace $workspaceB;
    private AiKnowledgeBase $kbA;
    private AiChatbot $salesAgentA;
    private AiChatbot $supportAgentA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspaceA = Workspace::create([
            'name' => 'Workspace A Knowledge',
            'slug' => 'workspace-a-kb',
            'status' => 'active',
        ]);

        $this->userA = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
            'role' => 'client',
        ]);

        $this->workspaceB = Workspace::create([
            'name' => 'Workspace B Knowledge',
            'slug' => 'workspace-b-kb',
            'status' => 'active',
        ]);

        $this->userB = User::factory()->create([
            'workspace_id' => $this->workspaceB->id,
            'role' => 'client',
        ]);

        $this->kbA = AiKnowledgeBase::create([
            'workspace_id' => $this->workspaceA->id,
            'name' => 'Workspace A Primary Knowledge Base',
            'category' => 'company',
            'status' => 'active',
            'answer_policy' => 'strict_kb_only',
            'allow_citations' => true,
            'fallback_message' => 'I do not have verified knowledge for that inquiry.',
        ]);

        $this->salesAgentA = AiChatbot::create([
            'workspace_id' => $this->workspaceA->id,
            'name' => 'Sales AI Assistant',
            'ai_kb_id' => $this->kbA->id,
            'channels' => ['whatsapp'],
            'enabled' => true,
        ]);

        $this->supportAgentA = AiChatbot::create([
            'workspace_id' => $this->workspaceA->id,
            'name' => 'Support AI Assistant',
            'ai_kb_id' => $this->kbA->id,
            'channels' => ['whatsapp', 'voice'],
            'enabled' => true,
        ]);
    }

    public function test_knowledge_dashboard_renders_with_metrics_and_sources(): void
    {
        $res = $this->actingAs($this->userA)->get(route('client.ai.knowledge.index'));
        $res->assertOk();
        $res->assertInertia(fn ($page) =>
            $page->component('AI/Knowledge/Index')
                ->has('kb')
                ->has('stats')
                ->has('allSources')
                ->has('availableAgents')
                ->has('knowledgeGaps')
        );
    }

    public function test_faq_creation_and_priority_indexing(): void
    {
        $res = $this->actingAs($this->userA)->postJson(route('client.ai.knowledge.faq'), [
            'question' => 'What is your WhatsApp API pricing?',
            'answer' => 'Our WhatsApp API plan starts at $49/mo for up to 10,000 messages.',
            'category' => 'pricing',
            'priority' => 9,
            'assigned_agents' => [(string) $this->salesAgentA->id],
        ]);

        $res->assertOk();
        $this->assertTrue($res->json('success'));

        $this->assertDatabaseHas('ai_kb_documents', [
            'kb_id' => $this->kbA->id,
            'source_type' => 'faq',
            'title' => 'What is your WhatsApp API pricing?',
            'category' => 'pricing',
            'status' => 'ready',
        ]);

        $doc = AiKbDocument::where('title', 'What is your WhatsApp API pricing?')->first();
        $this->assertNotNull($doc);
        $this->assertCount(1, $doc->chunks);
        $this->assertStringContainsString('WhatsApp API plan starts at $49/mo', $doc->chunks->first()->content);
    }

    public function test_plain_text_knowledge_ingestion(): void
    {
        $res = $this->actingAs($this->userA)->postJson(route('client.ai.knowledge.text'), [
            'title' => 'Return and Refund Policy',
            'content' => 'We offer a 30-day money-back guarantee on all software subscriptions if canceled within 30 days of purchase.',
            'category' => 'policies',
            'priority' => 7,
        ]);

        $res->assertOk();
        $this->assertTrue($res->json('success'));

        $this->assertDatabaseHas('ai_kb_documents', [
            'kb_id' => $this->kbA->id,
            'source_type' => 'text',
            'title' => 'Return and Refund Policy',
            'status' => 'ready',
        ]);
    }

    public function test_document_upload_and_chunking_for_txt(): void
    {
        $file = UploadedFile::fake()->createWithContent('security_policy.txt', 'Growbridge Connect enforces strict workspace isolation, AES-256 data encryption, and role-based access control.');

        $res = $this->actingAs($this->userA)->post(route('client.ai.knowledge.document'), [
            'file' => $file,
            'title' => 'Security & Privacy Policy',
            'category' => 'security',
        ]);

        $res->assertRedirect();

        $this->assertDatabaseHas('ai_kb_documents', [
            'kb_id' => $this->kbA->id,
            'title' => 'Security & Privacy Policy',
            'source_type' => 'file',
            'status' => 'ready',
        ]);
    }

    public function test_rag_search_retrieves_relevant_chunks_with_citations(): void
    {
        $service = app(AiKnowledgeService::class);

        // Ingest FAQ
        $service->ingestFaq(
            $this->kbA,
            'How do I connect Twilio Voice to my AI agent?',
            'Navigate to /app/voice/phone-numbers, click Connect Twilio, enter Account SID and Auth Token, and assign your Voice Agent.',
            'voice',
            8
        );

        // Ingest Pricing
        $service->ingestFaq(
            $this->kbA,
            'What is the pricing for Voice Agent calls?',
            'Twilio Voice Agent calls cost $0.02 per minute plus standard carrier rates.',
            'pricing',
            9
        );

        // Search for Voice Pricing
        $results = $service->search($this->kbA, 'Voice Agent pricing cost per minute', 3);

        $this->assertNotEmpty($results);
        $top = $results[0];
        $this->assertStringContainsString('$0.02 per minute', $top['content']);
        $this->assertArrayHasKey('citation', $top);
        $this->assertStringContainsString('Source:', $top['citation']);
    }

    public function test_agent_knowledge_assignment_scopes_retrieval(): void
    {
        $service = app(AiKnowledgeService::class);

        // Sales Knowledge assigned only to Sales Agent
        $salesDoc = $service->ingestFaq(
            $this->kbA,
            'What discounts are available for enterprise annual plans?',
            'Enterprise annual plans receive a 25% discount and dedicated account manager.',
            'pricing',
            9,
            [(string) $this->salesAgentA->id]
        );

        // Support Knowledge assigned only to Support Agent
        $supportDoc = $service->ingestFaq(
            $this->kbA,
            'How do I troubleshoot webhook connection errors?',
            'Check your webhook URL in Meta App Dashboard and verify your webhook verify token.',
            'support',
            8,
            [(string) $this->supportAgentA->id]
        );

        // 1. Sales Agent searching for enterprise discounts should find it
        $salesResults = $service->search($this->kbA, 'enterprise annual plan discount', 3, false, [], $this->salesAgentA->id);
        $this->assertNotEmpty($salesResults);
        $this->assertStringContainsString('25% discount', $salesResults[0]['content']);

        // 2. Support Agent searching for enterprise discounts should NOT find it because it is scoped to Sales Agent
        $supportResults = $service->search($this->kbA, 'enterprise annual plan discount', 3, false, [], $this->supportAgentA->id);
        $this->assertEmpty($supportResults);
    }

    public function test_knowledge_gap_logging_and_unanswered_question_capture(): void
    {
        $service = app(AiKnowledgeService::class);

        // Search for query that has no matching knowledge
        $results = $service->search($this->kbA, 'Do you support integrating with Zapier and Make?', 3);
        $this->assertEmpty($results);

        // Verify gap was logged in ai_unknown_questions
        $this->assertDatabaseHas('ai_unknown_questions', [
            'workspace_id' => $this->workspaceA->id,
            'question' => 'Do you support integrating with Zapier and Make?',
            'status' => 'pending',
            'occurrences' => 1,
        ]);
    }

    public function test_document_reprocessing_cleans_old_chunks_idempotently(): void
    {
        $service = app(AiKnowledgeService::class);

        $doc = $service->ingestText($this->kbA, 'Service Level Agreement', '99.9% uptime SLA guaranteed for all enterprise subscribers.');
        $initialChunkCount = $doc->chunks()->count();
        $this->assertGreaterThan(0, $initialChunkCount);

        // Reprocess
        $reprocessed = $service->reprocessDocument($doc);
        $this->assertEquals('ready', $reprocessed->status);
        $this->assertEquals($initialChunkCount, $reprocessed->chunks()->count());
        $this->assertEquals(2, $reprocessed->version);
    }

    public function test_document_toggle_and_deletion(): void
    {
        $service = app(AiKnowledgeService::class);
        $doc = $service->ingestText($this->kbA, 'Internal Note', 'This is confidential.');

        // Toggle to disabled
        $service->toggleDocument($doc);
        $this->assertEquals('disabled', $doc->fresh()->status);

        // Disabled doc should not be returned in search
        $results = $service->search($this->kbA, 'confidential', 3);
        $this->assertEmpty($results);

        // Delete
        $service->destroyDocument($doc);
        $this->assertDatabaseMissing('ai_kb_documents', ['id' => $doc->id]);
        $this->assertDatabaseMissing('ai_kb_chunks', ['document_id' => $doc->id]);
    }

    public function test_workspace_isolation_protects_knowledge_sources(): void
    {
        $service = app(AiKnowledgeService::class);
        $docA = $service->ingestText($this->kbA, 'Secret Product Blueprint', 'Quantum compression protocol details.');

        // User B cannot reprocess User A's document
        $resReprocess = $this->actingAs($this->userB)->post(route('client.ai.knowledge.document.reprocess', $docA->uuid));
        $resReprocess->assertForbidden();

        // User B cannot delete User A's document
        $resDelete = $this->actingAs($this->userB)->delete(route('client.ai.knowledge.document.destroy', $docA->uuid));
        $resDelete->assertForbidden();
    }
}
