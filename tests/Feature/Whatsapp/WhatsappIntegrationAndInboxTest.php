<?php

namespace Tests\Feature\Whatsapp;

use App\Models\Client;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\Integrations\Models\IntegrationConfig;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use App\Modules\Whatsapp\Models\WhatsappBusinessAccount;
use App\Modules\Whatsapp\Models\WhatsappPhoneNumber;
use App\Modules\Whatsapp\Services\CloudApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WhatsappIntegrationAndInboxTest extends TestCase
{
    use RefreshDatabase;

    private const META_APP_ID = '987654321098';
    private const META_APP_SECRET = 'super_secret_meta_app_key_321';

    protected function setupWorkspaceWithMeta(): array
    {
        $client = Client::create([
            'name' => 'Nexus Retail India',
            'email' => 'admin@nexusretail.in',
            'status' => 'active',
        ]);

        $workspace = Workspace::create([
            'client_id' => $client->id,
            'name' => 'Nexus Retail Bangalore',
            'industry' => 'Retail & E-commerce',
            'currency_code' => 'INR',
            'default_locale' => 'en',
            'service_type' => 'whatsapp_only',
        ]);

        $user = User::create([
            'name' => 'Vikram Nexus',
            'email' => 'vikram@nexusretail.in',
            'password' => bcrypt('Password123!'),
            'role' => User::ROLE_CLIENT,
            'client_id' => $client->id,
            'client_role' => User::CLIENT_ROLE_ADMINISTRATOR,
            'workspace_id' => $workspace->id,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        $workspace->forceFill(['owner_id' => $user->id])->saveQuietly();
        $workspace->members()->syncWithoutDetaching([$user->id => ['role' => 'owner']]);

        $plan = Plan::create([
            'name' => 'Growth Plan',
            'slug' => 'growth',
            'currency_code' => 'INR',
            'price_cents' => 4900,
            'features' => ['whatsapp' => true, 'inbox' => true],
            'limits' => ['whatsapp_messages_per_month' => 10000],
            'status' => 'active',
        ]);
        \App\Models\Subscription::create([
            'client_id' => $client->id,
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'gateway' => 'razorpay',
            'status' => 'active',
            'current_period_start' => now()->subDay(),
            'current_period_end' => now()->addMonth(),
        ]);

        // Seed system Meta app credentials
        IntegrationConfig::create([
            'provider' => 'meta_app',
            'label' => 'Meta App',
            'mode' => 'live',
            'enabled' => true,
            'credentials' => [
                'app_id' => self::META_APP_ID,
                'app_secret' => self::META_APP_SECRET,
                'verify_token' => 'system_wh_verify_token_789',
            ],
        ]);

        return compact('client', 'workspace', 'user');
    }

    public function test_whatsapp_connection_requires_real_meta_api_validation_and_rejects_fake_tokens(): void
    {
        ['workspace' => $workspace, 'user' => $user] = $this->setupWorkspaceWithMeta();

        // 1. Meta Graph API returns 401 Unauthorized for invalid token
        Http::fake([
            'graph.facebook.com/v20.0/1009988776655*' => Http::response([
                'error' => [
                    'message' => 'Invalid OAuth access token - Cannot parse access token',
                    'type' => 'OAuthException',
                    'code' => 190,
                ],
            ], 401),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->postJson(route('client.whatsapp.setup.manual'), [
                'waba_id' => '1009988776655',
                'access_token' => 'FAKE_INVALID_TOKEN',
            ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Could not verify the WABA with Meta: Invalid OAuth access token - Cannot parse access token — check the WABA ID and that the token has the whatsapp_business_management and whatsapp_business_messaging permissions.',
        ]);

        // Asserts NO fake connection success occurred
        $this->assertDatabaseMissing('whatsapp_business_accounts', [
            'waba_id' => '1009988776655',
        ]);
    }

    public function test_whatsapp_connection_succeeds_with_verified_meta_credentials(): void
    {
        ['workspace' => $workspace, 'user' => $user] = $this->setupWorkspaceWithMeta();

        // Mock legitimate Meta Graph responses
        Http::fake([
            'graph.facebook.com/v20.0/1009988776655/phone_numbers*' => Http::response([
                'data' => [
                    [
                        'id' => 'phone_node_554433',
                        'display_phone_number' => '+91 80 1234 5678',
                        'verified_name' => 'Nexus Retail Official',
                        'quality_rating' => 'GREEN',
                        'throughput' => ['level' => 'TIER_10K'],
                    ],
                ],
            ]),
            'graph.facebook.com/v20.0/1009988776655/subscribed_apps*' => Http::response(['success' => true]),
            'graph.facebook.com/v20.0/1009988776655*' => Http::response([
                'id' => '1009988776655',
                'name' => 'Nexus Retail Official',
                'currency' => 'INR',
                'timezone_id' => '1',
            ]),
            'graph.facebook.com/v20.0/phone_node_554433*' => Http::response([
                'id' => 'phone_node_554433',
                'display_phone_number' => '+91 80 1234 5678',
                'verified_name' => 'Nexus Retail Official',
                'quality_rating' => 'GREEN',
            ]),
            'graph.facebook.com/*' => Http::response(['success' => true]),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->postJson(route('client.whatsapp.setup.manual'), [
                'waba_id' => '1009988776655',
                'access_token' => 'VALID_META_SYSTEM_TOKEN_XYZ',
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'waba_id' => '1009988776655',
            'phone_count' => 1,
        ]);

        // Verify WABA was created with active status
        $waba = WhatsappBusinessAccount::where('waba_id', '1009988776655')->first();
        $this->assertNotNull($waba);
        $this->assertEquals($workspace->id, $waba->workspace_id);
        $this->assertEquals('active', $waba->status);

        // Verify ChannelAccount was created
        $channelAccount = ChannelAccount::where('workspace_id', $workspace->id)
            ->where('channel', 'whatsapp')
            ->where('phone_number_id', 'phone_node_554433')
            ->first();
        $this->assertNotNull($channelAccount);
        $this->assertEquals('active', $channelAccount->status);
    }

    public function test_global_webhook_verifies_challenge_and_hmac_signature(): void
    {
        $this->setupWorkspaceWithMeta();

        $expectedVerifyToken = hash('sha256', self::META_APP_ID . self::META_APP_SECRET . 'wh_global_verify');

        // 1. GET Challenge Verification
        $getRes = $this->get("/webhooks/whatsapp/global?" . http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => $expectedVerifyToken,
            'hub_challenge' => 'verify_challenge_token_12345',
        ]));

        $getRes->assertOk();
        $getRes->assertSee('verify_challenge_token_12345');

        // 2. Reject POST with missing/invalid signature
        $postUnsigned = $this->postJson('/webhooks/whatsapp/global', [
            'object' => 'whatsapp_business_account',
            'entry' => [],
        ]);
        $postUnsigned->assertStatus(401);

        // 3. Accept POST with valid HMAC SHA256 signature
        $payload = json_encode([
            'object' => 'whatsapp_business_account',
            'entry' => [],
        ]);
        $validSignature = 'sha256=' . hash_hmac('sha256', $payload, self::META_APP_SECRET);

        $postSigned = $this->withHeaders(['X-Hub-Signature-256' => $validSignature])
            ->postJson('/webhooks/whatsapp/global', json_decode($payload, true));

        $postSigned->assertOk();
        $postSigned->assertJson(['status' => 'ok']);
    }

    public function test_inbound_whatsapp_message_creates_contact_conversation_and_inbox_reply(): void
    {
        ['workspace' => $workspace, 'user' => $user] = $this->setupWorkspaceWithMeta();

        // Provision active WABA and Channel Account
        $waba = WhatsappBusinessAccount::create([
            'workspace_id' => $workspace->id,
            'waba_id' => '1009988776655',
            'credentials' => [
                'system_user_token' => 'VALID_TOKEN_ABC',
                'access_token' => 'VALID_TOKEN_ABC',
            ],
            'status' => 'active',
            'webhook_verify_token' => 'wh_token_sample',
        ]);

        $phoneNumber = WhatsappPhoneNumber::create([
            'waba_id_fk' => $waba->id,
            'phone_number_id' => 'phone_node_554433',
            'display_phone' => '+91 80 1234 5678',
            'verified_name' => 'Nexus Retail Official',
        ]);

        $channelAccount = ChannelAccount::create([
            'workspace_id' => $workspace->id,
            'channel' => 'whatsapp',
            'provider' => 'meta',
            'business_account_id' => '1009988776655',
            'phone_number_id' => 'phone_node_554433',
            'display_name' => 'Nexus Retail Official',
            'status' => 'active',
        ]);

        // Process Inbound WhatsApp Webhook Payload
        $inboundPayload = [
            'entry' => [
                [
                    'id' => '1009988776655',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '918012345678',
                                    'phone_number_id' => 'phone_node_554433',
                                ],
                                'contacts' => [
                                    [
                                        'profile' => ['name' => 'Amit Verma'],
                                        'wa_id' => '919876543210',
                                    ],
                                ],
                                'messages' => [
                                    [
                                        'from' => '919876543210',
                                        'id' => 'wamid.HBgLOTg3NjU0MzIxMBUCMRIA',
                                        'timestamp' => (string) time(),
                                        'text' => ['body' => 'Hello, what is the price for bulk order?'],
                                        'type' => 'text',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $driver = app(\App\Modules\Whatsapp\Services\WhatsappDriver::class);
        $driver->processWebhookPayload($inboundPayload);

        // Verify Contact created
        $contact = Contact::where('workspace_id', $workspace->id)
            ->where('phone_e164', '+919876543210')
            ->first();
        $this->assertNotNull($contact);
        $this->assertEquals('Amit Verma', $contact->full_name);

        // Verify Conversation created
        $conversation = Conversation::where('workspace_id', $workspace->id)
            ->where('contact_id', $contact->id)
            ->first();
        $this->assertNotNull($conversation);
        $this->assertEquals('open', $conversation->status);

        // Verify Message recorded
        $inboundMsg = Message::where('conversation_id', $conversation->id)->first();
        $this->assertNotNull($inboundMsg);
        $this->assertEquals('in', $inboundMsg->direction);
        $this->assertEquals('Hello, what is the price for bulk order?', $inboundMsg->body);

        // 3. Outbound Agent Reply via Inbox Controller
        Http::fake([
            'graph.facebook.com/v20.0/phone_node_554433/messages' => Http::response([
                'messaging_product' => 'whatsapp',
                'contacts' => [['input' => '+919876543210', 'wa_id' => '919876543210']],
                'messages' => [['id' => 'wamid.OUTBOUND_REPLY_112233']],
            ]),
        ]);

        $replyResponse = $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('client.inbox.reply', $conversation), [
                'body' => 'Hi Amit, our bulk catalog starts at 15% discount for 50+ units.',
                'type' => 'text',
            ]);

        $replyResponse->assertRedirect();

        // Verify outbound message was recorded
        $outboundMsg = Message::where('conversation_id', $conversation->id)
            ->where('direction', 'out')
            ->first();
        $this->assertNotNull($outboundMsg);
        $this->assertEquals('sent', $outboundMsg->status);
        $this->assertEquals('wamid.OUTBOUND_REPLY_112233', $outboundMsg->provider_message_id);
    }
}
