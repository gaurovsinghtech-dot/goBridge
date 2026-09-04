<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\Shared\Models\Contact;
use App\Modules\Voice\Models\VoiceAgent;
use App\Services\Billing\FeatureService;
use App\Services\Billing\Gateways\RazorpayGateway;
use App\Services\Billing\SubscriptionService;
use App\Services\Billing\UsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaaSBillingAndUsageTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private User $user;
    private Plan $starterPlan;
    private Plan $proPlan;

    protected function setUp(): void
    {
        parent::setUp();

        $ctx = $this->createWorkspaceContext();
        $this->user = $ctx['user'];
        $this->workspace = $ctx['workspace'];

        $this->starterPlan = Plan::create([
            'name' => 'Starter Plan',
            'slug' => 'starter',
            'price_cents' => 99900,
            'monthly_price_cents' => 99900,
            'yearly_price_cents' => 999000,
            'currency_code' => 'INR',
            'trial_days' => 14,
            'enabled' => true,
            'limits' => [
                'contacts' => 500,
                'ai_messages' => 100,
                'voice_calls' => 10,
                'ai_voice_agents' => 1,
            ],
            'features' => [
                'whatsapp' => true,
                'ai_agents' => true,
                'ai_voice_agents' => false,
            ],
        ]);

        $this->proPlan = Plan::create([
            'name' => 'Pro Growth Plan',
            'slug' => 'pro',
            'price_cents' => 299900,
            'monthly_price_cents' => 299900,
            'yearly_price_cents' => 2999000,
            'currency_code' => 'INR',
            'trial_days' => 14,
            'enabled' => true,
            'limits' => [
                'contacts' => 5000,
                'ai_messages' => 2000,
                'voice_calls' => 200,
                'ai_voice_agents' => 5,
            ],
            'features' => [
                'whatsapp' => true,
                'ai_agents' => true,
                'ai_voice_agents' => true,
                'twilio_voice' => true,
            ],
        ]);
    }

    public function test_feature_service_evaluates_plan_features_accurately(): void
    {
        // Subscribe to Starter (no voice agents)
        Subscription::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'plan_id' => $this->starterPlan->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'gateway' => 'manual',
        ]);

        $this->assertTrue(FeatureService::can($this->workspace, 'whatsapp'));
        $this->assertFalse(FeatureService::can($this->workspace, 'ai_voice_agents'));

        // Upgrade to Pro (voice agents enabled)
        $this->workspace->subscriptions()->update(['plan_id' => $this->proPlan->id]);

        $this->assertTrue(FeatureService::can($this->workspace, 'ai_voice_agents'));
        $this->assertTrue(FeatureService::can($this->workspace, 'twilio_voice'));
    }

    public function test_usage_service_tracks_quota_and_detects_limit_exceeded(): void
    {
        Subscription::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'plan_id' => $this->starterPlan->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'gateway' => 'manual',
        ]);

        $usageService = app(UsageService::class);

        // Initially 0 AI messages used
        $check = $usageService->checkQuota($this->workspace, 'ai_messages', 1);
        $this->assertTrue($check['allowed']);
        $this->assertEquals(100, $check['max']);

        // Record 100 messages
        $usageService->recordUsage($this->workspace, 'ai_messages', 100);

        // Next message should exceed limit
        $checkAfter = $usageService->checkQuota($this->workspace, 'ai_messages', 1);
        $this->assertFalse($checkAfter['allowed']);
        $this->assertStringContainsString('limit reached', $checkAfter['warning']);
    }

    public function test_usage_service_soft_limit_warnings_at_80_and_90_percent(): void
    {
        Subscription::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'plan_id' => $this->starterPlan->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'gateway' => 'manual',
        ]);

        $usageService = app(UsageService::class);

        // Record 85 messages (85% of 100)
        $usageService->recordUsage($this->workspace, 'ai_messages', 85);

        $check85 = $usageService->checkQuota($this->workspace, 'ai_messages', 1);
        $this->assertTrue($check85['allowed']);
        $this->assertStringContainsString('approaching your ai_messages usage limit', $check85['warning']);

        // Record 10 more (95% of 100)
        $usageService->recordUsage($this->workspace, 'ai_messages', 10);
        $check95 = $usageService->checkQuota($this->workspace, 'ai_messages', 1);
        $this->assertStringContainsString('90% of your ai_messages quota', $check95['warning']);
    }

    public function test_downgrade_validation_prevents_downgrading_when_usage_exceeds_limits(): void
    {
        $subscriptionService = app(SubscriptionService::class);

        // Add 600 contacts to workspace
        for ($i = 0; $i < 600; $i++) {
            Contact::create([
                'workspace_id' => $this->workspace->id,
                'first_name' => "Contact {$i}",
            ]);
        }

        // Try downgrading to Starter plan (limit: 500 contacts)
        $validation = $subscriptionService->validateDowngrade($this->workspace, $this->starterPlan);

        $this->assertFalse($validation['allowed']);
        $this->assertStringContainsString('exceeds the Starter Plan plan limit of 500 contacts', $validation['reason']);
    }

    public function test_razorpay_server_side_payment_verification_and_subscription_activation(): void
    {
        $gateway = new RazorpayGateway('test_key_id', 'test_secret_123');

        $orderId = 'order_test_998877';
        $paymentId = 'pay_test_112233';
        $signature = hash_hmac('sha256', $orderId.'|'.$paymentId, 'test_secret_123');

        $verified = $gateway->verifyPayment([
            'razorpay_order_id' => $orderId,
            'razorpay_payment_id' => $paymentId,
            'razorpay_signature' => $signature,
        ]);

        $this->assertTrue($verified);

        // Activate paid subscription
        $subscriptionService = app(SubscriptionService::class);
        $sub = $subscriptionService->activatePaidSubscription(
            $this->workspace,
            $this->proPlan,
            'monthly',
            'razorpay',
            $paymentId,
            299900
        );

        $this->assertEquals('active', $sub->status);
        $this->assertDatabaseHas('invoices', [
            'workspace_id' => $this->workspace->id,
            'plan_id' => $this->proPlan->id,
            'total_cents' => 299900,
            'status' => 'paid',
        ]);
    }

    public function test_trial_days_remaining_calculation(): void
    {
        $sub = Subscription::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'plan_id' => $this->starterPlan->id,
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(8),
            'gateway' => 'manual',
        ]);

        $this->assertTrue($sub->isTrialing());
        $this->assertEquals(8, $sub->getTrialDaysRemaining());
    }
}
