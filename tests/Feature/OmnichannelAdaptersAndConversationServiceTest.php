<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Modules\Inbox\Services\Adapters\EmailAdapter;
use App\Modules\Inbox\Services\Adapters\InstagramAdapter;
use App\Modules\Inbox\Services\Adapters\MessengerAdapter;
use App\Modules\Inbox\Services\Adapters\TwilioAdapter;
use App\Modules\Inbox\Services\Adapters\WhatsAppAdapter;
use App\Modules\Shared\DTOs\NormalizedMessage;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use App\Modules\Shared\Services\ChannelAdapterManager;
use App\Services\Conversation\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OmnichannelAdaptersAndConversationServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Workspace $workspace;
    private ChannelAdapterManager $adapterManager;
    private ConversationService $conversationService;

    protected function setUp(): void
    {
        parent::setUp();

        $ctx = $this->createWorkspaceContext();
        $this->user = $ctx['user'];
        $this->workspace = $ctx['workspace'];

        $this->adapterManager = app(ChannelAdapterManager::class);
        $this->conversationService = app(ConversationService::class);
    }

    public function test_channel_adapter_manager_registers_all_five_adapters(): void
    {
        $this->assertInstanceOf(WhatsAppAdapter::class, $this->adapterManager->adapter('whatsapp'));
        $this->assertInstanceOf(InstagramAdapter::class, $this->adapterManager->adapter('instagram'));
        $this->assertInstanceOf(MessengerAdapter::class, $this->adapterManager->adapter('messenger'));
        $this->assertInstanceOf(EmailAdapter::class, $this->adapterManager->adapter('email'));
        $this->assertInstanceOf(TwilioAdapter::class, $this->adapterManager->adapter('phone'));
        $this->assertInstanceOf(TwilioAdapter::class, $this->adapterManager->adapter('voice'));
    }

    public function test_whatsapp_adapter_normalizes_meta_payload(): void
    {
        $adapter = $this->adapterManager->adapter('whatsapp');

        $metaPayload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => ['display_phone_number' => '15551234567'],
                                'contacts' => [
                                    ['profile' => ['name' => 'Rahul Sharma']],
                                ],
                                'messages' => [
                                    [
                                        'from' => '919876543210',
                                        'id' => 'wamid.HBgLMTIzNDU2Nzg5',
                                        'timestamp' => '1700000000',
                                        'type' => 'text',
                                        'text' => ['body' => 'I need pricing for omnichannel package'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $normalized = $adapter->normalizeMessage($metaPayload);

        $this->assertInstanceOf(NormalizedMessage::class, $normalized);
        $this->assertEquals('whatsapp', $normalized->channel);
        $this->assertEquals('inbound', $normalized->direction);
        $this->assertEquals('customer', $normalized->senderType);
        $this->assertEquals('text', $normalized->messageType);
        $this->assertEquals('I need pricing for omnichannel package', $normalized->body);
        $this->assertEquals('+919876543210', $normalized->senderIdentifier);
        $this->assertEquals('Rahul Sharma', $normalized->senderName);
        $this->assertEquals('wamid.HBgLMTIzNDU2Nzg5', $normalized->externalMessageId);
    }

    public function test_instagram_adapter_normalizes_graph_api_payload(): void
    {
        $adapter = $this->adapterManager->adapter('instagram');

        $igPayload = [
            'entry' => [
                [
                    'messaging' => [
                        [
                            'sender' => ['id' => 'ig_user_9988'],
                            'recipient' => ['id' => 'ig_page_1122'],
                            'timestamp' => 1700000000000,
                            'message' => [
                                'mid' => 'm_ig_message_123',
                                'text' => 'What are your working hours?',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $normalized = $adapter->normalizeMessage($igPayload);

        $this->assertEquals('instagram', $normalized->channel);
        $this->assertEquals('inbound', $normalized->direction);
        $this->assertEquals('What are your working hours?', $normalized->body);
        $this->assertEquals('ig_user_9988', $normalized->senderIdentifier);
        $this->assertEquals('m_ig_message_123', $normalized->externalMessageId);
    }

    public function test_email_adapter_normalizes_inbound_email(): void
    {
        $adapter = $this->adapterManager->adapter('email');

        $emailPayload = [
            'from_email' => 'client@enterprise.com',
            'from_name' => 'Enterprise Lead',
            'body' => '<p>Please send quotation for 50 licenses.</p>',
            'message_id' => 'msg_em_887766',
        ];

        $normalized = $adapter->normalizeMessage($emailPayload);

        $this->assertEquals('email', $normalized->channel);
        $this->assertEquals('client@enterprise.com', $normalized->senderIdentifier);
        $this->assertEquals('Enterprise Lead', $normalized->senderName);
        $this->assertEquals('Please send quotation for 50 licenses.', $normalized->body);
    }

    public function test_twilio_adapter_normalizes_phone_and_sms(): void
    {
        $adapter = $this->adapterManager->adapter('phone');

        $twilioSmsPayload = [
            'From' => '+919988776655',
            'To' => '+14155552671',
            'Body' => 'Confirming my appointment.',
            'MessageSid' => 'SM1234567890abcdef',
        ];

        $normalized = $adapter->normalizeMessage($twilioSmsPayload);

        $this->assertEquals('phone', $normalized->channel);
        $this->assertEquals('+919988776655', $normalized->senderIdentifier);
        $this->assertEquals('Confirming my appointment.', $normalized->body);
        $this->assertEquals('SM1234567890abcdef', $normalized->externalMessageId);
    }

    public function test_conversation_service_processes_incoming_message_and_matches_contact(): void
    {
        $normalized = NormalizedMessage::make(
            channel: 'whatsapp',
            direction: 'inbound',
            senderType: 'customer',
            messageType: 'text',
            body: 'Hi, I need immediate assistance.',
            senderIdentifier: '+919876500001',
            senderName: 'Sanjay Gupta',
            externalMessageId: 'wamid.TEST9988'
        );

        $message = $this->conversationService->processIncomingMessage($normalized, $this->workspace->id);

        $this->assertInstanceOf(Message::class, $message);
        $this->assertEquals('in', $message->direction);
        $this->assertEquals('Hi, I need immediate assistance.', $message->body);
        $this->assertEquals('wamid.TEST9988', $message->provider_message_id);

        // Verify Contact was created / matched
        $contact = Contact::where('workspace_id', $this->workspace->id)
            ->where('phone_e164', '+919876500001')
            ->first();
        $this->assertNotNull($contact);
        $this->assertEquals('Sanjay', $contact->first_name);

        // Verify Conversation was created
        $conv = $message->conversation;
        $this->assertEquals($this->workspace->id, $conv->workspace_id);
        $this->assertEquals($contact->id, $conv->contact_id);
        $this->assertEquals(1, $conv->unread_count);

        // Second incoming message from same contact should match existing conversation
        $normalized2 = NormalizedMessage::make(
            channel: 'whatsapp',
            direction: 'inbound',
            senderType: 'customer',
            messageType: 'text',
            body: 'Also tell me about AI agents.',
            senderIdentifier: '+919876500001',
            senderName: 'Sanjay Gupta',
            externalMessageId: 'wamid.TEST9989'
        );

        $message2 = $this->conversationService->processIncomingMessage($normalized2, $this->workspace->id);
        $this->assertEquals($conv->id, $message2->conversation_id);
        $this->assertEquals(2, $conv->fresh()->unread_count);
    }

    public function test_conversation_service_send_reply_dispatches_outbound(): void
    {
        $contact = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Aditya',
            'phone_e164' => '+919876500002',
        ]);

        $channelAccount = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel' => 'whatsapp',
            'display_name' => 'WhatsApp Primary',
            'status' => 'active',
        ]);

        $conv = Conversation::create([
            'workspace_id' => $this->workspace->id,
            'contact_id' => $contact->id,
            'channel_account_id' => $channelAccount->id,
            'channel' => 'whatsapp',
            'status' => 'open',
        ]);

        $outbound = $this->conversationService->sendReply(
            $conv,
            ['body' => 'Here is the link to our pricing sheet: https://growbridge.io/pricing', 'type' => 'text'],
            $this->user
        );

        $this->assertInstanceOf(Message::class, $outbound);
        $this->assertEquals('out', $outbound->direction);
        $this->assertEquals('human', $outbound->sender_type);
        $this->assertEquals('Here is the link to our pricing sheet: https://growbridge.io/pricing', $outbound->body);
        $this->assertNotNull($outbound->provider_message_id);
        $this->assertNotNull($conv->fresh()->first_response_at);
    }

    public function test_conversation_service_delivery_receipt_and_ai_mode_management(): void
    {
        $contact = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Ritu',
            'phone_e164' => '+919876500003',
        ]);

        $conv = Conversation::create([
            'workspace_id' => $this->workspace->id,
            'contact_id' => $contact->id,
            'channel' => 'whatsapp',
            'status' => 'open',
            'assigned_to' => 'human',
            'unread_count' => 3,
        ]);

        $msg = Message::create([
            'conversation_id' => $conv->id,
            'direction' => 'out',
            'channel' => 'whatsapp',
            'type' => 'text',
            'body' => 'Hello Ritu',
            'provider_message_id' => 'wamid.STATUS_TEST_01',
            'status' => 'sent',
        ]);

        // 1. Delivery Receipt
        $this->conversationService->handleDeliveryReceipt('whatsapp', 'wamid.STATUS_TEST_01', 'read');
        $this->assertEquals('read', $msg->fresh()->status);

        // 2. Set AI Mode
        $this->conversationService->setAiMode($conv, 'bot');
        $this->assertEquals('bot', $conv->fresh()->assigned_to);

        // 3. Mark As Read
        $this->conversationService->markAsRead($conv);
        $this->assertEquals(0, $conv->fresh()->unread_count);
    }
}
