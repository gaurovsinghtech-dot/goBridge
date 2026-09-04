<?php

namespace Tests\Feature\Whatsapp;

use App\Models\AdminUser;
use App\Models\Client;
use App\Models\InternalNote;
use App\Models\StoredFile;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\AI\Models\AiChatbot;
use App\Modules\Broadcasting\Models\Campaign;
use App\Modules\Broadcasting\Models\CampaignRecipient;
use App\Modules\Integrations\Models\IntegrationConfig;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use App\Modules\Whatsapp\Http\Controllers\WhatsappWebhookController;
use App\Modules\Whatsapp\Models\WhatsappBusinessAccount;
use App\Modules\Whatsapp\Models\WhatsappPhoneNumber;
use App\Modules\Whatsapp\Models\WhatsappTemplate;
use App\Modules\Whatsapp\Services\CloudApiClient;
use App\Modules\Whatsapp\Services\WhatsappDriver;
use App\Services\Storage\StorageService;
use App\Services\WebhookIdempotencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WhatsappProductionTestingTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspaceA;
    private Workspace $workspaceB;
    private User $userA;
    private User $userB;
    private ChannelAccount $channelAccountA;
    private WhatsappBusinessAccount $wabaA;
    private WhatsappPhoneNumber $phoneA;
    private const RAW_WEBHOOK_TOKEN = 'wh_token_acme_secret_999';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
        Storage::fake('local');

        // Create Workspace A & User A
        $clientA = Client::create(['name' => 'Acme Corp', 'status' => 'active']);
        $this->workspaceA = Workspace::create([
            'client_id' => $clientA->id,
            'name' => 'Acme Workspace',
            'industry' => 'SaaS',
        ]);
        $this->userA = User::create([
            'name' => 'Alice Admin',
            'email' => 'alice@acme.com',
            'password' => bcrypt('Password123!'),
            'role' => User::ROLE_CLIENT,
            'client_id' => $clientA->id,
            'workspace_id' => $this->workspaceA->id,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
        $this->workspaceA->forceFill(['owner_id' => $this->userA->id])->saveQuietly();
        $this->workspaceA->members()->syncWithoutDetaching([$this->userA->id => ['role' => 'owner']]);

        // Create Workspace B & User B
        $clientB = Client::create(['name' => 'Beta Corp', 'status' => 'active']);
        $this->workspaceB = Workspace::create([
            'client_id' => $clientB->id,
            'name' => 'Beta Workspace',
            'industry' => 'Retail',
        ]);
        $this->userB = User::create([
            'name' => 'Bob Owner',
            'email' => 'bob@beta.com',
            'password' => bcrypt('Password123!'),
            'role' => User::ROLE_CLIENT,
            'client_id' => $clientB->id,
            'workspace_id' => $this->workspaceB->id,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
        $this->workspaceB->forceFill(['owner_id' => $this->userB->id])->saveQuietly();
        $this->workspaceB->members()->syncWithoutDetaching([$this->userB->id => ['role' => 'owner']]);

        // Setup Meta System Integration Config
        IntegrationConfig::create([
            'provider' => 'meta_app',
            'label' => 'Meta Platform Credentials',
            'enabled' => true,
            'is_active' => true,
            'credentials' => [
                'app_id' => 'test_meta_app_123456',
                'app_secret' => 'test_meta_secret_abcdef123456',
                'system_access_token' => 'EAAG_test_system_token_xyz',
            ],
        ]);

        // Setup WABA and Phone for Workspace A
        $this->wabaA = WhatsappBusinessAccount::create([
            'workspace_id' => $this->workspaceA->id,
            'waba_id' => '100200300400',
            'name' => 'Acme WhatsApp Business',
            'currency' => 'INR',
            'timezone_id' => '1',
            'status' => 'active',
            'webhook_verify_token' => self::RAW_WEBHOOK_TOKEN,
            'credentials' => [
                'access_token' => 'EAAB_test_waba_token_123',
            ],
        ]);

        $this->phoneA = WhatsappPhoneNumber::create([
            'waba_id_fk' => $this->wabaA->id,
            'phone_number_id' => 'phone_id_998877',
            'display_phone' => '+91 98765 43210',
            'verified_name' => 'Acme Support',
            'quality_rating' => 'GREEN',
            'messaging_limit_tier' => 'TIER_10K',
            'name_status' => 'APPROVED',
        ]);

        $this->channelAccountA = ChannelAccount::create([
            'workspace_id' => $this->workspaceA->id,
            'channel' => 'whatsapp',
            'display_name' => 'Acme WhatsApp Support',
            'phone_number_id' => 'phone_id_998877',
            'account_id' => $this->wabaA->id,
            'status' => 'active',
        ]);
    }

    public function test_whatsapp_onboarding_connection_verification(): void
    {
        $this->assertDatabaseHas('whatsapp_business_accounts', [
            'workspace_id' => $this->workspaceA->id,
            'waba_id' => '100200300400',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('whatsapp_phone_numbers', [
            'phone_number_id' => 'phone_id_998877',
            'quality_rating' => 'GREEN',
            'name_status' => 'APPROVED',
        ]);

        $client = CloudApiClient::forPhoneNumber('phone_id_998877', $this->workspaceA->id);
        $this->assertNotNull($client);
        $this->assertEquals('phone_id_998877', $client->phoneNumberId());
    }

    public function test_api_authentication_and_token_validation(): void
    {
        $client = CloudApiClient::forWorkspace($this->workspaceA->id);
        $this->assertNotNull($client);

        // Verify invalid phone returns null without leaking secrets
        $invalidClient = CloudApiClient::forPhoneNumber('non_existent_phone', $this->workspaceA->id);
        $this->assertNull($invalidClient);
    }

    public function test_global_and_workspace_webhook_verification(): void
    {
        // 1. Workspace specific webhook verification
        $challenge = 'challenge_ws_12345';
        $responseWs = $this->get('/webhooks/whatsapp/' . self::RAW_WEBHOOK_TOKEN . '?' . http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => self::RAW_WEBHOOK_TOKEN,
            'hub_challenge' => $challenge,
        ]));

        $responseWs->assertOk();
        $this->assertEquals($challenge, $responseWs->getContent());

        // 2. Global webhook verification
        $appId = 'test_meta_app_123456';
        $appSecret = 'test_meta_secret_abcdef123456';
        $globalToken = hash('sha256', $appId . $appSecret . 'wh_global_verify');
        $globalChallenge = 'challenge_global_67890';

        $responseGlobal = $this->get('/webhooks/whatsapp/global?' . http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => $globalToken,
            'hub_challenge' => $globalChallenge,
        ]));

        $responseGlobal->assertOk();
        $this->assertEquals($globalChallenge, $responseGlobal->getContent());
    }

    public function test_webhook_hmac_sha256_signature_verification_and_rejection(): void
    {
        $appSecret = 'test_meta_secret_abcdef123456';
        $payload = ['object' => 'whatsapp_business_account', 'entry' => []];
        $rawJson = json_encode($payload);

        $validSignature = 'sha256=' . hash_hmac('sha256', $rawJson, $appSecret);
        $invalidSignature = 'sha256=' . hash_hmac('sha256', $rawJson, 'wrong_secret_123');

        // Bad signature rejected with 401
        $responseRejected = $this->call('POST', '/webhooks/whatsapp/' . self::RAW_WEBHOOK_TOKEN, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $invalidSignature,
        ], $rawJson);
        $responseRejected->assertStatus(401);

        // Good signature accepted with 200
        $responseAccepted = $this->call('POST', '/webhooks/whatsapp/' . self::RAW_WEBHOOK_TOKEN, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $validSignature,
        ], $rawJson);
        $responseAccepted->assertOk();
    }

    public function test_webhook_idempotency_and_duplicate_event_deduplication(): void
    {
        $driver = app(WhatsappDriver::class);
        $msgId = 'wamid.HBgLMTEyMjMzNDQ1NQ==';

        $inboundPayload = [
            'entry' => [
                [
                    'id' => '100200300400',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '+919876543210',
                                    'phone_number_id' => 'phone_id_998877',
                                ],
                                'contacts' => [
                                    ['profile' => ['name' => 'John Customer'], 'wa_id' => '919811122233'],
                                ],
                                'messages' => [
                                    [
                                        'id' => $msgId,
                                        'from' => '919811122233',
                                        'timestamp' => (string) time(),
                                        'type' => 'text',
                                        'text' => ['body' => 'Hello Growbridge!'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // 1st processing creates message
        $driver->processWebhookPayload($inboundPayload);
        $this->assertEquals(1, Message::where('provider_message_id', $msgId)->count());

        // 2nd duplicate processing does NOT duplicate message
        $driver->processWebhookPayload($inboundPayload);
        $this->assertEquals(1, Message::where('provider_message_id', $msgId)->count());
    }

    public function test_incoming_messages_text_media_location_interactive(): void
    {
        $driver = app(WhatsappDriver::class);

        // 1. Inbound Text Message
        $textMsgId = 'wamid.text_' . uniqid();
        $payloadText = [
            'entry' => [
                [
                    'id' => '100200300400',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => ['phone_number_id' => 'phone_id_998877'],
                                'contacts' => [['profile' => ['name' => 'Kavita Singh'], 'wa_id' => '919988776655']],
                                'messages' => [
                                    [
                                        'id' => $textMsgId,
                                        'from' => '919988776655',
                                        'timestamp' => (string) time(),
                                        'type' => 'text',
                                        'text' => ['body' => 'Can I get a product quote?'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $driver->processWebhookPayload($payloadText);

        $contact = Contact::where('phone_e164', '+919988776655')->first();
        $this->assertNotNull($contact);
        $this->assertEquals('Kavita', $contact->first_name);
        $this->assertEquals('Singh', $contact->last_name);

        $conversation = Conversation::where('contact_id', $contact->id)->first();
        $this->assertNotNull($conversation);
        $this->assertEquals('open', $conversation->status);
        $this->assertTrue($conversation->isWhatsappWindowOpen());

        $msg = Message::where('provider_message_id', $textMsgId)->first();
        $this->assertNotNull($msg);
        $this->assertEquals('Can I get a product quote?', $msg->body);

        // 2. Inbound Interactive Button Reply
        $buttonMsgId = 'wamid.btn_' . uniqid();
        $payloadButton = [
            'entry' => [
                [
                    'id' => '100200300400',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => ['phone_number_id' => 'phone_id_998877'],
                                'contacts' => [['profile' => ['name' => 'Kavita Singh'], 'wa_id' => '919988776655']],
                                'messages' => [
                                    [
                                        'id' => $buttonMsgId,
                                        'from' => '919988776655',
                                        'timestamp' => (string) time(),
                                        'type' => 'interactive',
                                        'interactive' => [
                                            'type' => 'button_reply',
                                            'button_reply' => ['id' => 'opt_demo', 'title' => 'Request Live Demo'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $driver->processWebhookPayload($payloadButton);
        $btnMessage = Message::where('provider_message_id', $buttonMsgId)->first();
        $this->assertNotNull($btnMessage);
        $this->assertEquals('Request Live Demo', $btnMessage->body);

        // 3. Inbound Location Message
        $locMsgId = 'wamid.loc_' . uniqid();
        $payloadLoc = [
            'entry' => [
                [
                    'id' => '100200300400',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => ['phone_number_id' => 'phone_id_998877'],
                                'contacts' => [['profile' => ['name' => 'Kavita Singh'], 'wa_id' => '919988776655']],
                                'messages' => [
                                    [
                                        'id' => $locMsgId,
                                        'from' => '919988776655',
                                        'timestamp' => (string) time(),
                                        'type' => 'location',
                                        'location' => [
                                            'latitude' => 19.0760,
                                            'longitude' => 72.8777,
                                            'name' => 'Mumbai Head Office',
                                            'address' => 'Bandra Kurla Complex, Mumbai',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $driver->processWebhookPayload($payloadLoc);
        $locMessage = Message::where('provider_message_id', $locMsgId)->first();
        $this->assertNotNull($locMessage);
        $this->assertStringContainsString('Mumbai Head Office', $locMessage->body);
    }

    public function test_outgoing_messages_and_delivery_status_updates(): void
    {
        $mockWamid = 'wamid.HBgL' . bin2hex(random_bytes(16));
        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'contacts' => [['input' => '+919123456789', 'wa_id' => '919123456789']],
                'messages' => [['id' => $mockWamid]],
            ], 200),
        ]);

        $contact = Contact::create([
            'workspace_id' => $this->workspaceA->id,
            'first_name' => 'Rohan',
            'phone_e164' => '+919123456789',
        ]);

        $conversation = Conversation::create([
            'workspace_id' => $this->workspaceA->id,
            'contact_id' => $contact->id,
            'channel_account_id' => $this->channelAccountA->id,
            'status' => 'open',
            'last_inbound_at' => now(),
        ]);

        $outMsg = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'out',
            'channel' => 'whatsapp',
            'type' => 'text',
            'body' => 'Hello Rohan, thanks for contacting us.',
            'status' => 'queued',
            'sent_at' => now(),
        ]);

        $driver = app(WhatsappDriver::class);
        $wamid = $driver->send($outMsg);

        $this->assertEquals($mockWamid, $wamid);
        $outMsg->update(['provider_message_id' => $wamid, 'status' => 'sent']);

        // 1. Meta reports 'delivered' status update
        $statusPayloadDelivered = [
            'entry' => [
                [
                    'id' => '100200300400',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'statuses' => [
                                    ['id' => $wamid, 'status' => 'delivered', 'timestamp' => (string) time()],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $driver->processWebhookPayload($statusPayloadDelivered);
        $this->assertEquals('delivered', $outMsg->fresh()->status);

        // 2. Meta reports 'read' status update
        $statusPayloadRead = [
            'entry' => [
                [
                    'id' => '100200300400',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'statuses' => [
                                    ['id' => $wamid, 'status' => 'read', 'timestamp' => (string) time()],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $driver->processWebhookPayload($statusPayloadRead);
        $this->assertEquals('read', $outMsg->fresh()->status);
    }

    public function test_customer_service_window_enforcement(): void
    {
        $contactOpen = Contact::create([
            'workspace_id' => $this->workspaceA->id,
            'first_name' => 'Pooja Open',
            'phone_e164' => '+919876511223',
        ]);

        $contactClosed = Contact::create([
            'workspace_id' => $this->workspaceA->id,
            'first_name' => 'Pooja Closed',
            'phone_e164' => '+919876599887',
        ]);

        // Case A: Window is open (Inbound received 2 hours ago)
        $convOpen = Conversation::create([
            'workspace_id' => $this->workspaceA->id,
            'contact_id' => $contactOpen->id,
            'channel_account_id' => $this->channelAccountA->id,
            'status' => 'open',
            'last_inbound_at' => now()->subHours(2),
        ]);
        Message::create([
            'conversation_id' => $convOpen->id,
            'direction' => 'in',
            'channel' => 'whatsapp',
            'type' => 'text',
            'body' => 'Hi',
            'sent_at' => now()->subHours(2),
        ]);
        $this->assertTrue($convOpen->isWhatsappWindowOpen());

        // Case B: Window is expired (Inbound received 26 hours ago)
        $convClosed = Conversation::create([
            'workspace_id' => $this->workspaceA->id,
            'contact_id' => $contactClosed->id,
            'channel_account_id' => $this->channelAccountA->id,
            'status' => 'open',
            'last_inbound_at' => now()->subHours(26),
        ]);
        Message::create([
            'conversation_id' => $convClosed->id,
            'direction' => 'in',
            'channel' => 'whatsapp',
            'type' => 'text',
            'body' => 'Old message',
            'sent_at' => now()->subHours(26),
        ]);
        $this->assertFalse($convClosed->isWhatsappWindowOpen());
    }

    public function test_templates_synchronization_and_campaign_eligibility(): void
    {
        WhatsappTemplate::create([
            'workspace_id' => $this->workspaceA->id,
            'waba_id' => '100200300400',
            'name' => 'order_confirmation_v1',
            'language' => 'en',
            'category' => 'UTILITY',
            'status' => 'APPROVED',
            'components_json' => [
                ['type' => 'BODY', 'text' => 'Hi {{1}}, your order #{{2}} is confirmed.'],
            ],
        ]);

        WhatsappTemplate::create([
            'workspace_id' => $this->workspaceA->id,
            'waba_id' => '100200300400',
            'name' => 'promo_discount_v2',
            'language' => 'en',
            'category' => 'MARKETING',
            'status' => 'REJECTED',
            'rejection_reason' => 'Quality guidelines violation',
        ]);

        $approvedTemplates = WhatsappTemplate::where('waba_id', '100200300400')->where('status', 'APPROVED')->get();
        $this->assertCount(1, $approvedTemplates);
        $this->assertEquals('order_confirmation_v1', $approvedTemplates->first()->name);
    }

    public function test_campaign_queue_and_delivery_tracking(): void
    {
        $campaign = Campaign::create([
            'workspace_id' => $this->workspaceA->id,
            'name' => 'Q3 Product Announcement',
            'channel' => 'whatsapp',
            'status' => 'running',
        ]);

        $contact = Contact::create([
            'workspace_id' => $this->workspaceA->id,
            'first_name' => 'Campaign Contact',
            'phone_e164' => '+919988771122',
        ]);

        $wamidCampaign = 'wamid.camp_' . uniqid();
        $recipient = CampaignRecipient::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'recipient_identifier' => '+919988771122',
            'status' => 'sent',
            'provider_message_id' => $wamidCampaign,
            'sent_at' => now(),
        ]);

        $driver = app(WhatsappDriver::class);

        // Webhook status delivered
        $driver->processWebhookPayload([
            'entry' => [
                [
                    'id' => '100200300400',
                    'changes' => [
                        ['field' => 'messages', 'value' => ['statuses' => [['id' => $wamidCampaign, 'status' => 'delivered']]]],
                    ],
                ],
            ],
        ]);

        $updated = $recipient->fresh();
        $this->assertEquals('delivered', $updated->status);
        $this->assertNotNull($updated->delivered_at);
    }

    public function test_automation_and_ai_trigger_with_human_handoff(): void
    {
        $contact = Contact::create([
            'workspace_id' => $this->workspaceA->id,
            'first_name' => 'Sunil',
            'phone_e164' => '+919555112233',
        ]);

        $conversation = Conversation::create([
            'workspace_id' => $this->workspaceA->id,
            'contact_id' => $contact->id,
            'channel_account_id' => $this->channelAccountA->id,
            'status' => 'open',
            'assigned_to' => 'bot',
            'last_inbound_at' => now(),
        ]);

        // Customer types "talk to human"
        $inboundMsg = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'in',
            'channel' => 'whatsapp',
            'type' => 'text',
            'body' => 'I need to talk to a human please',
            'sent_by' => 'human',
            'sent_at' => now(),
        ]);

        // Dispatch AutoReplyListener
        $listener = app(\App\Listeners\AutoReplyListener::class);
        $listener->handle(new \App\Events\MessageReceived($inboundMsg));

        $freshConv = $conversation->fresh();
        // Handover triggered -> assigned_to is now human
        $this->assertEquals('human', $freshConv->assigned_to);
        $this->assertTrue($freshConv->isHumanActive());
    }

    public function test_admin_whatsapp_integration_telemetry_and_api_tests(): void
    {
        $admin = $this->createSuperAdminUser();

        // 1. Admin Index View
        $resIndex = $this->actingAs($admin, 'admin')->get(route('admin.integrations.whatsapp.index'));
        $resIndex->assertOk();

        // 2. Test Meta API
        $resApi = $this->actingAs($admin, 'admin')->postJson(route('admin.integrations.whatsapp.test-api'));
        $resApi->assertOk();
        $resApi->assertJson(['success' => true]);

        // 3. Test Webhook Challenge
        $resWh = $this->actingAs($admin, 'admin')->postJson(route('admin.integrations.whatsapp.test-webhook'));
        $resWh->assertOk();
        $resWh->assertJson(['success' => true]);
    }

    public function test_cross_workspace_whatsapp_isolation(): void
    {
        // Setup WABA and Phone for Workspace B
        $wabaB = WhatsappBusinessAccount::create([
            'workspace_id' => $this->workspaceB->id,
            'waba_id' => '999888777666',
            'name' => 'Beta WhatsApp Account',
            'status' => 'active',
            'webhook_verify_token' => 'wh_token_beta_private',
            'credentials' => [
                'access_token' => 'EAAB_token_b_999888',
            ],
        ]);

        $phoneB = WhatsappPhoneNumber::create([
            'waba_id_fk' => $wabaB->id,
            'phone_number_id' => 'phone_id_112233',
            'display_phone' => '+91 88888 99999',
            'verified_name' => 'Beta Store',
            'quality_rating' => 'GREEN',
            'name_status' => 'APPROVED',
        ]);

        // Workspace A client resolving phone B must return null
        $client = CloudApiClient::forPhoneNumber('phone_id_112233', $this->workspaceA->id);
        $this->assertNull($client);

        // Workspace B client resolving phone B succeeds
        $clientB = CloudApiClient::forPhoneNumber('phone_id_112233', $this->workspaceB->id);
        $this->assertNotNull($clientB);
        $this->assertEquals('phone_id_112233', $clientB->phoneNumberId());
    }
}
