<?php

namespace Tests\Feature;

use App\Models\AppRelease;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use App\Modules\Voice\Models\VoiceCall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileAppAndApkManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Workspace $workspace;
    protected Client $client;
    protected Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plan = Plan::create([
            'name' => 'Scale Pro Plan',
            'slug' => 'scale-pro-plan',
            'currency_code' => 'INR',
            'price_cents' => 9900,
            'is_active' => true,
            'features' => [
                'whatsapp_api' => true,
                'voice_calling' => true,
                'ai_agents' => true,
                'ai_voice_agents' => true,
                'campaigns' => true,
                'automations' => true,
                'crm_integrations' => true,
            ],
        ]);

        $context = $this->createWorkspaceContext(
            [],
            ['email' => 'mobile.agent@growbridge.test'],
            ['service_type' => 'whatsapp_voice', 'currency_code' => 'INR']
        );

        $this->user = $context['user'];
        $this->workspace = $context['workspace'];
        $this->client = $context['client'];

        $this->attachPlanToClient($this->client, $this->plan);

        Subscription::create([
            'user_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
            'gateway' => 'manual',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'current_period_end' => now()->addMonth(),
        ]);
    }

    public function test_mobile_bootstrap_api_returns_entitlements_and_channels(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/mobile/bootstrap');

        $response->assertOk();
        $response->assertJsonStructure([
            'user' => ['id', 'name', 'email'],
            'workspace' => ['id', 'name', 'service_type'],
            'entitlements' => ['whatsapp_api', 'voice_calling', 'ai_agents'],
            'stats' => ['whatsapp_count', 'calls_count', 'leads_count', 'contacts_count'],
            'recent_conversations',
            'latest_app_release',
        ]);
        $this->assertTrue($response->json('entitlements.whatsapp_api'));
        $this->assertTrue($response->json('entitlements.voice_calling'));
    }

    public function test_mobile_conversations_and_ai_assist_endpoints(): void
    {
        $contact = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Amit',
            'last_name' => 'Kumar',
            'phone_e164' => '+919876543210',
        ]);

        $conversation = Conversation::create([
            'workspace_id' => $this->workspace->id,
            'contact_id' => $contact->id,
            'channel' => 'whatsapp',
            'status' => 'open',
            'is_ai_active' => true,
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'direction' => 'inbound',
            'body' => 'What is the pricing for heavy machinery?',
            'status' => 'received',
            'channel' => 'whatsapp',
        ]);

        // 1. Fetch Feed
        $feedResponse = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/mobile/feed');
        $feedResponse->assertOk();
        $this->assertNotEmpty($feedResponse->json('data'));

        // 2. Chat Detail
        $detailResponse = $this->actingAs($this->user, 'sanctum')->getJson("/api/v1/mobile/chat/{$conversation->id}");
        $detailResponse->assertOk();
        $detailResponse->assertJsonStructure(['conversation', 'customer_profile', 'messages']);

        // 3. Send Message
        $sendResponse = $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/mobile/chat/{$conversation->id}/send", [
            'body' => 'Our pricing starts at INR 4,999.',
        ]);
        $sendResponse->assertOk();
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'body' => 'Our pricing starts at INR 4,999.',
        ]);

        // 4. AI Assist Suggest Reply
        $aiResponse = $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/mobile/chat/{$conversation->id}/ai-assist", [
            'action' => 'suggest_reply',
        ]);
        $aiResponse->assertOk();
        $this->assertNotEmpty($aiResponse->json('suggested_reply'));

        // 5. Toggle Human Handoff
        $handoffResponse = $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/mobile/chat/{$conversation->id}/handoff", [
            'mode' => 'human',
        ]);
        $handoffResponse->assertOk();
        $this->assertFalse($handoffResponse->json('is_ai_active'));
    }

    public function test_mobile_in_app_calling_enforces_plan_entitlements(): void
    {
        // 1. Pro plan with voice enabled
        $callResponse = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/mobile/calls/initiate', [
            'phone_number' => '+919876543210',
        ]);
        $callResponse->assertOk();
        $callResponse->assertJsonStructure(['call_id', 'from_number', 'to_number', 'webrtc_session_token']);

        // 2. WhatsApp-only workspace without voice entitlement
        $waOnlyPlan = Plan::create([
            'name' => 'WhatsApp Only Plan',
            'slug' => 'wa-only',
            'currency_code' => 'INR',
            'price_cents' => 2900,
            'is_active' => true,
            'features' => [
                'whatsapp_api' => true,
                'voice_calling' => false,
            ],
        ]);

        Subscription::where('workspace_id', $this->workspace->id)->update(['plan_id' => $waOnlyPlan->id]);
        $this->workspace->update(['service_type' => 'whatsapp_only']);
        \App\Services\Billing\EntitlementService::clearCache();

        $blockedResponse = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/mobile/calls/initiate', [
            'phone_number' => '+919876543210',
        ]);
        $blockedResponse->assertStatus(403);
        $this->assertEquals('upgrade_required', $blockedResponse->json('error'));
    }

    public function test_mobile_360_customer_profile_aggregates_whatsapp_and_voice(): void
    {
        $contact = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Priya',
            'last_name' => 'Sharma',
            'phone_e164' => '+919876500000',
        ]);

        $conversation = Conversation::create([
            'workspace_id' => $this->workspace->id,
            'contact_id' => $contact->id,
            'channel' => 'whatsapp',
        ]);

        VoiceCall::create([
            'workspace_id' => $this->workspace->id,
            'contact_id' => $contact->id,
            'direction' => 'inbound',
            'from_number' => '+919876500000',
            'to_number' => '+12025550199',
            'duration_sec' => 312,
            'status' => 'completed',
            'summary' => 'Inquired about annual subscription discount.',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->getJson("/api/v1/mobile/chat/{$conversation->id}");
        $response->assertOk();
        $profile = $response->json('customer_profile');
        $this->assertStringContainsString('Priya', $profile['name']);
        $this->assertCount(1, $profile['recent_calls']);
        $this->assertEquals('05:12', $profile['recent_calls'][0]['formatted_duration']);
    }

    public function test_apk_download_endpoint_increments_download_counter_and_delivers_file(): void
    {
        $release = AppRelease::create([
            'platform' => 'android',
            'version' => '1.0.0',
            'version_code' => 100,
            'file_size_mb' => 28.50,
            'download_count' => 5,
            'is_active' => true,
        ]);

        $response = $this->get('/download/android-apk');
        $response->assertOk();
        $this->assertEquals('application/vnd.android.package-archive', $response->headers->get('Content-Type'));

        $release->refresh();
        $this->assertEquals(6, $release->download_count);
    }

    public function test_admin_can_update_apk_release_configuration(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin, 'admin')->post('/admin/app-management/android', [
            'version' => '1.1.0',
            'version_code' => 110,
            'min_supported_version' => '1.0.0',
            'file_size_mb' => 31.20,
            'release_notes' => 'New VoIP audio codecs and faster WhatsApp sync.',
            'force_update_required' => true,
            'is_active' => true,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('app_releases', [
            'version' => '1.1.0',
            'version_code' => 110,
            'force_update_required' => true,
        ]);
    }

    public function test_user_settings_page_renders_android_app_download_card(): void
    {
        $response = $this->actingAs($this->user)->get('/app/settings');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('client/Settings/Index')
            ->has('android_app')
            ->where('android_app.version', '1.0.0')
        );
    }

    public function test_mobile_qr_code_endpoint_returns_svg(): void
    {
        $response = $this->get('/download/android-apk/qr');
        $response->assertOk();
        $this->assertEquals('image/svg+xml', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('<svg', $response->getContent());
    }
}
