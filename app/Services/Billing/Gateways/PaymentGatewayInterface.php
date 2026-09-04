<?php

namespace App\Services\Billing\Gateways;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;

interface PaymentGatewayInterface
{
    /**
     * Create a payment checkout order / session for plan subscription.
     */
    public function createSubscriptionOrder(Workspace $workspace, User $user, Plan $plan, string $billingCycle): array;

    /**
     * Verify payment signature server-side. Never trust frontend callbacks.
     */
    public function verifyPayment(array $paymentData): bool;

    /**
     * Handle and process authenticated gateway webhooks.
     */
    public function handleWebhook(array $payload, array $headers): array;

    /**
     * Cancel an active subscription on the gateway.
     */
    public function cancelSubscription(Subscription $subscription): bool;
}
