<?php

namespace App\Http\Controllers\Client\Crm;

use App\Http\Controllers\Controller;
use App\Models\CrmConnection;
use App\Models\CrmFieldMapping;
use App\Models\CrmSyncLog;
use App\Models\Workspace;
use App\Services\Crm\Connectors\CrmManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientCrmIntegrationController extends Controller
{
    public function __construct(
        protected CrmManager $crmManager
    ) {}

    protected function resolveWorkspace(Request $request): ?Workspace
    {
        $user = $request->user();
        return ($user->workspace_id ? Workspace::find($user->workspace_id) : null)
            ?? $user->workspace
            ?? $user->ownedWorkspaces()->first()
            ?? $user->workspaces()->first()
            ?? Workspace::first();
    }

    public function index(Request $request): Response|JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        abort_unless($workspace, 404, 'Workspace not found');

        $connections = CrmConnection::where('workspace_id', $workspace->id)->get()->keyBy('provider');
        $providers = $this->crmManager->getProviders();

        $formatted = [];
        foreach ($providers as $slug => $meta) {
            $conn = $connections->get($slug);
            $formatted[] = [
                'provider' => $slug,
                'label' => $meta['label'],
                'connected' => $conn?->isConnected() ?? false,
                'status' => $conn?->status ?? 'not_configured',
                'sync_direction' => $conn?->sync_direction ?? 'two_way',
                'sync_mode' => $conn?->sync_mode ?? 'realtime',
                'conflict_resolution' => $conn?->conflict_resolution ?? 'most_recent',
                'last_sync_at' => $conn?->last_sync_at?->toISOString(),
                'last_sync_status' => $conn?->last_sync_status,
                'last_sync_message' => $conn?->last_sync_message,
                'credentials' => $conn ? $conn->maskedCredentials() : [],
            ];
        }

        $logs = CrmSyncLog::where('workspace_id', $workspace->id)->latest('id')->limit(30)->get();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'providers' => $formatted,
                'logs' => $logs,
            ]);
        }

        return Inertia::render('client/Crm/Integrations', [
            'providers' => $formatted,
            'logs' => $logs,
        ]);
    }

    public function connect(Request $request, string $provider): JsonResponse|RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);
        abort_unless($workspace, 404);

        $driver = $this->crmManager->driver($provider);
        if (! $driver) {
            return response()->json(['success' => false, 'message' => "Invalid CRM provider {$provider}"], 422);
        }

        $credentials = $request->input('credentials', []);
        $syncDirection = $request->input('sync_direction', 'two_way');
        $syncMode = $request->input('sync_mode', 'realtime');
        $conflictResolution = $request->input('conflict_resolution', 'most_recent');

        // Test connection
        $testResult = $driver->testConnection($credentials);

        $conn = CrmConnection::firstOrNew([
            'workspace_id' => $workspace->id,
            'provider' => $provider,
        ]);

        // Merge existing credentials if masked
        $existing = $conn->credentials ?? [];
        $merged = $existing;
        foreach ($credentials as $k => $v) {
            if ($v === null || $v === '') continue;
            if (is_string($v) && preg_match('/^•+/', $v)) continue;
            $merged[$k] = $v;
        }

        $conn->fill([
            'name' => $driver->getLabel(),
            'credentials' => $merged,
            'status' => 'active',
            'sync_direction' => $syncDirection,
            'sync_mode' => $syncMode,
            'conflict_resolution' => $conflictResolution,
            'last_sync_message' => $testResult['message'] ?? 'Connected',
        ])->save();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "{$driver->getLabel()} connected successfully.",
                'test' => $testResult,
                'connection' => $conn,
            ]);
        }

        return back()->with('success', "{$driver->getLabel()} connected successfully.");
    }

    public function disconnect(Request $request, string $provider): JsonResponse|RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);
        abort_unless($workspace, 404);

        CrmConnection::where('workspace_id', $workspace->id)
            ->where('provider', $provider)
            ->update(['status' => 'paused']);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'CRM disconnected.']);
        }

        return back()->with('success', 'CRM disconnected.');
    }

    public function syncNow(Request $request, string $provider): JsonResponse|RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);
        abort_unless($workspace, 404);

        $conn = CrmConnection::where('workspace_id', $workspace->id)
            ->where('provider', $provider)
            ->first();

        if (! $conn) {
            return response()->json(['success' => false, 'message' => 'CRM connection not found.'], 404);
        }

        $res = $this->crmManager->pullFromCrm($conn);

        if ($request->wantsJson()) {
            return response()->json($res);
        }

        return back()->with($res['success'] ? 'success' : 'error', $res['message'] ?? 'Sync finished.');
    }

    public function testConnection(Request $request, string $provider): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $conn = $workspace ? CrmConnection::where('workspace_id', $workspace->id)->where('provider', $provider)->first() : null;

        $credentials = $request->input('credentials') ?? ($conn ? $conn->credentials : []);
        $result = $this->crmManager->test($provider, $credentials ?? []);

        return response()->json($result);
    }
}
