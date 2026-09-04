<?php

namespace App\Services\Storage;

use App\Models\StoredFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FileCleanupService
{
    public function __construct(
        protected StorageService $storageService
    ) {}

    /**
     * Delete a stored file from storage and database.
     */
    public function deleteStoredFile(StoredFile $file, bool $forceDelete = false): bool
    {
        $key = $file->key;
        $disk = $file->disk;

        $this->storageService->ensureConfigured($disk);

        try {
            if (Storage::disk($disk)->exists($key)) {
                Storage::disk($disk)->delete($key);
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to delete S3 object [{$key}] during file cleanup: " . $e->getMessage());
        }

        return $forceDelete ? (bool) $file->forceDelete() : (bool) $file->delete();
    }

    /**
     * Get statistics on currently trashed or orphan files eligible for pruning.
     */
    public function getOrphanStats(): array
    {
        $trashedCount = StoredFile::onlyTrashed()->count();
        $trashedBytes = (int) StoredFile::onlyTrashed()->sum('size_bytes');

        return [
            'trashed_count' => $trashedCount,
            'trashed_bytes' => $trashedBytes,
            'trashed_formatted' => $this->storageService->formatBytes($trashedBytes),
        ];
    }

    /**
     * Prune orphaned and soft-deleted files older than threshold days.
     */
    public function pruneOrphanedFiles(int $daysOld = 7): int
    {
        $threshold = now()->subDays($daysOld);

        $orphaned = StoredFile::onlyTrashed()
            ->where('deleted_at', '<=', $threshold)
            ->get();

        $prunedCount = 0;
        foreach ($orphaned as $file) {
            $this->deleteStoredFile($file, true);
            $prunedCount++;
        }

        Log::info("Cleaned up {$prunedCount} orphaned/expired files from AWS S3 storage layer.");

        return $prunedCount;
    }
}
