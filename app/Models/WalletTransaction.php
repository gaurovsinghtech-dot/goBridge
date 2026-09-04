<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WalletTransaction extends Model
{
    use HasFactory;

    protected $table = 'wallet_transactions';

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    protected $fillable = [
        'uuid',
        'wallet_id',
        'workspace_id',
        'type', // credit, debit, refund, adjustment
        'category', // topup, whatsapp_usage, ai_usage, voice_usage, sms_usage, phone_number, refund, adjustment
        'amount_cents',
        'balance_after_cents',
        'currency',
        'description',
        'reference_type',
        'reference_id',
        'metadata',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'balance_after_cents' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function formattedAmount(): string
    {
        $prefix = $this->type === 'credit' || $this->type === 'refund' ? '+' : '-';
        $amount = number_format($this->amount_cents / 100, 2);
        $symbol = $this->currency === 'INR' ? '₹' : '$';

        return "{$prefix}{$symbol}{$amount}";
    }
}
