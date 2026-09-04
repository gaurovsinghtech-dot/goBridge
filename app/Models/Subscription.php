<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    protected $fillable = [
        'user_id',
        'workspace_id',
        'plan_id',
        'status',
        'billing_cycle',
        'starts_at',
        'ends_at',
        'current_period_start',
        'current_period_end',
        'cancelled_at',
        'gateway',
        'gateway_subscription_id',
        'gateway_metadata',
        'renews_at',
        'trial_ends_at',
        'trial_reminder_sent_at',
    ];

    protected static function booted(): void
    {
        static::saved(function ($sub) {
            \App\Services\Billing\EntitlementService::clearCache();
        });

        static::deleted(function ($sub) {
            \App\Services\Billing\EntitlementService::clearCache();
        });
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'cancelled_at' => 'datetime',
            'renews_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'trial_reminder_sent_at' => 'datetime',
            'gateway_metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function isActive(): bool
    {
        if (in_array($this->status, ['active', 'trialing', 'trial'], true)) {
            if ($this->isTrialing()) {
                return $this->trial_ends_at === null || $this->trial_ends_at->isFuture();
            }

            return $this->ends_at === null || $this->ends_at->isFuture();
        }

        return false;
    }

    public function isTrialing(): bool
    {
        return in_array($this->status, ['trial', 'trialing'], true)
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isFuture();
    }

    public function getTrialDaysRemaining(): int
    {
        if (! $this->isTrialing() || ! $this->trial_ends_at) {
            return 0;
        }

        return max(0, (int) ceil(now()->floatDiffInDays($this->trial_ends_at, false)));
    }

    public function isPastDue(): bool
    {
        return $this->status === 'past_due';
    }

    public function isExpired(): bool
    {
        return in_array($this->status, ['expired', 'cancelled'], true)
            || ($this->ends_at !== null && $this->ends_at->isPast());
    }
}
