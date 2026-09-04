<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Currency extends Model
{
    public const CACHE_KEY = 'currencies:enabled_list';

    protected static function booted(): void
    {
        $flush = fn () => Cache::forget(self::CACHE_KEY);
        static::saved($flush);
        static::deleted($flush);
    }

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'symbol',
        'decimals',
        'exchange_rate',
        'is_default',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'exchange_rate' => 'decimal:8',
            'is_default' => 'boolean',
            'enabled' => 'boolean',
            'decimals' => 'integer',
        ];
    }

    public static function defaultCode(): ?string
    {
        $currencies = static::enabledCurrenciesList();
        foreach ($currencies as $c) {
            return $c['code'];
        }

        return 'USD';
    }

    public static function enabledCurrenciesList(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, 3600, function () {
                return static::where('enabled', true)
                    ->orderByRaw('is_default DESC')
                    ->orderBy('code')
                    ->get(['code', 'symbol', 'decimals', 'exchange_rate'])
                    ->map(fn ($c) => [
                        'code' => $c->code,
                        'symbol' => $c->symbol,
                        'decimals' => $c->decimals,
                        'exchange_rate' => (float) $c->exchange_rate,
                    ])
                    ->all();
            });
        } catch (\Throwable) {
            return [];
        }
    }
}
