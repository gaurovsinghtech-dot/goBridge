<?php

namespace App\Models;

use App\Services\Storage\StorageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class StoredFile extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'stored_files';

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
        'workspace_id',
        'user_id',
        'disk',
        'bucket',
        'region',
        'key',
        'filename',
        'original_name',
        'mime_type',
        'size_bytes',
        'category',
        'visibility',
        'checksum',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'metadata_json' => 'array',
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

    public function scopeForWorkspace(Builder $query, int $workspaceId): Builder
    {
        return $query->where('workspace_id', $workspaceId);
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    protected $appends = [
        'uploaded_by',
        'storage_disk',
        'storage_key',
        'size',
        'metadata',
        'formatted_size',
    ];

    public function getUploadedByAttribute(): ?int
    {
        return $this->user_id;
    }

    public function setUploadedByAttribute(?int $value): void
    {
        $this->attributes['user_id'] = $value;
    }

    public function getStorageDiskAttribute(): string
    {
        return $this->disk;
    }

    public function setStorageDiskAttribute(string $value): void
    {
        $this->attributes['disk'] = $value;
    }

    public function getStorageKeyAttribute(): string
    {
        return $this->key;
    }

    public function setStorageKeyAttribute(string $value): void
    {
        $this->attributes['key'] = $value;
    }

    public function getSizeAttribute(): int
    {
        return (int) $this->size_bytes;
    }

    public function setSizeAttribute(int $value): void
    {
        $this->attributes['size_bytes'] = $value;
    }

    public function getMetadataAttribute(): ?array
    {
        return $this->metadata_json;
    }

    public function setMetadataAttribute(?array $value): void
    {
        $this->attributes['metadata_json'] = $value ? json_encode($value) : null;
    }

    public function isOwnedByWorkspace(int $workspaceId): bool
    {
        return (int) $this->workspace_id === $workspaceId;
    }

    public function downloadUrl(int $minutes = 30): string
    {
        return app(StorageService::class)->downloadUrl($this, $minutes);
    }

    /**
     * Generate a temporary signed preview/download URL.
     */
    public function temporarySignedUrl(int $minutes = 30): string
    {
        return app(StorageService::class)->temporaryUrl($this, $minutes);
    }

    public function isPrivate(): bool
    {
        return $this->visibility !== 'public';
    }

    /**
     * Human-readable file size format.
     */
    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->size_bytes;
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' B';
    }
}
