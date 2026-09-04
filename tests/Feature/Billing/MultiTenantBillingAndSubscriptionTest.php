<?php

namespace Tests\Feature\Billing;

use App\Models\AdminUser;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\Shared\Models\Contact;
use App\Services\Billing\Contracts\PaymentProviderInterface;
use App\Services\Billing\EntitlementService;
use App\Services\Billing\Gateways\RazorpayGateway;
use App\Services\Billing\SubscriptionService;
use App\Services\Billing\UsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MultiTenantBillingAndSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspaceA;
    private Workspace $workspaceB;
    private User $userA;
    private User $userB;
    private Plan $starterPlan;
    private Plan $growthPlan;
    private Plan $businessPlan;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
        Storage::fake('local');
        EntitlementService::clearCache();

        // 1. Create Standard Plans (Starter, Growth, Business)
        $this->starterPlan = Plan::create([
            'name' => 'Starter Plan',
            'slug' => 'starter',
            'description' => 'For early-stage startups and small businesses',
            'currency_code' => 'INR',
            'price_cents' => 99900,
            'monthly_price_cents' => 99900,  // ₹999
            'yearly_price_cents' => 999000,  // ₹9,990
            'trial_days' => 14,
            'enabled' => true,
            'features' => [
                'crm' => true,
                'whatsapp' => true,
                'email_marketing' => false,
                'ai_agent' => false,
                'knowledge_base' => false,
                'advanced_automation' => false,
                'crm_integrations' => false,
                'calling' => false,
                'advanced_analytics' => false,
            ],
            'limits' => [
                'contacts' => 500,
                'whatsapp_messages' => 1000,
                'ai_messages' => 0,
                'email_sends' => 0,
                'storage_mb' => 200,
                'automation_executions' => 10,
            ],
        ]);

        $this->growthPlan = Plan::create([
            'name' => 'Growth Plan',
            'slug' => 'growth',
            'description' => 'For growing teams requiring AI & automations',
            'currency_code' => 'INR',
            'price_cents' => 249900,
            'monthly_price_cents' => 249900, // ₹2,499
            'yearly_price_cents' => 2499000, // ₹24,990
            'trial_days' => 14,
            'enabled' => true,
            'features' => [
                'crm' => true,
                'whatsapp' => true,
                'email_marketing' => true,
                'ai_agent' => true,
                'knowledge_base' => true,
                'advanced_automation' => true,
                'crm_integrations' => false,
                'calling' => false,
                'advanced_analytics' => true,
            ],
            'limits' => [
                'contacts' => 5000,
                'whatsapp_messages' => 10000,
                'ai_messages' => 1000,
                'email_sends' => 10000,
                'storage_mb' => 2000,
                'automation_executions' => 200,
            ],
        ]);

        $this->businessPlan = Plan::create([
            'name' => 'Business Plan',
            'slug' => 'business',
            'description' => 'Full all-in-one suite with telephony & CRM integrations',
            'currency_code' => 'INR',
            'price_cents' => 499900,
            'monthly_price_cents' => 499900, // ₹4,999
            'yearly_price_cents' => 4999000, // ₹49,990
            'trial_days' => 14,
            'enabled' => true,
            'features' => [
                'crm' => true,
                'whatsapp' => true,
                'email_marketing' => true,
                'ai_agent' => true,
                'knowledge_base' => true,
                'advanced_automation' => true,
                'crm_integrations' => true,
                'calling' => true,
                'advanced_analytics' => true,
            ],
            'limits' => [
                'contacts' => 50000,
                'whatsapp_messages' => 50000,
                'ai_messages' => 10000,
                'email_sends' => 50000,
                'storage_mb' => 10000,
                'automation_executions' => 1000,
                'voice_minutes' => 500,
            ],
        ]);

        // 2. Setup Tenants Workspace A and Workspace B
        $clientA = Client::create(['name' => 'Acme Corp', 'status' => 'active']);
        $this->workspaceA = Workspace::create([
            'client_id' => $clientA->id,
            'name' => 'Acme Workspace',
            'industry' => 'SaaS',
            'currency_code' => 'INR',
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

        $clientB = Client::create(['name' => 'Beta Corp', 'status' => 'active']);
        $this->workspaceB = Workspace::create([
            'client_id' => $clientB->id,
            'name' => 'Beta Workspace',
            'industry' => 'Retail',
            'currency_code' => 'INR',
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
    }

    public function test_trial_creation_and_lifecycle(): void
    {
        $service = app(SubscriptionService::class);
        $subscription = $service->startTrial($this->workspaceA, $this->starterPlan);

        $this->assertEquals('trial', $subscription->status);
        $this->assertTrue($subscription->isTrialing());
        $this->assertTrue($subscription->isActive());
        $this->assertEquals(14, $subscription->getTrialDaysRemaining());
        $this->assertEquals($this->starterPlan->id, $subscription->plan_id);
    }

    public function test_plan_upgrade_and_downgrade_validation(): void
    {
        $service = app(SubscriptionService::class);

        // Populate 1000 contacts in Workspace A
        for ($i = 0; $i < 10; $i++) {
            Contact::create([
                'workspace_id' => $this->workspaceA->id,
                'first_name' => 'Contact ' . $i,
                'phone' => '+9198765432' . $i,
                'email' => "contact{$i}@test.com",
            ]);
        }

        // Downgrade check should pass when within limits
        $check = $service->validateDowngrade($this->workspaceA, $this->starterPlan);
        $this->assertTrue($check['allowed']);

        // If target plan limit was 5 contacts, downgrade must be rejected
        $restrictedPlan = Plan::create([
            'name' => 'Mini Plan',
            'slug' => 'mini',
            'currency_code' => 'INR',
            'price_cents' => 100,
            'limits' => ['contacts' => 5],
        ]);

        $checkRestricted = $service->validateDowngrade($this->workspaceA, $restrictedPlan);
        $this->assertFalse($checkRestricted['allowed']);
        $this->assertNotNull($checkRestricted['reason']);
    }

    public function test_successful_payment_activates_subscription_and_generates_invoice(): void
    {
        $service = app(SubscriptionService::class);
        $paymentId = 'pay_test_' . uniqid();
        $amount = 249900; // ₹2,499

        $sub = $service->activatePaidSubscription(
            $this->workspaceA,
            $this->growthPlan,
            'monthly',
            'razorpay',
            $paymentId,
            $amount
        );

        $this->assertEquals('active', $sub->status);
        $this->assertEquals($this->growthPlan->id, $sub->plan_id);
        $this->assertNotNull($sub->current_period_end);

        // Verify Invoice creation with tax
        $invoice = Invoice::where('workspace_id', $this->workspaceA->id)->latest('id')->first();
        $this->assertNotNull($invoice);
        $this->assertEquals('paid', $invoice->status);
        $this->assertEquals($amount, $invoice->total_cents);
        $this->assertEquals('razorpay', $invoice->payment_method);
    }

    public function test_failed_payment_webhook_marks_subscription_past_due(): void
    {
        $sub = Subscription::create([
            'workspace_id' => $this->workspaceA->id,
            'user_id' => $this->userA->id,
            'plan_id' => $this->growthPlan->id,
            'status' => 'active',
            'gateway' => 'manual',
            'billing_cycle' => 'monthly',
        ]);

        $gateway = app(RazorpayGateway::class);
        $payload = [
            'event' => 'payment.failed',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => 'pay_failed_123',
                        'notes' => ['workspace_id' => $this->workspaceA->id],
                    ],
                ],
            ],
        ];

        $gateway->handleWebhook($payload, []);

        $this->assertEquals('past_due', $sub->fresh()->status);
    }

    public function test_subscription_renewal_extends_billing_period(): void
    {
        $sub = Subscription::create([
            'workspace_id' => $this->workspaceA->id,
            'user_id' => $this->userA->id,
            'plan_id' => $this->growthPlan->id,
            'status' => 'active',
            'gateway' => 'razorpay',
            'gateway_subscription_id' => 'sub_rzp_renew123',
            'billing_cycle' => 'monthly',
            'current_period_end' => now()->subDay(),
        ]);

        $gateway = app(RazorpayGateway::class);
        $payload = [
            'event' => 'subscription.charged',
            'payload' => [
                'subscription' => [
                    'entity' => [
                        'id' => 'sub_rzp_renew123',
                    ],
                ],
            ],
        ];

        $gateway->handleWebhook($payload, []);

        $updated = $sub->fresh();
        $this->assertEquals('active', $updated->status);
        $this->assertTrue($updated->current_period_end->isFuture());
    }

    public function test_subscription_cancellation_retains_access_until_period_ends(): void
    {
        $futureDate = now()->addDays(20);
        $sub = Subscription::create([
            'workspace_id' => $this->workspaceA->id,
            'user_id' => $this->userA->id,
            'plan_id' => $this->growthPlan->id,
            'status' => 'active',
            'gateway' => 'manual',
            'current_period_end' => $futureDate,
            'ends_at' => $futureDate,
        ]);

        $gateway = app(RazorpayGateway::class);
        $gateway->cancelSubscription($sub);

        $freshSub = $sub->fresh();
        $this->assertEquals('cancelled', $freshSub->status);
        $this->assertNotNull($freshSub->cancelled_at);
        $this->assertTrue($freshSub->ends_at->isFuture());
    }

    public function test_webhook_replay_protection_via_idempotency(): void
    {
        $gateway = app(RazorpayGateway::class);
        $eventId = 'evt_test_dedup_123';

        $payload = [
            'id' => $eventId,
            'event' => 'payment.captured',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => 'pay_dedup_test',
                        'amount' => 99900,
                        'notes' => ['workspace_id' => $this->workspaceA->id, 'plan_id' => $this->starterPlan->id],
                    ],
                ],
            ],
        ];

        // 1st delivery
        $res1 = $gateway->handleWebhook($payload, ['x-razorpay-event-id' => $eventId]);
        $this->assertTrue($res1['success']);

        // 2nd delivery with identical event ID
        $res2 = $gateway->handleWebhook($payload, ['x-razorpay-event-id' => $eventId]);
        $this->assertTrue($res2['success']);
        $this->assertStringContainsString('idempotent', $res2['message']);
    }

    public function test_feature_entitlements_gating_backend_apis(): void
    {
        // Workspace A has Starter Plan (No calling or CRM integrations)
        Subscription::create([
            'workspace_id' => $this->workspaceA->id,
            'user_id' => $this->userA->id,
            'plan_id' => $this->starterPlan->id,
            'status' => 'active',
            'gateway' => 'manual',
            'billing_cycle' => 'monthly',
        ]);
        EntitlementService::clearCache();

        $this->assertTrue(EntitlementService::can($this->workspaceA, 'crm'));
        $this->assertTrue(EntitlementService::can($this->workspaceA, 'whatsapp'));
        $this->assertFalse(EntitlementService::can($this->workspaceA, 'calling'));
        $this->assertFalse(EntitlementService::can($this->workspaceA, 'crm_integrations'));

        // Workspace B has Business Plan (Has calling and CRM integrations)
        Subscription::create([
            'workspace_id' => $this->workspaceB->id,
            'user_id' => $this->userB->id,
            'plan_id' => $this->businessPlan->id,
            'status' => 'active',
            'gateway' => 'manual',
            'billing_cycle' => 'monthly',
        ]);
        EntitlementService::clearCache();

        $this->assertTrue(EntitlementService::can($this->workspaceB, 'calling'));
        $this->assertTrue(EntitlementService::can($this->workspaceB, 'crm_integrations'));
    }

    public function test_usage_limit_enforcement_and_rejection(): void
    {
        Subscription::create([
            'workspace_id' => $this->workspaceA->id,
            'user_id' => $this->userA->id,
            'plan_id' => $this->starterPlan->id, // Starter limits: 10 automations
            'status' => 'active',
            'gateway' => 'manual',
        ]);

        $usageService = app(UsageService::class);

        // Record 10 automation executions
        $usageService->recordUsage($this->workspaceA, 'automation_runs', 10);

        $quota = $usageService->checkQuota($this->workspaceA, 'automation_runs');
        $this->assertEquals(10, $quota['current']);
        $this->assertEquals(10, $quota['max']);
        $this->assertFalse($usageService->canConsume($this->workspaceA, 'automation_runs', 1));
    }

    public function test_usage_warning_thresholds_at_80_90_100_percent(): void
    {
        Subscription::create([
            'workspace_id' => $this->workspaceA->id,
            'user_id' => $this->userA->id,
            'plan_id' => $this->starterPlan->id, // WhatsApp messages limit: 1000
            'status' => 'active',
            'gateway' => 'manual',
        ]);

        $usageService = app(UsageService::class);

        // 80% usage
        $usageService->recordUsage($this->workspaceA, 'whatsapp_messages', 800);
        $check80 = $usageService->checkQuota($this->workspaceA, 'whatsapp_messages');
        $this->assertEquals(80, $check80['threshold_level']);

        // 90% usage
        $usageService->recordUsage($this->workspaceA, 'whatsapp_messages', 100);
        $check90 = $usageService->checkQuota($this->workspaceA, 'whatsapp_messages');
        $this->assertEquals(90, $check90['threshold_level']);

        // 100% usage
        $usageService->recordUsage($this->workspaceA, 'whatsapp_messages', 100);
        $check100 = $usageService->checkQuota($this->workspaceA, 'whatsapp_messages');
        $this->assertEquals(100, $check100['threshold_level']);
    }

    public function test_expired_subscription_blocks_features_without_deleting_customer_data(): void
    {
        // Add 5 contacts
        for ($i = 0; $i < 5; $i++) {
            Contact::create([
                'workspace_id' => $this->workspaceA->id,
                'first_name' => 'Contact ' . $i,
                'phone' => '+9198765432' . $i,
            ]);
        }

        // Expire subscription
        Subscription::create([
            'workspace_id' => $this->workspaceA->id,
            'user_id' => $this->userA->id,
            'plan_id' => $this->starterPlan->id,
            'status' => 'expired',
            'gateway' => 'manual',
            'ends_at' => now()->subDays(5),
        ]);
        EntitlementService::clearCache();

        // Ensure data is NEVER deleted
        $this->assertEquals(5, Contact::where('workspace_id', $this->workspaceA->id)->count());
    }

    public function test_cross_workspace_billing_and_invoice_isolation(): void
    {
        $invoiceA = Invoice::create([
            'workspace_id' => $this->workspaceA->id,
            'user_id' => $this->userA->id,
            'invoice_number' => 'INV-ACME-001',
            'amount_cents' => 10000,
            'tax_cents' => 1800,
            'total_cents' => 11800,
            'status' => 'paid',
        ]);

        $invoiceB = Invoice::create([
            'workspace_id' => $this->workspaceB->id,
            'user_id' => $this->userB->id,
            'invoice_number' => 'INV-BETA-001',
            'amount_cents' => 20000,
            'tax_cents' => 3600,
            'total_cents' => 23600,
            'status' => 'paid',
        ]);

        // User A downloading Invoice A -> OK (200)
        $responseA = $this->actingAs($this->userA)
            ->get(route('client.billing.invoice.download', $invoiceA->id));
        $responseA->assertOk();

        // User A attempting to download Invoice B -> 403 Forbidden
        $responseForbidden = $this->actingAs($this->userA)
            ->get(route('client.billing.invoice.download', $invoiceB->id));
        $responseForbidden->assertForbidden();
    }
}
