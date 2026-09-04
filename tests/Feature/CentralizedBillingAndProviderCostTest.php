<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Client;
use App\Models\Permission;
use App\Models\ProviderAccount;
use App\Models\Role;
use App\Models\ServicePrice;
use App\Models\UsageRecord;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Workspace;
use App\Services\Billing\ProviderLedgerService;
use App\Services\Billing\UsageBillingService;
use App\Services\Billing\WalletService;
use Database\Seeders\CentralizedBillingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CentralizedBillingAndProviderCostTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Workspace $workspace;
    protected Client $client;
    protected AdminUser $adminUser;
    protected WalletService $walletService;
    protected UsageBillingService $usageBillingService;
    protected ProviderLedgerService $providerLedgerService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CentralizedBillingSeeder::class);

        $this->client = Client::create([
            'name' => 'Acme Enterprise',
            'email' => 'billing@acme.com',
            'status' => 'active',
        ]);

        $this->user = User::create([
            'name' => 'Jane Billing',
            'email' => 'jane@acme.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_CLIENT,
            'client_id' => $this->client->id,
            'email_verified_at' => now(),
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->workspace = Workspace::create([
            'name' => 'Acme Main Workspace',
            'client_id' => $this->client->id,
            'owner_user_id' => $this->user->id,
            'service_type' => 'whatsapp_voice',
        ]);

        $this->user->update(['workspace_id' => $this->workspace->id]);

        // Admin User with Super Admin role
        $this->adminUser = AdminUser::create([
            'name' => 'Super Admin',
            'email' => 'admin@growbridge.io',
            'password' => bcrypt('password'),
            'status' => 'ACTIVE',
        ]);

        $role = Role::firstOrCreate(
            ['key' => Role::KEY_SUPER_ADMIN],
            ['name' => 'Super Admin', 'is_system' => true]
        );
        $this->adminUser->roles()->sync([$role->id]);

        $this->walletService = app(WalletService::class);
        $this->usageBillingService = app(UsageBillingService::class);
        $this->providerLedgerService = app(ProviderLedgerService::class);
    }

    public function test_workspace_wallet_creation_and_deposit(): void
    {
        $wallet = $this->walletService->getWallet($this->workspace);
        $this->assertEquals(0, $wallet->balance_cents);
        $this->assertEquals(50000, $wallet->low_balance_threshold_cents);

        // Deposit ₹2,500 (250,000 paise/cents)
        $tx = $this->walletService->deposit(
            $this->workspace,
            250000,
            'Account Top-up',
            'manual',
            null,
            ['category' => 'topup'],
            $this->user->id
        );

        $this->assertEquals('credit', $tx->type);
        $this->assertEquals(250000, $tx->amount_cents);
        $this->assertEquals(250000, $tx->balance_after_cents);
        $this->assertEquals(250000, $this->walletService->getBalance($this->workspace));
        $this->assertTrue($this->walletService->hasSufficientBalance($this->workspace, 100000));
    }

    public function test_metered_usage_records_provider_cost_and_deducts_customer_wallet(): void
    {
        // 1. Initial Deposit of ₹1,000 (100,000 paise)
        $this->walletService->deposit($this->workspace, 100000, 'Initial Top-up');

        // 2. Record WhatsApp Marketing Campaign Usage (100 messages)
        // Meta cost = 78 paise * 100 = 7800 paise (₹78)
        // Customer charge = 105 paise * 100 = 10500 paise (₹105)
        // Gross margin = +₹27 (2700 paise)
        $record = $this->usageBillingService->recordUsage(
            $this->workspace,
            'whatsapp_marketing',
            'meta',
            100.0,
            'conversations',
            ['description' => 'Diwali Campaign 100 recipients']
        );

        $this->assertEquals(7800, $record->provider_cost_cents);
        $this->assertEquals(10500, $record->customer_charge_cents);
        $this->assertEquals(2700, $record->gross_margin_cents);
        $this->assertNotNull($record->wallet_transaction_id);

        // Wallet balance after deduction: 100,000 - 10,500 = 89,500 paise (₹895.00)
        $this->assertEquals(89500, $this->walletService->getBalance($this->workspace));

        $this->assertDatabaseHas('usage_records', [
            'workspace_id' => $this->workspace->id,
            'service' => 'whatsapp_marketing',
            'provider' => 'meta',
            'provider_cost_cents' => 7800,
            'customer_charge_cents' => 10500,
        ]);
    }

    public function test_customer_connected_byok_model_incurs_zero_provider_cost(): void
    {
        // Customer uses their own WhatsApp token
        $record = $this->usageBillingService->recordUsage(
            $this->workspace,
            'whatsapp_marketing',
            'meta',
            50.0,
            'conversations',
            ['connection_model' => 'customer_connected']
        );

        $this->assertEquals(0, $record->provider_cost_cents);
        $this->assertEquals(0, $record->customer_charge_cents);
        $this->assertEquals(0, $record->gross_margin_cents);
        $this->assertNull($record->wallet_transaction_id);
    }

    public function test_campaign_cost_preflight_estimator(): void
    {
        // Wallet has ₹500
        $this->walletService->deposit($this->workspace, 50000, 'Seed deposit');

        // Estimate 1,000 recipients -> 1000 * 105 paise = ₹1,050 (105000 paise)
        $estimate = $this->usageBillingService->estimateCampaignCost($this->workspace, 1000, 'whatsapp');

        $this->assertEquals(105000, $estimate['estimated_cost_cents']);
        $this->assertEquals(50000, $estimate['available_balance_cents']);
        $this->assertFalse($estimate['is_sufficient']);
        $this->assertEquals(55000, $estimate['shortfall_cents']); // ₹550 shortfall

        // Top up ₹1,000 more -> total ₹1,500
        $this->walletService->deposit($this->workspace, 100000, 'Second deposit');
        $newEstimate = $this->usageBillingService->estimateCampaignCost($this->workspace, 1000, 'whatsapp');
        $this->assertTrue($newEstimate['is_sufficient']);
        $this->assertEquals(0, $newEstimate['shortfall_cents']);
    }

    public function test_provider_ledger_service_calculates_margins_and_health(): void
    {
        $financials = $this->providerLedgerService->getFinancialOverview();
        $this->assertArrayHasKey('gross_revenue_cents', $financials);
        $this->assertArrayHasKey('total_provider_cost_cents', $financials);
        $this->assertArrayHasKey('gross_margin_cents', $financials);
        $this->assertArrayHasKey('gross_margin_percent', $financials);
        $this->assertGreaterThan(0, $financials['gross_margin_cents']);

        $accounts = $this->providerLedgerService->getProviderAccounts();
        $this->assertNotEmpty($accounts);
        $this->assertEquals('meta', $accounts[0]['provider']);
    }

    public function test_client_wallet_endpoints(): void
    {
        $response = $this->actingAs($this->user)->get(route('client.billing.wallet.index'));
        $response->assertOk();

        // Top-up
        $topupResponse = $this->actingAs($this->user)->post(route('client.billing.wallet.topup'), [
            'amount_in_rupees' => 1500,
        ]);
        $topupResponse->assertRedirect();
        $this->assertEquals(150000, $this->walletService->getBalance($this->workspace));

        // Settings update
        $settingsResponse = $this->actingAs($this->user)->post(route('client.billing.wallet.settings'), [
            'low_balance_threshold_rupees' => 750,
            'low_balance_alert_enabled' => true,
        ]);
        $settingsResponse->assertRedirect();
        $wallet = $this->walletService->getWallet($this->workspace);
        $this->assertEquals(75000, $wallet->low_balance_threshold_cents);
        $this->assertTrue($wallet->low_balance_alert_enabled);

        // Preflight estimate API
        $estimateResponse = $this->actingAs($this->user)->getJson(route('client.billing.estimate-campaign', [
            'recipient_count' => 500,
            'channel' => 'whatsapp',
        ]));
        $estimateResponse->assertOk();
        $estimateResponse->assertJsonStructure(['estimated_cost_cents', 'available_balance_cents', 'is_sufficient']);
    }

    public function test_admin_provider_billing_endpoints(): void
    {
        $response = $this->actingAs($this->adminUser, 'admin')->get(route('admin.billing.provider-costs.index'));
        $response->assertOk();

        // Update pricing rule
        $rule = ServicePrice::where('service', 'whatsapp_marketing')->first();
        $updateResponse = $this->actingAs($this->adminUser, 'admin')->put(route('admin.billing.pricing-rules.update', $rule->id), [
            'provider_cost_rupees' => 0.80,
            'customer_price_rupees' => 1.20,
            'is_active' => true,
        ]);
        $updateResponse->assertRedirect();

        $rule->refresh();
        $this->assertEquals(80, $rule->provider_cost_cents);
        $this->assertEquals(120, $rule->customer_price_cents);

        // Admin Adjust Wallet
        $wallet = $this->walletService->getWallet($this->workspace);
        $adjustResponse = $this->actingAs($this->adminUser, 'admin')->post(route('admin.billing.wallets.adjust', $wallet->id), [
            'type' => 'credit',
            'amount_rupees' => 500,
            'reason' => 'Complimentary credit for pilot test',
        ]);
        $adjustResponse->assertRedirect();
        $this->assertEquals(50000, $this->walletService->getBalance($this->workspace));
    }
}
