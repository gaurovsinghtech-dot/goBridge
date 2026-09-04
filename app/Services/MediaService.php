<?php

namespace App\Services;

use App\Models\Media;
use App\Models\StoredFile;
use App\Services\Storage\SecureUploadService;
use App\Services\Storage\StorageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaService
{
    public function __construct(
        private StorageManager $storageManager,
        private ?SecureUploadService $uploadService = null,
        private ?StorageService $storageService = null
    ) {
        $this->uploadService = $uploadService ?? app(SecureUploadService::class);
        $this->storageService = $storageService ?? app(StorageService::class);
    }

    public function store(
        UploadedFile $file,
        Model $owner,
        string $collection = 'default',
        ?string $disk = null
    ): Media {
        $workspaceId = (int) ($owner->workspace_id ?? ($owner instanceof \App\Models\User ? ($owner->current_workspace_id ?? $owner->workspace_id) : 1));
        $userId = $owner instanceof \App\Models\User ? $owner->id : null;

        $category = match ($collection) {
            'logos', 'logo' => 'logos',
            'avatars', 'avatar' => 'avatars',
            'whatsapp', 'chat' => 'whatsapp_media',
            'campaigns', 'broadcast' => 'campaign_media',
            'knowledge', 'documents' => 'ai_knowledge',
            default => 'crm_attachments',
        };

        // Use SecureUploadService for workspace scoping and S3 upload
        $storedFile = $this->uploadService->upload($file, $workspaceId, $userId, $category, [
            'mediable_type' => get_class($owner),
            'mediable_id' => $owner->getKey(),
            'collection' => $collection,
        ]);

        return Media::create([
            'mediable_type' => get_class($owner),
            'mediable_id' => $owner->getKey(),
            'disk' => $storedFile->disk,
            'path' => $storedFile->key,
            'filename' => $storedFile->original_name,
            'mime_type' => $storedFile->mime_type,
            'size_bytes' => $storedFile->size_bytes,
            'collection' => $collection,
            'meta' => [
                'stored_file_id' => $storedFile->id,
                'stored_file_uuid' => $storedFile->uuid,
                's3_key' => $storedFile->key,
                'category' => $category,
            ],
        ]);
    }

    /**
     * Get total storage used by owner in bytes.
     */
    public function usedBytes(Model $owner, string $collection = null): int
    {
        $workspaceId = (int) ($owner->workspace_id ?? ($owner instanceof \App\Models\User ? ($owner->current_workspace_id ?? $owner->workspace_id) : $owner->getKey()));
        
        $query = StoredFile::where('workspace_id', $workspaceId);

        if ($collection) {
            $query->where('category', $collection);
        }

        $bytes = (int) $query->sum('size_bytes');

        if ($bytes === 0) {
            $mediaQuery = Media::where('mediable_type', get_class($owner))
                ->where('mediable_id', $owner->getKey());
            if ($collection) {
                $mediaQuery->where('collection', $collection);
            }
            $bytes = (int) $mediaQuery->sum('size_bytes');
        }

        return $bytes;
    }

    /**
     * Get storage quota in bytes from plan limits (storage_gb).
     */
    public function quotaBytes(\App\Models\User $user): int
    {
        $plan = $user->effectiveSubscription()?->plan;
        $gb = $plan?->limitValue('storage_gb') ?? 1;

        return (int) ($gb * 1024 * 1024 * 1024);
    }
}
