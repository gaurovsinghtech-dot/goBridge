<?php

namespace Tests\Feature;

use App\Models\Crm\CrmPipelineStage;
use App\Models\User;
use App\Modules\Broadcasting\Models\Campaign;
use App\Modules\Broadcasting\Models\CampaignRecipient;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\ContactTag;
use App\Modules\Shared\Models\Segment;
use App\Services\Campaigns\CampaignAiAssistantService;
use App\Services\Campaigns\CampaignAudienceService;
use App\Services\Campaigns\CampaignSafetyService;
use App\Services\Campaigns\CampaignService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CampaignsAndBulkBroadcastTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected $workspace;
    protected $client;
    protected CampaignService $campaignService;
    protected CampaignAudienceService $audienceService;
    protected CampaignSafetyService $safetyService;
    protected CampaignAiAssistantService $aiService;

    protected function setUp(): void
    {
        parent::setUp();

        $ctx = $this->createWorkspaceContext();
        $this->user = $ctx['user'];
        $this->workspace = $ctx['workspace'];
        $this->client = $ctx['client'];

        $this->campaignService = app(CampaignService::class);
        $this->audienceService = app(CampaignAudienceService::class);
        $this->safetyService = app(CampaignSafetyService::class);
        $this->aiService = app(CampaignAiAssistantService::class);
    }

    public function test_campaign_creation_with_omnichannel_support(): void
    {
        // 1. Create WhatsApp Campaign
        $waCampaign = $this->campaignService->createCampaign($this->workspace->id, [
            'name' => 'Q4 Flash Sale WhatsApp',
            'channel' => 'whatsapp',
            'audience_type' => 'all_contacts',
            'template_ref' => ['name' => 'q4_promo', 'language' => 'en'],
        ], $this->user);

        $this->assertDatabaseHas('campaigns', [
            'id' => $waCampaign->id,
            'name' => 'Q4 Flash Sale WhatsApp',
            'channel' => 'whatsapp',
            'status' => 'draft',
        ]);

        // 2. Create Instagram Campaign
        $igCampaign = $this->campaignService->createCampaign($this->workspace->id, [
            'name' => 'Instagram Fall Collection',
            'channel' => 'instagram',
            'audience_type' => 'all_contacts',
            'payload_json' => ['body' => 'Check out our new fall collection!'],
        ], $this->user);

        $this->assertDatabaseHas('campaigns', [
            'id' => $igCampaign->id,
            'channel' => 'instagram',
        ]);

        // 3. Create Messenger Campaign
        $fbCampaign = $this->campaignService->createCampaign($this->workspace->id, [
            'name' => 'Facebook VIP Launch',
            'channel' => 'messenger',
            'audience_type' => 'all_contacts',
            'payload_json' => ['body' => 'VIP Access now open on Facebook!'],
        ], $this->user);

        $this->assertDatabaseHas('campaigns', [
            'id' => $fbCampaign->id,
            'channel' => 'messenger',
        ]);

        // 4. Create Email Campaign
        $emailCampaign = $this->campaignService->createCampaign($this->workspace->id, [
            'name' => 'Monthly Newsletter',
            'channel' => 'email',
            'audience_type' => 'all_contacts',
            'payload_json' => [
                'subject' => 'Monthly Insights',
                'body' => '<p>Hello {{first_name}}</p>',
            ],
        ], $this->user);

        $this->assertDatabaseHas('campaigns', [
            'id' => $emailCampaign->id,
            'channel' => 'email',
        ]);
    }

    public function test_audience_suppression_and_deliverable_count_calculation(): void
    {
        // 1. Deliverable Contact
        $validContact = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Alice',
            'phone_e164' => '+12025550101',
            'email' => 'alice@example.com',
            'opt_in_whatsapp' => true,
            'opt_in_email' => true,
            'lead_score' => 85,
        ]);

        // 2. Opted Out Contact
        $optedOutContact = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Bob',
            'phone_e164' => '+12025550102',
            'email' => 'bob@example.com',
            'opt_in_whatsapp' => false,
            'opt_in_email' => false,
            'lead_score' => 50,
        ]);

        // 3. Missing Phone / Address Contact
        $noPhoneContact = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Charlie',
            'phone_e164' => null,
            'email' => 'charlie@example.com',
            'opt_in_whatsapp' => true,
            'opt_in_email' => true,
            'lead_score' => 60,
        ]);

        // Analyze WhatsApp suppression on All Contacts
        $waAnalysis = $this->audienceService->analyzeAudienceSuppression(
            $this->workspace->id,
            'whatsapp',
            'all_contacts'
        );

        $this->assertEquals(3, $waAnalysis['total_matched']);
        $this->assertEquals(1, $waAnalysis['opted_out_count']); // Bob
        $this->assertEquals(1, $waAnalysis['invalid_address_count']); // Charlie has no phone
        $this->assertEquals(1, $waAnalysis['deliverable_count']); // Only Alice
        $this->assertContains($validContact->id, $waAnalysis['deliverable_ids']);

        // Analyze Email suppression
        $emailAnalysis = $this->audienceService->analyzeAudienceSuppression(
            $this->workspace->id,
            'email',
            'all_contacts'
        );

        $this->assertEquals(3, $emailAnalysis['total_matched']);
        $this->assertEquals(1, $emailAnalysis['opted_out_count']); // Bob
        $this->assertEquals(0, $emailAnalysis['invalid_address_count']); // All have emails
        $this->assertEquals(2, $emailAnalysis['deliverable_count']); // Alice & Charlie
    }

    public function test_campaign_audience_resolution_by_crm_stage_and_lead_score(): void
    {
        $pipeline = \App\Models\Crm\CrmPipeline::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Sales Pipeline',
            'is_default' => true,
        ]);

        $stage = CrmPipelineStage::create([
            'workspace_id' => $this->workspace->id,
            'pipeline_id' => $pipeline->id,
            'name' => 'Proposal Sent',
            'position' => 1,
            'probability' => 60,
            'color' => '#3b82f6',
        ]);

        $stageContact = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Daniel',
            'phone_e164' => '+12025550104',
            'opt_in_whatsapp' => true,
            'stage_id' => $stage->id,
            'lead_score' => 90,
        ]);

        $otherContact = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Eve',
            'phone_e164' => '+12025550105',
            'opt_in_whatsapp' => true,
            'lead_score' => 20,
        ]);

        // Stage resolution
        $stageAnalysis = $this->audienceService->analyzeAudienceSuppression(
            $this->workspace->id,
            'whatsapp',
            'crm_stage',
            (string) $stage->id
        );

        $this->assertEquals(1, $stageAnalysis['deliverable_count']);
        $this->assertContains($stageContact->id, $stageAnalysis['deliverable_ids']);

        // Lead score 'hot' resolution (>= 70)
        $scoreAnalysis = $this->audienceService->analyzeAudienceSuppression(
            $this->workspace->id,
            'whatsapp',
            'lead_score',
            'hot'
        );

        $this->assertContains($stageContact->id, $scoreAnalysis['deliverable_ids']);
        $this->assertNotContains($otherContact->id, $scoreAnalysis['deliverable_ids']);
    }

    public function test_campaign_safety_quiet_hours_and_duplicate_warning(): void
    {
        $campaign = $this->campaignService->createCampaign($this->workspace->id, [
            'name' => 'Black Friday Sale',
            'channel' => 'whatsapp',
            'audience_type' => 'all_contacts',
            'quiet_hours_enabled' => true,
            'quiet_hours_start' => '09:00',
            'quiet_hours_end' => '20:00',
            'timezone' => 'UTC',
        ], $this->user);

        // 1. Within quiet hours (e.g. 14:30 UTC) -> Allowed
        $midday = Carbon::create(2026, 8, 22, 14, 30, 0, 'UTC');
        $this->assertTrue($this->safetyService->isAllowedMessagingTime($campaign, $midday));

        // 2. Outside quiet hours (e.g. 03:00 UTC) -> Blocked
        $lateNight = Carbon::create(2026, 8, 22, 3, 0, 0, 'UTC');
        $this->assertFalse($this->safetyService->isAllowedMessagingTime($campaign, $lateNight));

        // 3. Duplicate detection within 2 hours
        $duplicate = $this->safetyService->detectDuplicateCampaign($this->workspace->id, [
            'name' => 'Black Friday Sale',
            'channel' => 'whatsapp',
            'audience_type' => 'all_contacts',
        ]);

        $this->assertNotNull($duplicate);
        $this->assertEquals($campaign->id, $duplicate->id);
    }

    public function test_campaign_duplicate_and_cloning(): void
    {
        $original = $this->campaignService->createCampaign($this->workspace->id, [
            'name' => 'Summer Special',
            'channel' => 'whatsapp',
            'audience_type' => 'all_contacts',
            'template_ref' => ['name' => 'summer_offer', 'language' => 'en'],
            'status' => 'completed',
        ], $this->user);

        $clone = $this->campaignService->duplicateCampaign($original, $this->user);

        $this->assertEquals('Summer Special (Copy)', $clone->name);
        $this->assertEquals('whatsapp', $clone->channel);
        $this->assertEquals('draft', $clone->status); // Reset to draft
        $this->assertEquals(0, $clone->totals_json['total']); // Reset metrics
    }

    public function test_campaign_pause_resume_and_cancel_lifecycle(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $campaign = $this->campaignService->createCampaign($this->workspace->id, [
            'name' => 'Lifecycle Test Campaign',
            'channel' => 'whatsapp',
            'audience_type' => 'all_contacts',
            'status' => 'queued',
        ], $this->user);

        // 1. Pause
        $this->campaignService->pauseCampaign($campaign);
        $this->assertEquals('paused', $campaign->fresh()->status);

        // 2. Resume
        $this->campaignService->resumeCampaign($campaign->fresh());
        $this->assertEquals('queued', $campaign->fresh()->status);

        // 3. Cancel
        $this->campaignService->cancelCampaign($campaign->fresh());
        $this->assertEquals('cancelled', $campaign->fresh()->status);
    }

    public function test_ai_campaign_copy_generation_and_reply_classification(): void
    {
        // 1. Copy Generation
        $aiCopy = $this->aiService->generateCampaignCopy(
            'Create a 20% discount offer for returning customers',
            'whatsapp',
            'en'
        );

        $this->assertArrayHasKey('objective', $aiCopy);
        $this->assertArrayHasKey('message_body', $aiCopy);
        $this->assertStringContainsString('20%', $aiCopy['message_body']);
        $this->assertStringContainsString('{{first_name', $aiCopy['message_body']);

        // 2. Tone Adjustment
        $friendly = $this->aiService->adjustMessageTone('We have an update for you.', 'friendly');
        $this->assertStringContainsString('Hey', $friendly['text']);

        // 3. Inbound Reply Classification: Price Request
        $priceClass = $this->aiService->classifyReply('Can you send me the price quote for 10 users?');
        $this->assertEquals('price_request', $priceClass['intent']);
        $this->assertEquals('positive', $priceClass['sentiment']);
        $this->assertTrue($priceClass['requires_human_attention']);
        $this->assertGreaterThanOrEqual(20, $priceClass['lead_score_boost']);

        // 4. Inbound Reply Classification: Opt-out
        $optOutClass = $this->aiService->classifyReply('Please stop sending messages and remove me.');
        $this->assertEquals('not_interested', $optOutClass['intent']);
        $this->assertEquals('negative', $optOutClass['sentiment']);
        $this->assertFalse($optOutClass['requires_human_attention']);
    }

    public function test_campaign_rest_api_v1_endpoints(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        Sanctum::actingAs($this->user, ['campaigns:read', 'campaigns:write']);

        // 1. GET /api/v1/campaigns
        $response = $this->getJson('/api/v1/campaigns');
        $response->assertOk();

        // 2. POST /api/v1/campaigns (Create Draft)
        $postResponse = $this->postJson('/api/v1/campaigns', [
            'name' => 'API Created Campaign',
            'channel' => 'whatsapp',
            'audience_type' => 'all_contacts',
            'template_ref' => ['name' => 'api_template', 'language' => 'en'],
        ]);
        $postResponse->assertStatus(201);
        $campaignId = $postResponse->json('data.id');

        // 3. GET /api/v1/campaigns/{id}
        $showResponse = $this->getJson("/api/v1/campaigns/{$campaignId}");
        $showResponse->assertOk();
        $this->assertEquals('API Created Campaign', $showResponse->json('data.name'));

        // 4. POST /api/v1/campaigns/{id}/send
        $sendResponse = $this->postJson("/api/v1/campaigns/{$campaignId}/send");
        $sendResponse->assertOk();
        $this->assertEquals('queued', $sendResponse->json('status'));

        // 5. POST /api/v1/campaigns/{id}/pause
        $pauseResponse = $this->postJson("/api/v1/campaigns/{$campaignId}/pause");
        $pauseResponse->assertOk();
        $this->assertEquals('paused', $pauseResponse->json('status'));

        // 6. POST /api/v1/campaigns/{id}/duplicate
        $dupResponse = $this->postJson("/api/v1/campaigns/{$campaignId}/duplicate");
        $dupResponse->assertStatus(201);
        $this->assertEquals('API Created Campaign (Copy)', $dupResponse->json('data.name'));

        // 7. POST /api/v1/campaigns/{id}/test
        $testResponse = $this->postJson("/api/v1/campaigns/{$campaignId}/test", [
            'phone_e164' => '+12025550199',
        ]);
        $testResponse->assertOk();
        $this->assertTrue($testResponse->json('success'));
    }
}
