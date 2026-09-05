<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    use HasFactory;

    protected $table = 'workspaces';

    protected $fillable = [
        'owner_id',
        'client_id',
        'name',
        'logo_path',
        'industry',
        'website',
        'country',
        'timezone',
        'business_hours',
        'default_locale',
        'currency_code',
        'service_type',
        'onboarding_completed',
        'status',
    ];

    public function logoUrl(int $minutes = 60): ?string
    {
        if (empty($this->logo_path)) {
            return null;
        }

        if (str_starts_with($this->logo_path, 'http://') || str_starts_with($this->logo_path, 'https://')) {
            return $this->logo_path;
        }

        $manager = app(\App\Services\StorageManager::class);
        $diskName = $manager->diskName();

        if ($diskName === 's3' || $diskName === 'do_spaces' || $diskName === 'wasabi') {
            try {
                return $manager->disk()->temporaryUrl($this->logo_path, now()->addMinutes($minutes));
            } catch (\Throwable $e) {
                // fallback
            }
        }

        return $manager->disk()->url($this->logo_path);
    }

    protected $casts = [
        'onboarding_completed' => 'boolean',
        'business_hours' => 'array',
    ];

    protected $attributes = [
        'default_locale' => 'en',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** Users who are members of this workspace (via pivot). */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /** Users whose primary workspace is this one. */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'workspace_id');
    }

    /** Whether the given user can access this workspace (owner or member). */
    public function isAccessibleBy(User $user): bool
    {
        if ($this->owner_id === $user->id) {
            return true;
        }

        return $this->members()->where('user_id', $user->id)->exists();
    }

    public function storedFiles(): HasMany
    {
        return $this->hasMany(StoredFile::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function subscription(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function hasEntitlement(string $feature): bool
    {
        return \App\Services\Billing\EntitlementService::can($this, $feature);
    }

    public function entitlements(): array
    {
        return \App\Services\Billing\EntitlementService::getEntitlements($this);
    }
}
