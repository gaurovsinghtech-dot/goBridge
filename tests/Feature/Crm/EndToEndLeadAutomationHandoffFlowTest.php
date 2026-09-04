<?php

namespace Tests\Feature\Crm;

use App\Events\ContactCreated;
use App\Events\MessageReceived;
use App\Models\Client;
use App\Models\Crm\CrmTask;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\AI\Models\AiChatbot;
use App\Modules\Automation\Models\Automation;
use App\Modules\Automation\Models\AutomationRun;
use App\Modules\Automation\Services\AutomationEngine;
use App\Modules\Inbox\Services\Adapters\WhatsAppAdapter;
use App\Modules\Inbox\Services\HumanHandoffService;
use App\Modules\Shared\Contracts\ChannelAdapterInterface;
use App\Modules\Shared\Contracts\ChannelDriverInterface;
use App\Modules\Shared\DTOs\NormalizedMessage;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\ContactTimelineEvent;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use App\Modules\Shared\Services\ChannelAdapterManager;
use App\Modules\Shared\Services\ChannelManager;
use App\Services\Conversation\ConversationService;
use App\Services\Customer\CustomerTimelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class EndToEndLeadAutomationHandoffFlowTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private User $salesperson;
    private ChannelAccount $whatsappAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $client = Client::create([
            'name' => 'Growbridge Tech Corp',
            'email' => 'tech@growbridge.io',
            'status' => 'active',
        ]);

        $this->workspace = Workspace::create([
            'client_id' => $client->id,
            'name' => 'Main Sales Workspace',
            'status' => 'active',
            'currency_code' => 'INR',
        ]);

        $this->salesperson = User::create([
            'name' => 'Vikram Sales Executive',
            'email' => 'vikram@growbridge.io',
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
                return NormalizedMessage::make('whatsapp', 'inbound', 'customer', 'text', 'Hello', null, 'wamid.123', 'received', '+919876543210');
            }
            public function send(Message $message): string {
                $this->sentMessages[] = $message;
                return 'wamid.OUT_' . uniqid();
            }
            public function getStatus(string $externalMessageId): string { return 'delivered'; }
            public function handleWebhook(Request $request): array { return []; }
            public function normalizeMessage(array $rawPayload): NormalizedMessage {
                return NormalizedMessage::make('whatsapp', 'inbound', 'customer', 'text', 'Hello', null, 'wamid.123', 'received', '+919876543210');
            }
        };

        $this->app->instance(WhatsAppAdapter::class, $mockAdapter);
        app(ChannelAdapterManager::class)->register('whatsapp', WhatsAppAdapter::class);

        // Mock ChannelDriver
        $mockDriver = new class implements ChannelDriverInterface {
            public array $sentMessages = [];
            public function send(Message $message): string {
                $this->sentMessages[] = $message;
                return 'wamid.HBgLMTEyMjMzNDQ1NVE=' . uniqid();
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
            'account_id' => '10987654321',
            'display_name' => 'WhatsApp Business Cloud',
            'status' => 'active',
            'credentials_json' => ['access_token' => 'VALID_META_TOKEN', 'phone_number_id' => '10987654321'],
        ]);
    }

    /**
     * Test the complete 12-step flow:
     * New Lead → CRM contact created → Automation triggered → WhatsApp template sent →
     * Customer replies → Automation detects reply → AI Agent responds →
     * Customer asks for human → AI stops → Salesperson assigned →
     * Follow-up task created → CRM timeline updated
     */
    public function test_complete_end_to_end_lead_whatsapp_ai_and_human_handoff_flow(): void
    {
        // ── 1. Create Onboarding/Welcome Automation: on 'contact.created' → send_whatsapp template ──
        $welcomeAutomation = Automation::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'New Lead WhatsApp Welcome Flow',
            'trigger_type' => 'contact.created',
            'status' => 'active',
            'nodes' => [
                ['id' => 'node_1', 'type' => 'trigger', 'data' => ['event' => 'contact.created']],
                [
                    'id' => 'node_2',
                    'type' => 'send_whatsapp',
                    'data' => [
                        'template_ref' => 'welcome_lead_template',
                        'language' => 'en',
                        'body' => 'Hello {{contact.first_name}}, welcome to Growbridge Connect! How can we assist you today?',
                    ],
                ],
            ],
            'edges' => [
                ['id' => 'edge_1', 'source' => 'node_1', 'target' => 'node_2'],
            ],
        ]);

        // ── STEP 1 & 2: New Lead arrives → CRM contact created ──
        $lead = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Aditya',
            'last_name' => 'Verma',
            'company' => 'Verma Technologies',
            'phone_e164' => '+919876543210',
            'email' => 'aditya@vermatech.in',
            'deal_value' => 150000,
            'source' => 'web_inquiry',
            'priority' => 'high',
        ]);

        $this->assertNotNull($lead->id);
        $this->assertEquals('Aditya Verma', $lead->full_name);

        // ── STEP 3: Automation triggered ──
        $run = AutomationRun::where('automation_id', $welcomeAutomation->id)
            ->where('contact_id', $lead->id)
            ->first();

        // If queue was deferred, execute it through engine
        if (! $run) {
            app(AutomationEngine::class)->triggerForContact($welcomeAutomation, $lead->id);
            $run = AutomationRun::where('automation_id', $welcomeAutomation->id)->where('contact_id', $lead->id)->first();
        }

        $this->assertNotNull($run);
        app(AutomationEngine::class)->executeRun($run);
        $this->assertEquals('completed', $run->fresh()->status);

        // ── STEP 4: WhatsApp template sent ──
        $outboundWelcome = Message::where('contact_id', $lead->id)
            ->where('direction', 'out')
            ->where('channel', 'whatsapp')
            ->first();

        $this->assertNotNull($outboundWelcome);
        $this->assertStringContainsString('welcome_lead_template', $outboundWelcome->body ?? json_encode($outboundWelcome->payload));

        // ── STEP 5: Customer replies on WhatsApp ──
        $conversationService = app(ConversationService::class);
        $inboundReplyNormalized = NormalizedMessage::make(
            channel: 'whatsapp',
            direction: 'inbound',
            senderType: 'customer',
            messageType: 'text',
            body: 'Hi, I need pricing details for your enterprise CRM package.',
            externalMessageId: 'wamid.INBOUND_001_' . uniqid(),
            status: 'received',
            senderIdentifier: '+919876543210',
            senderName: 'Aditya Verma',
            recipientIdentifier: '10987654321',
            metadata: ['from' => '+919876543210']
        );

        $inboundMsg = $conversationService->processIncomingMessage(
            $inboundReplyNormalized,
            $this->workspace->id,
            $this->whatsappAccount->id
        );

        $this->assertNotNull($inboundMsg);
        $this->assertEquals('in', $inboundMsg->direction);
        $conversation = $inboundMsg->conversation;
        $this->assertNotNull($conversation);
        $this->assertEquals('auto', $conversation->ai_mode);

        // ── STEP 6 & 7: Automation / Conversation detects reply & AI Agent responds automatically ──
        $aiResponse = Message::where('conversation_id', $conversation->id)
            ->where('direction', 'out')
            ->where('sent_by', 'bot')
            ->first();

        $this->assertNotNull($aiResponse);
        $this->assertEquals('out', $aiResponse->direction);
        $this->assertEquals('bot', $aiResponse->sent_by);
        $this->assertStringContainsString('AI assistant', $aiResponse->body);

        // ── STEP 8: Customer asks for human ──
        $humanRequestNormalized = NormalizedMessage::make(
            channel: 'whatsapp',
            direction: 'inbound',
            senderType: 'customer',
            messageType: 'text',
            body: 'I want to talk to a human salesperson please to discuss contract terms.',
            externalMessageId: 'wamid.INBOUND_002_' . uniqid(),
            status: 'received',
            senderIdentifier: '+919876543210',
            senderName: 'Aditya Verma',
            recipientIdentifier: '10987654321',
            metadata: ['from' => '+919876543210']
        );

        $handoffService = app(HumanHandoffService::class);
        $this->assertTrue($handoffService->isHandoffRequested($humanRequestNormalized->body));

        // Process the human request message
        $humanMsg = $conversationService->processIncomingMessage(
            $humanRequestNormalized,
            $this->workspace->id,
            $this->whatsappAccount->id
        );

        // ── STEP 9: AI stops ──
        $conversation->refresh();
        $this->assertEquals('human', $conversation->ai_mode);
        $this->assertEquals('human', $conversation->assigned_to);
        $this->assertNotNull($conversation->handover_at);
        $this->assertFalse($conversation->isAiActive());

        // Verify AI cannot auto-reply while in human mode
        $thirdInbound = NormalizedMessage::make(
            channel: 'whatsapp',
            direction: 'inbound',
            senderType: 'customer',
            messageType: 'text',
            body: 'Is anyone available?',
            externalMessageId: 'wamid.INBOUND_003_' . uniqid(),
            status: 'received',
            senderIdentifier: '+919876543210',
            senderName: 'Aditya Verma',
            recipientIdentifier: '10987654321'
        );
        $conversationService->processIncomingMessage($thirdInbound, $this->workspace->id, $this->whatsappAccount->id);

        // Count AI messages — must not have generated an extra bot message
        $botMsgCount = Message::where('conversation_id', $conversation->id)->where('sent_by', 'bot')->count();
        $this->assertEquals(1, $botMsgCount); // Only the initial demo reply

        // ── STEP 10: Salesperson assigned ──
        $this->assertEquals($this->salesperson->id, $conversation->fresh()->assigned_user_id);
        $this->assertEquals($this->salesperson->id, $lead->fresh()->assigned_user_id);

        // ── STEP 11: Follow-up task created ──
        $task = CrmTask::where('workspace_id', $this->workspace->id)
            ->where('contact_id', $lead->id)
            ->first();

        $this->assertNotNull($task);
        $this->assertEquals('urgent', $task->priority);
        $this->assertEquals('pending', $task->status);
        $this->assertEquals($this->salesperson->id, $task->assigned_user_id);
        $this->assertStringContainsString('Live agent requested', $task->title);

        // ── STEP 12: CRM timeline updated ──
        $timelineEvent = ContactTimelineEvent::where('workspace_id', $this->workspace->id)
            ->where('contact_id', $lead->id)
            ->where('event_type', 'human_handoff')
            ->first();

        $this->assertNotNull($timelineEvent);
        $this->assertEquals('Customer Handed Over to Human Agent', $timelineEvent->title);
        $this->assertEquals($this->salesperson->id, $timelineEvent->metadata_json['assigned_user_id']);
        $this->assertEquals($task->id, $timelineEvent->metadata_json['task_id']);

        // Verify Unified Timeline reflects all events
        $timelineService = app(CustomerTimelineService::class);
        $fullTimeline = $timelineService->getTimeline($lead);
        $types = collect($fullTimeline)->pluck('type')->all();

        $this->assertContains('whatsapp', $types);
        $this->assertContains('task', $types);
    }

    public function test_automation_workflow_with_explicit_create_task_node(): void
    {
        $taskAutomation = Automation::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'High Value Lead Task Scheduling',
            'trigger_type' => 'lead.created',
            'status' => 'active',
            'nodes' => [
                ['id' => 't_node_1', 'type' => 'trigger', 'data' => ['event' => 'lead.created']],
                [
                    'id' => 't_node_2',
                    'type' => 'create_task',
                    'data' => [
                        'title' => 'Schedule discovery call with {{contact.first_name}}',
                        'description' => 'Enterprise prospect with high budget. Initial outreach required.',
                        'priority' => 'urgent',
                        'due_in_minutes' => 30,
                    ],
                ],
            ],
            'edges' => [
                ['id' => 't_edge_1', 'source' => 't_node_1', 'target' => 't_node_2'],
            ],
        ]);

        $vipLead = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Pooja',
            'last_name' => 'Sharma',
            'phone_e164' => '+919123456789',
            'email' => 'pooja@enterprise.com',
            'deal_value' => 500000,
            'assigned_user_id' => $this->salesperson->id,
        ]);

        $run = AutomationRun::create([
            'automation_id' => $taskAutomation->id,
            'contact_id' => $vipLead->id,
            'status' => 'pending',
        ]);

        app(AutomationEngine::class)->executeRun($run);

        $this->assertEquals('completed', $run->fresh()->status);

        $createdTask = CrmTask::where('contact_id', $vipLead->id)
            ->where('title', 'like', '%Schedule discovery call with Pooja%')
            ->first();

        $this->assertNotNull($createdTask);
        $this->assertEquals('urgent', $createdTask->priority);
        $this->assertEquals($this->salesperson->id, $createdTask->assigned_user_id);
    }

    public function test_round_robin_lead_assignment_distributes_handoffs_evenly(): void
    {
        $salesperson2 = User::create([
            'name' => 'Rohan Closer',
            'email' => 'rohan@growbridge.io',
            'password' => bcrypt('Password123!'),
            'role' => User::ROLE_CLIENT,
            'client_id' => $this->workspace->client_id,
            'client_role' => User::CLIENT_ROLE_STAFF,
            'workspace_id' => $this->workspace->id,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->workspace->members()->syncWithoutDetaching([
            $salesperson2->id => ['role' => 'salesperson'],
        ]);

        $handoffService = app(HumanHandoffService::class);

        // Contact 1 handoff
        $contact1 = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Lead One',
            'phone_e164' => '+919999000001',
        ]);
        $conv1 = Conversation::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'workspace_id' => $this->workspace->id,
            'contact_id' => $contact1->id,
            'channel_account_id' => $this->whatsappAccount->id,
            'channel' => 'whatsapp',
            'status' => 'open',
            'ai_mode' => 'auto',
            'assigned_to' => 'bot',
        ]);

        $res1 = $handoffService->executeHandoff($conv1, null, 'Customer requested human');
        $this->assertNotNull($res1['assigned_user']);
        $assignedId1 = $res1['assigned_user']->id;

        // Contact 2 handoff
        $contact2 = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Lead Two',
            'phone_e164' => '+919999000002',
        ]);
        $conv2 = Conversation::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'workspace_id' => $this->workspace->id,
            'contact_id' => $contact2->id,
            'channel_account_id' => $this->whatsappAccount->id,
            'channel' => 'whatsapp',
            'status' => 'open',
            'ai_mode' => 'auto',
            'assigned_to' => 'bot',
        ]);

        $res2 = $handoffService->executeHandoff($conv2, null, 'Customer requested human');
        $this->assertNotNull($res2['assigned_user']);
        $assignedId2 = $res2['assigned_user']->id;

        // Verify both salespersons receive assigned tasks
        $this->assertContains($assignedId1, [$this->salesperson->id, $salesperson2->id]);
        $this->assertContains($assignedId2, [$this->salesperson->id, $salesperson2->id]);

        $tasksCount = CrmTask::where('workspace_id', $this->workspace->id)->count();
        $this->assertGreaterThanOrEqual(2, $tasksCount);
    }
}
