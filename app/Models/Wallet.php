<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    use HasFactory;

    protected $table = 'wallets';

    protected $fillable = [
        'workspace_id',
        'balance_cents',
        'currency',
        'low_balance_threshold_cents',
        'low_balance_alert_enabled',
        'auto_recharge_enabled',
        'auto_recharge_amount_cents',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'balance_cents' => 'integer',
            'low_balance_threshold_cents' => 'integer',
            'low_balance_alert_enabled' => 'boolean',
            'auto_recharge_enabled' => 'boolean',
            'auto_recharge_amount_cents' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class)->latest();
    }

    public function isLowBalance(): bool
    {
        return $this->balance_cents <= $this->low_balance_threshold_cents;
    }

    public function formattedBalance(): string
    {
        $amount = number_format($this->balance_cents / 100, 2);
        $symbol = $this->currency === 'INR' ? '₹' : '$';

        return "{$symbol}{$amount}";
    }
}
