<?php

namespace Tests\Feature\Automation;

use App\Models\Client;
use App\Models\Crm\CrmDeal;
use App\Models\Crm\CrmPipeline;
use App\Models\Crm\CrmPipelineStage;
use App\Models\Crm\CrmTask;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\Automation\Jobs\ExecuteAutomationRunJob;
use App\Modules\Automation\Models\Automation;
use App\Modules\Automation\Models\AutomationRun;
use App\Modules\Automation\Models\AutomationRunLog;
use App\Modules\Automation\Services\AutomationEngine;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\ContactTag;
use App\Modules\Shared\Models\ContactTimelineEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AutomationEngineHardeningAndProductionTest extends TestCase
{
    use RefreshDatabase;

    protected Client $client;
    protected Workspace $workspace;
    protected User $user;
    protected Plan $growthPlan;
    protected Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->growthPlan = Plan::create([
            'name' => 'Growth Automation Plan',
            'slug' => 'growth',
            'price_cents' => 9900,
            'currency_code' => 'USD',
            'interval' => 'month',
            'enabled' => true,
            'features' => [
                'automations' => true,
                'advanced_automation' => true,
                'crm' => true,
                'whatsapp' => true,
                'email_marketing' => true,
            ],
        ]);

        $this->client = Client::create([
            'name' => 'Automations Corp',
            'status' => 'active',
        ]);

        $this->workspace = Workspace::create([
            'client_id' => $this->client->id,
            'name' => 'Production Workflows',
            'status' => 'active',
        ]);

        $this->user = User::create([
            'name' => 'Workflow Engineer',
            'email' => 'engineer@automations.com',
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
    }

    public function test_execution_id_and_idempotency_prevents_duplicate_runs(): void
    {
        Queue::fake([ExecuteAutomationRunJob::class]);

        $contact = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Kunal',
            'phone_e164' => '+919876543210',
        ]);

        $automation = Automation::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Idempotent Trigger Test',
            'trigger_type' => 'webhook',
            'status' => 'active',
            'nodes' => [
                ['id' => 'n_trig', 'type' => 'trigger', 'data' => ['event' => 'webhook']],
            ],
            'edges' => [],
        ]);

        $engine = app(AutomationEngine::class);

        // First trigger with event_id
        $run1 = $engine->triggerForContact($automation, $contact->id, ['event_id' => 'evt_unique_101']);
        $this->assertNotNull($run1);
        $this->assertNotNull($run1->execution_id);
        $this->assertEquals('evt_unique_101', $run1->idempotency_key);

        // Second trigger with IDENTICAL event_id -> should return null and not create duplicate run
        $run2 = $engine->triggerForContact($automation, $contact->id, ['event_id' => 'evt_unique_101']);
        $this->assertNull($run2);

        $totalRuns = AutomationRun::where('automation_id', $automation->id)->count();
        $this->assertEquals(1, $totalRuns);
    }

    public function test_infinite_loop_detection_and_max_steps_watchdog(): void
    {
        // Construct a circular loop: Node A -> Node B -> Node A
        $automation = Automation::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Cycle Loop Test',
            'trigger_type' => 'contact.created',
            'status' => 'active',
            'nodes' => [
                ['id' => 'n_trig', 'type' => 'trigger', 'data' => ['event' => 'contact.created']],
                ['id' => 'n_a', 'type' => 'update_contact', 'data' => ['field' => 'city', 'value' => 'Alpha']],
                ['id' => 'n_b', 'type' => 'update_contact', 'data' => ['field' => 'city', 'value' => 'Beta']],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'n_trig', 'target' => 'n_a'],
                ['id' => 'e2', 'source' => 'n_a', 'target' => 'n_b'],
                ['id' => 'e3', 'source' => 'n_b', 'target' => 'n_a'], // cycle back!
            ],
        ]);

        $contact = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'LoopTester',
            'phone_e164' => '+919876543211',
        ]);

        $run = AutomationRun::create([
            'automation_id' => $automation->id,
            'contact_id' => $contact->id,
            'status' => 'pending',
            'max_steps' => 20,
        ]);

        $engine = app(AutomationEngine::class);
        $engine->executeRun($run);

        $run->refresh();

        // Run should terminate safely in 'failed' status with loop warning instead of hanging
        $this->assertEquals('failed', $run->status);
        $this->assertStringContainsString('Infinite loop detected', $run->error);
    }

    public function test_pause_and_cancel_halts_execution_immediately(): void
    {
        Queue::fake([ExecuteAutomationRunJob::class]);

        $automation = Automation::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Pausable Flow',
            'trigger_type' => 'contact.created',
            'status' => 'active',
            'nodes' => [
                ['id' => 'n_trig', 'type' => 'trigger', 'data' => []],
                ['id' => 'n_wait', 'type' => 'wait', 'data' => ['amount' => 1, 'unit' => 'hours']],
                ['id' => 'n_action', 'type' => 'update_contact', 'data' => ['field' => 'city', 'value' => 'Rome']],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'n_trig', 'target' => 'n_wait'],
                ['id' => 'e2', 'source' => 'n_wait', 'target' => 'n_action'],
            ],
        ]);

        $contact = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Marco',
        ]);

        $run = AutomationRun::create([
            'automation_id' => $automation->id,
            'contact_id' => $contact->id,
            'status' => 'pending',
        ]);

        $engine = app(AutomationEngine::class);
        $engine->executeRun($run);

        $run->refresh();
        $this->assertEquals('waiting', $run->status);

        // Cancel the run
        $run->update(['status' => 'cancelled']);

        // Resume attempt
        $engine->executeRun($run);
        $run->refresh();

        // Status remains cancelled and action was never executed
        $this->assertEquals('cancelled', $run->status);
        $contact->refresh();
        $this->assertNotEquals('Rome', $contact->city);
    }

    public function test_mvp_actions_execution_and_timeline_logging(): void
    {
        Http::fake([
            'https://webhook.site/test-hook' => Http::response(['success' => true], 200),
        ]);

        $pipeline = CrmPipeline::create(['workspace_id' => $this->workspace->id, 'name' => 'Enterprise Sales', 'order' => 1]);
        $stageNew = CrmPipelineStage::create(['workspace_id' => $this->workspace->id, 'pipeline_id' => $pipeline->id, 'name' => 'Prospecting', 'order' => 1]);
        $stageWon = CrmPipelineStage::create(['workspace_id' => $this->workspace->id, 'pipeline_id' => $pipeline->id, 'name' => 'Closed Won', 'order' => 2]);

        $automation = Automation::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Full MVP Actions Test',
            'trigger_type' => 'contact.created',
            'status' => 'active',
            'nodes' => [
                ['id' => 'n_trig', 'type' => 'trigger', 'data' => []],
                ['id' => 'n_tag_add', 'type' => 'add_tag', 'data' => ['tag' => 'HighPriority']],
                ['id' => 'n_update_contact', 'type' => 'update_contact', 'data' => ['field' => 'city', 'value' => 'Bangalore']],
                ['id' => 'n_stage', 'type' => 'move_pipeline_stage', 'data' => ['stage_id' => $stageWon->id]],
                ['id' => 'n_task', 'type' => 'create_task', 'data' => ['title' => 'Follow up with {{contact.name}}', 'priority' => 'urgent']],
                ['id' => 'n_webhook', 'type' => 'webhook', 'data' => ['url' => 'https://webhook.site/test-hook', 'method' => 'POST']],
                ['id' => 'n_tag_rem', 'type' => 'remove_tag', 'data' => ['tag' => 'HighPriority']],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'n_trig', 'target' => 'n_tag_add'],
                ['id' => 'e2', 'source' => 'n_tag_add', 'target' => 'n_update_contact'],
                ['id' => 'e3', 'source' => 'n_update_contact', 'target' => 'n_stage'],
                ['id' => 'e4', 'source' => 'n_stage', 'target' => 'n_task'],
                ['id' => 'e5', 'source' => 'n_task', 'target' => 'n_webhook'],
                ['id' => 'e6', 'source' => 'n_webhook', 'target' => 'n_tag_rem'],
            ],
        ]);

        $contact = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Vijay',
            'last_name' => 'Kumar',
            'phone_e164' => '+919876543212',
            'stage_id' => $stageNew->id,
            'pipeline_id' => $pipeline->id,
        ]);

        $deal = CrmDeal::create([
            'workspace_id' => $this->workspace->id,
            'contact_id' => $contact->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stageNew->id,
            'name' => 'Vijay Enterprise Deal',
            'title' => 'Vijay Enterprise Deal',
            'value' => 50000.00,
            'currency' => 'USD',
            'status' => 'open',
        ]);

        $run = AutomationRun::create([
            'automation_id' => $automation->id,
            'contact_id' => $contact->id,
            'status' => 'pending',
        ]);

        $engine = app(AutomationEngine::class);
        $engine->executeRun($run);
        $run->refresh();

        $this->assertEquals('completed', $run->status);

        // 1. Verify Contact updated
        $contact->refresh();
        $this->assertEquals('Bangalore', $contact->custom_fields['city'] ?? null);
        $this->assertEquals($stageWon->id, $contact->stage_id);

        // 2. Verify Deal stage synced
        $deal->refresh();
        $this->assertEquals($stageWon->id, $deal->stage_id);

        // 3. Verify Task created
        $task = CrmTask::where('workspace_id', $this->workspace->id)->where('contact_id', $contact->id)->first();
        $this->assertNotNull($task);
        $this->assertStringContainsString('Vijay Kumar', $task->title);
        $this->assertEquals('urgent', $task->priority);

        // 4. Verify Webhook dispatched
        Http::assertSent(fn ($req) => $req->url() === 'https://webhook.site/test-hook');

        // 5. Verify Step Logs recorded with detailed metadata
        $logs = AutomationRunLog::where('run_id', $run->id)->orderBy('step_index')->get();
        $this->assertCount(6, $logs);
        $this->assertEquals('add_tag', $logs[0]->node_type);
        $this->assertEquals('move_pipeline_stage', $logs[2]->node_type);
        $this->assertEquals('webhook', $logs[4]->node_type);
        $this->assertNotNull($logs[4]->provider_response);

        // 6. Verify Timeline event logged for stage movement
        $timelineEvent = ContactTimelineEvent::where('workspace_id', $this->workspace->id)
            ->where('contact_id', $contact->id)
            ->where('event_type', 'stage_changed')
            ->first();

        $this->assertNotNull($timelineEvent);
        $this->assertStringContainsString('Closed Won', $timelineEvent->title);
    }
}
