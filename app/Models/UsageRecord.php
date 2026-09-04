<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UsageRecord extends Model
{
    use HasFactory;

    protected $table = 'usage_records';

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->recorded_at)) {
                $model->recorded_at = now();
            }
        });
    }

    protected $fillable = [
        'uuid',
        'workspace_id',
        'service',
        'provider',
        'connection_model', // growbridge_managed, customer_connected
        'quantity',
        'unit',
        'provider_cost_cents',
        'customer_charge_cents',
        'gross_margin_cents',
        'currency',
        'is_billed',
        'wallet_transaction_id',
        'metadata',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'provider_cost_cents' => 'integer',
            'customer_charge_cents' => 'integer',
            'gross_margin_cents' => 'integer',
            'is_billed' => 'boolean',
            'metadata' => 'array',
            'recorded_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class);
    }
}
