<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Modules\Voice\Models\TelephonyApiLog;
use App\Modules\Voice\Models\TelephonyWebhookLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SystemLogsController extends Controller
{
    public function index(Request $request): Response
    {
        $category = $request->input('category', 'all');

        $auditLogs = AuditLog::with('user:id,name,email')
            ->latest('created_at')
            ->take(30)
            ->get()
            ->map(fn ($log) => [
                'id' => 'audit_'.$log->id,
                'category' => 'Security & Auth',
                'level' => 'info',
                'action' => $log->action,
                'user' => $log->user?->name ?? 'System',
                'ip' => $log->ip_address,
                'metadata' => $log->metadata,
                'created_at' => $log->created_at->toIso8601String(),
            ]);

        $apiLogs = TelephonyApiLog::with('workspace:id,name')
            ->latest('created_at')
            ->take(30)
            ->get()
            ->map(fn ($log) => [
                'id' => 'api_'.$log->id,
                'category' => 'Telephony API',
                'level' => $log->success ? 'info' : 'error',
                'action' => "{$log->http_method} {$log->endpoint}",
                'organization' => $log->workspace?->name ?? "Org #{$log->workspace_id}",
                'response_time' => "{$log->response_time_ms}ms",
                'status_code' => $log->status_code,
                'created_at' => $log->created_at->toIso8601String(),
            ]);

        $webhookLogs = TelephonyWebhookLog::with('workspace:id,name')
            ->latest('created_at')
            ->take(30)
            ->get()
            ->map(fn ($log) => [
                'id' => 'webhook_'.$log->id,
                'category' => 'Webhooks',
                'level' => $log->status === 'processed' ? 'info' : 'warning',
                'action' => $log->event_name,
                'organization' => $log->workspace?->name ?? "Org #{$log->workspace_id}",
                'call_id' => $log->call_id,
                'status' => $log->status,
                'created_at' => $log->created_at->toIso8601String(),
            ]);

        return Inertia::render('Admin/SystemLogs', [
            'auditLogs' => $auditLogs,
            'apiLogs' => $apiLogs,
            'webhookLogs' => $webhookLogs,
            'activeCategory' => $category,
        ]);
    }
}
