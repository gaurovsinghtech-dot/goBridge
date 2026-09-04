<?php

namespace Tests\Feature;

use App\Models\OnboardingStep;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\EntitlementService;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanEntitlementAndOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => 'client',
            'client_role' => 'administrator',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->workspace = Workspace::create([
            'owner_id' => $this->user->id,
            'name' => 'Acme Test Corp',
            'service_type' => 'whatsapp_only',
            'timezone' => 'Asia/Kolkata',
            'currency_code' => 'INR',
        ]);

        $this->user->update([
            'workspace_id' => $this->workspace->id,
            'current_workspace_id' => $this->workspace->id,
        ]);
    }

    /**
     * Test Scenario A: WhatsApp-only service selection & 6-step dynamic pipeline
     */
    public function test_whatsapp_only_onboarding_pipeline_has_six_steps_and_skips_telephony(): void
    {
        $onboardingService = app(OnboardingService::class);

        // Select WhatsApp only via API
        $response = $this->actingAs($this->user)
            ->postJson(route('client.onboarding.service'), [
                'service_type' => 'whatsapp_only',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'service_type' => 'whatsapp_only',
            ]);

        $this->workspace->refresh();
        $this->assertSame('whatsapp_only', $this->workspace->service_type);

        $progress = $onboardingService->getProgress($this->user);
        $stepKeys = array_column($progress['steps'], 'key');

        $this->assertSame('whatsapp_only', $progress['service_type']);
        $this->assertCount(6, $progress['steps']);
        $this->assertSame(['account', 'choose_service', 'whatsapp', 'ai_agent', 'business', 'launch'], $stepKeys);

        // Ensure phone & calling are NOT in the active pipeline
        $this->assertNotContains('phone', $stepKeys);
        $this->assertNotContains('calling', $stepKeys);
    }

    /**
     * Test Scenario B: WhatsApp + Voice service selection & 9-step full pipeline
     */
    public function test_whatsapp_voice_onboarding_pipeline_includes_all_nine_steps(): void
    {
        $onboardingService = app(OnboardingService::class);

        $response = $this->actingAs($this->user)
            ->postJson(route('client.onboarding.service'), [
                'service_type' => 'whatsapp_voice',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'service_type' => 'whatsapp_voice',
            ]);

        $this->workspace->refresh();
        $this->assertSame('whatsapp_voice', $this->workspace->service_type);

        $progress = $onboardingService->getProgress($this->user);
        $stepKeys = array_column($progress['steps'], 'key');

        $this->assertSame('whatsapp_voice', $progress['service_type']);
        $this->assertCount(9, $progress['steps']);
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
    }

    /**
     * Test Backend Entitlement Protection: Voice routes are locked for WhatsApp-only tier
     */
    public function test_voice_routes_are_locked_when_workspace_has_no_voice_entitlement(): void
    {
        // Setup Starter plan with voice_calling = false
        $starterPlan = Plan::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'currency_code' => 'USD',
            'features' => [
                'whatsapp_api' => true,
                'voice_calling' => false,
                'ai_voice_agents' => false,
            ],
            'price_cents' => 0,
            'interval' => 'month',
        ]);

        Subscription::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'plan_id' => $starterPlan->id,
            'gateway' => 'manual',
            'status' => 'active',
            'starts_at' => now(),
        ]);

        $this->assertFalse(EntitlementService::can($this->workspace, 'voice_calling'));
        $this->assertTrue(EntitlementService::can($this->workspace, 'whatsapp_api'));

        // Direct web request to /app/voice/call-center is redirected with upgrade flash
        $response = $this->actingAs($this->user)
            ->get(route('client.voice.call-center'));

        $response->assertRedirect(route('client.pricing'));
        $response->assertSessionHas('upgrade_required', true);

        // Direct JSON/API request returns 403 Forbidden
        $apiResponse = $this->actingAs($this->user)
            ->getJson(route('client.voice.call-center'));

        $apiResponse->assertStatus(403)
            ->assertJson([
                'error' => 'Upgrade Required',
                'feature' => 'voice_calling',
            ]);
    }

    /**
     * Test Scenario C: Seamless Upgrade Flow unlocks Voice features immediately
     */
    public function test_upgrading_plan_to_pro_unlocks_voice_features_immediately(): void
    {
        $proPlan = Plan::create([
            'name' => 'Pro Plan',
            'slug' => 'pro',
            'currency_code' => 'USD',
            'features' => [
                'whatsapp_api' => true,
                'voice_calling' => true,
                'ai_voice_agents' => true,
            ],
            'price_cents' => 2900,
            'interval' => 'month',
        ]);

        $subscription = Subscription::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'plan_id' => $proPlan->id,
            'gateway' => 'stripe',
            'status' => 'active',
            'starts_at' => now(),
        ]);

        $this->assertTrue(EntitlementService::can($this->workspace, 'voice_calling'));
        $this->assertTrue(EntitlementService::can($this->workspace, 'ai_voice_agents'));
        $this->assertTrue($this->workspace->hasEntitlement('voice_calling'));
        $this->assertTrue($this->user->hasEntitlement('voice_calling'));

        // Pro workspace can access voice routes without being blocked
        $response = $this->actingAs($this->user)
            ->get(route('client.voice.call-center'));

        $response->assertOk();
    }
}
