<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\AI\Models\AiChatbot;
use App\Modules\AI\Models\AiKbDocument;
use App\Modules\AI\Models\AiKnowledgeBase;
use App\Modules\AI\Models\AiProviderConfig;
use App\Modules\AI\Models\AiRun;
use App\Modules\Integrations\Models\IntegrationConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AiDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        // 1. Workspace-level custom provider counts
        $workspaceCounts = AiProviderConfig::where('enabled', true)
            ->select('provider', DB::raw('count(*) as count'))
            ->groupBy('provider')
            ->pluck('count', 'provider')
            ->toArray();

        // 2. Platform-wide AI Provider Integration Configs
        $aiIntegrations = IntegrationConfig::whereIn('provider', [
            'ai_providers',
            'llm_openai_default',
            'llm_anthropic_default',
            'llm_gemini_default',
        ])->where('enabled', true)->get();

        $aiCreds = [];
        foreach ($aiIntegrations as $cfg) {
            if (is_array($cfg->credentials)) {
                $aiCreds = array_merge($aiCreds, $cfg->credentials);
            }
        }

        $providers = ['openai', 'anthropic', 'gemini'];
        $providerStats = [];

        foreach ($providers as $p) {
            $wsCount = $workspaceCounts[$p] ?? 0;

            // System .env or IntegrationConfig key check
            $envKey = env(strtoupper($p).'_API_KEY') ?? env(strtoupper($p).'_KEY');
            $integrationKey = $aiCreds["{$p}_api_key"] ?? $aiCreds['api_key'] ?? null;
            $hasPlatformConfig = ! empty($envKey) || ! empty($integrationKey) || ! empty(config("services.{$p}.api_key"));

            // Check if chatbots or voice agents are using this provider
            $hasChatbots = AiChatbot::where('provider', $p)->exists();

            if ($wsCount > 0) {
                $providerStats[$p] = $wsCount;
            } elseif ($hasPlatformConfig || $hasChatbots) {
                $providerStats[$p] = 9999; // Platform Default
            } else {
                $providerStats[$p] = 0;
            }
        }

        // Total workspaces that have at least one enabled/active AI provider
        $configuredWorkspaces = AiProviderConfig::where('enabled', true)
            ->distinct('workspace_id')
            ->count('workspace_id');

        if ($configuredWorkspaces === 0 && array_filter($providerStats, fn ($c) => $c > 0)) {
            $configuredWorkspaces = \App\Models\Workspace::count() ?: 1;
        }

        // Qdrant status
        $qdrantConfigured = ! empty(config('services.qdrant.url'));
        $qdrantHealthy = false;
        if ($qdrantConfigured) {
            try {
                $resp = \Illuminate\Support\Facades\Http::timeout(3)
                    ->get(rtrim(config('services.qdrant.url'), '/').'/healthz');
                $qdrantHealthy = $resp->successful();
            } catch (\Throwable) {
                $qdrantHealthy = false;
            }
        }

        // Usage stats (last 30 days)
        $usageStats = AiRun::where('created_at', '>=', now()->subDays(30))
            ->select(
                DB::raw('SUM(prompt_tokens + completion_tokens) as total_tokens'),
                DB::raw('COUNT(*) as total_runs'),
                DB::raw('SUM(CASE WHEN status = "error" THEN 1 ELSE 0 END) as error_runs'),
                DB::raw('AVG(latency_ms) as avg_latency_ms')
            )
            ->first();

        // Top models used
        $topModels = AiRun::where('created_at', '>=', now()->subDays(30))
            ->whereNotNull('model')
            ->select('model', DB::raw('count(*) as runs'), DB::raw('sum(prompt_tokens + completion_tokens) as tokens'))
            ->groupBy('model')
            ->orderByDesc('runs')
            ->limit(5)
            ->get();

        // Daily token usage (last 14 days)
        $dailyUsage = AiRun::where('created_at', '>=', now()->subDays(14))
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(prompt_tokens + completion_tokens) as tokens'),
                DB::raw('COUNT(*) as runs')
            )
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        // KB and document counts
        $kbCount = AiKnowledgeBase::count();
        $documentStats = AiKbDocument::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $chatbotCount = AiChatbot::count();
        $activeChatbotCount = AiChatbot::where('enabled', true)->count();

        return Inertia::render('Admin/AI/Dashboard', [
            'providerStats' => $providerStats,
            'configuredWorkspaces' => $configuredWorkspaces,
            'qdrant' => [
                'configured' => $qdrantConfigured,
                'healthy' => $qdrantHealthy,
                'url' => $qdrantConfigured ? config('services.qdrant.url') : null,
            ],
            'usage' => [
                'total_tokens' => (int) ($usageStats->total_tokens ?? 0),
                'total_runs' => (int) ($usageStats->total_runs ?? 0),
                'error_runs' => (int) ($usageStats->error_runs ?? 0),
                'avg_latency_ms' => (int) ($usageStats->avg_latency_ms ?? 0),
            ],
            'topModels' => $topModels,
            'dailyUsage' => $dailyUsage,
            'kbCount' => $kbCount,
            'documentStats' => $documentStats,
            'chatbotCount' => $chatbotCount,
            'activeChatbotCount' => $activeChatbotCount,
        ]);
    }
}
