<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppRelease;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AndroidAppManagementController extends Controller
{
    /**
     * Display Admin Android App Management Control Center.
     */
    public function index(): Response
    {
        $currentRelease = AppRelease::getLatestActive('android') ?? AppRelease::firstOrNew(['platform' => 'android']);
        $allReleases = AppRelease::where('platform', 'android')
            ->orderByDesc('created_at')
            ->get();

        $totalDownloads = AppRelease::where('platform', 'android')->sum('download_count');

        return Inertia::render('Admin/AppManagement/AndroidApp', [
            'release' => [
                'id' => $currentRelease->id,
                'platform' => $currentRelease->platform ?? 'android',
                'version' => $currentRelease->version ?? '1.0.0',
                'version_code' => $currentRelease->version_code ?? 100,
                'min_supported_version' => $currentRelease->min_supported_version ?? '1.0.0',
                'download_url' => $currentRelease->download_url ?? '',
                'file_path' => $currentRelease->file_path ?? '',
                'file_size_mb' => $currentRelease->file_size_mb ?? 28.50,
                'release_notes' => $currentRelease->release_notes ?? '',
                'force_update_required' => (bool) ($currentRelease->force_update_required ?? false),
                'is_active' => (bool) ($currentRelease->is_active ?? true),
                'download_count' => (int) ($currentRelease->download_count ?? 0),
                'published_at' => $currentRelease->published_at ? $currentRelease->published_at->format('M d, Y H:i') : null,
                'effective_download_url' => route('download.android-apk'),
                'qr_code_url' => route('download.android-apk.qr'),
            ],
            'stats' => [
                'total_downloads' => $totalDownloads,
                'active_version' => $currentRelease->version ?? '1.0.0',
                'force_update_active' => (bool) ($currentRelease->force_update_required ?? false),
            ],
            'releases_history' => $allReleases->map(fn ($r) => [
                'id' => $r->id,
                'version' => $r->version,
                'version_code' => $r->version_code,
                'file_size_mb' => $r->file_size_mb,
                'download_count' => $r->download_count,
                'is_active' => $r->is_active,
                'force_update' => $r->force_update_required,
                'published_at' => $r->published_at ? $r->published_at->format('M d, Y') : $r->created_at->format('M d, Y'),
            ]),
        ]);
    }

    /**
     * Update release settings & version metadata.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'version' => ['required', 'string', 'max:32'],
            'version_code' => ['required', 'integer', 'min:1'],
            'min_supported_version' => ['required', 'string', 'max:32'],
            'download_url' => ['nullable', 'string', 'max:2048'],
            'file_size_mb' => ['nullable', 'numeric', 'min:0.1'],
            'release_notes' => ['nullable', 'string'],
            'force_update_required' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $release = AppRelease::getLatestActive('android') ?? new AppRelease(['platform' => 'android']);

        $release->fill([
            'platform' => 'android',
            'version' => $validated['version'],
            'version_code' => (int) $validated['version_code'],
            'min_supported_version' => $validated['min_supported_version'],
            'download_url' => $validated['download_url'] ?? null,
            'file_size_mb' => isset($validated['file_size_mb']) ? (float) $validated['file_size_mb'] : $release->file_size_mb,
            'release_notes' => $validated['release_notes'] ?? null,
            'force_update_required' => (bool) ($validated['force_update_required'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'published_at' => now(),
        ]);
        $release->save();

        return back()->with('success', 'Android App release configuration updated successfully!');
    }

    /**
     * Handle direct APK file upload.
     */
    public function uploadApk(Request $request): RedirectResponse
    {
        $request->validate([
            'apk_file' => ['required', 'file', 'max:153600'], // Max 150MB
        ]);

        $file = $request->file('apk_file');
        $filename = 'growbridge-connect-v' . time() . '.apk';
        $path = $file->storeAs('apk', $filename, 'public');

        $fileSizeMb = round($file->getSize() / (1024 * 1024), 2);

        $release = AppRelease::getLatestActive('android') ?? new AppRelease(['platform' => 'android']);
        $release->file_path = $path;
        $release->file_size_mb = $fileSizeMb;
        $release->download_url = null; // Reset external URL to prioritize uploaded file
        $release->save();

        return back()->with('success', "APK uploaded successfully ({$fileSizeMb} MB)!");
    }
}
