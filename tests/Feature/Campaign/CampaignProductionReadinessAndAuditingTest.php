<?php

namespace Tests\Feature\Campaign;

use App\Models\Client;
use App\Models\Crm\CrmPipeline;
use App\Models\Crm\CrmPipelineStage;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\Broadcasting\Jobs\LaunchCampaignJob;
use App\Modules\Broadcasting\Jobs\SendCampaignMessageJob;
use App\Modules\Broadcasting\Models\Campaign;
use App\Modules\Broadcasting\Models\CampaignRecipient;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\ContactTag;
use App\Modules\Whatsapp\Models\WhatsappBusinessAccount;
use App\Modules\Whatsapp\Models\WhatsappTemplate;
use App\Services\Campaigns\CampaignAudienceService;
use App\Services\Campaigns\CampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CampaignProductionReadinessAndAuditingTest extends TestCase
{
    use RefreshDatabase;

    protected Client $client;
    protected Workspace $workspace;
    protected Workspace $otherWorkspace;
    protected User $user;
    protected Plan $growthPlan;
    protected Subscription $subscription;
    protected WhatsappBusinessAccount $waba;
    protected WhatsappTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        $this->growthPlan = Plan::create([
            'name' => 'Growth',
            'slug' => 'growth',
            'price_cents' => 9900,
            'currency_code' => 'USD',
            'interval' => 'month',
            'enabled' => true,
            'features' => [
                'campaigns' => true,
                'email_marketing' => true,
                'whatsapp' => true,
                'automations' => true,
                'crm' => true,
            ],
        ]);

        $this->client = Client::create([
            'name' => 'Acme Global',
            'status' => 'active',
        ]);

        $this->workspace = Workspace::create([
            'client_id' => $this->client->id,
            'name' => 'Acme Production Marketing',
            'status' => 'active',
        ]);

        $this->otherWorkspace = Workspace::create([
            'client_id' => $this->client->id,
            'name' => 'Other Tenant Workspace',
            'status' => 'active',
        ]);

        $this->user = User::create([
            'name' => 'Campaign Manager',
            'email' => 'manager@acmeglobal.com',
            'password' => bcrypt('Secret123!'),
            'role' => User::ROLE_CLIENT,
            'client_id' => $this->client->id,
            'client_role' => User::CLIENT_ROLE_ADMINISTRATOR,
            'workspace_id' => $this->workspace->id,
            'current_workspace_id' => $this->workspace->id,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->subscription = Subscription::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'plan_id' => $this->growthPlan->id,
            'gateway' => 'stripe',
            'status' => 'active',
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->endOfMonth(),
        ]);

        $this->waba = WhatsappBusinessAccount::create([
            'workspace_id' => $this->workspace->id,
            'waba_id' => 'WABA_TEST_100',
            'credentials' => ['access_token' => 'meta_mock_token_xyz'],
            'status' => 'active',
        ]);

        \App\Modules\Whatsapp\Models\WhatsappPhoneNumber::create([
            'waba_id_fk' => $this->waba->id,
            'phone_number_id' => 'PHONE_TEST_100',
            'display_phone' => '+15551234567',
            'quality_rating' => 'GREEN',
        ]);

        $this->template = WhatsappTemplate::create([
            'workspace_id' => $this->workspace->id,
            'waba_id' => $this->waba->waba_id,
            'name' => 'lead_welcome_promo',
            'language' => 'en_US',
            'category' => 'MARKETING',
            'status' => 'APPROVED',
            'components' => [
                ['type' => 'BODY', 'text' => 'Hello {{1}}, welcome to Growbridge! Special offer for {{2}}.'],
            ],
        ]);
    }

    public function test_campaign_crud_draft_and_duplication(): void
    {
        $response = $this->actingAs($this->user)->post(route('client.campaigns.store'), [
            'name' => 'Q4 Holiday WhatsApp Special',
            'channel' => 'whatsapp',
            'audience_type' => 'all_contacts',
            'whatsapp_phone_number_id' => 'PHONE_TEST_100',
            'template_ref' => [
                'name' => $this->template->name,
                'language' => $this->template->language,
            ],
            'status' => 'draft',
        ]);

        $response->assertRedirect();

        $campaign = Campaign::where('workspace_id', $this->workspace->id)
            ->where('name', 'Q4 Holiday WhatsApp Special')
            ->first();

        $this->assertNotNull($campaign);
        $this->assertEquals('whatsapp', $campaign->channel);
        $this->assertEquals('draft', $campaign->status);

        // Duplicate
        $dupResponse = $this->actingAs($this->user)->post(route('client.campaigns.duplicate', $campaign->uuid));
        $dupResponse->assertRedirect();

        $duplicate = Campaign::where('workspace_id', $this->workspace->id)
            ->where('name', 'Q4 Holiday WhatsApp Special (Copy)')
            ->first();

        $this->assertNotNull($duplicate);
        $this->assertEquals('draft', $duplicate->status);
    }

    public function test_advanced_audience_segmentation_and_strict_cross_workspace_isolation(): void
    {
        // Setup Pipeline & Stage
        $pipeline = CrmPipeline::create(['workspace_id' => $this->workspace->id, 'name' => 'Sales Funnel', 'order' => 1]);
        $stage = CrmPipelineStage::create(['workspace_id' => $this->workspace->id, 'pipeline_id' => $pipeline->id, 'name' => 'Qualified Leads', 'order' => 1]);

        // Contacts in Workspace A
        $tagVip = ContactTag::create(['workspace_id' => $this->workspace->id, 'name' => 'VIP']);
        
        $c1 = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Aditi',
            'phone_e164' => '+919900000001',
            'email' => 'aditi@acme.com',
            'source' => 'facebook',
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
            'assigned_user_id' => $this->user->id,
            'custom_fields' => ['industry' => 'FinTech', 'city' => 'Mumbai', 'status' => 'qualified'],
            'opt_in_whatsapp' => true,
            'opt_in_email' => true,
        ]);
        $c1->tags()->attach($tagVip->id);

        $c2 = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Rohan',
            'phone_e164' => '+919900000002',
            'email' => 'rohan@acme.com',
            'source' => 'google',
            'custom_fields' => ['city' => 'Delhi', 'status' => 'new'],
            'opt_in_whatsapp' => true,
            'opt_in_email' => false, // Opted out of email
        ]);

        // Contact in Other Workspace (Tenant B) with identical parameters
        $otherContact = Contact::create([
            'workspace_id' => $this->otherWorkspace->id,
            'first_name' => 'Hacker',
            'phone_e164' => '+919900000999',
            'email' => 'hacker@other.com',
            'source' => 'facebook',
            'stage_id' => $stage->id,
            'custom_fields' => ['city' => 'Mumbai', 'status' => 'qualified'],
            'opt_in_whatsapp' => true,
            'opt_in_email' => true,
        ]);

        $audienceService = app(CampaignAudienceService::class);

        // 1. Tag filtering
        $tagCandidates = $audienceService->resolveCandidateContactIds($this->workspace->id, 'tag', 'VIP');
        $this->assertContains($c1->id, $tagCandidates);
        $this->assertNotContains($c2->id, $tagCandidates);
        $this->assertNotContains($otherContact->id, $tagCandidates);

        // 2. Stage filtering
        $stageCandidates = $audienceService->resolveCandidateContactIds($this->workspace->id, 'pipeline_stage', (string) $stage->id);
        $this->assertContains($c1->id, $stageCandidates);
        $this->assertNotContains($otherContact->id, $stageCandidates);

        // 3. Structured multi-filter JSON
        $structuredFilter = json_encode([
            'filters' => [
                'source' => 'facebook',
                'city' => 'Mumbai',
                'custom_fields' => ['industry' => 'FinTech'],
            ],
        ]);
        $structuredCandidates = $audienceService->resolveCandidateContactIds($this->workspace->id, 'structured', $structuredFilter);
        $this->assertEquals([$c1->id], $structuredCandidates);
        $this->assertNotContains($otherContact->id, $structuredCandidates);

        // 4. Suppression matrix analysis
        $previewWhatsApp = $audienceService->analyzeAudienceSuppression($this->workspace->id, 'whatsapp', 'all_contacts');
        $this->assertEquals(2, $previewWhatsApp['total_audience']);
        $this->assertEquals(2, $previewWhatsApp['valid_recipients']);
        $this->assertEquals(0, $previewWhatsApp['excluded_recipients']);

        $previewEmail = $audienceService->analyzeAudienceSuppression($this->workspace->id, 'email', 'all_contacts');
        $this->assertEquals(2, $previewEmail['total_audience']);
        $this->assertEquals(1, $previewEmail['valid_recipients']); // c2 is opted out of email
        $this->assertEquals(1, $previewEmail['opted_out_count']);
    }

    public function test_large_campaign_requires_explicit_confirmation(): void
    {
        // Create 505 dummy contacts
        $bulkContacts = [];
        $now = now();
        for ($i = 1; $i <= 505; $i++) {
            $bulkContacts[] = [
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'workspace_id' => $this->workspace->id,
                'first_name' => "User{$i}",
                'phone_e164' => "+1555000" . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'opt_in_whatsapp' => true,
                'opt_in_email' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        Contact::insert($bulkContacts);

        $campaign = Campaign::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Mega Broadcast 500+',
            'channel' => 'whatsapp',
            'audience_type' => 'all_contacts',
            'whatsapp_phone_number_id' => 'PHONE_TEST_100',
            'template_ref' => ['name' => $this->template->name, 'language' => $this->template->language],
            'status' => 'draft',
        ]);

        $campaignService = app(CampaignService::class);

        // Attempt launch without confirmation -> should throw InvalidArgumentException
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('requires explicit confirmation');
        $campaignService->launchCampaign($campaign, false);
    }

    public function test_large_campaign_launches_successfully_with_confirmed_flag(): void
    {
        Queue::fake([LaunchCampaignJob::class]);

        $bulkContacts = [];
        $now = now();
        for ($i = 1; $i <= 505; $i++) {
            $bulkContacts[] = [
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'workspace_id' => $this->workspace->id,
                'first_name' => "User{$i}",
                'phone_e164' => "+1555100" . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'opt_in_whatsapp' => true,
                'opt_in_email' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        Contact::insert($bulkContacts);

        $campaign = Campaign::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Mega Broadcast Confirmed',
            'channel' => 'whatsapp',
            'audience_type' => 'all_contacts',
            'whatsapp_phone_number_id' => 'PHONE_TEST_100',
            'template_ref' => ['name' => $this->template->name, 'language' => $this->template->language],
            'status' => 'draft',
        ]);

        // Launch via controller with confirmed = true
        $response = $this->actingAs($this->user)->post(route('client.campaigns.launch', $campaign->uuid), [
            'confirmed' => true,
        ]);

        $response->assertRedirect();
        $campaign->refresh();

        $this->assertEquals('queued', $campaign->status);
        $this->assertNotNull($campaign->confirmed_at);
        $this->assertGreaterThan(0, (float) $campaign->estimated_cost);

        Queue::assertPushed(LaunchCampaignJob::class, fn ($j) => $j->campaignId === $campaign->id);
    }

    public function test_email_campaign_delivery_open_click_and_rfc8058_unsubscribe(): void
    {
        Mail::fake();

        $contact = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Priya',
            'last_name' => 'Sharma',
            'email' => 'priya@growbridgeclient.com',
            'opt_in_email' => true,
        ]);

        $campaign = Campaign::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Product Newsletter Q3',
            'channel' => 'email',
            'audience_type' => 'all_contacts',
            'payload_json' => [
                'subject' => 'Hello {{contact.first_name}} - Big Updates',
                'body' => '<p>Check out our <a href="https://example.com/pricing">Pricing Page</a></p>',
                'track_opens' => true,
                'track_clicks' => true,
            ],
            'status' => 'sending',
        ]);

        $recipient = CampaignRecipient::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'status' => 'queued',
        ]);

        // Execute send job
        $job = new SendCampaignMessageJob($campaign->id, $contact->id);
        $job->handle(app(\App\Modules\Broadcasting\Services\CampaignPersonalizer::class));

        $recipient->refresh();
        $this->assertEquals('sent', $recipient->status);
        $this->assertNotNull($recipient->tracking_token);
        $this->assertNotNull($recipient->unsubscribe_token);

        // 1. Open tracking
        $openResponse = $this->get(route('track.email.open', ['token' => $recipient->tracking_token]));
        $openResponse->assertStatus(200);
        $openResponse->assertHeader('Content-Type', 'image/gif');

        $recipient->refresh();
        $this->assertEquals('read', $recipient->status);
        $this->assertNotNull($recipient->delivered_at);
        $this->assertNotNull($recipient->read_at);

        // 2. Click tracking (signed URL)
        $targetUrl = 'https://example.com/pricing';
        $signedClickUrl = URL::signedRoute('track.email.click', [
            'token' => $recipient->tracking_token,
            'url' => $targetUrl,
        ]);

        $clickResponse = $this->get($signedClickUrl);
        $clickResponse->assertRedirect($targetUrl);

        $recipient->refresh();
        $this->assertNotNull($recipient->clicked_at);

        // 3. RFC 8058 One-Click Unsubscribe (POST)
        $unsubResponse = $this->post(route('track.email.unsubscribe', ['token' => $recipient->unsubscribe_token]));
        $unsubResponse->assertStatus(200);

        $recipient->refresh();
        $contact->refresh();
        $this->assertNotNull($recipient->opted_out_at);
        $this->assertFalse((bool) $contact->opt_in_email);

        // Verify future sends to this contact are blocked as permanent failure / opted out
        $nextCampaign = Campaign::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Product Newsletter Q4 Subsequent',
            'channel' => 'email',
            'audience_type' => 'all_contacts',
            'payload_json' => [
                'subject' => 'Should Not Send',
                'body' => '<p>Blocked</p>',
            ],
            'status' => 'sending',
        ]);

        $newRecipient = CampaignRecipient::create([
            'campaign_id' => $nextCampaign->id,
            'contact_id' => $contact->id,
            'status' => 'queued',
        ]);

        $newJob = new SendCampaignMessageJob($nextCampaign->id, $contact->id);
        $newJob->handle(app(\App\Modules\Broadcasting\Services\CampaignPersonalizer::class));

        $newRecipient->refresh();
        $this->assertEquals('failed', $newRecipient->status);
        $this->assertEquals('opted_out', $newRecipient->failed_reason);
    }

    public function test_permanent_failure_does_not_retry(): void
    {
        $contact = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'InvalidNumberUser',
            'phone_e164' => '+10000000000',
            'opt_in_whatsapp' => true,
        ]);

        $campaign = Campaign::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Error Test Broadcast',
            'channel' => 'whatsapp',
            'audience_type' => 'all_contacts',
            'whatsapp_phone_number_id' => 'PHONE_TEST_100',
            'template_ref' => ['name' => $this->template->name, 'language' => $this->template->language],
            'status' => 'sending',
        ]);

        $recipient = CampaignRecipient::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'status' => 'queued',
        ]);

        // Mock Meta Cloud API returning permanent failure (131026: Undeliverable / Not on WhatsApp)
        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'error' => [
                    'message' => 'Message undeliverable: User is not registered on WhatsApp (code 131026)',
                    'code' => 131026,
                ],
            ], 400),
        ]);

        $job = new SendCampaignMessageJob($campaign->id, $contact->id);
        
        // Handle should catch permanent error and NOT throw exception
        $job->handle(app(\App\Modules\Broadcasting\Services\CampaignPersonalizer::class));

        $recipient->refresh();
        $this->assertEquals('failed', $recipient->status);
        $this->assertStringContainsString('131026', $recipient->failed_reason);
    }

    public function test_campaign_pause_resume_cancel_lifecycle(): void
    {
        Queue::fake();

        $campaign = Campaign::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Pausable Campaign',
            'channel' => 'whatsapp',
            'audience_type' => 'all_contacts',
            'whatsapp_phone_number_id' => 'PHONE_TEST_100',
            'template_ref' => ['name' => $this->template->name, 'language' => $this->template->language],
            'status' => 'queued',
        ]);

        $service = app(CampaignService::class);

        // Pause
        $service->pauseCampaign($campaign);
        $campaign->refresh();
        $this->assertEquals('paused', $campaign->status);

        // Resume
        $service->resumeCampaign($campaign);
        $campaign->refresh();
        $this->assertEquals('queued', $campaign->status);

        // Cancel
        $service->cancelCampaign($campaign);
        $campaign->refresh();
        $this->assertEquals('cancelled', $campaign->status);
    }
}
