<?php

namespace App\Services\Storage;

use App\Models\StoredFile;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class SecureUploadService
{
    /** Category-specific MIME type whitelists */
    public const ALLOWED_MIME_TYPES = [
        'logos' => [
            'image/jpeg', 'image/png', 'image/webp', 'image/svg+xml', 'image/gif',
        ],
        'avatars' => [
            'image/jpeg', 'image/png', 'image/webp', 'image/gif',
        ],
        'crm_attachments' => [
            'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain', 'text/csv', 'image/jpeg', 'image/png', 'image/webp', 'application/zip',
        ],
        'whatsapp_media' => [
            'image/jpeg', 'image/png', 'image/webp',
            'audio/ogg', 'audio/mpeg', 'audio/mp4', 'audio/wav', 'audio/aac', 'audio/amr',
            'video/mp4', 'video/3gpp',
            'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain', 'text/csv',
        ],
        'email_attachments' => [
            'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'image/jpeg', 'image/png', 'image/webp', 'text/plain', 'text/csv', 'application/zip',
        ],
        'campaign_media' => [
            'image/jpeg', 'image/png', 'image/webp',
            'video/mp4', 'audio/mpeg', 'audio/ogg', 'audio/mp4',
            'application/pdf',
        ],
        'ai_knowledge' => [
            'application/pdf', 'text/plain', 'text/markdown', 'text/csv',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/msword',
        ],
        'exports' => [
            'text/csv', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/pdf', 'application/json', 'application/zip',
        ],
        'reports' => [
            'application/pdf', 'text/csv', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ],
        'call_recordings' => [
            'audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/x-wav', 'audio/mp4', 'audio/aac',
        ],
        'voice_files' => [
            'audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/x-wav', 'audio/mp4', 'audio/aac',
        ],
        'general' => [
            'image/jpeg', 'image/png', 'image/webp', 'image/gif',
            'application/pdf', 'text/plain', 'text/csv', 'application/zip',
            'audio/mpeg', 'audio/ogg', 'video/mp4',
        ],
    ];

    /** Category-specific Maximum file sizes in bytes */
    public const MAX_FILE_SIZES = [
        'logos' => 5 * 1024 * 1024,           // 5 MB
        'avatars' => 5 * 1024 * 1024,         // 5 MB
        'crm_attachments' => 50 * 1024 * 1024, // 50 MB
        'whatsapp_media' => 64 * 1024 * 1024,  // 64 MB
        'email_attachments' => 25 * 1024 * 1024, // 25 MB
        'campaign_media' => 64 * 1024 * 1024,  // 64 MB
        'ai_knowledge' => 50 * 1024 * 1024,    // 50 MB
        'exports' => 100 * 1024 * 1024,        // 100 MB
        'reports' => 100 * 1024 * 1024,        // 100 MB
        'call_recordings' => 100 * 1024 * 1024, // 100 MB
        'voice_files' => 100 * 1024 * 1024,    // 100 MB
        'general' => 50 * 1024 * 1024,         // 50 MB
    ];

    public function __construct(
        protected StorageService $storageService
    ) {}

    /**
     * Upload an HTTP UploadedFile securely into S3.
     */
    public function upload(
        UploadedFile $file,
        int $workspaceId,
        ?int $userId = null,
        string $category = 'general',
        array $metadata = []
    ): StoredFile {
        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $size = $file->getSize();
        $originalName = $file->getClientOriginalName();
        $ext = strtolower($file->getClientOriginalExtension());

        $this->validateFile($mime, $size, $category);

        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            throw new RuntimeException("Could not read uploaded file contents from [{$originalName}].");
        }

        return $this->processAndStore(
            $contents,
            $originalName,
            $mime,
            $size,
            $ext,
            $workspaceId,
            $userId,
            $category,
            $metadata
        );
    }

    /**
     * Store raw binary contents directly into S3.
     */
    public function uploadRaw(
        string $contents,
        string $originalName,
        string $mimeType,
        int $workspaceId,
        ?int $userId = null,
        string $category = 'general',
        array $metadata = []
    ): StoredFile {
        $size = strlen($contents);
        $ext = pathinfo($originalName, PATHINFO_EXTENSION) ?: 'bin';

        $this->validateFile($mimeType, $size, $category);

        return $this->processAndStore(
            $contents,
            $originalName,
            $mimeType,
            $size,
            $ext,
            $workspaceId,
            $userId,
            $category,
            $metadata
        );
    }

    /**
     * Upload from an existing local filesystem path.
     */
    public function uploadFromPath(
        string $sourcePath,
        string $originalName,
        string $mimeType,
        int $workspaceId,
        ?int $userId = null,
        string $category = 'general',
        array $metadata = []
    ): StoredFile {
        if (! file_exists($sourcePath)) {
            throw new InvalidArgumentException("Source file not found at [{$sourcePath}].");
        }

        $contents = file_get_contents($sourcePath);
        if ($contents === false) {
            throw new RuntimeException("Failed to read file from [{$sourcePath}].");
        }

        return $this->uploadRaw(
            $contents,
            $originalName,
            $mimeType,
            $workspaceId,
            $userId,
            $category,
            $metadata
        );
    }

    /**
     * Core processing and S3 storage routine with workspace scoping and rollback protection.
     */
    protected function processAndStore(
        string $contents,
        string $originalName,
        string $mimeType,
        int $size,
        string $ext,
        int $workspaceId,
        ?int $userId,
        string $category,
        array $metadata
    ): StoredFile {
        // Quota check
        if (! $this->storageService->checkStorageQuota($workspaceId, $size)) {
            throw new RuntimeException("Workspace storage quota exceeded. Please upgrade your subscription plan to upload more files.");
        }

        // 1. Path traversal defense & safe filename generation
        $sanitizedOriginal = $this->sanitizeFilename($originalName);
        $safeExt = $this->sanitizeExtension($ext);
        $fileUuid = (string) Str::uuid();
        $safeFilename = "{$fileUuid}.{$safeExt}";

        // 2. Strict Workspace-Scoped S3 Path: workspaces/{workspace_id}/{category}/{filename}
        $safeCategory = $this->sanitizeCategory($category);
        $s3Key = "workspaces/{$workspaceId}/{$safeCategory}/{$safeFilename}";

        // 3. Checksum calculation
        $checksum = hash('sha256', $contents);

        $diskName = $this->storageService->diskName();
        $this->storageService->ensureConfigured($diskName);

        $s3Config = $this->storageService->getS3Config();
        $bucket = $s3Config['bucket'];
        $region = $s3Config['region'] ?? 'us-east-1';

        // 4. Upload object to S3 (or active disk)
        try {
            Storage::disk($diskName)->put($s3Key, $contents, 'private');
        } catch (\Throwable $e) {
            Log::error("S3 Object Upload Failed for [{$s3Key}]: " . $e->getMessage());
            throw new RuntimeException("AWS S3 object storage upload failed: " . $e->getMessage(), 0, $e);
        }

        // 5. Transactional record creation with rollback cleanup
        try {
            return DB::transaction(function () use (
                $fileUuid,
                $workspaceId,
                $userId,
                $diskName,
                $bucket,
                $region,
                $s3Key,
                $safeFilename,
                $sanitizedOriginal,
                $mimeType,
                $size,
                $safeCategory,
                $checksum,
                $metadata
            ) {
                return StoredFile::create([
                    'uuid' => $fileUuid,
                    'workspace_id' => $workspaceId,
                    'user_id' => $userId,
                    'disk' => $diskName,
                    'bucket' => $bucket,
                    'region' => $region,
                    'key' => $s3Key,
                    'filename' => $safeFilename,
                    'original_name' => $sanitizedOriginal,
                    'mime_type' => $mimeType,
                    'size_bytes' => $size,
                    'category' => $safeCategory,
                    'visibility' => 'private',
                    'checksum' => $checksum,
                    'metadata_json' => ! empty($metadata) ? $metadata : null,
                ]);
            });
        } catch (\Throwable $dbException) {
            // Rollback: delete written S3 object if database write fails
            Log::error("Database record creation failed for S3 object [{$s3Key}], cleaning up: " . $dbException->getMessage());
            try {
                Storage::disk($diskName)->delete($s3Key);
            } catch (\Throwable $cleanupException) {
                Log::warning("Failed to prune orphan S3 object [{$s3Key}] during rollback: " . $cleanupException->getMessage());
            }

            throw new RuntimeException("Database error recording stored file: " . $dbException->getMessage(), 0, $dbException);
        }
    }

    /**
     * Validate MIME type and file size against category bounds.
     */
    protected function validateFile(string $mimeType, int $sizeBytes, string $category): void
    {
        $allowedMimes = self::ALLOWED_MIME_TYPES[$category] ?? self::ALLOWED_MIME_TYPES['general'];

        // Normalize MIME type
        $cleanMime = strtolower(trim(explode(';', $mimeType)[0]));

        if (! in_array($cleanMime, $allowedMimes, true)) {
            throw new InvalidArgumentException("File type [{$cleanMime}] is not permitted for category [{$category}].");
        }

        $maxSize = self::MAX_FILE_SIZES[$category] ?? self::MAX_FILE_SIZES['general'];
        if ($sizeBytes > $maxSize) {
            $formattedMax = $this->storageService->formatBytes($maxSize);
            throw new InvalidArgumentException("File size exceeds the allowable limit of {$formattedMax} for [{$category}].");
        }
    }

    /**
     * Sanitize original filename to prevent path traversal, control chars, and injection.
     */
    public function sanitizeFilename(string $filename): string
    {
        // Strip directory separators, null bytes, and parent path tokens
        $clean = str_replace(['..', '/', '\\', "\0", '%00'], '', $filename);
        $clean = preg_replace('/[^\w\.\-\s\(\)\[\]]/u', '_', $clean);
        $clean = trim($clean, '. ');

        return $clean !== '' ? Str::limit($clean, 250, '') : 'unnamed_file';
    }

    /**
     * Sanitize extension.
     */
    public function sanitizeExtension(string $ext): string
    {
        $clean = strtolower(preg_replace('/[^a-z0-9]/i', '', $ext));

        return $clean ?: 'bin';
    }

    /**
     * Sanitize category to prevent path traversal in directory structure.
     */
    public function sanitizeCategory(string $category): string
    {
        $clean = strtolower(preg_replace('/[^a-z0-9_\-]/i', '', $category));

        return $clean ?: 'general';
    }
}
