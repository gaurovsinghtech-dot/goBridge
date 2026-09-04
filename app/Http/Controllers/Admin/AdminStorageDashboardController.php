<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StoredFile;
use App\Models\Workspace;
use App\Services\Storage\FileCleanupService;
use App\Services\Storage\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminStorageDashboardController extends Controller
{
    public function __construct(
        protected StorageService $storageService,
        protected FileCleanupService $cleanupService
    ) {}

    /**
     * Display AWS S3 & Platform Storage Control Center
     */
    public function index(Request $request): Response
    {
        $stats = $this->storageService->getStorageStats();
        $orphanStats = $this->cleanupService->getOrphanStats();

        // Recent 15 uploaded files across platform
        $recentFiles = StoredFile::with(['workspace:id,name', 'user:id,name'])
            ->latest()
            ->take(15)
            ->get()
            ->map(function ($file) {
                return [
                    'id' => $file->id,
                    'uuid' => $file->uuid,
                    'filename' => $file->filename,
                    'original_name' => $file->original_name,
                    'category' => $file->category,
                    'mime_type' => $file->mime_type,
                    'size' => $file->size_bytes,
                    'formatted_size' => $file->formatted_size,
                    'disk' => $file->disk,
                    'bucket' => $file->bucket,
                    'key' => $file->key,
                    'workspace_name' => $file->workspace?->name ?? 'Unknown',
                    'uploaded_by_name' => $file->user?->name ?? 'System',
                    'created_at' => $file->created_at?->format('M d, Y H:i'),
                ];
            });

        // Top 10 workspaces by storage consumption with quota percentages
        $workspaces = Workspace::with('client.activeSubscription.plan')
            ->has('storedFiles')
            ->get()
            ->map(function ($ws) {
                $usage = $this->storageService->workspaceStorageUsage($ws->id);
                $plan = $ws->client?->effectivePlan() ?? $ws->client?->activePlan();
                return [
                    'id' => $ws->id,
                    'name' => $ws->name,
                    'client_name' => $ws->client?->name ?? 'N/A',
                    'plan_name' => $plan?->name ?? 'No Plan',
                    'used_bytes' => $usage['bytes'],
                    'used_formatted' => $usage['formatted'],
                    'quota_formatted' => $usage['quota_formatted'],
                    'usage_percentage' => $usage['usage_percentage'],
                    'object_count' => $usage['object_count'],
                ];
            })
            ->sortByDesc('used_bytes')
            ->values()
            ->take(10);

        return Inertia::render('Admin/Storage/Index', [
            'stats' => $stats,
            'orphanStats' => $orphanStats,
            'recentFiles' => $recentFiles,
            'workspaces' => $workspaces,
        ]);
    }

    /**
     * Trigger on-demand orphan & soft-deleted file cleanup
     */
    public function pruneOrphans(Request $request): RedirectResponse
    {
        $days = (int) $request->input('days', 7);
        $pruned = $this->cleanupService->pruneOrphanedFiles($days);

        return back()->with('success', "Successfully pruned {$pruned} orphaned/expired files from AWS S3 storage.");
    }

    /**
     * Test live S3 connectivity from Storage Dashboard
     */
    public function testConnection(Request $request): JsonResponse
    {
        $result = $this->storageService->testConnection();

        return response()->json($result);
    }
}
