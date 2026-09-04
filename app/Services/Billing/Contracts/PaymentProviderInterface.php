<?php

namespace App\Services\Billing\Contracts;

use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;

interface PaymentProviderInterface
{
    /**
     * Get the identifier name of the payment provider.
     */
    public function getProviderName(): string;

    /**
     * Create an order or session for a subscription purchase.
     *
     * @return array{
     *     success: bool,
     *     order_id?: string,
     *     amount?: int,
     *     currency?: string,
     *     key_id?: string,
     *     message?: string,
     *     [key: string]: mixed
     * }
     */
    public function createSubscriptionOrder(Workspace $workspace, User $user, Plan $plan, string $billingCycle = 'monthly'): array;

    /**
     * Verify the payment response payload and signature.
     */
    public function verifyPayment(array $paymentData): bool;

    /**
     * Process an inbound webhook request from the payment provider.
     *
     * @return array{
     *     success: bool,
     *     status: int,
     *     message: string,
     *     event?: string
     * }
     */
    public function handleWebhook(array $payload, array $headers): array;

    /**
     * Cancel an active gateway subscription.
     */
    public function cancelSubscription(Subscription $subscription): bool;

    /**
     * Process a refund for a previously captured payment.
     *
     * @return array{
     *     success: bool,
     *     refund_id?: string,
     *     message?: string
     * }
     */
    public function processRefund(PaymentTransaction|string $payment, ?int $amountCents = null, ?string $reason = null): array;
}
