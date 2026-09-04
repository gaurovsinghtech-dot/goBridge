<?php

namespace Tests\Feature\Crm;

use App\Mail\AutomationEmail;
use App\Models\Client;
use App\Models\Crm\CrmTask;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\Automation\Jobs\ExecuteAutomationRunJob;
use App\Modules\Automation\Models\Automation;
use App\Modules\Automation\Models\AutomationRun;
use App\Modules\Automation\Services\AutomationEngine;
use App\Modules\Inbox\Services\Adapters\WhatsAppAdapter;
use App\Modules\Shared\Contracts\ChannelAdapterInterface;
use App\Modules\Shared\Contracts\ChannelDriverInterface;
use App\Modules\Shared\DTOs\NormalizedMessage;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\ContactTimelineEvent;
use App\Modules\Shared\Models\Message;
use App\Modules\Shared\Services\ChannelAdapterManager;
use App\Modules\Shared\Services\ChannelManager;
use App\Services\Customer\CustomerTimelineService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LeadNoResponseDripNurtureFlowTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private User $salesperson;
    private ChannelAccount $whatsappAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $client = Client::create([
            'name' => 'Growbridge Global Inc',
            'email' => 'global@growbridge.io',
            'status' => 'active',
        ]);

        $this->workspace = Workspace::create([
            'client_id' => $client->id,
            'name' => 'Enterprise Sales Desk',
            'status' => 'active',
            'currency_code' => 'USD',
        ]);

        $this->salesperson = User::create([
            'name' => 'Priya Sharma',
            'email' => 'priya@growbridge.io',
            'password' => bcrypt('Password123!'),
            'role' => User::ROLE_CLIENT,
            'client_id' => $client->id,
            'client_role' => User::CLIENT_ROLE_STAFF,
            'workspace_id' => $this->workspace->id,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->workspace->members()->syncWithoutDetaching([
            $this->salesperson->id => ['role' => 'salesperson'],
        ]);

        // Mock WhatsApp adapter
        $mockAdapter = new class implements ChannelAdapterInterface {
            public array $sentMessages = [];
            public function getChannelName(): string { return 'whatsapp'; }
            public function receive(array|Request $payload, array $context = []): NormalizedMessage {
                return NormalizedMessage::make('whatsapp', 'inbound', 'customer', 'text', 'Hi', null, 'wamid.1');
            }
            public function send(Message $message): string {
                $this->sentMessages[] = $message;
                return 'wamid.OUT_DRIP_' . uniqid();
            }
            public function getStatus(string $externalMessageId): string { return 'delivered'; }
            public function handleWebhook(Request $request): array { return []; }
            public function normalizeMessage(array $rawPayload): NormalizedMessage {
                return NormalizedMessage::make('whatsapp', 'inbound', 'customer', 'text', 'Hi', null, 'wamid.1');
            }
        };

        $this->app->instance(WhatsAppAdapter::class, $mockAdapter);
        app(ChannelAdapterManager::class)->register('whatsapp', WhatsAppAdapter::class);

        // Mock ChannelDriver
        $mockDriver = new class implements ChannelDriverInterface {
            public array $sentMessages = [];
            public function send(Message $message): string {
                $this->sentMessages[] = $message;
                return 'wamid.DRIP_' . uniqid();
            }
            public function receiveWebhook(Request $request): array { return []; }
            public function verifyCreds(): bool { return true; }
        };

        $driverClass = get_class($mockDriver);
        $this->app->instance($driverClass, $mockDriver);
        app(ChannelManager::class)->register('whatsapp', $driverClass);

        $this->whatsappAccount = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel' => 'whatsapp',
            'account_id' => '98765432100',
            'display_name' => 'Official WhatsApp',
            'status' => 'active',
            'credentials_json' => ['access_token' => 'VALID_TOKEN', 'phone_number_id' => '98765432100'],
        ]);
    }

    /**
     * Test the exact sequence:
     * New Lead → No response → Wait 2 hours → Follow-up WhatsApp → Wait 1 day → Email → Create salesperson task
     */
    public function test_complete_no_response_lead_nurture_drip_workflow(): void
    {
        Mail::fake();
        Queue::fake([ExecuteAutomationRunJob::class]);

        // ── 1. Create the complete drip automation workflow ──
        $dripAutomation = Automation::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Unresponsive Lead Multi-Channel Drip Nurture',
            'trigger_type' => 'contact.created',
            'status' => 'active',
            'nodes' => [
                ['id' => 'node_trigger', 'type' => 'trigger', 'data' => ['event' => 'contact.created']],
                [
                    'id' => 'node_check_reply',
                    'type' => 'condition',
                    'data' => [
                        'field' => 'no_reply',
                        'operator' => 'equals',
                        'value' => 'true',
                    ],
                ],
                [
                    'id' => 'node_wait_2h',
                    'type' => 'wait',
                    'data' => ['amount' => 2, 'unit' => 'hours'],
                ],
                [
                    'id' => 'node_followup_whatsapp',
                    'type' => 'send_whatsapp',
                    'data' => [
                        'body' => 'Hi {{contact.first_name}}, we noticed you reached out earlier. Did you have any questions about our solutions?',
                    ],
                ],
                [
                    'id' => 'node_wait_1d',
                    'type' => 'wait',
                    'data' => ['amount' => 1, 'unit' => 'days'],
                ],
                [
                    'id' => 'node_send_email',
                    'type' => 'send_email',
                    'data' => [
                        'subject' => 'Following up on your Growbridge inquiry - {{contact.first_name}}',
                        'body' => 'Hi {{contact.first_name}}, just checking in to see if you would like to schedule a quick 10-minute demo this week.',
                    ],
                ],
                [
                    'id' => 'node_create_task',
                    'type' => 'create_task',
                    'data' => [
                        'title' => 'Direct Sales Outreach: Call {{contact.full_name}}',
                        'description' => 'Lead has been sent WhatsApp and Email follow-ups without response. Please initiate direct phone call.',
                        'priority' => 'urgent',
                        'due_in_minutes' => 30,
                    ],
                ],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'node_trigger', 'target' => 'node_check_reply'],
                ['id' => 'e2', 'source' => 'node_check_reply', 'target' => 'node_wait_2h', 'sourceHandle' => 'true'],
                ['id' => 'e3', 'source' => 'node_wait_2h', 'target' => 'node_followup_whatsapp'],
                ['id' => 'e4', 'source' => 'node_followup_whatsapp', 'target' => 'node_wait_1d'],
                ['id' => 'e5', 'source' => 'node_wait_1d', 'target' => 'node_send_email'],
                ['id' => 'e6', 'source' => 'node_send_email', 'target' => 'node_create_task'],
            ],
        ]);

        $initialTime = Carbon::parse('2026-08-26 10:00:00');
        Carbon::setTestNow($initialTime);

        // ── STEP 1: New Lead Arrives ──
        $lead = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Karan',
            'last_name' => 'Mehta',
            'phone_e164' => '+919811223344',
            'email' => 'karan.mehta@enterprise.org',
            'company' => 'Mehta Logistics',
            'deal_value' => 75000,
            'assigned_user_id' => $this->salesperson->id,
        ]);

        $this->assertNotNull($lead->id);

        // ── STEP 2 & 3: Automation triggered & evaluates No Response → Wait 2 Hours ──
        $engine = app(AutomationEngine::class);
        $run = AutomationRun::where('automation_id', $dripAutomation->id)
            ->where('contact_id', $lead->id)
            ->first();

        if (! $run) {
            $engine->triggerForContact($dripAutomation, $lead->id);
            $run = AutomationRun::where('automation_id', $dripAutomation->id)->where('contact_id', $lead->id)->first();
        }

        $this->assertNotNull($run);

        // Execute step 1 (trigger -> condition -> wait 2h)
        $engine->executeRun($run);
        $run->refresh();

        $this->assertEquals('waiting', $run->status);
        $this->assertEquals('node_followup_whatsapp', $run->resume_node_id);

        // ── STEP 4: Advance 2 Hours → Follow-up WhatsApp Sent ──
        Carbon::setTestNow($initialTime->copy()->addHours(2));

        // Resume execution after 2 hours
        $engine->executeRun($run);
        $run->refresh();

        // Verify Follow-up WhatsApp was sent
        $followupWhatsapp = Message::where('contact_id', $lead->id)
            ->where('direction', 'out')
            ->where('channel', 'whatsapp')
            ->first();

        $this->assertNotNull($followupWhatsapp);
        $this->assertStringContainsString('Karan', $followupWhatsapp->body);
        $this->assertStringContainsString('noticed you reached out earlier', $followupWhatsapp->body);

        // ── STEP 5: Execution enters Wait 1 Day ──
        $this->assertEquals('waiting', $run->status);
        $this->assertEquals('node_send_email', $run->resume_node_id);

        // ── STEP 6: Advance 1 Day (24 hours) → Email Sent ──
        Carbon::setTestNow($initialTime->copy()->addHours(2)->addDays(1));

        // Resume execution after 1 day
        $engine->executeRun($run);
        $run->refresh();

        // Verify Email was queued & sent to contact's email
        Mail::assertQueued(AutomationEmail::class, function (AutomationEmail $mail) use ($lead) {
            return str_contains($mail->emailSubject, 'Following up on your Growbridge inquiry - Karan');
        });

        // Verify Email Timeline Event logged
        $emailTimeline = ContactTimelineEvent::where('workspace_id', $this->workspace->id)
            ->where('contact_id', $lead->id)
            ->where('event_type', 'email_sent')
            ->first();

        $this->assertNotNull($emailTimeline);
        $this->assertStringContainsString('Karan', $emailTimeline->title);

        // ── STEP 7: Create Salesperson Task ──
        $this->assertEquals('completed', $run->status);

        $salesTask = CrmTask::where('workspace_id', $this->workspace->id)
            ->where('contact_id', $lead->id)
            ->first();

        $this->assertNotNull($salesTask);
        $this->assertEquals('urgent', $salesTask->priority);
        $this->assertEquals('pending', $salesTask->status);
        $this->assertEquals($this->salesperson->id, $salesTask->assigned_user_id);
        $this->assertStringContainsString('Call Karan Mehta', $salesTask->title);
        $this->assertStringContainsString('direct phone call', $salesTask->description);

        // ── STEP 8: CRM Timeline Fully Updated ──
        $taskTimeline = ContactTimelineEvent::where('workspace_id', $this->workspace->id)
            ->where('contact_id', $lead->id)
            ->where('event_type', 'crm_task_created')
            ->first();

        $this->assertNotNull($taskTimeline);
        $this->assertEquals($salesTask->id, $taskTimeline->metadata_json['task_id']);

        // Check unified timeline
        $timelineService = app(CustomerTimelineService::class);
        $fullTimeline = $timelineService->getTimeline($lead);
        $eventTypes = collect($fullTimeline)->pluck('type')->all();

        $this->assertContains('whatsapp', $eventTypes);
        $this->assertContains('email', $eventTypes);
        $this->assertContains('task', $eventTypes);
    }

    public function test_lead_who_replies_exits_no_response_drip_flow(): void
    {
        Mail::fake();
        Queue::fake([ExecuteAutomationRunJob::class]);

        $dripAutomation = Automation::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Responsive vs Unresponsive Branching Flow',
            'trigger_type' => 'contact.created',
            'status' => 'active',
            'nodes' => [
                ['id' => 'n_trig', 'type' => 'trigger', 'data' => ['event' => 'contact.created']],
                [
                    'id' => 'n_cond',
                    'type' => 'condition',
                    'data' => [
                        'field' => 'no_reply',
                        'operator' => 'equals',
                        'value' => 'true',
                    ],
                ],
                [
                    'id' => 'n_wait',
                    'type' => 'wait',
                    'data' => ['amount' => 2, 'unit' => 'hours'],
                ],
                [
                    'id' => 'n_nudge',
                    'type' => 'send_whatsapp',
                    'data' => ['body' => 'Nudge message'],
                ],
            ],
            'edges' => [
                ['id' => 'edge_1', 'source' => 'n_trig', 'target' => 'n_cond'],
                ['id' => 'edge_2', 'source' => 'n_cond', 'target' => 'n_wait', 'sourceHandle' => 'true'],
                ['id' => 'edge_3', 'source' => 'n_wait', 'target' => 'n_nudge'],
            ],
        ]);

        $activeContact = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Sneha',
            'phone_e164' => '+919876500000',
            'email' => 'sneha@activeprospect.com',
        ]);

        // Customer immediately sent an inbound message
        $conv = \App\Modules\Shared\Models\Conversation::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'workspace_id' => $this->workspace->id,
            'contact_id' => $activeContact->id,
            'channel_account_id' => $this->whatsappAccount->id,
            'channel' => 'whatsapp',
            'status' => 'open',
            'ai_mode' => 'auto',
            'assigned_to' => 'bot',
        ]);

        Message::create([
            'conversation_id' => $conv->id,
            'contact_id' => $activeContact->id,
            'channel' => 'whatsapp',
            'direction' => 'in',
            'type' => 'text',
            'body' => 'Hello! I need a quote.',
            'status' => 'delivered',
            'sent_by' => 'customer',
            'sent_at' => now(),
        ]);

        $run = AutomationRun::create([
            'automation_id' => $dripAutomation->id,
            'contact_id' => $activeContact->id,
            'status' => 'pending',
        ]);

        app(AutomationEngine::class)->executeRun($run);
        $run->refresh();

        // Run should complete cleanly without waiting since condition (no_reply = true) was false
        $this->assertEquals('completed', $run->status);

        // Verify NO nudge was sent because customer already replied
        $nudgeSent = Message::where('contact_id', $activeContact->id)
            ->where('direction', 'out')
            ->where('body', 'like', '%Nudge message%')
            ->exists();

        $this->assertFalse($nudgeSent);
    }

    public function test_template_registry_installs_unresponsive_lead_drip_workflow(): void
    {
        $adminUser = User::create([
            'name' => 'Admin Manager',
            'email' => 'admin@growbridge.io',
            'password' => bcrypt('Password123!'),
            'role' => User::ROLE_CLIENT,
            'client_id' => $this->workspace->client_id,
            'client_role' => User::CLIENT_ROLE_ADMINISTRATOR,
            'workspace_id' => $this->workspace->id,
            'current_workspace_id' => $this->workspace->id,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($adminUser)->post(route('client.automations.templates.install', 'unresponsive_lead_drip'));
        $response->assertRedirect();

        $installed = Automation::where('workspace_id', $this->workspace->id)
            ->where('name', 'Unresponsive Lead Multi-Channel Drip Nurture')
            ->first();

        $this->assertNotNull($installed);
        $this->assertEquals('contact.created', $installed->trigger_type);
        $this->assertCount(7, $installed->nodes);
        $this->assertCount(6, $installed->edges);

        // Verify key nodes exist
        $nodeTypes = collect($installed->nodes)->pluck('type')->all();
        $this->assertContains('trigger', $nodeTypes);
        $this->assertContains('condition', $nodeTypes);
        $this->assertContains('wait', $nodeTypes);
        $this->assertContains('send_whatsapp', $nodeTypes);
        $this->assertContains('send_email', $nodeTypes);
        $this->assertContains('create_task', $nodeTypes);
    }
}
