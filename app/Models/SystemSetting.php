<?php

namespace App\Models;

use App\Providers\BrandingServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class SystemSetting extends Model
{
    public const CACHE_KEY = 'system_settings:all_dict';

    /**
     * In-memory request-level dictionary cache.
     * Eliminates duplicate cache/DB roundtrips within the same lifecycle.
     */
    protected static ?array $requestCache = null;

    protected static function booted(): void
    {
        $flush = function () {
            static::$requestCache = null;
            Cache::forget(self::CACHE_KEY);
            Cache::forget(BrandingServiceProvider::CACHE_KEY);
        };

        static::saved($flush);
        static::deleted($flush);
    }

    protected $fillable = ['key', 'value', 'is_secret', 'group'];

    protected function casts(): array
    {
        return ['is_secret' => 'boolean'];
    }

    public function getValueAttribute($value): ?string
    {
        $raw = $this->attributes['value'] ?? $value;
        if ($this->is_secret && $raw) {
            try {
                return Crypt::decryptString($raw);
            } catch (\Throwable) {
                return null;
            }
        }

        return $raw;
    }

    public function setValueAttribute($value): void
    {
        if ($this->is_secret && $value !== null && $value !== '') {
            $this->attributes['value'] = Crypt::encryptString($value);
        } else {
            $this->attributes['value'] = $value;
        }
    }

    /**
     * Get the full settings map with persistent & in-memory caching.
     */
    public static function allSettingsDict(): array
    {
        if (static::$requestCache !== null) {
            return static::$requestCache;
        }

        try {
            $dict = Cache::remember(self::CACHE_KEY, 3600, function () {
                $all = static::all();
                $map = [];
                foreach ($all as $item) {
                    $map[$item->key] = $item->value;
                }

                return $map;
            });
        } catch (\Throwable) {
            return [];
        }

        static::$requestCache = $dict;

        return $dict;
    }

    public static function get(string $key, $default = null)
    {
        $dict = static::allSettingsDict();

        return array_key_exists($key, $dict) ? $dict[$key] : $default;
    }

    public static function set(string $key, $value, bool $isSecret = false, ?string $group = null): void
    {
        $s = static::firstOrNew(['key' => $key]);
        $s->value = $value;
        $s->is_secret = $isSecret;
        $s->group = $group;
        $s->save();

        static::$requestCache = null;
        Cache::forget(self::CACHE_KEY);
        Cache::forget(BrandingServiceProvider::CACHE_KEY);
    }

    public static function flushCache(): void
    {
        static::$requestCache = null;
        Cache::forget(self::CACHE_KEY);
        Cache::forget(BrandingServiceProvider::CACHE_KEY);
    }
}
