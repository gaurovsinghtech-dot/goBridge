<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Locale extends Model
{
    public const CACHE_KEY = 'locales:switcher_list';

    protected static function booted(): void
    {
        $flush = fn () => Cache::forget(self::CACHE_KEY);
        static::saved($flush);
        static::deleted($flush);
    }

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['code', 'name', 'native_name', 'flag', 'enabled', 'is_default', 'is_rtl', 'sort_order'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'is_default' => 'boolean',
            'is_rtl' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getNativeNameAttribute($value): string
    {
        return $value ?? $this->name ?? $this->code;
    }

    /** Scope: only enabled locales. */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /** Get the default locale code from DB. */
    public static function defaultCode(): string
    {
        $locale = static::where('is_default', true)->where('enabled', true)->first();

        return $locale ? $locale->code : 'en';
    }

    /** Get enabled locales ordered for switcher. */
    public static function forSwitcher(): \Illuminate\Database\Eloquent\Collection
    {
        return static::enabled()->orderByRaw('is_default DESC')->orderBy('sort_order')->orderBy('code')->get();
    }

    /** Get cached switcher array */
    public static function forSwitcherCached(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, 3600, function () {
                return static::enabled()
                    ->orderByRaw('is_default DESC')
                    ->orderBy('sort_order')
                    ->orderBy('code')
                    ->get()
                    ->map(fn ($loc) => [
                        'code' => $loc->code,
                        'name' => $loc->name,
                        'native_name' => $loc->native_name ?? $loc->name,
                        'is_rtl' => (bool) $loc->is_rtl,
                        'flag' => $loc->flag,
                    ])
                    ->all();
            });
        } catch (\Throwable) {
            return [
                ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_rtl' => false, 'flag' => null],
            ];
        }
    }
}
