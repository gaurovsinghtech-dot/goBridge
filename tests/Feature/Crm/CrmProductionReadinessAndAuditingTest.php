<?php

namespace Tests\Feature\Crm;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Crm\CrmCompany;
use App\Models\Crm\CrmCustomField;
use App\Models\Crm\CrmDeal;
use App\Models\Crm\CrmNote;
use App\Models\Crm\CrmPipeline;
use App\Models\Crm\CrmPipelineStage;
use App\Models\Crm\CrmTask;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\ContactTag;
use App\Modules\Shared\Models\ContactTimelineEvent;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use App\Modules\Voice\Models\VoiceCall;
use App\Services\Crm\ContactDuplicateService;
use App\Services\Crm\CrmAnalyticsService;
use App\Services\Crm\CrmCustomFieldService;
use App\Services\Crm\CrmImportService;
use App\Services\Customer\CustomerTimelineService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrmProductionReadinessAndAuditingTest extends TestCase
{
    use RefreshDatabase;

    protected function setupWorkspace(string $name = 'Alpha Corp', string $userEmail = 'admin@alphacorp.in', string $clientRole = User::CLIENT_ROLE_ADMINISTRATOR): array
    {
        $client = Client::create([
            'name' => $name,
            'email' => $userEmail,
            'status' => 'active',
        ]);

        $workspace = Workspace::create([
            'client_id' => $client->id,
            'name' => "{$name} Primary Workspace",
            'currency_code' => 'INR',
            'status' => 'active',
        ]);

        $user = User::create([
            'name' => "Manager {$name}",
            'email' => $userEmail,
            'password' => bcrypt('SecretPassword123!'),
            'role' => User::ROLE_CLIENT,
            'client_id' => $client->id,
            'client_role' => $clientRole,
            'workspace_id' => $workspace->id,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        $workspace->forceFill(['owner_id' => $user->id])->saveQuietly();
        $workspace->members()->syncWithoutDetaching([$user->id => ['role' => 'owner']]);

        return compact('client', 'workspace', 'user');
    }

    // ── 1. LEADS: CRUD, Status, Tags, Custom Fields, Convert, Search, Filter, Bulk ──

    public function test_leads_crud_custom_fields_convert_and_bulk_actions(): void
    {
        ['workspace' => $ws, 'user' => $user] = $this->setupWorkspace();

        // 1. Define custom field for lead
        CrmCustomField::create([
            'workspace_id' => $ws->id,
            'entity_type' => 'lead',
            'name' => 'Budget Range',
            'key' => 'budget_range',
            'type' => 'dropdown',
            'options' => ['Low', 'Medium', 'High', 'Enterprise'],
            'is_required' => false,
        ]);

        // 2. Create Lead
        $response = $this->actingAs($user)
            ->withSession(['current_workspace_id' => $ws->id])
            ->postJson(route('client.crm.leads.store'), [
                'first_name' => 'Rajesh',
                'last_name' => 'Kumar',
                'company' => 'Kumar Logistics Pvt Ltd',
                'phone_e164' => '+919876543210',
                'email' => 'rajesh@kumarlogistics.in',
                'deal_value' => 50000,
                'priority' => 'high',
                'tags' => ['Hot Lead', 'B2B'],
                'custom_fields' => ['budget_range' => 'High'],
            ]);

        $response->assertStatus(201);
        $leadId = $response->json('data.id');
        $uuid = $response->json('data.uuid');

        $lead = Contact::findOrFail($leadId);
        $this->assertEquals('Rajesh Kumar', $lead->full_name);
        $this->assertEquals('High', $lead->custom_fields['budget_range']);
        $this->assertCount(2, $lead->tags);

        // 3. Edit Lead
        $updateResp = $this->actingAs($user)
            ->withSession(['current_workspace_id' => $ws->id])
            ->putJson(route('client.crm.leads.update', $uuid), [
                'first_name' => 'Rajesh',
                'last_name' => 'Kumar Sharma',
                'deal_value' => 75000,
                'priority' => 'urgent',
            ]);

        $updateResp->assertOk();
        $this->assertEquals('Rajesh Kumar Sharma', $lead->fresh()->full_name);
        $this->assertEquals(75000, $lead->fresh()->deal_value);

        // 4. Convert Lead to Contact + Deal
        $convertResp = $this->actingAs($user)
            ->withSession(['current_workspace_id' => $ws->id])
            ->postJson(route('client.crm.leads.convert', $uuid), [
                'deal_name' => 'Kumar Fleet Management Deal',
                'deal_value' => 75000,
                'company_name' => 'Kumar Logistics Pvt Ltd',
            ]);

        $convertResp->assertOk();
        $deal = CrmDeal::where('contact_id', $lead->id)->first();
        $this->assertNotNull($deal);
        $this->assertEquals('Kumar Fleet Management Deal', $deal->name);
        $this->assertEquals(75000, $deal->value);
        $this->assertEquals('Kumar Logistics Pvt Ltd', $deal->company?->name);

        // 5. Search & Filter Leads
        $searchResp = $this->actingAs($user)
            ->withSession(['current_workspace_id' => $ws->id])
            ->getJson(route('client.crm.leads.index', ['search' => 'Rajesh', 'priority' => 'urgent']));

        $searchResp->assertOk();
        $this->assertCount(1, $searchResp->json('data'));

        // 6. Bulk Actions (Bulk Stage / Bulk Assign / Bulk Delete)
        $bulkLead2 = Contact::create([
            'workspace_id' => $ws->id,
            'first_name' => 'Sunil',
            'last_name' => 'Patel',
            'phone_e164' => '+919988776655',
            'email' => 'sunil@patel.in',
            'deal_value' => 20000,
        ]);

        $bulkResp = $this->actingAs($user)
            ->withSession(['current_workspace_id' => $ws->id])
            ->postJson(route('client.crm.leads.bulk'), [
                'action' => 'assign',
                'ids' => [$lead->id, $bulkLead2->id],
                'assigned_user_id' => $user->id,
            ]);

        $bulkResp->assertOk();
        $this->assertEquals($user->id, $lead->fresh()->assigned_user_id);
        $this->assertEquals($user->id, $bulkLead2->fresh()->assigned_user_id);
    }

    // ── 2. CONTACTS: Duplicate Detection & Normalized Phone/Email ──

    public function test_contacts_duplicate_detection_and_normalization(): void
    {
        ['workspace' => $ws] = $this->setupWorkspace();
        $dupService = app(ContactDuplicateService::class);

        // Normalized phone checks
        $this->assertEquals('+919876543210', $dupService->normalizePhone(' +91 98765-43210 '));
        $this->assertEquals('+919876543210', $dupService->normalizePhone('+91 (987) 654-3210'));
        $this->assertEquals('9876543210', $dupService->normalizePhone('09876543210'));

        // Normalized email checks
        $this->assertEquals('user@example.com', $dupService->normalizeEmail('  USER@EXAMPLE.COM  '));

        // Create initial contact
        $primary = Contact::create([
            'workspace_id' => $ws->id,
            'first_name' => 'Anil',
            'last_name' => 'Mehta',
            'phone_e164' => '+919876543210',
            'email' => 'anil@mehta.com',
            'deal_value' => 30000,
        ]);

        // Exact match by formatted phone
        $found1 = $dupService->findDuplicate($ws->id, ' +91 98765-43210 ', null);
        $this->assertNotNull($found1);
        $this->assertEquals($primary->id, $found1->id);

        // Exact match by upper case email
        $found2 = $dupService->findDuplicate($ws->id, null, 'ANIL@MEHTA.COM');
        $this->assertNotNull($found2);
        $this->assertEquals($primary->id, $found2->id);

        // Merge contacts test (secondary matched by duplicate email)
        $secondary = Contact::create([
            'workspace_id' => $ws->id,
            'first_name' => 'Anil',
            'company' => 'Mehta Trading Co',
            'phone_e164' => '+919876543211',
            'email' => 'anil@mehta.com',
            'custom_fields' => ['tier' => 'Platinum'],
        ]);

        $merged = $dupService->mergeContacts($primary, $secondary);
        $this->assertEquals('Mehta Trading Co', $merged->company);
        $this->assertEquals('Platinum', $merged->custom_fields['tier']);
        $this->assertTrue($secondary->fresh()->trashed());
        $this->assertEquals($primary->id, $secondary->fresh()->duplicate_of_id);
    }

    // ── 3. COMPANIES: Full CRUD, Association, and Custom Fields ──

    public function test_companies_crud_and_contact_deal_association(): void
    {
        ['workspace' => $ws, 'user' => $user] = $this->setupWorkspace();

        // 1. Create Company
        $createResp = $this->actingAs($user)
            ->withSession(['current_workspace_id' => $ws->id])
            ->postJson(route('client.crm.companies.store'), [
                'name' => 'Tata Consultancy Services',
                'industry' => 'Information Technology',
                'website' => 'https://tcs.com',
                'phone' => '+912267789999',
                'email' => 'contact@tcs.com',
                'city' => 'Mumbai',
                'country' => 'India',
            ]);

        $createResp->assertStatus(201);
        $companyId = $createResp->json('data.id');

        $company = CrmCompany::findOrFail($companyId);
        $this->assertEquals('Tata Consultancy Services', $company->name);
        $this->assertEquals('Information Technology', $company->industry);

        // 2. Associate Contact & Deal with Company
        $contact = Contact::create([
            'workspace_id' => $ws->id,
            'first_name' => 'Natarajan',
            'last_name' => 'Chandrasekaran',
            'company_id' => $company->id,
            'company' => $company->name,
            'phone_e164' => '+919800011122',
            'email' => 'chandra@tcs.com',
        ]);

        $deal = CrmDeal::create([
            'workspace_id' => $ws->id,
            'contact_id' => $contact->id,
            'company_id' => $company->id,
            'name' => 'Enterprise Cloud Migration 2026',
            'value' => 5000000,
            'currency' => 'INR',
            'status' => 'open',
        ]);

        $this->assertCount(1, $company->contacts);
        $this->assertCount(1, $company->deals);

        // 3. Show Company endpoint
        $showResp = $this->actingAs($user)
            ->withSession(['current_workspace_id' => $ws->id])
            ->getJson(route('client.crm.companies.show', $company->id));

        $showResp->assertOk();
        $this->assertEquals('Enterprise Cloud Migration 2026', $showResp->json('data.deals.0.name'));

        // 4. Update Company
        $updateResp = $this->actingAs($user)
            ->withSession(['current_workspace_id' => $ws->id])
            ->putJson(route('client.crm.companies.update', $company->id), [
                'name' => 'Tata Sons & TCS Ltd',
                'industry' => 'Conglomerate & IT',
            ]);

        $updateResp->assertOk();
        $this->assertEquals('Tata Sons & TCS Ltd', $company->fresh()->name);
    }

    // ── 4. PIPELINES: Multiple Pipelines, Stages, Ordering, Deal Counts ──

    public function test_multiple_pipelines_stage_reordering_and_deal_counts(): void
    {
        ['workspace' => $ws, 'user' => $user] = $this->setupWorkspace();

        // 1. Create Inbound & Enterprise Pipelines
        $inboundResp = $this->actingAs($user)
            ->withSession(['current_workspace_id' => $ws->id])
            ->postJson(route('client.crm.pipelines.store'), [
                'name' => 'Enterprise Sales Pipeline',
                'is_default' => true,
            ]);

        $inboundResp->assertStatus(201);
        $pipelineId = $inboundResp->json('data.id');
        $pipeline = CrmPipeline::findOrFail($pipelineId);

        $this->assertCount(7, $pipeline->stages); // 7 default stages

        // 2. Add Custom Stage
        $stageResp = $this->actingAs($user)
            ->withSession(['current_workspace_id' => $ws->id])
            ->postJson(route('client.crm.stages.store', $pipeline->id), [
                'name' => 'Security Audit & Compliance',
                'color' => 'indigo',
                'probability' => 80,
            ]);

        $stageResp->assertStatus(201);
        $newStageId = $stageResp->json('data.id');

        // 3. Reorder Stages
        $reorderResp = $this->actingAs($user)
            ->withSession(['current_workspace_id' => $ws->id])
            ->postJson(route('client.crm.stages.reorder', $pipeline->id), [
                'stages' => [
                    ['id' => $newStageId, 'position' => 0],
                ],
            ]);

        $reorderResp->assertOk();
        $this->assertEquals(0, CrmPipelineStage::find($newStageId)->position);
    }

    // ── 5. DEALS: Probability, Close Date, Notes, Stage Transitions ──

    public function test_deals_lifecycle_stage_transitions_and_weighted_values(): void
    {
        ['workspace' => $ws, 'user' => $user] = $this->setupWorkspace();

        $pipeline = CrmPipeline::create([
            'workspace_id' => $ws->id,
            'name' => 'B2B SaaS Sales',
            'is_default' => true,
        ]);

        $stageQualified = CrmPipelineStage::create([
            'workspace_id' => $ws->id,
            'pipeline_id' => $pipeline->id,
            'name' => 'Qualified',
            'color' => 'blue',
            'probability' => 50,
            'position' => 1,
        ]);

        $stageWon = CrmPipelineStage::create([
            'workspace_id' => $ws->id,
            'pipeline_id' => $pipeline->id,
            'name' => 'Closed Won',
            'color' => 'emerald',
            'probability' => 100,
            'is_won' => true,
            'position' => 2,
        ]);

        $contact = Contact::create([
            'workspace_id' => $ws->id,
            'first_name' => 'Pooja',
            'last_name' => 'Hegde',
            'phone_e164' => '+919871122334',
        ]);

        // 1. Create Deal
        $createResp = $this->actingAs($user)
            ->withSession(['current_workspace_id' => $ws->id])
            ->postJson(route('client.crm.deals.store'), [
                'contact_id' => $contact->id,
                'pipeline_id' => $pipeline->id,
                'stage_id' => $stageQualified->id,
                'name' => 'Annual SaaS Subscription 2026',
                'value' => 120000,
                'probability' => 50,
                'expected_close_date' => '2026-12-31',
                'notes' => 'Customer requested customized billing cycle.',
            ]);

        $createResp->assertStatus(201);
        $dealId = $createResp->json('data.id');
        $deal = CrmDeal::findOrFail($dealId);

        $this->assertEquals(60000.0, $deal->weighted_value); // 120000 * 50%

        // 2. Transition Stage to Won
        $stageMoveResp = $this->actingAs($user)
            ->withSession(['current_workspace_id' => $ws->id])
            ->postJson(route('client.crm.deals.stage', $deal->id), [
                'stage_id' => $stageWon->id,
            ]);

        $stageMoveResp->assertOk();
        $this->assertEquals('won', $deal->fresh()->status);
        $this->assertEquals(100, $deal->fresh()->probability);
        $this->assertEquals(120000.0, $deal->fresh()->weighted_value);
    }

    // ── 6. UNIFIED ACTIVITY TIMELINE: WhatsApp, Email, Calls, Notes, Tasks, Stage Changes ──

    public function test_unified_activity_timeline_aggregates_all_channels(): void
    {
        ['workspace' => $ws] = $this->setupWorkspace();
        $timelineService = app(CustomerTimelineService::class);

        $contact = Contact::create([
            'workspace_id' => $ws->id,
            'first_name' => 'Kavita',
            'last_name' => 'Krishnan',
            'phone_e164' => '+919811223344',
            'email' => 'kavita@krishnan.in',
        ]);

        // 1. WhatsApp conversation & message
        $conv = Conversation::create([
            'workspace_id' => $ws->id,
            'contact_id' => $contact->id,
            'channel' => 'whatsapp',
            'ai_mode' => 'auto',
            'last_message_at' => now()->subHours(2),
        ]);
        Message::create([
            'conversation_id' => $conv->id,
            'direction' => 'in',
            'channel' => 'whatsapp',
            'body' => 'I would like to schedule a product demo tomorrow.',
            'status' => 'delivered',
            'sent_at' => now()->subHours(2),
        ]);

        // 2. Voice Call
        VoiceCall::create([
            'workspace_id' => $ws->id,
            'contact_id' => $contact->id,
            'direction' => 'outbound',
            'from_number' => '+918012345678',
            'to_number' => '+919811223344',
            'duration_sec' => 185,
            'outcome' => 'qualified',
            'summary' => 'Customer confirmed demo attendance.',
            'status' => 'completed',
        ]);

        // 3. CRM Task
        CrmTask::create([
            'workspace_id' => $ws->id,
            'contact_id' => $contact->id,
            'title' => 'Send Demo Meeting Link',
            'priority' => 'high',
            'status' => 'pending',
            'due_at' => now()->addDay(),
        ]);

        // 4. CRM Note
        CrmNote::create([
            'workspace_id' => $ws->id,
            'contact_id' => $contact->id,
            'content' => 'Key decision maker for 100+ seats.',
        ]);

        // 5. Timeline Event (Email Sent & Stage Change)
        ContactTimelineEvent::create([
            'workspace_id' => $ws->id,
            'contact_id' => $contact->id,
            'channel' => 'email',
            'event_type' => 'email_sent',
            'title' => 'Product Demo Calendar Invite Sent',
            'description' => 'Sent invitation to kavita@krishnan.in',
            'occurred_at' => now()->subHour(),
        ]);

        $timeline = $timelineService->getTimeline($contact);

        $this->assertNotEmpty($timeline);
        $types = collect($timeline)->pluck('type')->all();

        $this->assertContains('whatsapp', $types);
        $this->assertContains('voice', $types);
        $this->assertContains('task', $types);
        $this->assertContains('note', $types);
        $this->assertContains('email', $types);
    }

    // ── 7. TASKS & FOLLOW-UPS: Overdue, Due Today, Upcoming ──

    public function test_tasks_statuses_and_follow_up_dashboard_filtering(): void
    {
        ['workspace' => $ws, 'user' => $user] = $this->setupWorkspace();

        $contact = Contact::create([
            'workspace_id' => $ws->id,
            'first_name' => 'Deepak',
            'phone_e164' => '+919822334455',
        ]);

        // 1. Create Overdue Task
        $overdueTask = CrmTask::create([
            'workspace_id' => $ws->id,
            'contact_id' => $contact->id,
            'title' => 'Send Follow-up Quotation',
            'priority' => 'urgent',
            'status' => 'pending',
            'due_at' => Carbon::yesterday(),
        ]);
        $this->assertTrue($overdueTask->isOverdue());

        // 2. Create Due Today Task
        $todayTask = CrmTask::create([
            'workspace_id' => $ws->id,
            'contact_id' => $contact->id,
            'title' => 'Call Client at 3 PM',
            'priority' => 'high',
            'status' => 'in_progress',
            'due_at' => Carbon::today()->addHours(15),
        ]);

        // 3. Create Upcoming Task
        $upcomingTask = CrmTask::create([
            'workspace_id' => $ws->id,
            'contact_id' => $contact->id,
            'title' => 'Quarterly Business Review',
            'priority' => 'medium',
            'status' => 'pending',
            'due_at' => Carbon::now()->addDays(5),
        ]);

        // 4. Test Follow-ups API summary
        $followUpResp = $this->actingAs($user)
            ->withSession(['current_workspace_id' => $ws->id])
            ->getJson(route('client.crm.tasks.follow-ups'));

        $followUpResp->assertOk();
        $this->assertEquals(1, $followUpResp->json('data.overdue_count'));
        $this->assertEquals(1, $followUpResp->json('data.due_today_count'));
        $this->assertEquals(1, $followUpResp->json('data.upcoming_count'));

        // 5. Update Status to Completed
        $completeResp = $this->actingAs($user)
            ->withSession(['current_workspace_id' => $ws->id])
            ->putJson(route('client.crm.tasks.status', $overdueTask->id), [
                'status' => 'completed',
            ]);

        $completeResp->assertOk();
        $this->assertEquals('completed', $overdueTask->fresh()->status);
        $this->assertFalse($overdueTask->fresh()->isOverdue());
    }

    // ── 8. CSV IMPORT: Upload, Preview, Mapping, Validation, Error Report ──

    public function test_csv_import_validation_duplicate_resolution_and_error_reporting(): void
    {
        ['workspace' => $ws, 'user' => $user] = $this->setupWorkspace();
        $importService = app(CrmImportService::class);

        // Pre-existing contact for duplicate test
        Contact::create([
            'workspace_id' => $ws->id,
            'first_name' => 'Existing',
            'last_name' => 'Customer',
            'phone_e164' => '+919999900001',
            'email' => 'existing@growbridge.io',
            'company' => 'Old Company',
        ]);

        // CSV content: 1 valid, 1 existing duplicate, 1 invalid email, 1 missing contact identifiers
        $csvContent = implode("\n", [
            'Name,Phone,Email,Company,Deal Value',
            'Aarav Gupta,+919999900002,aarav@gupta.com,Gupta Enterprises,45000',
            'Existing Customer,+919999900001,existing@growbridge.io,Updated Company,80000',
            'Invalid User,+919999900003,not-an-email,Bad Email Corp,20000',
            ',,,Missing Everything,10000',
        ]);

        $tmpFile = tempnam(sys_get_temp_dir(), 'crm_test_');
        file_put_contents($tmpFile, $csvContent);

        $parsed = $importService->parseCsv($tmpFile);
        $this->assertEquals(4, $parsed['total']);

        $mapping = [
            'Name' => 'name',
            'Phone' => 'phone',
            'Email' => 'email',
            'Company' => 'company',
            'Deal Value' => 'deal_value',
        ];

        // Preview
        $preview = $importService->preview($parsed['headers'], $parsed['rows'], $mapping);
        $this->assertCount(4, $preview);
        $this->assertEquals('Aarav Gupta', $preview[0]['mapped']['name']);

        // Execute Import with 'update' strategy for duplicates
        $result = $importService->import(
            workspace: $ws,
            headers: $parsed['headers'],
            rows: $parsed['rows'],
            columnMapping: $mapping,
            duplicateStrategy: 'update',
            assignedUserId: $user->id
        );

        $this->assertEquals(1, $result['imported']); // Aarav
        $this->assertEquals(1, $result['updated']);  // Existing Customer updated
        $this->assertEquals(2, $result['failed']);   // Bad email + missing identifiers
        $this->assertCount(2, $result['errors']);

        $this->assertStringContainsString('Invalid email', $result['errors'][0]['reason']);
        $this->assertStringContainsString('missing required contact identifier', $result['errors'][1]['reason']);

        unlink($tmpFile);
    }

    // ── 9. CUSTOM FIELDS: Schema-less validation for text, number, date, dropdown, boolean ──

    public function test_custom_fields_schema_less_definitions_and_validations(): void
    {
        ['workspace' => $ws] = $this->setupWorkspace();
        $cfService = app(CrmCustomFieldService::class);

        CrmCustomField::create([
            'workspace_id' => $ws->id,
            'entity_type' => 'contact',
            'name' => 'GSTIN Number',
            'key' => 'gstin_number',
            'type' => 'text',
            'is_required' => true,
        ]);

        CrmCustomField::create([
            'workspace_id' => $ws->id,
            'entity_type' => 'contact',
            'name' => 'Credit Limit',
            'key' => 'credit_limit',
            'type' => 'currency',
            'is_required' => false,
        ]);

        CrmCustomField::create([
            'workspace_id' => $ws->id,
            'entity_type' => 'contact',
            'name' => 'Lead Source Channel',
            'key' => 'channel',
            'type' => 'dropdown',
            'options' => ['Exhibition', 'Referral', 'LinkedIn', 'Cold Call'],
            'is_required' => false,
        ]);

        // Valid payload
        $validated = $cfService->validateValues($ws->id, 'contact', [
            'gstin_number' => '29ABCDE1234F2Z5',
            'credit_limit' => '250000',
            'channel' => 'LinkedIn',
        ]);

        $this->assertEquals('29ABCDE1234F2Z5', $validated['gstin_number']);
        $this->assertEquals(250000.0, $validated['credit_limit']);
        $this->assertEquals('LinkedIn', $validated['channel']);

        // Invalid payload: missing required field
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $cfService->validateValues($ws->id, 'contact', [
            'channel' => 'InvalidChannelOption',
        ]);
    }

    // ── 10. PERMISSIONS & SERVER-SIDE AUTHORIZATION: Owner, Admin, Manager, Salesperson ──

    public function test_crm_role_permissions_and_server_side_authorization(): void
    {
        ['workspace' => $ws, 'user' => $owner] = $this->setupWorkspace('Beta Logistics', 'owner@beta.in');

        // Salesperson user
        $salesUser = User::create([
            'name' => 'Sales Agent Amit',
            'email' => 'amit@beta.in',
            'password' => bcrypt('Password123!'),
            'role' => User::ROLE_CLIENT,
            'client_id' => $ws->client_id,
            'client_role' => User::CLIENT_ROLE_STAFF,
            'workspace_id' => $ws->id,
            'status' => User::STATUS_ACTIVE,
        ]);
        $ws->members()->syncWithoutDetaching([$salesUser->id => ['role' => 'salesperson']]);

        // Salesperson attempting to manage pipeline stages must be forbidden (403)
        $pipeline = CrmPipeline::create(['workspace_id' => $ws->id, 'name' => 'Beta Pipeline']);

        $forbiddenResp = $this->actingAs($salesUser)
            ->withSession(['current_workspace_id' => $ws->id])
            ->postJson(route('client.crm.custom-fields.store'), [
                'entity_type' => 'lead',
                'name' => 'Forbidden Field',
                'type' => 'text',
            ]);

        $forbiddenResp->assertStatus(403);

        // Owner can manage custom fields
        $allowedResp = $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $ws->id])
            ->postJson(route('client.crm.custom-fields.store'), [
                'entity_type' => 'lead',
                'name' => 'Allowed Field',
                'type' => 'text',
            ]);

        $allowedResp->assertStatus(201);
    }

    // ── 11. MULTI-TENANT ISOLATION: Workspace A vs Workspace B ──

    public function test_cross_workspace_crm_isolation_is_strictly_enforced(): void
    {
        ['workspace' => $wsA, 'user' => $userA] = $this->setupWorkspace('Workspace Alpha', 'userA@alpha.in');
        ['workspace' => $wsB, 'user' => $userB] = $this->setupWorkspace('Workspace Beta', 'userB@beta.in');

        $leadA = Contact::create([
            'workspace_id' => $wsA->id,
            'first_name' => 'Secret Lead A',
            'deal_value' => 999999,
        ]);

        $companyA = CrmCompany::create([
            'workspace_id' => $wsA->id,
            'name' => 'Confidential Alpha Client',
        ]);

        // 1. User B attempting to view Lead A via Show route -> 404 or 403
        $respLead = $this->actingAs($userB)
            ->withSession(['current_workspace_id' => $wsB->id])
            ->getJson(route('client.crm.leads.show', $leadA->uuid));

        $this->assertTrue(in_array($respLead->status(), [403, 404]));

        // 2. User B attempting to view Company A -> 403
        $respComp = $this->actingAs($userB)
            ->withSession(['current_workspace_id' => $wsB->id])
            ->getJson(route('client.crm.companies.show', $companyA->id));

        $respComp->assertStatus(403);

        // 3. User B search cannot reveal Lead A or Company A
        $searchResp = $this->actingAs($userB)
            ->withSession(['current_workspace_id' => $wsB->id])
            ->getJson(route('client.crm.leads.index', ['search' => 'Secret Lead A']));

        $searchResp->assertOk();
        $this->assertCount(0, $searchResp->json('data'));
    }

    // ── 12. REST APIs: Full CRUD under /api/v1/crm/... with Token Auth ──

    public function test_crm_rest_api_endpoints_with_sanctum_token(): void
    {
        ['workspace' => $ws, 'user' => $user] = $this->setupWorkspace();
        Sanctum::actingAs($user, ['contacts:read', 'contacts:write', 'leads:read', 'leads:write', 'analytics:read']);

        // 1. API: Create Lead
        $leadResp = $this->postJson('/api/v1/crm/leads', [
            'first_name' => 'Vikrant',
            'last_name' => 'Rona',
            'phone_e164' => '+919911223344',
            'deal_value' => 60000,
        ]);

        $leadResp->assertStatus(201);
        $leadId = $leadResp->json('data.id');

        // 2. API: Create Company
        $compResp = $this->postJson('/api/v1/crm/companies', [
            'name' => 'Rona Mining Corp',
            'industry' => 'Mining',
        ]);
        $compResp->assertStatus(201);
        $compId = $compResp->json('data.id');

        // 3. API: Create Deal
        $dealResp = $this->postJson('/api/v1/crm/deals', [
            'name' => 'Heavy Equipment Supply',
            'value' => 450000,
            'contact_id' => $leadId,
            'company_id' => $compId,
        ]);
        $dealResp->assertStatus(201);

        // 4. API: Create Task
        $taskResp = $this->postJson('/api/v1/crm/tasks', [
            'title' => 'Send Contract Draft',
            'priority' => 'high',
            'contact_id' => $leadId,
        ]);
        $taskResp->assertStatus(201);

        // 5. API: Timeline
        $timelineResp = $this->getJson("/api/v1/crm/contacts/{$leadId}/timeline");
        $timelineResp->assertOk();

        // 6. API: Analytics
        $analyticsResp = $this->getJson('/api/v1/crm/analytics');
        $analyticsResp->assertOk();
        $this->assertArrayHasKey('total_leads', $analyticsResp->json('data'));
        $this->assertArrayHasKey('pipeline_value', $analyticsResp->json('data'));
    }

    // ── 13. ANALYTICS: Conversion Rates, Won Revenue, Pipeline Values, Sales Cycle ──

    public function test_crm_analytics_metrics_calculations(): void
    {
        ['workspace' => $ws] = $this->setupWorkspace();
        $analytics = app(CrmAnalyticsService::class);

        $pipeline = CrmPipeline::create(['workspace_id' => $ws->id, 'name' => 'Analytics Pipeline']);
        $wonStage = CrmPipelineStage::create([
            'workspace_id' => $ws->id,
            'pipeline_id' => $pipeline->id,
            'name' => 'Won',
            'probability' => 100,
            'is_won' => true,
        ]);

        $lostStage = CrmPipelineStage::create([
            'workspace_id' => $ws->id,
            'pipeline_id' => $pipeline->id,
            'name' => 'Lost',
            'probability' => 0,
            'is_lost' => true,
        ]);

        // 2 Won Leads
        $lead1 = Contact::create([
            'workspace_id' => $ws->id,
            'first_name' => 'Won Lead 1',
            'deal_value' => 100000,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $wonStage->id,
        ]);
        $lead1->created_at = now()->subDays(10);
        $lead1->updated_at = now();
        $lead1->saveQuietly();

        $lead2 = Contact::create([
            'workspace_id' => $ws->id,
            'first_name' => 'Won Lead 2',
            'deal_value' => 200000,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $wonStage->id,
        ]);
        $lead2->created_at = now()->subDays(6);
        $lead2->updated_at = now();
        $lead2->saveQuietly();

        // 1 Lost Lead
        Contact::create([
            'workspace_id' => $ws->id,
            'first_name' => 'Lost Lead',
            'deal_value' => 50000,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $lostStage->id,
        ]);

        $metrics = $analytics->getDashboardMetrics($ws->id);

        $this->assertEquals(3, $metrics['total_leads']);
        $this->assertEquals(2, $metrics['won_deals']);
        $this->assertEquals(1, $metrics['lost_deals']);
        $this->assertEquals(300000.0, $metrics['won_revenue']);
        $this->assertEquals(150000.0, $metrics['average_deal_value']);
        $this->assertEquals(8.0, $metrics['sales_cycle_days']); // (10 + 6) / 2 = 8 days
        $this->assertEquals(66.7, $metrics['conversion_rate']); // 2/3 * 100 = 66.7%
    }

    // ── 14. AUDIT LOG: Sensitive Actions Recorded ──

    public function test_sensitive_crm_actions_record_audit_logs(): void
    {
        ['workspace' => $ws, 'user' => $user] = $this->setupWorkspace();

        $contact = Contact::create([
            'workspace_id' => $ws->id,
            'first_name' => 'Audit Lead',
            'deal_value' => 50000,
        ]);

        // 1. Assign lead logs audit entry
        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $ws->id])
            ->postJson(route('client.crm.leads.assign', $contact->uuid), [
                'strategy' => 'manual',
                'assigned_user_id' => $user->id,
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'client_id' => $ws->client_id,
            'action' => 'crm.lead_assigned',
            'auditable_type' => 'contact',
            'auditable_id' => (string) $contact->id,
        ]);

        // 2. Export logs audit entry
        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $ws->id])
            ->get(route('client.crm.leads.export'));

        $this->assertDatabaseHas('audit_logs', [
            'client_id' => $ws->client_id,
            'action' => 'crm.leads_exported',
            'auditable_type' => 'export',
        ]);
    }
}
