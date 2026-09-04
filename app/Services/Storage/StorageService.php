<?php

namespace App\Services\Storage;

use App\Models\StoredFile;
use App\Models\Workspace;
use App\Modules\Integrations\Models\IntegrationConfig;
use App\Services\StorageManager;
use Aws\S3\S3Client;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StorageService
{
    public function __construct(
        protected StorageManager $storageManager
    ) {}

    /**
     * Get the active filesystem disk instance.
     */
    public function disk(?string $diskName = null): Filesystem
    {
        $name = $diskName ?? $this->diskName();
        $this->ensureConfigured($name);

        return Storage::disk($name);
    }

    /**
     * Get the active disk name.
     */
    public function diskName(): string
    {
        // 1. If AWS S3 environment variables are present and valid, prioritize S3
        if ($this->hasEnvS3Config()) {
            return 's3';
        }

        // 2. Check database IntegrationConfig for storage_s3 or other cloud providers
        $activeFromManager = $this->storageManager->diskName();
        if ($activeFromManager !== 'public') {
            return $activeFromManager;
        }

        // 3. Check if default filesystem disk is set to s3
        if (config('filesystems.default') === 's3' && $this->isS3Configured()) {
            return 's3';
        }

        return 'local';
    }

    /**
     * Determine if AWS S3 is fully configured (via env or DB).
     */
    public function isS3Configured(): bool
    {
        $cfg = $this->getS3Config();

        return ! empty($cfg['key']) && ! empty($cfg['secret']) && ! empty($cfg['bucket']) && ! empty($cfg['region']);
    }

    /**
     * Check if standard AWS environment variables are set.
     */
    public function hasEnvS3Config(): bool
    {
        return ! empty(env('AWS_ACCESS_KEY_ID')) &&
               ! empty(env('AWS_SECRET_ACCESS_KEY')) &&
               ! empty(env('AWS_BUCKET'));
    }

    /**
     * Retrieve resolved S3 configuration (Env merged with DB IntegrationConfig).
     */
    public function getS3Config(): array
    {
        $dbConfig = IntegrationConfig::forProvider('storage_s3');
        $creds = $dbConfig?->credentials ?? [];

        return [
            'key' => env('AWS_ACCESS_KEY_ID') ?: ($creds['key'] ?? null),
            'secret' => env('AWS_SECRET_ACCESS_KEY') ?: ($creds['secret'] ?? null),
            'region' => env('AWS_DEFAULT_REGION') ?: ($creds['region'] ?? 'us-east-1'),
            'bucket' => env('AWS_BUCKET') ?: ($creds['bucket'] ?? null),
            'url' => env('AWS_URL') ?: ($creds['url'] ?? null),
            'endpoint' => env('AWS_ENDPOINT') ?: ($creds['endpoint'] ?? null),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'directory_prefix' => $creds['directory_prefix'] ?? '',
        ];
    }

    /**
     * Test real AWS S3 connectivity by performing a temporary put, exists, and delete operation.
     */
    public function testConnection(?array $overrideConfig = null): array
    {
        $config = $overrideConfig ?? $this->getS3Config();

        if (empty($config['key']) || empty($config['secret']) || empty($config['bucket'])) {
            return [
                'ok' => false,
                'status' => 'Not Connected',
                'message' => 'AWS S3 credentials (Access Key, Secret Key, Bucket) are incomplete.',
                'tested_at' => now()->toIso8601String(),
            ];
        }

        $region = $config['region'] ?? 'us-east-1';
        $bucket = $config['bucket'] ?? '';
        if (app()->environment('testing')) {
            $testDiskName = 's3';
        } else {
            $testDiskName = 's3_test_' . Str::random(8);
            Config::set("filesystems.disks.{$testDiskName}", [
                'driver' => 's3',
                'key' => $config['key'],
                'secret' => $config['secret'],
                'region' => $region,
                'bucket' => $bucket,
                'url' => $config['url'] ?? null,
                'endpoint' => $config['endpoint'] ?? null,
                'use_path_style_endpoint' => $config['use_path_style_endpoint'] ?? false,
                'visibility' => 'private',
                'throw' => true,
                'http' => ['connect_timeout' => 5, 'timeout' => 10],
            ]);
        }

        $testKey = '.growbridge-test/ping-' . Str::uuid() . '.txt';

        try {
            $disk = Storage::disk($testDiskName);
            $disk->put($testKey, 'growbridge-s3-connectivity-check-' . time(), 'private');
            $exists = $disk->exists($testKey);
            $disk->delete($testKey);

            if ($exists) {
                // Update integration config audit timestamps if DB record exists
                $dbConfig = IntegrationConfig::forProvider('storage_s3');
                if ($dbConfig) {
                    $dbConfig->update([
                        'last_tested_at' => now(),
                        'last_test_status' => 'ok',
                        'last_test_message' => "Successfully connected to S3 bucket [{$bucket}] in region [{$region}].",
                    ]);
                }

                return [
                    'ok' => true,
                    'status' => 'Connected',
                    'bucket' => $bucket,
                    'region' => $region,
                    'message' => "Successfully verified AWS S3 bucket [{$bucket}] ({$region}). Put, read, and delete tests passed.",
                    'tested_at' => now()->toIso8601String(),
                ];
            }

            return [
                'ok' => false,
                'status' => 'Not Connected',
                'message' => "Write test did not confirm object existence in bucket [{$bucket}].",
                'tested_at' => now()->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            Log::error("AWS S3 Connection Test Failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            $dbConfig = IntegrationConfig::forProvider('storage_s3');
            if ($dbConfig) {
                $dbConfig->update([
                    'last_tested_at' => now(),
                    'last_test_status' => 'fail',
                    'last_test_message' => "S3 Connection Error: " . $e->getMessage(),
                ]);
            }

            return [
                'ok' => false,
                'status' => 'Not Connected',
                'message' => "AWS S3 Connection Error: " . $e->getMessage(),
                'tested_at' => now()->toIso8601String(),
            ];
        } finally {
            if ($testDiskName !== 's3') {
                Storage::forgetDisk($testDiskName);
            }
        }
    }

    /**
     * Upload a file into the storage layer with workspace scoping.
     */
    public function upload(
        Workspace|int $workspace,
        \Illuminate\Http\UploadedFile|string $file,
        string $category = 'general',
        \App\Models\User|int|null $user = null,
        array $metadata = []
    ): StoredFile {
        $workspaceId = $workspace instanceof Workspace ? $workspace->id : $workspace;
        $userId = $user instanceof \App\Models\User ? $user->id : $user;

        $uploader = app(SecureUploadService::class);

        if ($file instanceof \Illuminate\Http\UploadedFile) {
            return $uploader->upload($file, $workspaceId, $userId, $category, $metadata);
        }

        if (is_file($file)) {
            $originalName = basename($file);
            $mimeType = mime_content_type($file) ?: 'application/octet-stream';
            return $uploader->uploadFromPath($file, $originalName, $mimeType, $workspaceId, $userId, $category, $metadata);
        }

        // Raw string contents
        $originalName = $metadata['filename'] ?? ('file_' . Str::random(8) . '.dat');
        $mimeType = $metadata['mime_type'] ?? 'application/octet-stream';
        return $uploader->uploadRaw($file, $originalName, $mimeType, $workspaceId, $userId, $category, $metadata);
    }

    /**
     * Generate a temporary signed download URL.
     */
    public function downloadUrl(StoredFile $file, int $minutes = 30): string
    {
        return $this->temporaryUrl($file, $minutes);
    }

    /**
     * Generate a temporary signed URL for authorized access to a private object.
     */
    public function temporaryUrl(StoredFile $file, int $minutes = 30): string
    {
        $diskName = $file->disk;
        $this->ensureConfigured($diskName);

        $disk = Storage::disk($diskName);

        // For S3 disks, use native temporary signed URLs with expiration
        if ($diskName === 's3' || $diskName === 'do_spaces' || $diskName === 'wasabi') {
            try {
                return $disk->temporaryUrl($file->key, now()->addMinutes($minutes));
            } catch (\Throwable $e) {
                Log::warning("Native temporaryUrl failed for [{$file->key}], falling back to signed route: " . $e->getMessage());
            }
        }

        // Fallback for local disk or proxy: signed route endpoint
        return route('client.storage.preview', [
            'uuid' => $file->uuid,
            'expires' => now()->addMinutes($minutes)->timestamp,
            'signature' => hash_hmac('sha256', "{$file->uuid}:" . now()->addMinutes($minutes)->timestamp, config('app.key')),
        ]);
    }

    /**
     * Determine if an object exists on storage.
     */
    public function exists(StoredFile|string $fileOrKey, ?string $diskName = null): bool
    {
        if ($fileOrKey instanceof StoredFile) {
            $key = $fileOrKey->key;
            $disk = $fileOrKey->disk;
        } else {
            $key = $fileOrKey;
            $disk = $diskName ?? $this->diskName();
        }

        $this->ensureConfigured($disk);

        try {
            return Storage::disk($disk)->exists($key);
        } catch (\Throwable $e) {
            Log::warning("Storage exists check failed for [{$key}] on [{$disk}]: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get the size of an object in bytes.
     */
    public function size(StoredFile|string $fileOrKey, ?string $diskName = null): int
    {
        if ($fileOrKey instanceof StoredFile) {
            return (int) $fileOrKey->size_bytes;
        }

        $key = $fileOrKey;
        $disk = $diskName ?? $this->diskName();
        $this->ensureConfigured($disk);

        try {
            return (int) Storage::disk($disk)->size($key);
        } catch (\Throwable $e) {
            Log::warning("Storage size check failed for [{$key}] on [{$disk}]: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Move an object from one storage key to another.
     */
    public function move(StoredFile|string $from, string $toKey, ?string $diskName = null): bool
    {
        if ($from instanceof StoredFile) {
            $fromKey = $from->key;
            $disk = $from->disk;
        } else {
            $fromKey = $from;
            $disk = $diskName ?? $this->diskName();
        }

        $this->ensureConfigured($disk);

        try {
            $moved = Storage::disk($disk)->move($fromKey, $toKey);
            if ($moved && $from instanceof StoredFile) {
                $from->update(['key' => $toKey]);
            }
            return $moved;
        } catch (\Throwable $e) {
            Log::error("Storage move failed from [{$fromKey}] to [{$toKey}] on [{$disk}]: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Copy an object to a new storage key.
     */
    public function copy(StoredFile|string $from, string $toKey, ?string $diskName = null): bool
    {
        if ($from instanceof StoredFile) {
            $fromKey = $from->key;
            $disk = $from->disk;
        } else {
            $fromKey = $from;
            $disk = $diskName ?? $this->diskName();
        }

        $this->ensureConfigured($disk);

        try {
            return Storage::disk($disk)->copy($fromKey, $toKey);
        } catch (\Throwable $e) {
            Log::error("Storage copy failed from [{$fromKey}] to [{$toKey}] on [{$disk}]: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete an object from disk and optionally soft-delete the model.
     */
    public function delete(StoredFile|string $fileOrKey, ?string $diskName = null): bool
    {
        if ($fileOrKey instanceof StoredFile) {
            $key = $fileOrKey->key;
            $disk = $fileOrKey->disk;
            $fileOrKey->delete();
        } else {
            $key = $fileOrKey;
            $disk = $diskName ?? $this->diskName();
        }

        $this->ensureConfigured($disk);

        try {
            return Storage::disk($disk)->delete($key);
        } catch (\Throwable $e) {
            Log::warning("Failed to delete object [{$key}] on disk [{$disk}]: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if a workspace has enough quota remaining to store additional bytes.
     */
    public function checkStorageQuota(int $workspaceId, int $additionalBytes = 0): bool
    {
        $workspace = Workspace::with('client.activeSubscription.plan')->find($workspaceId);
        if (! $workspace) {
            return true;
        }

        $plan = $workspace->client?->effectivePlan() ?? $workspace->client?->activePlan();
        if (! $plan) {
            return true;
        }

        // Check plan limits (storage_mb or storage_gb)
        $limits = $plan->limits ?? [];
        $maxBytes = null;

        if (isset($limits['storage_gb'])) {
            $maxBytes = ((int) $limits['storage_gb']) * 1024 * 1024 * 1024;
        } elseif (isset($limits['storage_mb'])) {
            $maxBytes = ((int) $limits['storage_mb']) * 1024 * 1024;
        } elseif (isset($limits['storage'])) {
            $maxBytes = ((int) $limits['storage']) * 1024 * 1024;
        }

        if ($maxBytes === null || $maxBytes <= 0) {
            return true; // Unlimited
        }

        $currentUsage = (int) StoredFile::where('workspace_id', $workspaceId)->sum('size_bytes');

        return ($currentUsage + $additionalBytes) <= $maxBytes;
    }

    /**
     * Get workspace-specific storage usage with quota details.
     */
    public function workspaceStorageUsage(int $workspaceId): array
    {
        $bytes = (int) StoredFile::where('workspace_id', $workspaceId)->sum('size_bytes');
        $count = (int) StoredFile::where('workspace_id', $workspaceId)->count();

        $workspace = Workspace::with('client.activeSubscription.plan')->find($workspaceId);
        $plan = $workspace?->client?->effectivePlan() ?? $workspace?->client?->activePlan();
        $limits = $plan?->limits ?? [];

        $quotaBytes = null;
        if (isset($limits['storage_gb'])) {
            $quotaBytes = ((int) $limits['storage_gb']) * 1024 * 1024 * 1024;
        } elseif (isset($limits['storage_mb'])) {
            $quotaBytes = ((int) $limits['storage_mb']) * 1024 * 1024;
        } elseif (isset($limits['storage'])) {
            $quotaBytes = ((int) $limits['storage']) * 1024 * 1024;
        }

        $percentage = ($quotaBytes && $quotaBytes > 0) ? min(100, round(($bytes / $quotaBytes) * 100, 1)) : 0;

        return [
            'workspace_id' => $workspaceId,
            'bytes' => $bytes,
            'formatted' => $this->formatBytes($bytes),
            'quota_bytes' => $quotaBytes,
            'quota_formatted' => $quotaBytes ? $this->formatBytes($quotaBytes) : 'Unlimited',
            'usage_percentage' => $percentage,
            'object_count' => $count,
        ];
    }

    /**
     * Get platform-wide storage statistics for Admin reporting.
     */
    public function getStorageStats(): array
    {
        $totalBytes = (int) StoredFile::sum('size_bytes');
        $totalObjects = (int) StoredFile::count();

        // Breakdown by category
        $byCategory = StoredFile::select('category', DB::raw('count(*) as count'), DB::raw('sum(size_bytes) as total_bytes'))
            ->groupBy('category')
            ->get()
            ->mapWithKeys(function ($row) {
                return [$row->category => [
                    'count' => (int) $row->count,
                    'bytes' => (int) $row->total_bytes,
                    'formatted' => $this->formatBytes((int) $row->total_bytes),
                ]];
            })->toArray();

        // Breakdown by top workspaces
        $byWorkspace = StoredFile::with('workspace:id,name')
            ->select('workspace_id', DB::raw('count(*) as count'), DB::raw('sum(size_bytes) as total_bytes'))
            ->groupBy('workspace_id')
            ->orderByDesc('total_bytes')
            ->take(10)
            ->get()
            ->map(function ($row) {
                return [
                    'workspace_id' => $row->workspace_id,
                    'workspace_name' => $row->workspace?->name ?? 'Unknown Workspace',
                    'count' => (int) $row->count,
                    'bytes' => (int) $row->total_bytes,
                    'formatted' => $this->formatBytes((int) $row->total_bytes),
                ];
            })->toArray();

        $s3Config = $this->getS3Config();
        $dbConfig = IntegrationConfig::forProvider('storage_s3');

        $status = $this->isS3Configured() ? ($dbConfig?->last_test_status === 'ok' ? 'Connected' : ($dbConfig?->last_tested_at ? 'Connection Error' : 'Configured (Untested)')) : 'Not Connected';

        return [
            'provider' => 'AWS S3',
            'status' => $status,
            'is_connected' => $status === 'Connected',
            'is_configured' => $this->isS3Configured(),
            'bucket' => $s3Config['bucket'] ?? 'Not Configured',
            'region' => $s3Config['region'] ?? 'us-east-1',
            'directory_prefix' => $s3Config['directory_prefix'] ?? '',
            'total_bytes' => $totalBytes,
            'total_storage_formatted' => $this->formatBytes($totalBytes),
            'total_objects' => $totalObjects,
            'categories' => $byCategory,
            'top_workspaces' => $byWorkspace,
            'last_tested_at' => $dbConfig?->last_tested_at?->format('M d, Y H:i:s'),
            'last_test_message' => $dbConfig?->last_test_message,
        ];
    }

    /**
     * Ensure disk configuration is injected if needed.
     */
    public function ensureConfigured(string $diskName): void
    {
        if (app()->environment('testing')) {
            return;
        }

        if ($diskName === 's3') {
            $cfg = $this->getS3Config();
            Config::set('filesystems.disks.s3', [
                'driver' => 's3',
                'key' => $cfg['key'],
                'secret' => $cfg['secret'],
                'region' => $cfg['region'] ?? 'us-east-1',
                'bucket' => $cfg['bucket'],
                'url' => $cfg['url'],
                'endpoint' => $cfg['endpoint'],
                'use_path_style_endpoint' => $cfg['use_path_style_endpoint'] ?? false,
                'visibility' => 'private',
                'throw' => true,
            ]);
        } elseif ($diskName !== 'local' && $diskName !== 'public') {
            $this->storageManager->ensureDiskReady($diskName);
        }
    }

    public function formatBytes(int $bytes): string
    {
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
