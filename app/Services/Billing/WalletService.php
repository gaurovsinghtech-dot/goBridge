<?php

namespace App\Services\Billing;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class WalletService
{
    /**
     * Get or create wallet for workspace.
     */
    public function getWallet(Workspace|int $workspace): Wallet
    {
        $workspaceId = $workspace instanceof Workspace ? $workspace->id : $workspace;

        return Wallet::firstOrCreate(
            ['workspace_id' => $workspaceId],
            [
                'balance_cents' => 0,
                'currency' => 'INR',
                'low_balance_threshold_cents' => 50000,
                'low_balance_alert_enabled' => true,
                'auto_recharge_enabled' => false,
                'auto_recharge_amount_cents' => 200000,
                'status' => 'active',
            ]
        );
    }

    /**
     * Get current wallet balance in cents/paise.
     */
    public function getBalance(Workspace|int $workspace): int
    {
        return $this->getWallet($workspace)->balance_cents;
    }

    /**
     * Check if wallet has sufficient balance for an operation.
     */
    public function hasSufficientBalance(Workspace|int $workspace, int $requiredCents): bool
    {
        return $this->getBalance($workspace) >= $requiredCents;
    }

    /**
     * Deposit/credit funds into workspace wallet.
     */
    public function deposit(
        Workspace|int $workspace,
        int $amountCents,
        string $description,
        ?string $referenceType = null,
        ?string $referenceId = null,
        array $metadata = [],
        ?int $createdBy = null
    ): WalletTransaction {
        if ($amountCents <= 0) {
            throw new InvalidArgumentException('Deposit amount must be positive.');
        }

        $workspaceId = $workspace instanceof Workspace ? $workspace->id : $workspace;

        return DB::transaction(function () use ($workspaceId, $amountCents, $description, $referenceType, $referenceId, $metadata, $createdBy) {
            $wallet = Wallet::where('workspace_id', $workspaceId)->lockForUpdate()->first();
            if (! $wallet) {
                $wallet = $this->getWallet($workspaceId);
                $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();
            }

            $wallet->balance_cents += $amountCents;
            $wallet->save();

            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'workspace_id' => $workspaceId,
                'type' => 'credit',
                'category' => $metadata['category'] ?? 'topup',
                'amount_cents' => $amountCents,
                'balance_after_cents' => $wallet->balance_cents,
                'currency' => $wallet->currency,
                'description' => $description,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'metadata' => $metadata,
                'created_by' => $createdBy,
            ]);
        });
    }

    /**
     * Deduct/debit funds for metered service usage.
     */
    public function deduct(
        Workspace|int $workspace,
        int $amountCents,
        string $category,
        string $description,
        ?string $referenceType = null,
        ?string $referenceId = null,
        array $metadata = []
    ): WalletTransaction {
        if ($amountCents <= 0) {
            throw new InvalidArgumentException('Deduction amount must be positive.');
        }

        $workspaceId = $workspace instanceof Workspace ? $workspace->id : $workspace;

        return DB::transaction(function () use ($workspaceId, $amountCents, $category, $description, $referenceType, $referenceId, $metadata) {
            $wallet = Wallet::where('workspace_id', $workspaceId)->lockForUpdate()->first();
            if (! $wallet) {
                $wallet = $this->getWallet($workspaceId);
                $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();
            }

            $wallet->balance_cents -= $amountCents;
            $wallet->save();

            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'workspace_id' => $workspaceId,
                'type' => 'debit',
                'category' => $category,
                'amount_cents' => $amountCents,
                'balance_after_cents' => $wallet->balance_cents,
                'currency' => $wallet->currency,
                'description' => $description,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'metadata' => $metadata,
            ]);
        });
    }

    /**
     * Update wallet threshold & auto-recharge settings.
     */
    public function updateSettings(Workspace|int $workspace, array $data): Wallet
    {
        $wallet = $this->getWallet($workspace);

        $wallet->update([
            'low_balance_threshold_cents' => $data['low_balance_threshold_cents'] ?? $wallet->low_balance_threshold_cents,
            'low_balance_alert_enabled' => isset($data['low_balance_alert_enabled']) ? (bool) $data['low_balance_alert_enabled'] : $wallet->low_balance_alert_enabled,
            'auto_recharge_enabled' => isset($data['auto_recharge_enabled']) ? (bool) $data['auto_recharge_enabled'] : $wallet->auto_recharge_enabled,
            'auto_recharge_amount_cents' => $data['auto_recharge_amount_cents'] ?? $wallet->auto_recharge_amount_cents,
        ]);

        return $wallet->fresh();
    }
}
