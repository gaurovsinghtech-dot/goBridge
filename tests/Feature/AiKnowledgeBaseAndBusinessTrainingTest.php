<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Modules\AI\Models\AiKbChunk;
use App\Modules\AI\Models\AiKbDocument;
use App\Modules\AI\Models\AiKnowledgeBase;
use App\Services\AI\AiKnowledgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiKnowledgeBaseAndBusinessTrainingTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;
    private Workspace $workspaceA;
    private User $userB;
    private Workspace $workspaceB;
    private AiKnowledgeService $knowledgeService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspaceA = Workspace::create([
            'name' => 'Workspace A (Electronics)',
            'slug' => 'workspace-a-electronics',
            'status' => 'active',
        ]);

        $this->userA = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
            'role' => 'client',
        ]);

        $this->workspaceB = Workspace::create([
            'name' => 'Workspace B (Restaurant)',
            'slug' => 'workspace-b-restaurant',
            'status' => 'active',
        ]);

        $this->userB = User::factory()->create([
            'workspace_id' => $this->workspaceB->id,
            'role' => 'client',
        ]);

        $this->knowledgeService = app(AiKnowledgeService::class);
    }

    public function test_business_profile_information_ingestion_and_indexing(): void
    {
        $res = $this->actingAs($this->userA)->post(route('client.ai.knowledge.business'), [
            'name' => 'ABC Electronics',
            'industry' => 'Consumer Electronics',
            'description' => 'We provide genuine smartphones, laptops and accessories with warranty.',
            'business_hours' => '10:00 AM – 8:00 PM',
            'address' => '123 Tech Park, Delhi',
            'phone' => '+919876543210',
            'email' => 'support@abcelectronics.com',
            'return_policy' => '7 days replacement policy for defective units.',
            'shipping_policy' => 'Same-day delivery across Delhi NCR.',
            'payment_info' => 'UPI, Credit Cards, Cash on Delivery.',
        ]);

        $res->assertRedirect();
        $res->assertSessionHas('success');

        $kb = AiKnowledgeBase::where('workspace_id', $this->workspaceA->id)->first();
        $this->assertNotNull($kb);

        $doc = AiKbDocument::where('kb_id', $kb->id)->where('category', 'business')->first();
        $this->assertNotNull($doc);
        $this->assertEquals('ready', $doc->status);

        // Verify chunks were created
        $chunks = AiKbChunk::where('document_id', $doc->id)->get();
        $this->assertGreaterThan(0, $chunks->count());
        $this->assertStringContainsString('ABC Electronics', $chunks[0]->content);
        $this->assertStringContainsString('10:00 AM – 8:00 PM', $chunks[0]->content);
    }

    public function test_product_and_service_ingestion(): void
    {
        $res = $this->actingAs($this->userA)->post(route('client.ai.knowledge.product'), [
            'name' => 'Paper Plate 10-inch',
            'price' => '2.20',
            'currency' => 'INR',
            'description' => 'Biodegradable sugarcane pulp paper plates, pack of 50.',
            'availability' => 'In Stock',
            'features' => 'Eco-friendly, microwave safe, water resistant',
            'sku' => 'PP-10-50',
        ]);

        $res->assertRedirect();
        $res->assertSessionHas('success');

        $kb = AiKnowledgeBase::where('workspace_id', $this->workspaceA->id)->first();
        $doc = AiKbDocument::where('kb_id', $kb->id)->where('category', 'products')->first();

        $this->assertNotNull($doc);
        $this->assertEquals('Product: Paper Plate 10-inch', $doc->title);
        $this->assertEquals('ready', $doc->status);

        // Search test: AI searches for product price
        $results = $this->knowledgeService->search($kb, 'paper plate price');
        $this->assertNotEmpty($results);
        $this->assertStringContainsString('2.20', $results[0]['content']);
    }

    public function test_faq_creation_and_search_retrieval(): void
    {
        $res = $this->actingAs($this->userA)->post(route('client.ai.knowledge.faq'), [
            'question' => 'Do you provide delivery across India?',
            'answer' => 'Yes, we provide courier delivery to all pin codes in India within 3-5 business days.',
        ]);

        $res->assertRedirect();
        $res->assertSessionHas('success');

        $kb = AiKnowledgeBase::where('workspace_id', $this->workspaceA->id)->first();
        $doc = AiKbDocument::where('kb_id', $kb->id)->where('category', 'faq')->first();

        $this->assertNotNull($doc);

        $results = $this->knowledgeService->search($kb, 'delivery across India');
        $this->assertNotEmpty($results);
        $this->assertStringContainsString('3-5 business days', $results[0]['content']);
    }

    public function test_website_url_import(): void
    {
        Http::fake([
            'https://example-business.com' => Http::response(
                '<html><head><title>Example Store</title></head><body><h1>Welcome to Example Store</h1><p>We are a leading wholesaler in Delhi offering bulk discounts on orders above 10,000 units.</p></body></html>',
                200
            ),
        ]);

        $res = $this->actingAs($this->userA)->post(route('client.ai.knowledge.website'), [
            'url' => 'https://example-business.com',
        ]);

        $res->assertRedirect();
        $res->assertSessionHas('success');

        $kb = AiKnowledgeBase::where('workspace_id', $this->workspaceA->id)->first();
        $doc = AiKbDocument::where('kb_id', $kb->id)->where('category', 'website')->first();

        $this->assertNotNull($doc);
        $this->assertEquals('ready', $doc->status);

        $results = $this->knowledgeService->search($kb, 'bulk discounts wholesale');
        $this->assertNotEmpty($results);
        $this->assertStringContainsString('bulk discounts', $results[0]['content']);
    }

    public function test_document_text_upload_and_processing(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->createWithContent(
            'Company_Profile.txt',
            "Growbridge Connect is an omnichannel AI marketing automation platform helping SMBs automate customer communication."
        );

        $res = $this->actingAs($this->userA)->post(route('client.ai.knowledge.document'), [
            'file' => $file,
        ]);

        $res->assertRedirect();
        $res->assertSessionHas('success');

        $kb = AiKnowledgeBase::where('workspace_id', $this->workspaceA->id)->first();
        $doc = AiKbDocument::where('kb_id', $kb->id)->where('title', 'Company_Profile.txt')->first();

        $this->assertNotNull($doc);
        $this->assertEquals('ready', $doc->status);
    }

    public function test_reprocess_knowledge_updates_all_documents(): void
    {
        $kb = AiKnowledgeBase::firstOrCreate(
            ['workspace_id' => $this->workspaceA->id, 'category' => 'company'],
            ['name' => 'Primary KB', 'status' => 'active']
        );

        $this->knowledgeService->ingestText($kb, 'Note 1', 'First knowledge content', 'general');
        $this->knowledgeService->ingestText($kb, 'Note 2', 'Second knowledge content', 'general');

        $res = $this->actingAs($this->userA)->post(route('client.ai.knowledge.process'));

        $res->assertRedirect();
        $res->assertSessionHas('success');

        $this->assertGreaterThanOrEqual(2, $kb->fresh()->documents()->count());
    }

    public function test_strict_tenant_isolation_prevents_cross_workspace_knowledge_leak(): void
    {
        // Workspace A has sensitive pricing
        $kbA = AiKnowledgeBase::firstOrCreate(
            ['workspace_id' => $this->workspaceA->id, 'category' => 'company'],
            ['name' => 'Workspace A KB', 'status' => 'active']
        );
        $this->knowledgeService->ingestProduct($kbA, [
            'name' => 'Confidential Enterprise Plan',
            'price' => '99999',
            'currency' => 'USD',
        ]);

        // Workspace B has its own KB
        $kbB = AiKnowledgeBase::firstOrCreate(
            ['workspace_id' => $this->workspaceB->id, 'category' => 'company'],
            ['name' => 'Workspace B KB', 'status' => 'active']
        );

        // Search in Workspace B should NEVER find Workspace A's confidential plan
        $resultsB = $this->knowledgeService->search($kbB, 'Confidential Enterprise Plan');
        $this->assertEmpty($resultsB);

        // Unauthorized user B cannot delete User A's document
        $docA = $kbA->documents()->first();
        $res = $this->actingAs($this->userB)->delete(route('client.ai.knowledge.document.destroy', $docA->uuid));
        $res->assertForbidden();
    }
}
