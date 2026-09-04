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

        return Inertia::render('Admin/Billing/ProviderCostLedger', [
            'financials' => $financials,
            'provider_accounts' => $providerAccounts,
            'pricing_rules' => $pricingRules,
            'recent_usage' => $recentUsage,
        ]);
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
