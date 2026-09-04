<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Workspace;

class FeatureService
{
    /**
     * Check if a workspace has access to a specific feature flag under its active plan.
     */
    public static function can(Workspace|int $workspace, string $feature): bool
    {
        $workspaceId = $workspace instanceof Workspace ? $workspace->id : $workspace;

        $subscription = Subscription::with('plan')
            ->where('workspace_id', $workspaceId)
            ->latest('id')
            ->first();

        // If no workspace subscription, check owner user's subscription
        if (! $subscription && $workspace instanceof Workspace && $workspace->user_id) {
            $subscription = Subscription::with('plan')
                ->where('user_id', $workspace->user_id)
                ->latest('id')
                ->first();
        }

        if (! $subscription || ! $subscription->isActive()) {
            return false;
        }

        $plan = $subscription->plan;
        if (! $plan) {
            return false;
        }

        $features = $plan->features ?? [];

        // If feature list is empty or wildcard, allow
        if (isset($features['*']) && $features['*'] === true) {
            return true;
        }

        // Standard feature flags
        if (isset($features[$feature])) {
            return (bool) $features[$feature];
        }

        // Default open features for basic communication
        $defaultOpen = ['whatsapp', 'inbox', 'contacts', 'campaigns'];

        return in_array($feature, $defaultOpen, true);
    }

    /**
     * Enforce feature access; throws HTTP 403 with descriptive message if disallowed.
     */
    public static function enforce(Workspace|int $workspace, string $feature, ?string $customMessage = null): void
    {
        if (! static::can($workspace, $feature)) {
            $msg = $customMessage ?: "The '{$feature}' feature requires an upgraded subscription plan.";
            abort(403, $msg);
        }
    }
}
