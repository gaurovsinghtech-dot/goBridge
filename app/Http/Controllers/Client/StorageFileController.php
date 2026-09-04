<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\StoredFile;
use App\Services\Storage\SecureDownloadService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StorageFileController extends Controller
{
    public function __construct(
        protected SecureDownloadService $downloadService
    ) {}

    /**
     * Preview private object (inline disposition).
     */
    public function preview(Request $request, string $uuid): StreamedResponse
    {
        $file = StoredFile::where('uuid', $uuid)->firstOrFail();

        // 1. Verify signature if signed preview URL
        $expires = $request->query('expires');
        $signature = $request->query('signature');

        if ($expires && $signature) {
            if (now()->timestamp > (int) $expires) {
                abort(403, 'Preview link has expired.');
            }

            $expected = hash_hmac('sha256', "{$file->uuid}:{$expires}", config('app.key'));
            if (! hash_equals($expected, (string) $signature)) {
                abort(403, 'Invalid signature.');
            }

            return $this->downloadService->streamDownload($file, null, 'inline');
        }

        // 2. Otherwise enforce authenticated user workspace isolation
        $user = $request->user();
        return $this->downloadService->streamDownload($file, $user, 'inline');
    }

    /**
     * Download private object (attachment disposition).
     */
    public function download(Request $request, string $uuid): StreamedResponse
    {
        $file = StoredFile::where('uuid', $uuid)->firstOrFail();
        $user = $request->user();

        return $this->downloadService->streamDownload($file, $user, 'attachment');
    }
}
