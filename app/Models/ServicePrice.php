<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServicePrice extends Model
{
    use HasFactory;

    protected $table = 'service_prices';

    protected $fillable = [
        'service',
        'provider',
        'unit',
        'provider_cost_cents',
        'customer_price_cents',
        'currency',
        'is_active',
        'tier_rules',
    ];

    protected function casts(): array
    {
        return [
            'provider_cost_cents' => 'integer',
            'customer_price_cents' => 'integer',
            'is_active' => 'boolean',
            'tier_rules' => 'array',
        ];
    }

    public function getMarginCentsAttribute(): int
    {
        return $this->customer_price_cents - $this->provider_cost_cents;
    }

    public function getMarginPercentAttribute(): float
    {
        if ($this->customer_price_cents <= 0) {
            return 0.0;
        }

        return round((($this->margin_cents) / $this->customer_price_cents) * 100, 1);
    }
}
