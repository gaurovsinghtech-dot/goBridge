<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProviderAccount;
use App\Models\ServicePrice;
use App\Models\UsageRecord;
use App\Models\Wallet;
use App\Services\Billing\ProviderLedgerService;
use App\Services\Billing\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProviderBillingController extends Controller
{
    public function __construct(
        protected ProviderLedgerService $ledgerService,
        protected WalletService $walletService
    ) {}

    /**
     * Admin Provider Cost & Revenue Analytics Dashboard.
     */
    public function index(): Response
    {
        $financials = $this->ledgerService->getFinancialOverview();
        $providerAccounts = $this->ledgerService->getProviderAccounts();
        $pricingRules = ServicePrice::all()->map(function (ServicePrice $p) {
            return [
                'id' => $p->id,
                'service' => $p->service,
                'provider' => $p->provider,
                'unit' => $p->unit,
                'provider_cost_cents' => $p->provider_cost_cents,
                'provider_cost_formatted' => '₹'.number_format($p->provider_cost_cents / 100, 2),
                'customer_price_cents' => $p->customer_price_cents,
                'customer_price_formatted' => '₹'.number_format($p->customer_price_cents / 100, 2),
                'margin_formatted' => '₹'.number_format($p->margin_cents / 100, 2),
                'margin_percent' => $p->margin_percent,
                'currency' => $p->currency,
                'is_active' => $p->is_active,
            ];
        });

        // Top Customer Usage Ledgers
        $recentUsage = UsageRecord::with('workspace')
            ->latest('recorded_at')
            ->take(30)
            ->get()
            ->map(function (UsageRecord $u) {
                return [
                    'id' => $u->id,
                    'workspace_name' => $u->workspace?->name ?? 'Unknown Workspace',
                    'service' => $u->service,
                    'provider' => strtoupper($u->provider),
                    'connection_model' => $u->connection_model,
                    'quantity' => (float) $u->quantity.' '.$u->unit,
                    'provider_cost_formatted' => '₹'.number_format($u->provider_cost_cents / 100, 2),
                    'customer_charge_formatted' => '₹'.number_format($u->customer_charge_cents / 100, 2),
                    'gross_margin_formatted' => '₹'.number_format($u->gross_margin_cents / 100, 2),
                    'recorded_at' => $u->recorded_at->format('M d, Y H:i'),
                ];
            });

        // Fetch system OpenAI integration summary
        $config = \App\Modules\Integrations\Models\IntegrationConfig::where('provider', 'llm_engine')->first();
        $creds = $config?->credentials ?? [];
        $apiKey = !empty($creds['openai_api_key']) ? $creds['openai_api_key'] : env('OPENAI_API_KEY');
        $month = now()->startOfMonth()->format('Y-m-d');
        $totalRecordedTokens = (int) \App\Models\WorkspaceUsage::whereDate('period_month', $month)->sum('ai_tokens_count');

        $openaiSummary = [
            'has_key' => !empty($apiKey),
            'masked_key' => !empty($apiKey) ? (substr($apiKey, 0, 7) . '...' . substr($apiKey, -4)) : 'Not Configured',
            'recorded_tokens_this_month' => $totalRecordedTokens,
        ];

        return Inertia::render('Admin/Billing/ProviderCostLedger', [
            'financials' => $financials,
            'provider_accounts' => $providerAccounts,
            'pricing_rules' => $pricingRules,
            'recent_usage' => $recentUsage,
            'openai_summary' => $openaiSummary,
        ]);
    }

    /**
     * Fetch Live Usage & Status directly from OpenAI API using system configured API Key.
     */
    public function fetchOpenAiUsage(Request $request): \Illuminate\Http\JsonResponse
    {
        $config = \App\Modules\Integrations\Models\IntegrationConfig::where('provider', 'llm_engine')->first();
        $creds = $config?->credentials ?? [];
        $apiKey = !empty($creds['openai_api_key']) ? $creds['openai_api_key'] : env('OPENAI_API_KEY');

        if (empty($apiKey)) {
            return response()->json([
                'ok' => false,
                'message' => 'No OpenAI API key is configured in System Integrations or .env file.',
            ], 422);
        }

        try {
            // Test connection / list models directly from OpenAI API platform
            $modelsResp = \Illuminate\Support\Facades\Http::withToken($apiKey)
                ->timeout(10)
                ->get('https://api.openai.com/v1/models');

            if (!$modelsResp->successful()) {
                $err = $modelsResp->json()['error']['message'] ?? 'OpenAI API authentication failed.';
                return response()->json(['ok' => false, 'message' => $err], 400);
            }

            // Get total tokens recorded in app database for current month
            $month = now()->startOfMonth()->format('Y-m-d');
            $totalRecordedTokens = (int) \App\Models\WorkspaceUsage::whereDate('period_month', $month)->sum('ai_tokens_count');
            $totalRequests = (int) \App\Models\WorkspaceUsage::whereDate('period_month', $month)->sum('ai_requests_count');

            $maskedKey = substr($apiKey, 0, 7) . '...' . substr($apiKey, -4);
            $models = $modelsResp->json('data') ?? [];
            $sampleModels = array_slice(array_column($models, 'id'), 0, 8);

            return response()->json([
                'ok' => true,
                'message' => 'Successfully connected to OpenAI platform API.',
                'api_key' => $maskedKey,
                'recorded_tokens_this_month' => $totalRecordedTokens,
                'recorded_requests_this_month' => $totalRequests,
                'models_count' => count($models),
                'sample_models' => $sampleModels,
                'openai_status' => 'Operational',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Failed to reach OpenAI platform: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update Service Pricing Rule.
     */
    public function updatePricingRule(Request $request, ServicePrice $rule): RedirectResponse
    {
        $validated = $request->validate([
            'provider_cost_rupees' => ['required', 'numeric', 'min:0'],
            'customer_price_rupees' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $rule->update([
            'provider_cost_cents' => (int) round($validated['provider_cost_rupees'] * 100),
            'customer_price_cents' => (int) round($validated['customer_price_rupees'] * 100),
            'is_active' => $validated['is_active'] ?? $rule->is_active,
        ]);

        return back()->with('success', 'Pricing rule updated successfully.');
    }

    /**
     * Adjust Customer Wallet Balance.
     */
    public function adjustWallet(Request $request, Wallet $wallet): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:credit,debit'],
            'amount_rupees' => ['required', 'numeric', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $amountCents = (int) round($validated['amount_rupees'] * 100);

        if ($validated['type'] === 'credit') {
            $this->walletService->deposit(
                $wallet->workspace_id,
                $amountCents,
                'Admin Balance Adjustment: '.$validated['reason'],
                'admin_adjustment',
                null,
                ['category' => 'adjustment'],
                $request->user()->id
            );
        } else {
            $this->walletService->deduct(
                $wallet->workspace_id,
                $amountCents,
                'adjustment',
                'Admin Balance Adjustment: '.$validated['reason'],
                'admin_adjustment',
                null,
                ['reason' => $validated['reason']]
            );
        }

        return back()->with('success', 'Customer wallet adjusted successfully.');
    }
}
