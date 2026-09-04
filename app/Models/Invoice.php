<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Invoice extends Model
{
    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->invoice_number)) {
                $model->invoice_number = 'INV-'.date('Ym').'-'.strtoupper(Str::random(5));
            }
        });
    }

    protected $fillable = [
        'workspace_id',
        'user_id',
        'subscription_id',
        'plan_id',
        'invoice_number',
        'amount_cents',
        'tax_cents',
        'total_cents',
        'currency_code',
        'status',
        'payment_method',
        'gateway_payment_id',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'tax_cents' => 'integer',
            'total_cents' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
