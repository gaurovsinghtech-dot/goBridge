<?php

namespace Tests\Feature;

use App\Models\CrmConnection;
use App\Models\OnboardingStep;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\AI\Models\AiChatbot;
use App\Modules\Automation\Models\Automation;
use App\Modules\Broadcasting\Models\Campaign;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Voice\Models\TelephonyPhoneNumber;
use App\Modules\Voice\Models\VoiceAgent;
use App\Modules\Whatsapp\Models\WhatsappBusinessAccount;
use App\Modules\Whatsapp\Models\WhatsappTemplate;
use App\Services\Billing\EntitlementService;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerJourneysEndToEndTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Complete Customer Journey 1: WhatsApp-only customer
     * Flow: Login -> Onboarding -> Choose WhatsApp API -> Connect WhatsApp -> Configure AI -> Business Profile -> Launch
     * Verify:
     * - No Twilio phone number, voice config, or voice agent required
     * - Completed steps show completed = true
     * - Campaigns, Automations, and AI Agents work seamlessly
     * - Voice endpoints are locked with HTTP 403 / Upgrade Required
     */
    public function test_whatsapp_only_customer_journey_end_to_end(): void
    {
        // 1. Sign Up / Register
        $registerResponse = $this->post('/register', [
            'name' => 'Rajesh Sharma',
            'email' => 'rajesh@company.in',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'agree_terms' => true,
        ]);
        $registerResponse->assertRedirect(route('client.dashboard', absolute: false));

        $user = User::where('email', 'rajesh@company.in')->firstOrFail();
        $this->assertAuthenticatedAs($user);

        // Ensure workspace is created
        $workspace = Workspace::where('owner_id', $user->id)->first() ?? Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Rajesh Tech Enterprises',
            'service_type' => 'whatsapp_only',
            'timezone' => 'Asia/Kolkata',
            'currency_code' => 'INR',
        ]);
        $user->update([
            'workspace_id' => $workspace->id,
            'current_workspace_id' => $workspace->id,
            'email_verified_at' => now(),
        ]);

        // Attach Starter Plan (WhatsApp-only tier)
        $starterPlan = Plan::firstOrCreate(
            ['slug' => 'starter'],
            [
                'name' => 'Starter Plan',
                'currency_code' => 'INR',
                'price_cents' => 0,
                'interval' => 'month',
                'features' => [
                    'whatsapp_api' => true,
                    'campaigns' => true,
                    'automations' => true,
                    'ai_agents' => true,
                    'voice_calling' => false,
                    'ai_voice_agents' => false,
                ],
            ]
        );

        Subscription::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'plan_id' => $starterPlan->id,
            'gateway' => 'manual',
            'status' => 'active',
            'starts_at' => now(),
        ]);

        // 2. Choose Service = whatsapp_only
        $serviceChoiceResponse = $this->actingAs($user)->postJson(route('client.onboarding.service'), [
            'service_type' => 'whatsapp_only',
        ]);
        $serviceChoiceResponse->assertOk()->assertJson(['success' => true, 'service_type' => 'whatsapp_only']);

        $onboardingService = app(OnboardingService::class);
        $progress = $onboardingService->getProgress($user);
        $stepKeys = array_column($progress['steps'], 'key');

        // Confirm 6-step flow without phone or calling
        $this->assertSame(['account', 'choose_service', 'whatsapp', 'ai_agent', 'business', 'launch'], $stepKeys);
        $this->assertNotContains('phone', $stepKeys);
        $this->assertNotContains('calling', $stepKeys);

        // 3. Connect WhatsApp (Meta API credentials)
        $connectWaResponse = $this->actingAs($user)->postJson(route('client.onboarding.whatsapp.connect'), [
            'waba_id' => 'WABA-99887766',
            'phone_number_id' => 'PNID-11223344',
            'phone_number' => '+919876543210',
        ]);
        $connectWaResponse->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('whatsapp_business_accounts', [
            'workspace_id' => $workspace->id,
            'waba_id' => 'WABA-99887766',
        ]);

        // 4. Configure AI Agent
        $aiAgentResponse = $this->actingAs($user)->postJson(route('client.onboarding.ai-agent'), [
            'name' => 'Growbridge WhatsApp Bot',
            'purpose' => 'sales',
            'language' => 'en',
            'tone' => 'professional',
            'welcome_message' => 'Hello! How can I assist you with your business needs today?',
        ]);
        $aiAgentResponse->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('ai_chatbots', [
            'workspace_id' => $workspace->id,
            'name' => 'Growbridge WhatsApp Bot',
            'enabled' => true,
        ]);

        // 5. Business Profile
        $businessResponse = $this->actingAs($user)->postJson(route('client.onboarding.business'), [
            'name' => 'Rajesh Tech Enterprises',
            'industry' => 'Software & SaaS',
            'website' => 'https://rajeshtech.in',
            'country' => 'India',
            'timezone' => 'Asia/Kolkata',
            'currency_code' => 'INR',
        ]);
        $businessResponse->assertOk()->assertJson(['success' => true]);

        // 6. Launch Account
        $launchResponse = $this->actingAs($user)->postJson(route('client.onboarding.launch'));
        $launchResponse->assertOk()->assertJson(['success' => true]);

        // Verify all 6 steps are marked completed with 100% progress
        $finalProgress = $onboardingService->getProgress($user);
        $this->assertEquals(100, $finalProgress['percent']);
        $this->assertEquals(6, $finalProgress['done']);
        $this->assertTrue($finalProgress['is_complete']);

        // 7. Verify Functional Features:
        // A. Campaign Creation Works
        $template = WhatsappTemplate::create([
            'workspace_id' => $workspace->id,
            'waba_id' => 'WABA-99887766',
            'name' => 'welcome_promo',
            'category' => 'MARKETING',
            'language' => 'en',
            'status' => 'APPROVED',
            'components' => [['type' => 'BODY', 'text' => 'Welcome to our store!']],
        ]);

        $campaign = Campaign::create([
            'workspace_id' => $workspace->id,
            'name' => 'Festive Broadcast 2026',
            'channel' => 'whatsapp',
            'status' => 'draft',
        ]);
        $this->assertDatabaseHas('campaigns', ['id' => $campaign->id, 'workspace_id' => $workspace->id]);

        // B. Automation Creation Works
        $automation = Automation::create([
            'workspace_id' => $workspace->id,
            'name' => 'Auto Welcome Flow',
            'trigger_type' => 'contact_created',
            'status' => 'active',
            'trigger_config' => ['source' => 'whatsapp'],
        ]);
        $this->assertDatabaseHas('automations', ['id' => $automation->id, 'workspace_id' => $workspace->id]);

        // C. AI Agent Works
        $this->assertDatabaseHas('ai_chatbots', [
            'workspace_id' => $workspace->id,
            'name' => 'Growbridge WhatsApp Bot',
            'enabled' => true,
        ]);

        // 8. Verify Voice is LOCKED (Backend & Frontend authorization)
        $this->assertFalse(EntitlementService::can($workspace, 'voice_calling'));
        $this->assertFalse(EntitlementService::can($workspace, 'ai_voice_agents'));

        // Direct web request to Voice Call Center redirects to Pricing with upgrade required
        $voiceWebResponse = $this->actingAs($user)->get(route('client.voice.call-center'));
        $voiceWebResponse->assertRedirect(route('client.pricing'));
        $voiceWebResponse->assertSessionHas('upgrade_required', true);

        // Direct API request to Voice Call Center returns 403 Forbidden
        $voiceApiResponse = $this->actingAs($user)->getJson(route('client.voice.call-center'));
        $voiceApiResponse->assertStatus(403)->assertJson([
            'error' => 'Upgrade Required',
            'feature' => 'voice_calling',
        ]);

        // Direct request to AI Voice Studio is also locked
        $studioResponse = $this->actingAs($user)->getJson(route('client.ai.voice-studio.index'));
        $studioResponse->assertStatus(403)->assertJson([
            'error' => 'Upgrade Required',
            'feature' => 'ai_voice_agents',
        ]);
    }

    /**
     * Complete Customer Journey 2: Voice-enabled customer
     * Flow: Register -> Activate Pro Plan -> Onboarding -> Choose WhatsApp+Voice -> Twilio Number -> WhatsApp -> Calling -> AI Voice Agent -> CRM -> Launch
     * Verify:
     * - All 9 onboarding steps complete
     * - Voice Calling, Call Center, AI Voice Studio, Twilio Numbers work without restriction
     */
    public function test_voice_enabled_customer_journey_end_to_end(): void
    {
        // 1. User Setup & Pro Plan Activation
        $user = User::factory()->create([
            'name' => 'Priya Patel',
            'email' => 'priya@voicecorp.com',
            'role' => 'client',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'VoiceCorp Global',
            'service_type' => 'whatsapp_voice',
            'timezone' => 'Asia/Kolkata',
            'currency_code' => 'INR',
        ]);
        $user->update([
            'workspace_id' => $workspace->id,
            'current_workspace_id' => $workspace->id,
        ]);

        $proPlan = Plan::firstOrCreate(
            ['slug' => 'pro'],
            [
                'name' => 'Pro Plan',
                'currency_code' => 'USD',
                'price_cents' => 2900,
                'interval' => 'month',
                'features' => [
                    'whatsapp_api' => true,
                    'campaigns' => true,
                    'automations' => true,
                    'ai_agents' => true,
                    'voice_calling' => true,
                    'ai_voice_agents' => true,
                ],
            ]
        );

        Subscription::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'plan_id' => $proPlan->id,
            'gateway' => 'stripe',
            'status' => 'active',
            'starts_at' => now(),
        ]);

        // 2. Choose Service = whatsapp_voice
        $serviceRes = $this->actingAs($user)->postJson(route('client.onboarding.service'), [
            'service_type' => 'whatsapp_voice',
        ]);
        $serviceRes->assertOk()->assertJson(['success' => true, 'service_type' => 'whatsapp_voice']);

        $onboardingService = app(OnboardingService::class);
        $progress = $onboardingService->getProgress($user);
        $stepKeys = array_column($progress['steps'], 'key');
        $this->assertSame([
            'account',
            'choose_service',
            'phone',
            'whatsapp',
            'calling',
            'ai_agent',
            'crm',
            'business',
            'launch',
        ], $stepKeys);

        // 3. Provision Virtual Twilio Line
        $provisionRes = $this->actingAs($user)->postJson(route('client.onboarding.numbers.provision'), [
            'phone_number' => '+919876500001',
            'country' => 'IN',
            'provider' => 'twilio',
        ]);
        $provisionRes->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('telephony_phone_numbers', [
            'workspace_id' => $workspace->id,
            'phone_number' => '+919876500001',
            'status' => 'connected',
        ]);

        // 4. Connect WhatsApp
        $waRes = $this->actingAs($user)->postJson(route('client.onboarding.whatsapp.connect'), [
            'waba_id' => 'WABA-VOICE-1122',
            'phone_number_id' => 'PNID-VOICE-3344',
            'phone_number' => '+919876500001',
        ]);
        $waRes->assertOk()->assertJson(['success' => true]);

        // 5. Configure Calling
        $callingRes = $this->actingAs($user)->postJson(route('client.onboarding.calling.configure'), [
            'phone_number' => '+919876500001',
        ]);
        $callingRes->assertOk()->assertJson(['success' => true]);

        // 6. Configure AI Agent
        $aiRes = $this->actingAs($user)->postJson(route('client.onboarding.ai-agent'), [
            'name' => 'Voice & Chat Assistant',
            'purpose' => 'support',
            'language' => 'en',
            'tone' => 'friendly',
        ]);
        $aiRes->assertOk()->assertJson(['success' => true]);

        // 7. Connect CRM (HubSpot or Skip)
        $crmRes = $this->actingAs($user)->postJson(route('client.onboarding.crm.save'), [
            'provider' => 'hubspot',
            'credentials' => ['access_token' => 'pat-live-token-12345'],
        ]);
        $crmRes->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('crm_connections', [
            'workspace_id' => $workspace->id,
            'provider' => 'hubspot',
            'status' => 'active',
        ]);

        // 8. Save Business Profile
        $bizRes = $this->actingAs($user)->postJson(route('client.onboarding.business'), [
            'name' => 'VoiceCorp Global',
            'industry' => 'Financial Services',
            'website' => 'https://voicecorp.com',
            'country' => 'India',
            'timezone' => 'Asia/Kolkata',
            'currency_code' => 'INR',
        ]);
        $bizRes->assertOk()->assertJson(['success' => true]);

        // 9. Launch Account
        $launchRes = $this->actingAs($user)->postJson(route('client.onboarding.launch'));
        $launchRes->assertOk()->assertJson(['success' => true]);

        $finalProgress = $onboardingService->getProgress($user);
        $this->assertEquals(100, $finalProgress['percent']);
        $this->assertEquals(9, $finalProgress['done']);
        $this->assertTrue($finalProgress['is_complete']);

        // 10. Verify Voice Features are FULLY UNLOCKED
        $this->assertTrue(EntitlementService::can($workspace, 'voice_calling'));
        $this->assertTrue(EntitlementService::can($workspace, 'ai_voice_agents'));

        // Call Center access
        $callCenterResponse = $this->actingAs($user)->get(route('client.voice.call-center'));
        $callCenterResponse->assertOk();

        // AI Voice Studio access
        $voiceStudioResponse = $this->actingAs($user)->get(route('client.ai.voice-studio.index'));
        $voiceStudioResponse->assertOk();

        // Voice Campaigns access
        $voiceCampResponse = $this->actingAs($user)->get(route('client.voice.campaigns.index'));
        $voiceCampResponse->assertOk();
    }

    /**
     * Plan Entitlement Authorization & Route Protection Verification:
     * - Starter: WhatsApp, Campaigns, Automations allowed; Voice locked.
     * - Growth: WhatsApp, Campaigns, Automations, AI Agents allowed; Voice locked.
     * - Pro: WhatsApp, Campaigns, Automations, AI Agents, Voice Calling, AI Voice Agents allowed.
     */
    public function test_plan_entitlements_and_tier_authorization_matrix(): void
    {
        $user = User::factory()->create([
            'role' => 'client',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Matrix Corp',
            'timezone' => 'UTC',
            'currency_code' => 'USD',
        ]);
        $user->update(['workspace_id' => $workspace->id]);

        // A. Test Starter Tier
        $starterPlan = Plan::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'currency_code' => 'USD',
            'price_cents' => 0,
            'interval' => 'month',
            'features' => [
                'whatsapp_api' => true,
                'campaigns' => true,
                'automations' => true,
                'ai_agents' => true,
                'voice_calling' => false,
                'ai_voice_agents' => false,
            ],
        ]);

        $sub = Subscription::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'plan_id' => $starterPlan->id,
            'gateway' => 'manual',
            'status' => 'active',
            'starts_at' => now(),
        ]);

        $this->assertTrue(EntitlementService::can($workspace, 'whatsapp_api'));
        $this->assertTrue(EntitlementService::can($workspace, 'campaigns'));
        $this->assertTrue(EntitlementService::can($workspace, 'automations'));
        $this->assertFalse(EntitlementService::can($workspace, 'voice_calling'));
        $this->assertFalse(EntitlementService::can($workspace, 'ai_voice_agents'));

        // Voice route is blocked
        $this->actingAs($user)->getJson(route('client.voice.call-center'))->assertStatus(403);

        // B. Upgrade to Growth Plan
        $growthPlan = Plan::create([
            'name' => 'Growth',
            'slug' => 'growth',
            'currency_code' => 'USD',
            'price_cents' => 1900,
            'interval' => 'month',
            'features' => [
                'whatsapp_api' => true,
                'campaigns' => true,
                'automations' => true,
                'ai_agents' => true,
                'voice_calling' => false,
                'ai_voice_agents' => false,
            ],
        ]);

        $sub->update(['plan_id' => $growthPlan->id]);
        $this->assertTrue(EntitlementService::can($workspace, 'whatsapp_api'));
        $this->assertTrue(EntitlementService::can($workspace, 'ai_agents'));
        $this->assertFalse(EntitlementService::can($workspace, 'voice_calling'));

        // C. Upgrade to Pro Plan
        $proPlan = Plan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'currency_code' => 'USD',
            'price_cents' => 4900,
            'interval' => 'month',
            'features' => [
                'whatsapp_api' => true,
                'campaigns' => true,
                'automations' => true,
                'ai_agents' => true,
                'voice_calling' => true,
                'ai_voice_agents' => true,
            ],
        ]);

        $sub->update(['plan_id' => $proPlan->id]);
        $this->assertTrue(EntitlementService::can($workspace, 'whatsapp_api'));
        $this->assertTrue(EntitlementService::can($workspace, 'ai_agents'));
        $this->assertTrue(EntitlementService::can($workspace, 'voice_calling'));
        $this->assertTrue(EntitlementService::can($workspace, 'ai_voice_agents'));

        // Voice route is now accessible
        $this->actingAs($user)->get(route('client.voice.call-center'))->assertOk();
    }
}
