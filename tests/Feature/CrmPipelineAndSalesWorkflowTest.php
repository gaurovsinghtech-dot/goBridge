<?php

namespace Tests\Feature;

use App\Models\Crm\CrmDeal;
use App\Models\Crm\CrmNote;
use App\Models\Crm\CrmPipeline;
use App\Models\Crm\CrmPipelineStage;
use App\Models\Crm\CrmTask;
use App\Models\Crm\CrmTeam;
use App\Models\Notification;
use App\Models\User;
use App\Modules\Shared\Models\Contact;
use App\Services\Crm\AiLeadQualificationService;
use App\Services\Crm\CrmAnalyticsService;
use App\Services\Crm\CrmPipelineService;
use App\Services\Crm\LeadAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrmPipelineAndSalesWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected $workspace;
    protected $client;
    protected CrmPipelineService $pipelineService;
    protected LeadAssignmentService $assignmentService;
    protected AiLeadQualificationService $qualificationService;
    protected CrmAnalyticsService $analyticsService;

    protected function setUp(): void
    {
        parent::setUp();

        $ctx = $this->createWorkspaceContext();
        $this->user = $ctx['user'];
        $this->workspace = $ctx['workspace'];
        $this->client = $ctx['client'];

        $this->pipelineService = app(CrmPipelineService::class);
        $this->assignmentService = app(LeadAssignmentService::class);
        $this->qualificationService = app(AiLeadQualificationService::class);
        $this->analyticsService = app(CrmAnalyticsService::class);
    }

    public function test_default_sales_pipeline_and_stages_auto_provision(): void
    {
        $pipeline = $this->pipelineService->ensureDefaultPipeline($this->workspace->id);

        $this->assertNotNull($pipeline);
        $this->assertEquals('Sales Pipeline', $pipeline->name);
        $this->assertTrue($pipeline->is_default);
        $this->assertCount(7, $pipeline->stages);

        $stages = $pipeline->stages()->orderBy('position')->get();
        $this->assertEquals('New Lead', $stages[0]->name);
        $this->assertEquals(10, $stages[0]->probability);
        $this->assertEquals('Won', $stages[5]->name);
        $this->assertTrue($stages[5]->is_won);
        $this->assertEquals('Lost', $stages[6]->name);
        $this->assertTrue($stages[6]->is_lost);
    }

    public function test_lead_creation_and_stage_movement_with_timeline_event(): void
    {
        $pipeline = $this->pipelineService->ensureDefaultPipeline($this->workspace->id);
        $newStage = $pipeline->stages()->where('name', 'New Lead')->first();
        $qualifiedStage = $pipeline->stages()->where('name', 'Qualified')->first();

        $contact = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Rahul',
            'last_name' => 'Singh',
            'company' => 'Acme Technologies',
            'phone_e164' => '+919876500001',
            'email' => 'rahul@example.com',
            'deal_value' => 50000,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $newStage->id,
            'source' => 'whatsapp',
        ]);

        $this->assertEquals(50000, $contact->deal_value);
        $this->assertEquals($newStage->id, $contact->stage_id);

        // Move to Qualified
        $this->pipelineService->moveContactStage($contact, $qualifiedStage->id, null, $this->user);

        $contact->refresh();
        $this->assertEquals($qualifiedStage->id, $contact->stage_id);

        $this->assertDatabaseHas('contact_timeline_events', [
            'contact_id' => $contact->id,
            'workspace_id' => $this->workspace->id,
            'event_type' => 'crm_stage_change',
        ]);
    }

    public function test_kanban_board_aggregation_and_weighted_value_calculation(): void
    {
        $pipeline = $this->pipelineService->ensureDefaultPipeline($this->workspace->id);
        $stages = $pipeline->stages()->orderBy('position')->get();

        // Lead 1: in Qualified stage (50% probability, value 100,000 => weighted 50,000)
        Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Amit',
            'phone_e164' => '+919876500002',
            'deal_value' => 100000,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stages[2]->id, // Qualified (50%)
        ]);

        // Lead 2: in Proposal stage (70% probability, value 200,000 => weighted 140,000)
        Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Priya',
            'phone_e164' => '+919876500003',
            'deal_value' => 200000,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stages[3]->id, // Proposal (70%)
        ]);

        $board = $this->pipelineService->getKanbanBoard($this->workspace->id, $pipeline->id);

        $this->assertEquals(2, $board['summary']['total_leads']);
        $this->assertEquals(300000.0, $board['summary']['total_pipeline_value']);
        $this->assertEquals(190000.0, $board['summary']['total_weighted_value']);
    }

    public function test_deals_creation_and_contact_deal_value_sync(): void
    {
        $pipeline = $this->pipelineService->ensureDefaultPipeline($this->workspace->id);
        $contact = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Sneha',
            'deal_value' => 0,
            'pipeline_id' => $pipeline->id,
        ]);

        // Create deal 1
        $deal1 = CrmDeal::create([
            'workspace_id' => $this->workspace->id,
            'contact_id' => $contact->id,
            'pipeline_id' => $pipeline->id,
            'name' => 'Software License',
            'value' => 75000,
            'probability' => 60,
            'status' => 'open',
        ]);

        // Create deal 2
        $deal2 = CrmDeal::create([
            'workspace_id' => $this->workspace->id,
            'contact_id' => $contact->id,
            'pipeline_id' => $pipeline->id,
            'name' => 'Implementation Services',
            'value' => 25000,
            'probability' => 80,
            'status' => 'open',
        ]);

        $this->assertEquals(45000.0, $deal1->weighted_value); // 75,000 * 60%

        $contact->update([
            'deal_value' => $contact->deals()->where('status', '!=', 'lost')->sum('value'),
        ]);

        $this->assertEquals(100000.0, $contact->deal_value);
    }

    public function test_tasks_creation_and_due_date_overdue_check(): void
    {
        $contact = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Vikram',
        ]);

        $task = CrmTask::create([
            'workspace_id' => $this->workspace->id,
            'contact_id' => $contact->id,
            'assigned_user_id' => $this->user->id,
            'title' => 'Send WhatsApp Demo Video',
            'due_at' => now()->subDay(), // Past
            'priority' => 'urgent',
            'status' => 'pending',
        ]);

        $this->assertTrue($task->isOverdue());

        $task->update(['status' => 'completed']);
        $this->assertFalse($task->isOverdue());
    }

    public function test_internal_note_creation_with_mention_notification(): void
    {
        $colleague = User::factory()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Kavita Sharma',
            'email' => 'kavita@growbridge.com',
            'status' => 'active',
        ]);

        $contact = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Deepak',
        ]);

        $response = $this->actingAs($this->user)->post(route('client.crm.notes.store'), [
            'contact_id' => $contact->id,
            'content' => 'High interest lead. @Kavita please follow up tomorrow with quotation.',
            'is_private' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('crm_notes', [
            'contact_id' => $contact->id,
            'workspace_id' => $this->workspace->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $colleague->id,
        ]);
    }

    public function test_automatic_lead_assignment_round_robin(): void
    {
        $agentA = User::factory()->create(['workspace_id' => $this->workspace->id, 'name' => 'Agent A', 'status' => 'active']);
        $agentB = User::factory()->create(['workspace_id' => $this->workspace->id, 'name' => 'Agent B', 'status' => 'active']);

        $contact1 = Contact::create(['workspace_id' => $this->workspace->id, 'first_name' => 'Lead 1']);
        $contact2 = Contact::create(['workspace_id' => $this->workspace->id, 'first_name' => 'Lead 2']);

        $assigned1 = $this->assignmentService->assignLead($contact1, 'round_robin');
        $assigned2 = $this->assignmentService->assignLead($contact2, 'round_robin');

        $this->assertNotNull($assigned1);
        $this->assertNotNull($assigned2);
    }

    public function test_ai_lead_qualification_and_human_handoff_trigger(): void
    {
        $contact = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Rohan',
            'last_name' => 'Verma',
            'lead_score' => 50,
        ]);

        $result = $this->qualificationService->qualifyContact($contact, [
            'message' => 'Hello, I need pricing and enterprise quotation for 200 users.',
        ]);

        $this->assertGreaterThanOrEqual(70, $result['score']);
        $this->assertContains($result['temperature'], ['Hot', 'Very Hot']);
        $this->assertNotEmpty($result['factors']);

        // Test Human Handoff
        $this->qualificationService->triggerHumanHandoff($contact, 'Customer asked complex custom API integration questions');

        $this->assertDatabaseHas('crm_tasks', [
            'contact_id' => $contact->id,
            'priority' => 'urgent',
            'workspace_id' => $this->workspace->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
        ]);
    }

    public function test_crm_rest_api_v1_endpoints(): void
    {
        Sanctum::actingAs($this->user, ['contacts:read', 'contacts:write', 'leads:read', 'leads:write']);

        // Create lead via API
        $createRes = $this->postJson('/api/v1/crm/leads', [
            'first_name' => 'Ananya',
            'phone_e164' => '+919876540099',
            'company' => 'Starlight Media',
            'deal_value' => 150000,
        ]);

        $createRes->assertStatus(201);
        $this->assertTrue($createRes->json('success'));
        $leadId = $createRes->json('data.id');

        // List leads via API
        $listRes = $this->getJson('/api/v1/crm/leads');
        $listRes->assertOk();
        $this->assertCount(1, $listRes->json('data'));

        // List pipelines via API
        $pipeRes = $this->getJson('/api/v1/crm/pipelines');
        $pipeRes->assertOk();
        $this->assertNotEmpty($pipeRes->json('data'));
    }
}
