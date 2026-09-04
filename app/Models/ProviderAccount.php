<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProviderAccount extends Model
{
    use HasFactory;

    protected $table = 'provider_accounts';

    protected $fillable = [
        'provider',
        'name',
        'balance_cents',
        'currency',
        'status',
        'threshold_alert_cents',
        'monthly_spend_cents',
        'last_sync_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'balance_cents' => 'integer',
            'threshold_alert_cents' => 'integer',
            'monthly_spend_cents' => 'integer',
            'last_sync_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
