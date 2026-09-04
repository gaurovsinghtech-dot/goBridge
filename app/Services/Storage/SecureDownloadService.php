<?php

namespace App\Services\Storage;

use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SecureDownloadService
{
    public function __construct(
        protected StorageService $storageService
    ) {}

    /**
     * Authorize user access to a StoredFile.
     */
    public function authorizeAccess(StoredFile $file, ?User $user): void
    {
        if (! $user) {
            throw new AuthorizationException("Authentication required to access this file.");
        }

        // Platform admins and superadmins have cross-tenant audit access
        if ($user->role === 'admin' || $user->role === 'superadmin') {
            return;
        }

        $userWorkspaceId = (int) ($user->current_workspace_id ?? $user->workspace_id);

        // Verify workspace matching
        if ($file->workspace_id !== $userWorkspaceId) {
            // Check if user belongs to the workspace via pivot
            $hasAccess = $user->workspaces()->where('workspaces.id', $file->workspace_id)->exists() ||
                         $user->ownedWorkspaces()->where('id', $file->workspace_id)->exists();

            if (! $hasAccess) {
                throw new AuthorizationException("Unauthorized access to workspace file.");
            }
        }
    }

    /**
     * Generate a temporary signed S3 URL after checking authorization.
     */
    public function getSignedUrl(StoredFile $file, User $user, int $minutes = 30): string
    {
        $this->authorizeAccess($file, $user);

        return $this->storageService->temporaryUrl($file, $minutes);
    }

    /**
     * Stream private object content directly through the application server with security headers.
     */
    public function streamDownload(StoredFile $file, ?User $user = null, string $disposition = 'inline'): StreamedResponse
    {
        if ($user) {
            $this->authorizeAccess($file, $user);
        }

        $diskName = $file->disk;
        $this->storageService->ensureConfigured($diskName);

        $disk = Storage::disk($diskName);

        if (! $disk->exists($file->key)) {
            abort(404, "File object not found on storage.");
        }

        $mimeType = $file->mime_type ?: 'application/octet-stream';
        $filename = $file->original_name ?: $file->filename;

        return response()->stream(
            function () use ($disk, $file) {
                $stream = $disk->readStream($file->key);
                if ($stream) {
                    fpassthru($stream);
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
            },
            200,
            [
                'Content-Type' => $mimeType,
                'Content-Length' => (string) $file->size_bytes,
                'Content-Disposition' => "{$disposition}; filename=\"" . addslashes($filename) . "\"",
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]
        );
    }
}
