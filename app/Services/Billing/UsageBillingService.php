<?php

namespace App\Services\Billing;

use App\Models\ServicePrice;
use App\Models\UsageRecord;
use App\Models\Workspace;
use App\Models\WorkspaceUsage;
use Carbon\Carbon;
use Illuminate\Support\Str;

class UsageBillingService
{
    public function __construct(
        protected WalletService $walletService
    ) {}

    /**
     * Record verified usage event, apply pricing rules, deduct from wallet if managed, and track profit margin.
     */
    public function recordUsage(
        Workspace|int $workspace,
        string $service,
        string $provider,
        float $quantity = 1.0,
        string $unit = 'messages',
        array $metadata = []
    ): UsageRecord {
        $workspaceId = $workspace instanceof Workspace ? $workspace->id : $workspace;
        $workspaceModel = $workspace instanceof Workspace ? $workspace : Workspace::find($workspaceId);

        $connectionModel = $metadata['connection_model'] ?? 'growbridge_managed';

        // 1. Resolve pricing from service_prices table
        $pricing = ServicePrice::where('service', $service)
            ->where('provider', $provider)
            ->where('is_active', true)
            ->first();

        // Standard Fallback Rates if not customized
        $unitProviderCost = $pricing?->provider_cost_cents ?? match ($service) {
            'whatsapp_marketing' => 78,
            'whatsapp_utility' => 35,
            'whatsapp', 'whatsapp_service' => 0,
            'ai', 'ai_message' => 10,
            'ai_token_1k' => 15,
            'voice', 'voice_outbound_minute' => 110,
            'voice_inbound_minute' => 85,
            'sms', 'sms_domestic' => 18,
            'phone_number_monthly' => 9500,
            default => 10,
        };

        $unitCustomerPrice = $pricing?->customer_price_cents ?? match ($service) {
            'whatsapp_marketing' => 105,
            'whatsapp_utility' => 50,
            'whatsapp', 'whatsapp_service' => 15,
            'ai', 'ai_message' => 25,
            'ai_token_1k' => 40,
            'voice', 'voice_outbound_minute' => 175,
            'voice_inbound_minute' => 125,
            'sms', 'sms_domestic' => 30,
            'phone_number_monthly' => 15000,
            default => 25,
        };

        // If customer provided their own credentials (BYOK), Growbridge incurs 0 provider cost
        if ($connectionModel === 'customer_connected') {
            $totalProviderCost = 0;
            $totalCustomerCharge = 0; // Provider bills customer directly
            $walletTransactionId = null;
        } else {
            $totalProviderCost = (int) round($unitProviderCost * $quantity);
            $totalCustomerCharge = (int) round($unitCustomerPrice * $quantity);

            // Deduct charge from customer wallet if > 0
            $walletTransactionId = null;
            if ($totalCustomerCharge > 0 && $workspaceModel) {
                $category = match ($service) {
                    'whatsapp', 'whatsapp_marketing', 'whatsapp_utility' => 'whatsapp_usage',
                    'ai', 'ai_message', 'ai_token_1k' => 'ai_usage',
                    'voice', 'voice_inbound_minute', 'voice_outbound_minute' => 'voice_usage',
                    'sms', 'sms_domestic' => 'sms_usage',
                    'phone_number_monthly' => 'phone_number',
                    default => 'service_usage',
                };

                $description = $metadata['description'] ?? sprintf(
                    '%s usage: %s %s (%s)',
                    ucfirst(str_replace('_', ' ', $service)),
                    $quantity,
                    $unit,
                    strtoupper($provider)
                );

                $tx = $this->walletService->deduct(
                    $workspaceModel,
                    $totalCustomerCharge,
                    $category,
                    $description,
                    'usage_record',
                    null,
                    $metadata
                );
                $walletTransactionId = $tx->id;
            }
        }

        $grossMargin = $totalCustomerCharge - $totalProviderCost;

        // 2. Create UsageRecord
        $record = UsageRecord::create([
            'workspace_id' => $workspaceId,
            'service' => $service,
            'provider' => $provider,
            'connection_model' => $connectionModel,
            'quantity' => $quantity,
            'unit' => $unit,
            'provider_cost_cents' => $totalProviderCost,
            'customer_charge_cents' => $totalCustomerCharge,
            'gross_margin_cents' => $grossMargin,
            'currency' => 'INR',
            'is_billed' => true,
            'wallet_transaction_id' => $walletTransactionId,
            'metadata' => $metadata,
            'recorded_at' => now(),
        ]);

        // 3. Update Monthly Counters in WorkspaceUsage
        $this->updateMonthlyCounters($workspaceId, $service, (int) $quantity);

        return $record;
    }

    /**
     * Pre-flight cost estimation for campaigns before broadcast dispatch.
     */
    public function estimateCampaignCost(Workspace|int $workspace, int $recipientCount, string $channel = 'whatsapp'): array
    {
        $workspaceId = $workspace instanceof Workspace ? $workspace->id : $workspace;
        $balanceCents = $this->walletService->getBalance($workspaceId);

        $unitPriceCents = match ($channel) {
            'whatsapp' => 105, // ₹1.05 per marketing message
            'sms' => 30, // ₹0.30 per SMS
            default => 105,
        };

        $estimatedCostCents = $recipientCount * $unitPriceCents;
        $isSufficient = $balanceCents >= $estimatedCostCents;

        return [
            'channel' => $channel,
            'recipient_count' => $recipientCount,
            'unit_price_cents' => $unitPriceCents,
            'unit_price_formatted' => '₹'.number_format($unitPriceCents / 100, 2),
            'estimated_cost_cents' => $estimatedCostCents,
            'estimated_cost_formatted' => '₹'.number_format($estimatedCostCents / 100, 2),
            'available_balance_cents' => $balanceCents,
            'available_balance_formatted' => '₹'.number_format($balanceCents / 100, 2),
            'is_sufficient' => $isSufficient,
            'shortfall_cents' => max(0, $estimatedCostCents - $balanceCents),
            'shortfall_formatted' => '₹'.number_format(max(0, $estimatedCostCents - $balanceCents) / 100, 2),
        ];
    }

    /**
     * Increment monthly aggregate usage counters.
     */
    protected function updateMonthlyCounters(int $workspaceId, string $service, int $quantity): void
    {
        try {
            $month = Carbon::now()->startOfMonth()->toDateString();
            $usage = WorkspaceUsage::firstOrCreate(
                ['workspace_id' => $workspaceId, 'period_month' => $month],
                [
                    'contacts_count' => 0,
                    'messages_count' => 0,
                    'ai_requests_count' => 0,
                    'ai_tokens_count' => 0,
                    'voice_calls_count' => 0,
                    'voice_minutes_count' => 0,
                    'automation_executions_count' => 0,
                    'campaigns_count' => 0,
                    'api_requests_count' => 0,
                ]
            );

            if (str_starts_with($service, 'whatsapp') || $service === 'sms') {
                $usage->increment('messages_count', $quantity);
            } elseif (str_starts_with($service, 'ai')) {
                $usage->increment('ai_requests_count', 1);
                $usage->increment('ai_tokens_count', $quantity);
            } elseif (str_starts_with($service, 'voice')) {
                $usage->increment('voice_calls_count', 1);
                $usage->increment('voice_minutes_count', $quantity);
            }
        } catch (\Throwable) {
            // Silently ignore logging counter errors
        }
    }
}
