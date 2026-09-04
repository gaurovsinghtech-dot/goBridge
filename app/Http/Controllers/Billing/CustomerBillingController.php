<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Workspace;
use App\Services\Billing\Gateways\RazorpayGateway;
use App\Services\Billing\SubscriptionService;
use App\Services\Billing\UsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerBillingController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly UsageService $usageService,
        private readonly RazorpayGateway $razorpayGateway
    ) {}

    public function index(Request $request): Response
    {
        $workspaceId = (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
        $workspace = Workspace::findOrFail($workspaceId);

        $subscription = Subscription::with('plan')
            ->where('workspace_id', $workspaceId)
            ->latest('id')
            ->first();

        $usage = $this->usageService->getDashboardUsage($workspace);

        $invoices = Invoice::where('workspace_id', $workspaceId)
            ->latest('created_at')
            ->take(10)
            ->get();

        return Inertia::render('Billing/Index', [
            'subscription' => $subscription ? [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'billing_cycle' => $subscription->billing_cycle,
                'is_trialing' => $subscription->isTrialing(),
                'trial_days_remaining' => $subscription->getTrialDaysRemaining(),
                'current_period_end' => $subscription->current_period_end?->toDateString(),
                'plan' => $subscription->plan ? [
                    'id' => $subscription->plan->id,
                    'name' => $subscription->plan->name,
                    'description' => $subscription->plan->description,
                    'monthly_price_cents' => $subscription->plan->monthly_price_cents,
                    'yearly_price_cents' => $subscription->plan->yearly_price_cents,
                    'currency_code' => $subscription->plan->currency_code,
                    'features' => $subscription->plan->features,
                    'limits' => $subscription->plan->limits,
                ] : null,
            ] : null,
            'usage' => $usage,
            'invoices' => $invoices,
        ]);
    }

    public function plans(Request $request): Response
    {
        $workspaceId = (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
        $subscription = Subscription::with('plan')->where('workspace_id', $workspaceId)->latest('id')->first();

        $plans = Plan::where('enabled', true)
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Billing/Plans', [
            'plans' => $plans,
            'currentPlanId' => $subscription?->plan_id,
            'currentSubscription' => $subscription,
        ]);
    }

    public function checkout(Request $request): JsonResponse
    {
        $workspaceId = (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
        $workspace = Workspace::findOrFail($workspaceId);

        $validated = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);

        // Check downgrade feasibility
        $downgradeCheck = $this->subscriptionService->validateDowngrade($workspace, $plan);
        if (! $downgradeCheck['allowed']) {
            return response()->json([
                'success' => false,
                'message' => $downgradeCheck['reason'],
            ], 422);
        }

        $order = $this->razorpayGateway->createSubscriptionOrder(
            $workspace,
            $request->user(),
            $plan,
            $validated['billing_cycle']
        );

        return response()->json($order);
    }

    public function verifyPayment(Request $request): JsonResponse
    {
        $workspaceId = (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
        $workspace = Workspace::findOrFail($workspaceId);

        $validated = $request->validate([
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
            'plan_id' => ['required', 'exists:plans,id'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
        ]);

        $verified = $this->razorpayGateway->verifyPayment($validated);

        if (! $verified) {
            return response()->json([
                'success' => false,
                'message' => __('Payment verification failed. Please contact support if amount was deducted.'),
            ], 400);
        }

        $plan = Plan::findOrFail($validated['plan_id']);
        $amount = $validated['billing_cycle'] === 'yearly'
            ? ($plan->yearly_price_cents ?: $plan->price_cents * 12)
            : ($plan->monthly_price_cents ?: $plan->price_cents);

        $this->subscriptionService->activatePaidSubscription(
            $workspace,
            $plan,
            $validated['billing_cycle'],
            'razorpay',
            $validated['razorpay_payment_id'],
            $amount
        );

        return response()->json([
            'success' => true,
            'message' => __('Subscription activated successfully! Welcome to ').$plan->name.'.',
        ]);
    }

    public function cancel(Request $request): RedirectResponse
    {
        $workspaceId = (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);

        $subscription = Subscription::where('workspace_id', $workspaceId)->latest('id')->first();
        if ($subscription) {
            $this->razorpayGateway->cancelSubscription($subscription);
        }

        return back()->with('success', __('Subscription cancelled. Access remains active until the end of your billing period.'));
    }

    /**
     * Download formatted invoice receipt with tenant authorization checks.
     */
    public function downloadInvoice(Request $request, Invoice $invoice): \Symfony\Component\HttpFoundation\Response
    {
        $user = $request->user();
        $workspaceId = (int) ($user->current_workspace_id ?? $user->workspace_id);

        // Strict cross-tenant isolation: forbid downloading another workspace's invoice
        if ((int) $invoice->workspace_id !== $workspaceId && ! $user->isAdmin()) {
            abort(403, 'Unauthorized access to invoice.');
        }

        $invoice->loadMissing(['workspace', 'plan', 'user']);

        $formattedAmount = '₹' . number_format($invoice->total_cents / 100, 2);
        $formattedTax = '₹' . number_format($invoice->tax_cents / 100, 2);
        $formattedSubtotal = '₹' . number_format($invoice->amount_cents / 100, 2);
        $planName = $invoice->plan?->name ?? 'Subscription Plan';
        $paidDate = $invoice->paid_at ? $invoice->paid_at->format('M d, Y') : now()->format('M d, Y');
        $invoiceNum = $invoice->invoice_number ?? 'INV-' . $invoice->id;

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {$invoiceNum} - Growbridge Connect</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 40px; color: #0F172A; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #011B40; padding-bottom: 20px; }
        .logo { font-size: 24px; font-weight: bold; color: #011B40; }
        .invoice-title { font-size: 20px; color: #064E3B; font-weight: bold; text-align: right; }
        .details { margin: 30px 0; display: flex; justify-content: space-between; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #E2E8F0; }
        th { background: #F8FAFC; color: #64748B; font-size: 12px; text-transform: uppercase; }
        .total-row { font-weight: bold; font-size: 16px; color: #011B40; }
        .badge { background: #DCFCE7; color: #166534; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .footer { margin-top: 40px; text-align: center; color: #94A3B8; font-size: 12px; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <div class="logo">Growbridge Connect</div>
            <div style="color: #64748B; font-size: 13px; margin-top: 4px;">All-in-One Business SaaS Workspace</div>
        </div>
        <div>
            <div class="invoice-title">TAX INVOICE</div>
            <div style="color: #64748B; font-size: 13px;">#{$invoiceNum}</div>
        </div>
    </div>

    <div class="details">
        <div>
            <strong>Billed To:</strong><br>
            {$invoice->workspace?->name}<br>
            Attn: {$invoice->user?->name} ({$invoice->user?->email})
        </div>
        <div style="text-align: right;">
            <strong>Invoice Date:</strong> {$paidDate}<br>
            <strong>Payment Status:</strong> <span class="badge">PAID</span><br>
            <strong>Payment Method:</strong> Razorpay ({$invoice->gateway_payment_id})
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th>Cycle</th>
                <th style="text-align: right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>{$planName} Subscription</strong><br><small style="color: #64748B;">Growbridge Connect multi-tenant workspace subscription</small></td>
                <td>Monthly / Yearly</td>
                <td style="text-align: right;">{$formattedSubtotal}</td>
            </tr>
            <tr>
                <td colspan="2" style="text-align: right; color: #64748B;">GST / Tax (18%)</td>
                <td style="text-align: right;">{$formattedTax}</td>
            </tr>
            <tr class="total-row">
                <td colspan="2" style="text-align: right;">Total Paid</td>
                <td style="text-align: right;">{$formattedAmount}</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        Thank you for choosing Growbridge Connect.<br>
        Questions? Contact support@growbridge.io
    </div>
</body>
</html>
HTML;

        return response($html, 200, [
            'Content-Type' => 'text/html',
            'Content-Disposition' => "inline; filename=\"invoice_{$invoiceNum}.html\"",
        ]);
    }
}
