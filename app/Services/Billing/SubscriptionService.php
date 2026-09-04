<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Workspace;
use App\Modules\Shared\Models\Contact;
use App\Modules\Voice\Models\VoiceAgent;

class SubscriptionService
{
    /**
     * Start a free trial for a new workspace.
     */
    public function startTrial(Workspace $workspace, Plan $plan): Subscription
    {
        $trialDays = $plan->trial_days ?: 14;
        $userId = $workspace->user_id ?? $workspace->owner_id ?? $workspace->users()->first()?->id ?? $workspace->client?->users()->first()?->id ?? 1;

        return Subscription::updateOrCreate(
            ['workspace_id' => $workspace->id],
            [
                'user_id' => $userId,
                'plan_id' => $plan->id,
                'status' => 'trial',
                'billing_cycle' => 'monthly',
                'starts_at' => now(),
                'trial_ends_at' => now()->addDays($trialDays),
                'current_period_start' => now(),
                'current_period_end' => now()->addDays($trialDays),
                'gateway' => 'manual',
            ]
        );
    }

    /**
     * Activate a paid subscription upon successful server-side payment verification.
     */
    public function activatePaidSubscription(
        Workspace $workspace,
        Plan $plan,
        string $billingCycle,
        string $gateway,
        ?string $paymentId = null,
        int $amountPaidCents = 0
    ): Subscription {
        $periodDays = $billingCycle === 'yearly' ? 365 : 30;

        $subscription = Subscription::updateOrCreate(
            ['workspace_id' => $workspace->id],
            [
                'user_id' => $workspace->user_id ?? $workspace->users()->first()?->id ?? $workspace->client?->users()->first()?->id ?? 1,
                'plan_id' => $plan->id,
                'status' => 'active',
                'billing_cycle' => $billingCycle,
                'starts_at' => now(),
                'current_period_start' => now(),
                'current_period_end' => now()->addDays($periodDays),
                'ends_at' => now()->addDays($periodDays),
                'renews_at' => now()->addDays($periodDays),
                'gateway' => $gateway,
                'gateway_subscription_id' => $paymentId,
            ]
        );

        // Generate paid invoice record
        $tax = (int) round($amountPaidCents * 0.18); // 18% GST standard
        $subtotal = max(0, $amountPaidCents - $tax);

        Invoice::create([
            'workspace_id' => $workspace->id,
            'user_id' => $workspace->user_id,
            'subscription_id' => $subscription->id,
            'plan_id' => $plan->id,
            'amount_cents' => $subtotal,
            'tax_cents' => $tax,
            'total_cents' => $amountPaidCents,
            'currency_code' => $plan->currency_code ?: 'INR',
            'status' => 'paid',
            'payment_method' => $gateway,
            'gateway_payment_id' => $paymentId,
            'paid_at' => now(),
        ]);

        return $subscription;
    }

    /**
     * Validate whether a workspace can safely downgrade to a new plan.
     * Checks if current contacts, voice agents, etc. exceed target plan limits.
     */
    public function validateDowngrade(Workspace $workspace, Plan $targetPlan): array
    {
        $targetLimits = $targetPlan->limits ?? [];

        // Check contacts
        if (isset($targetLimits['contacts']) && $targetLimits['contacts'] > 0) {
            $currentContacts = Contact::where('workspace_id', $workspace->id)->count();
            if ($currentContacts > $targetLimits['contacts']) {
                return [
                    'allowed' => false,
                    'reason' => "Current contact count ({$currentContacts}) exceeds the {$targetPlan->name} plan limit of {$targetLimits['contacts']} contacts.",
                ];
            }
        }

        // Check AI Voice Agents
        if (isset($targetLimits['ai_voice_agents']) && $targetLimits['ai_voice_agents'] >= 0) {
            $currentVoiceAgents = VoiceAgent::where('workspace_id', $workspace->id)->count();
            if ($currentVoiceAgents > $targetLimits['ai_voice_agents']) {
                return [
                    'allowed' => false,
                    'reason' => "You currently have {$currentVoiceAgents} AI Voice Agents, but the {$targetPlan->name} plan allows a maximum of {$targetLimits['ai_voice_agents']}.",
                ];
            }
        }

        return [
            'allowed' => true,
            'reason' => null,
        ];
    }
}
