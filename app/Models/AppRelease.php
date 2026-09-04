<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class AppRelease extends Model
{
    use HasFactory;

    protected $table = 'app_releases';

    protected $fillable = [
        'platform',
        'version',
        'version_code',
        'min_supported_version',
        'file_path',
        'download_url',
        'file_size_mb',
        'release_notes',
        'force_update_required',
        'is_active',
        'download_count',
        'published_at',
    ];

    protected $casts = [
        'version_code' => 'integer',
        'file_size_mb' => 'float',
        'force_update_required' => 'boolean',
        'is_active' => 'boolean',
        'download_count' => 'integer',
        'published_at' => 'datetime',
    ];

    /**
     * Scope active releases.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope platform releases.
     */
    public function scopeForPlatform(Builder $query, string $platform = 'android'): Builder
    {
        return $query->where('platform', $platform);
    }

    /**
     * Get the latest active release for a platform.
     */
    public static function getLatestActive(string $platform = 'android'): ?self
    {
        return static::active()
            ->forPlatform($platform)
            ->orderByDesc('version_code')
            ->first();
    }

    /**
     * Get effective download URL.
     */
    public function getEffectiveDownloadUrlAttribute(): string
    {
        if (! empty($this->download_url)) {
            return $this->download_url;
        }

        if (! empty($this->file_path)) {
            return Storage::disk('public')->url($this->file_path);
        }

        return route('download.android-apk');
    }

    /**
     * Increment download counter atomically.
     */
    public function incrementDownloadCount(): void
    {
        $this->increment('download_count');
    }
}
