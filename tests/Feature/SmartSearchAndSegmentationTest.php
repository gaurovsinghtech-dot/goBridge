<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Segment;
use App\Modules\Shared\Services\SegmentResolver;
use App\Services\Contacts\ContactMergeService;
use App\Services\Search\GlobalSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SmartSearchAndSegmentationTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private User $user;
    private GlobalSearchService $searchService;
    private SegmentResolver $segmentResolver;
    private ContactMergeService $mergeService;

    protected function setUp(): void
    {
        parent::setUp();

        $ctx = $this->createWorkspaceContext();
        $this->user = $ctx['user'];
        $this->workspace = $ctx['workspace'];

        $this->searchService = app(GlobalSearchService::class);
        $this->segmentResolver = app(SegmentResolver::class);
        $this->mergeService = app(ContactMergeService::class);
    }

    public function test_global_search_returns_matched_contacts_scoped_to_workspace(): void
    {
        $contact = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Vikram',
            'last_name' => 'Mehta',
            'phone_e164' => '+919811223344',
            'email' => 'vikram@example.com',
        ]);

        $results = $this->searchService->search($this->workspace, 'Vikram');

        $this->assertCount(1, $results['contacts']);
        $this->assertEquals('Vikram Mehta', $results['contacts'][0]['title']);
    }

    public function test_dynamic_segment_resolver_evaluates_lead_score_and_tags(): void
    {
        $vipTag = \App\Modules\Shared\Models\ContactTag::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'vip',
        ]);

        $c1 = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'High Intent Lead',
            'phone_e164' => '+919811000001',
            'lead_score' => 88,
        ]);
        $c1->tags()->attach($vipTag->id);

        Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Cold Lead',
            'phone_e164' => '+919811000002',
            'lead_score' => 20,
        ]);

        $rules = [
            'combinator' => 'AND',
            'conditions' => [
                ['field' => 'lead_score', 'operator' => '>=', 'value' => 80],
                ['field' => 'tags', 'operator' => 'tag_contains', 'value' => 'vip'],
            ],
        ];

        $count = $this->segmentResolver->previewCount($this->workspace->id, $rules);
        $this->assertEquals(1, $count);
    }

    public function test_contact_merge_service_combines_histories_and_deletes_secondary(): void
    {
        $tag1 = \App\Modules\Shared\Models\ContactTag::create(['workspace_id' => $this->workspace->id, 'name' => 'tag1']);
        $tag2 = \App\Modules\Shared\Models\ContactTag::create(['workspace_id' => $this->workspace->id, 'name' => 'tag2']);

        $primary = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Anil',
            'last_name' => 'Kumar',
            'phone_e164' => '+919800000001',
            'lead_score' => 30,
        ]);
        $primary->tags()->attach($tag1->id);

        $secondary = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Anil',
            'last_name' => 'Kumar',
            'email' => 'anil@example.com',
            'lead_score' => 75,
        ]);
        $secondary->tags()->attach($tag2->id);

        $conversation = Conversation::create([
            'workspace_id' => $this->workspace->id,
            'contact_id' => $secondary->id,
            'channel' => 'whatsapp',
            'status' => 'open',
        ]);

        $merged = $this->mergeService->merge($primary, $secondary, $this->user);

        $this->assertEquals('anil@example.com', $merged->email);
        $this->assertEquals(75, $merged->lead_score);
        $this->assertCount(2, $merged->tags);
        $this->assertSoftDeleted('contacts', ['id' => $secondary->id]);
        $this->assertEquals($primary->id, $conversation->fresh()->contact_id);
    }

    public function test_search_api_requires_contacts_read_scope(): void
    {
        Sanctum::actingAs($this->user, ['contacts:read']);

        Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Suresh',
            'phone_e164' => '+919877665544',
        ]);

        $response = $this->getJson('/api/v1/search?q=Suresh');

        $response->assertOk();
        $response->assertJsonStructure(['success', 'data' => ['contacts', 'conversations', 'calls']]);
    }
}
