<?php

namespace Tests\Feature;

use App\Models\Crm\CrmPipeline;
use App\Models\Crm\CrmPipelineStage;
use App\Models\Notification;
use App\Models\User;
use App\Modules\Automation\Models\Automation;
use App\Modules\Automation\Models\AutomationRun;
use App\Modules\Automation\Models\AutomationRunLog;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\ContactTag;
use App\Services\Automation\WorkflowAiBuilderService;
use App\Services\Automation\WorkflowExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AutomationBuilderAndAiWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected $workspace;
    protected $client;
    protected WorkflowExecutionService $workflowService;
    protected WorkflowAiBuilderService $aiBuilderService;

    protected function setUp(): void
    {
        parent::setUp();

        $ctx = $this->createWorkspaceContext();
        $this->user = $ctx['user'];
        $this->workspace = $ctx['workspace'];
        $this->client = $ctx['client'];

        $this->workflowService = app(WorkflowExecutionService::class);
        $this->aiBuilderService = app(WorkflowAiBuilderService::class);
    }

    public function test_automation_creation_and_template_hydration(): void
    {
        // 1. Create blank automation
        $auto = Automation::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Custom Lead Journey',
            'status' => 'draft',
            'trigger_type' => 'contact.created',
            'nodes' => [
                ['id' => 'trigger-1', 'type' => 'trigger', 'position' => ['x' => 250, 'y' => 50], 'data' => ['label' => 'New Lead']],
            ],
            'edges' => [],
        ]);

        $this->assertDatabaseHas('automations', [
            'id' => $auto->id,
            'name' => 'Custom Lead Journey',
            'status' => 'draft',
        ]);
        $this->assertNotEmpty($auto->webhook_public_key);

        // 2. Generate from template prompt
        $generated = $this->aiBuilderService->generateFromPrompt(
            'When someone sends a WhatsApp message asking about pricing, answer with AI, wait 2 hours, and call them with AI voice if no reply.',
            $this->workspace->id
        );

        $this->assertArrayHasKey('nodes', $generated);
        $this->assertArrayHasKey('edges', $generated);
        $this->assertArrayHasKey('explanation', $generated);
        $this->assertGreaterThanOrEqual(4, count($generated['nodes']));
    }

    public function test_workflow_execution_triggers_conditions_and_actions(): void
    {
        $pipeline = CrmPipeline::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Sales',
            'is_default' => true,
        ]);

        $stage = CrmPipelineStage::create([
            'workspace_id' => $this->workspace->id,
            'pipeline_id' => $pipeline->id,
            'name' => 'Qualified Hot Lead',
            'position' => 1,
            'probability' => 80,
            'color' => '#10b981',
        ]);

        $contact = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'John',
            'phone_e164' => '+12025550111',
            'email' => 'john@example.com',
            'lead_score' => 85,
        ]);

        // Build graph: Trigger -> Condition (score > 80) -> YES: Add Tag 'VIP' & Change Stage & Task -> Goal
        $automation = Automation::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Hot Lead Qualification Workflow',
            'status' => 'active',
            'trigger_type' => 'contact.created',
            'nodes' => [
                ['id' => 'trigger-1', 'type' => 'trigger', 'position' => ['x' => 250, 'y' => 50], 'data' => ['label' => 'New Contact']],
                ['id' => 'cond-1', 'type' => 'condition', 'position' => ['x' => 250, 'y' => 150], 'data' => [
                    'field' => 'lead.score',
                    'operator' => 'greater_than',
                    'value' => 80,
                ]],
                ['id' => 'tag-1', 'type' => 'add_tag', 'position' => ['x' => 150, 'y' => 250], 'data' => ['tag' => 'VIP']],
                ['id' => 'stage-1', 'type' => 'change_stage', 'position' => ['x' => 150, 'y' => 350], 'data' => ['stage_id' => $stage->id]],
                ['id' => 'task-1', 'type' => 'create_task', 'position' => ['x' => 150, 'y' => 450], 'data' => ['title' => 'Follow up with VIP lead {{contact.first_name}}']],
                ['id' => 'goal-1', 'type' => 'goal', 'position' => ['x' => 150, 'y' => 550], 'data' => ['goal_name' => 'Hot Lead Converted']],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'trigger-1', 'target' => 'cond-1'],
                ['id' => 'e2', 'source' => 'cond-1', 'sourceHandle' => 'yes', 'target' => 'tag-1'],
                ['id' => 'e3', 'source' => 'tag-1', 'target' => 'stage-1'],
                ['id' => 'e4', 'source' => 'stage-1', 'target' => 'task-1'],
                ['id' => 'e5', 'source' => 'task-1', 'target' => 'goal-1'],
            ],
        ]);

        // Execute workflow
        $run = $this->workflowService->startRun($automation, $contact);

        $this->assertEquals('completed', $run->fresh()->status);
        $this->assertDatabaseHas('contact_tag_pivot', [
            'contact_id' => $contact->id,
        ]);
        $this->assertEquals($stage->id, $contact->fresh()->stage_id);
        $this->assertDatabaseHas('crm_tasks', [
            'workspace_id' => $this->workspace->id,
            'contact_id' => $contact->id,
            'title' => 'Follow up with VIP lead John',
        ]);
    }

    public function test_ai_intent_detection_and_confidence_human_handoff(): void
    {
        $contact = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Sara',
            'phone_e164' => '+12025550122',
            'lead_score' => 20,
        ]);

        $automation = Automation::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'AI Intent & Escalation Flow',
            'status' => 'active',
            'trigger_type' => 'message.received',
            'nodes' => [
                ['id' => 'trigger-1', 'type' => 'trigger', 'position' => ['x' => 250, 'y' => 50], 'data' => ['label' => 'Message Received']],
                ['id' => 'ai-1', 'type' => 'ai_action', 'position' => ['x' => 250, 'y' => 150], 'data' => [
                    'confidence_threshold' => 99, // Force handoff for test
                ]],
                ['id' => 'handoff-1', 'type' => 'human_handoff', 'position' => ['x' => 250, 'y' => 250], 'data' => [
                    'assign_user_id' => $this->user->id,
                ]],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'trigger-1', 'target' => 'ai-1'],
                ['id' => 'e2', 'source' => 'ai-1', 'sourceHandle' => 'handoff', 'target' => 'handoff-1'],
            ],
        ]);

        $run = $this->workflowService->startRun(
            $automation,
            $contact,
            ['last_message' => 'I have an urgent complaint about my invoice']
        );

        $this->assertEquals('completed', $run->fresh()->status);
        $this->assertEquals($this->user->id, $contact->fresh()->assigned_user_id);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->user->id,
        ]);
    }

    public function test_wait_delay_node_and_state_persistence(): void
    {
        $contact = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Michael',
            'phone_e164' => '+12025550133',
        ]);

        $automation = Automation::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Wait Delay Journey',
            'status' => 'active',
            'trigger_type' => 'contact.created',
            'nodes' => [
                ['id' => 'trigger-1', 'type' => 'trigger', 'position' => ['x' => 250, 'y' => 50], 'data' => ['label' => 'Trigger']],
                ['id' => 'wait-1', 'type' => 'wait_delay', 'position' => ['x' => 250, 'y' => 150], 'data' => ['hours' => 24]],
                ['id' => 'tag-1', 'type' => 'add_tag', 'position' => ['x' => 250, 'y' => 250], 'data' => ['tag' => 'Nurtured']],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'trigger-1', 'target' => 'wait-1'],
                ['id' => 'e2', 'source' => 'wait-1', 'target' => 'tag-1'],
            ],
        ]);

        $run = $this->workflowService->startRun($automation, $contact);

        $this->assertEquals('waiting', $run->fresh()->status);
        $this->assertEquals('tag-1', $run->fresh()->resume_node_id);
    }

    public function test_loop_protection_and_maximum_step_limits(): void
    {
        $contact = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'LoopTest',
        ]);

        // Circular Loop: Node A -> Node B -> Node A
        $automation = Automation::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Infinite Loop Trap',
            'status' => 'active',
            'nodes' => [
                ['id' => 'trigger-1', 'type' => 'trigger', 'position' => ['x' => 250, 'y' => 50], 'data' => ['label' => 'Trigger']],
                ['id' => 'node-a', 'type' => 'add_tag', 'position' => ['x' => 250, 'y' => 150], 'data' => ['tag' => 'TagA']],
                ['id' => 'node-b', 'type' => 'add_tag', 'position' => ['x' => 250, 'y' => 250], 'data' => ['tag' => 'TagB']],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'trigger-1', 'target' => 'node-a'],
                ['id' => 'e2', 'source' => 'node-a', 'target' => 'node-b'],
                ['id' => 'e3', 'source' => 'node-b', 'target' => 'node-a'], // Circular loop!
            ],
        ]);

        $run = $this->workflowService->startRun($automation, $contact);

        $this->assertEquals('failed', $run->fresh()->status);
        $this->assertStringContainsString('Infinite loop protection triggered', $run->fresh()->error);
    }

    public function test_http_request_action_ssrf_protection(): void
    {
        // 1. Localhost and private IPs must be blocked
        $this->assertFalse($this->workflowService->isSafeUrl('http://127.0.0.1:8000/admin'));
        $this->assertFalse($this->workflowService->isSafeUrl('http://localhost:3000'));
        $this->assertFalse($this->workflowService->isSafeUrl('http://169.254.169.254/latest/meta-data'));
        $this->assertFalse($this->workflowService->isSafeUrl('http://10.0.0.1/internal'));
        $this->assertFalse($this->workflowService->isSafeUrl('http://192.168.1.1/secret'));

        // 2. Safe external HTTPS URLs must pass
        $this->assertTrue($this->workflowService->isSafeUrl('https://api.stripe.com/v1/charges'));
        $this->assertTrue($this->workflowService->isSafeUrl('https://hooks.zapier.com/hooks/catch/123/abc'));
    }

    public function test_automation_rest_api_v1_and_public_webhook_trigger(): void
    {
        Sanctum::actingAs($this->user, ['automations:read', 'automations:write']);

        // 1. GET /api/v1/automations
        $listRes = $this->getJson('/api/v1/automations');
        $listRes->assertOk();

        // 2. POST /api/v1/automations
        $postRes = $this->postJson('/api/v1/automations', [
            'name' => 'API Created Automation',
            'trigger_type' => 'message.received',
        ]);
        $postRes->assertStatus(201);
        $autoId = $postRes->json('data.id');
        $publicKey = $postRes->json('data.webhook_public_key');

        // 3. POST /api/v1/automations/{id}/activate
        $actRes = $this->postJson("/api/v1/automations/{$autoId}/activate");
        $actRes->assertOk();
        $this->assertEquals('active', $actRes->json('status'));

        // 4. POST /api/v1/automations/{id}/test
        $testRes = $this->postJson("/api/v1/automations/{$autoId}/test", [
            'sample_message' => 'Hello from API test',
        ]);
        $testRes->assertOk();
        $this->assertTrue($testRes->json('ok'));

        // 5. GET /api/v1/automations/{id}/runs
        $runsRes = $this->getJson("/api/v1/automations/{$autoId}/runs");
        $runsRes->assertOk();

        // 6. POST /api/v1/public/automations/webhook/{publicKey}
        $whRes = $this->postJson("/api/v1/public/automations/webhook/{$publicKey}", [
            'source' => 'Zapier CRM',
            'event' => 'form_submission',
        ]);
        $whRes->assertOk();
        $this->assertTrue($whRes->json('ok'));
    }
}
