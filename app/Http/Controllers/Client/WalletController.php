<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\UsageRecord;
use App\Models\WorkspaceUsage;
use App\Services\Billing\UsageBillingService;
use App\Services\Billing\WalletService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WalletController extends Controller
{
    public function __construct(
        protected WalletService $walletService,
        protected UsageBillingService $usageService
    ) {}

    /**
     * Display Client Wallet & Usage Breakdown.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $workspace = $user->activeWorkspace() ?? $user->workspace;
        $wallet = $this->walletService->getWallet($workspace);

        $transactions = $wallet->transactions()
            ->take(50)
            ->get()
            ->map(function ($tx) {
                return [
                    'id' => $tx->id,
                    'uuid' => $tx->uuid,
                    'type' => $tx->type,
                    'category' => $tx->category,
                    'amount_cents' => $tx->amount_cents,
                    'amount_formatted' => $tx->formattedAmount(),
                    'balance_after_formatted' => '₹'.number_format($tx->balance_after_cents / 100, 2),
                    'currency' => $tx->currency,
                    'description' => $tx->description,
                    'created_at' => $tx->created_at->format('M d, Y H:i'),
                ];
            });

        // Current Month Usage Breakdown
        $currentMonth = Carbon::now()->startOfMonth()->toDateString();
        $monthlyUsage = WorkspaceUsage::where('workspace_id', $workspace->id)
            ->where('period_month', $currentMonth)
            ->first();

        // Estimated usage cost this month
        $monthlySpendCents = (int) UsageRecord::where('workspace_id', $workspace->id)
            ->whereMonth('recorded_at', Carbon::now()->month)
            ->whereYear('recorded_at', Carbon::now()->year)
            ->sum('customer_charge_cents');

        $usageBreakdown = [
            'whatsapp_messages' => $monthlyUsage ? $monthlyUsage->messages_count : 0,
            'ai_messages' => $monthlyUsage ? $monthlyUsage->ai_requests_count : 0,
            'ai_tokens' => $monthlyUsage ? $monthlyUsage->ai_tokens_count : 0,
            'voice_minutes' => $monthlyUsage ? $monthlyUsage->voice_minutes_count : 0,
            'voice_calls' => $monthlyUsage ? $monthlyUsage->voice_calls_count : 0,
            'estimated_spend_cents' => $monthlySpendCents,
            'estimated_spend_formatted' => '₹'.number_format($monthlySpendCents / 100, 2),
        ];

        return Inertia::render('client/Billing/Wallet', [
            'wallet' => [
                'id' => $wallet->id,
                'balance_cents' => $wallet->balance_cents,
                'balance_formatted' => $wallet->formattedBalance(),
                'currency' => $wallet->currency,
                'low_balance_threshold_cents' => $wallet->low_balance_threshold_cents,
                'low_balance_threshold_formatted' => '₹'.number_format($wallet->low_balance_threshold_cents / 100, 2),
                'low_balance_alert_enabled' => $wallet->low_balance_alert_enabled,
                'auto_recharge_enabled' => $wallet->auto_recharge_enabled,
                'auto_recharge_amount_cents' => $wallet->auto_recharge_amount_cents,
                'auto_recharge_amount_formatted' => '₹'.number_format($wallet->auto_recharge_amount_cents / 100, 2),
                'is_low_balance' => $wallet->isLowBalance(),
            ],
            'transactions' => $transactions,
            'usage_breakdown' => $usageBreakdown,
        ]);
    }

    /**
     * Top-up wallet balance.
     */
    public function topup(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'amount_in_rupees' => ['required', 'numeric', 'min:100', 'max:500000'],
        ]);

        $user = $request->user();
        $workspace = $user->activeWorkspace() ?? $user->workspace;
        $amountCents = (int) round($validated['amount_in_rupees'] * 100);

        $this->walletService->deposit(
            $workspace,
            $amountCents,
            'Growbridge Balance Top-up',
            'manual_deposit',
            null,
            ['category' => 'topup'],
            $user->id
        );

        return back()->with('success', 'Balance added successfully to your Growbridge Wallet!');
    }

    /**
     * Update wallet settings (threshold, alerts, auto-recharge).
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'low_balance_threshold_rupees' => ['nullable', 'numeric', 'min:0'],
            'low_balance_alert_enabled' => ['nullable', 'boolean'],
            'auto_recharge_enabled' => ['nullable', 'boolean'],
            'auto_recharge_amount_rupees' => ['nullable', 'numeric', 'min:500'],
        ]);

        $user = $request->user();
        $workspace = $user->activeWorkspace() ?? $user->workspace;

        $this->walletService->updateSettings($workspace, [
            'low_balance_threshold_cents' => isset($validated['low_balance_threshold_rupees']) ? (int) ($validated['low_balance_threshold_rupees'] * 100) : null,
            'low_balance_alert_enabled' => $validated['low_balance_alert_enabled'] ?? false,
            'auto_recharge_enabled' => $validated['auto_recharge_enabled'] ?? false,
            'auto_recharge_amount_cents' => isset($validated['auto_recharge_amount_rupees']) ? (int) ($validated['auto_recharge_amount_rupees'] * 100) : null,
        ]);

        return back()->with('success', 'Wallet preferences updated successfully.');
    }

    /**
     * Pre-flight campaign usage estimate API.
     */
    public function estimateCampaign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recipient_count' => ['required', 'integer', 'min:1'],
            'channel' => ['nullable', 'string'],
        ]);

        $workspace = $request->user()->activeWorkspace() ?? $request->user()->workspace;
        $estimate = $this->usageService->estimateCampaignCost(
            $workspace,
            (int) $validated['recipient_count'],
            $validated['channel'] ?? 'whatsapp'
        );

        return response()->json($estimate);
    }
}
