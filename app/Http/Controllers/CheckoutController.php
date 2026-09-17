<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Workspace;
use App\Modules\Whatsapp\Models\WhatsappBusinessAccount;
use App\Services\Billing\BillingGatewayRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CheckoutController extends Controller
{
    public function __construct(
        private BillingGatewayRegistry $gateways
    ) {}

    /**
     * Start checkout for a plan with the selected gateway and billing cycle.
     */
    public function store(Request $request): RedirectResponse|Response
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')],
            'billing_cycle' => ['required', 'string', Rule::in(['month', 'year'])],
            'gateway' => ['required', 'string', Rule::in(['razorpay'])],
            'whatsapp_trial' => ['nullable', 'boolean'],
            'accept_trial' => ['nullable', 'boolean'],
        ]);

        $plan = Plan::where('enabled', true)->findOrFail($validated['plan_id']);
        $gateway = $this->gateways->get($validated['gateway']);

        if (! $gateway || ! $gateway->isConfigured()) {
            return back()->with('error', __('That payment gateway is not configured.'));
        }

        $trialDaysOverride = null;
        if ($request->boolean('whatsapp_trial')) {
            $user = $request->user();
            $workspaceId = $user->current_workspace_id ?? $user->workspace_id;
            $workspace = $workspaceId ? Workspace::find($workspaceId) : null;

            if (! $workspace) {
                return back()->with('error', __('No active workspace found.'));
            }

            $hasConnectedWhatsapp = WhatsappBusinessAccount::where('workspace_id', $workspace->id)
                ->where('status', 'active')
                ->exists();

            if (! $hasConnectedWhatsapp) {
                return back()->with('error', __('Connect WhatsApp before starting your trial.'));
            }

            $alreadySubscribed = Subscription::where('workspace_id', $workspace->id)
                ->whereIn('status', ['active', 'trialing', 'trial'])
                ->exists();

            if ($alreadySubscribed) {
                return redirect()->route('client.dashboard')->with('success', __('Your WhatsApp trial is already active.'));
            }

            $trialDaysOverride = 14;
        }

        // Apply new user ₹1 trial offer if they don't have a WhatsApp trial active
        // ONLY apply if accept_trial is true (confirmed by user)
        $setupFeeCents = null;
        if ($trialDaysOverride === null && $request->boolean('accept_trial')) {
            $user = $request->user();
            $workspaceId = $user->current_workspace_id ?? $user->workspace_id;
            
            $hasPastSubscriptions = false;
            if ($workspaceId) {
                $hasPastSubscriptions = Subscription::where('workspace_id', $workspaceId)->exists();
            } else {
                $hasPastSubscriptions = Subscription::where('user_id', $user->id)->exists();
            }

            if (! $hasPastSubscriptions) {
                $trialDaysOverride = 14;
                $setupFeeCents = 100; // 100 paise = 1 INR
            }
        }

        $result = $trialDaysOverride !== null
            ? $gateway->createCheckout($request->user(), $plan, $validated['billing_cycle'], $trialDaysOverride, $setupFeeCents)
            : $gateway->createCheckout($request->user(), $plan, $validated['billing_cycle']);

        if (isset($result['error'])) {
            return back()->with('error', $result['error']);
        }

        // Most gateways return a hosted redirect URL.
        if (isset($result['url'])) {
            return Inertia::location($result['url']);
        }

        // Some gateways (e.g. Cashfree) have no hosted redirect and require their JS SDK to
        // launch the authorization flow from a session id. Render an interstitial page that
        // loads the SDK and completes checkout, then returns to the billing page.
        if (isset($result['checkout'])) {
            return Inertia::render('client/Checkout/Sdk', [
                'checkout' => $result['checkout'],
                'plan_name' => $plan->name,
                'pricing_url' => route('client.pricing'),
            ]);
        }

        return back()->with('error', __('Checkout could not be started.'));
    }
}
