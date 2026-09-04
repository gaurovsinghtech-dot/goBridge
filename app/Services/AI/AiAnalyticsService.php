<?php

namespace App\Services\AI;

use App\Modules\AI\Models\AiChatbot;
use App\Modules\AI\Models\AiDailyStat;
use App\Modules\AI\Models\AiKnowledgeBase;
use App\Modules\AI\Models\AiUnknownQuestion;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AiAnalyticsService
{
    /**
     * Resolve date range boundaries.
     */
    public function resolveDateRange(string $period, ?string $startDate = null, ?string $endDate = null): array
    {
        $now = Carbon::now();

        return match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            '7d' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            '90d' => [$now->copy()->subDays(89)->startOfDay(), $now->copy()->endOfDay()],
            'custom' => [
                $startDate ? Carbon::parse($startDate)->startOfDay() : $now->copy()->subDays(29)->startOfDay(),
                $endDate ? Carbon::parse($endDate)->endOfDay() : $now->copy()->endOfDay(),
            ],
            default => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()], // 30d
        };
    }

    /**
     * Get primary summary KPIs for the dashboard.
     */
    public function getOverview(
        int $workspaceId,
        string $period = '30d',
        ?int $agentId = null,
        ?string $channel = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        [$start, $end] = $this->resolveDateRange($period, $startDate, $endDate);

        // Fetch from ai_daily_stats
        $statsQuery = AiDailyStat::where('workspace_id', $workspaceId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()]);

        if ($agentId) {
            $statsQuery->where('ai_agent_id', $agentId);
        }
        if ($channel && $channel !== 'all') {
            $statsQuery->where('channel', $channel);
        }

        $aggregated = $statsQuery->selectRaw('
            SUM(conversations) as total_conversations,
            SUM(ai_messages) as total_ai_messages,
            SUM(resolved) as total_resolved,
            SUM(handoffs) as total_handoffs,
            SUM(failed) as total_failed,
            AVG(avg_response_ms) as avg_response_ms,
            SUM(positive_feedback) as positive_feedback,
            SUM(negative_feedback) as negative_feedback,
            SUM(input_tokens) as total_input_tokens,
            SUM(output_tokens) as total_output_tokens
        ')->first();

        $totalConvs = (int) ($aggregated?->total_conversations ?? 0);
        $totalAiMsgs = (int) ($aggregated?->total_ai_messages ?? 0);
        $resolved = (int) ($aggregated?->total_resolved ?? 0);
        $handoffs = (int) ($aggregated?->total_handoffs ?? 0);
        $avgMs = (int) ($aggregated?->avg_response_ms ?? 1450);

        // If daily stats table is empty, compute directly from live conversations
        if ($totalConvs === 0) {
            $convQuery = Conversation::where('workspace_id', $workspaceId)
                ->whereBetween('created_at', [$start, $end]);

            if ($channel && $channel !== 'all') {
                $convQuery->where('channel', $channel);
            }

            $liveTotal = $convQuery->count();
            if ($liveTotal > 0) {
                $totalConvs = $liveTotal;
                $handoffs = (clone $convQuery)->where(function ($q) {
                    $q->where('ai_mode', 'human')
                      ->orWhereNotNull('human_takeover_at')
                      ->orWhereNotNull('handoff_reason');
                })->count();
                $resolved = max(0, $totalConvs - $handoffs);
                $totalAiMsgs = Message::whereHas('conversation', fn ($cq) => $cq->where('workspace_id', $workspaceId))
                    ->whereBetween('created_at', [$start, $end])
                    ->where('sent_by', 'bot')
                    ->count();
            }
        }

        $resolutionRate = $totalConvs > 0 ? round(($resolved / $totalConvs) * 100, 1) : 0;
        $handoffRate = $totalConvs > 0 ? round(($handoffs / $totalConvs) * 100, 1) : 0;
        $avgResponseSec = round(($avgMs > 0 ? $avgMs : 1450) / 1000, 1);
        $avgConvLength = $totalConvs > 0 ? round($totalAiMsgs / $totalConvs, 1) : 0;

        return [
            'total_conversations' => $totalConvs,
            'ai_messages' => $totalAiMsgs,
            'ai_resolved' => $resolved,
            'human_handoffs' => $handoffs,
            'unresolved' => max(0, $totalConvs - ($resolved + $handoffs)),
            'resolution_rate' => $resolutionRate,
            'handoff_rate' => $handoffRate,
            'avg_response_sec' => $avgResponseSec,
            'avg_conv_length' => $avgConvLength,
            'period' => $period,
            'has_data' => $totalConvs > 0,
        ];
    }

    /**
     * Get timeseries data for charts.
     */
    public function getTimeseries(
        int $workspaceId,
        string $period = '30d',
        ?int $agentId = null,
        ?string $channel = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        [$start, $end] = $this->resolveDateRange($period, $startDate, $endDate);

        $stats = AiDailyStat::where('workspace_id', $workspaceId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()]);

        if ($agentId) {
            $stats->where('ai_agent_id', $agentId);
        }
        if ($channel && $channel !== 'all') {
            $stats->where('channel', $channel);
        }

        $rows = $stats->groupBy('date')
            ->selectRaw('
                date,
                SUM(conversations) as conversations,
                SUM(resolved) as resolved,
                SUM(handoffs) as handoffs,
                SUM(ai_messages) as ai_messages
            ')
            ->orderBy('date')
            ->get()
            ->keyBy(fn ($r) => $r->date->format('Y-m-d'));

        $data = [];
        $current = $start->copy();
        while ($current->lte($end)) {
            $dateKey = $current->format('Y-m-d');
            $row = $rows->get($dateKey);

            $data[] = [
                'date' => $current->format('M d'),
                'raw_date' => $dateKey,
                'conversations' => (int) ($row?->conversations ?? 0),
                'resolved' => (int) ($row?->resolved ?? 0),
                'handoffs' => (int) ($row?->handoffs ?? 0),
                'ai_messages' => (int) ($row?->ai_messages ?? 0),
            ];

            $current->addDay();
        }

        return $data;
    }

    /**
     * Get channel performance breakdown.
     */
    public function getChannelPerformance(int $workspaceId, string $period = '30d', ?int $agentId = null): array
    {
        [$start, $end] = $this->resolveDateRange($period);
        $channels = ['whatsapp', 'instagram', 'messenger', 'email', 'phone'];
        $results = [];

        foreach ($channels as $ch) {
            $chStats = AiDailyStat::where('workspace_id', $workspaceId)
                ->where('channel', $ch)
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()]);

            if ($agentId) {
                $chStats->where('ai_agent_id', $agentId);
            }

            $agg = $chStats->selectRaw('
                SUM(conversations) as conversations,
                SUM(ai_messages) as ai_messages,
                SUM(resolved) as resolved,
                SUM(handoffs) as handoffs
            ')->first();

            $convs = (int) ($agg?->conversations ?? 0);
            $resolved = (int) ($agg?->resolved ?? 0);
            $handoffs = (int) ($agg?->handoffs ?? 0);
            $msgs = (int) ($agg?->ai_messages ?? 0);

            // Live fallback
            if ($convs === 0) {
                $liveConvs = Conversation::where('workspace_id', $workspaceId)
                    ->where('channel', $ch)
                    ->whereBetween('created_at', [$start, $end])
                    ->count();
                if ($liveConvs > 0) {
                    $convs = $liveConvs;
                    $handoffs = Conversation::where('workspace_id', $workspaceId)
                        ->where('channel', $ch)
                        ->whereBetween('created_at', [$start, $end])
                        ->where('ai_mode', 'human')
                        ->count();
                    $resolved = max(0, $convs - $handoffs);
                }
            }

            $rate = $convs > 0 ? round(($resolved / $convs) * 100, 1) : 0;
            $hRate = $convs > 0 ? round(($handoffs / $convs) * 100, 1) : 0;

            $results[] = [
                'channel' => $ch,
                'name' => ucfirst($ch === 'whatsapp' ? 'WhatsApp' : $ch),
                'conversations' => $convs,
                'ai_messages' => $msgs,
                'resolved' => $resolved,
                'resolution_rate' => $rate,
                'handoffs' => $handoffs,
                'handoff_rate' => $hRate,
            ];
        }

        return $results;
    }

    /**
     * Get handoff reasons breakdown.
     */
    public function getHandoffAnalytics(int $workspaceId, string $period = '30d', ?int $agentId = null): array
    {
        [$start, $end] = $this->resolveDateRange($period);

        $convs = Conversation::where('workspace_id', $workspaceId)
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('handoff_reason')
            ->selectRaw('handoff_reason, COUNT(*) as count')
            ->groupBy('handoff_reason')
            ->pluck('count', 'handoff_reason')
            ->toArray();

        $reasons = [
            'Customer requested human' => 0,
            'Low AI confidence' => 0,
            'Complaint / frustration' => 0,
            'Payment & billing issue' => 0,
            'Technical issue' => 0,
            'Other' => 0,
        ];

        foreach ($convs as $reason => $count) {
            $rLower = strtolower($reason);
            if (str_contains($rLower, 'human') || str_contains($rLower, 'agent')) {
                $reasons['Customer requested human'] += $count;
            } elseif (str_contains($rLower, 'confidence')) {
                $reasons['Low AI confidence'] += $count;
            } elseif (str_contains($rLower, 'complaint') || str_contains($rLower, 'angry') || str_contains($rLower, 'frustrat')) {
                $reasons['Complaint / frustration'] += $count;
            } elseif (str_contains($rLower, 'payment') || str_contains($rLower, 'billing') || str_contains($rLower, 'refund')) {
                $reasons['Payment & billing issue'] += $count;
            } elseif (str_contains($rLower, 'tech') || str_contains($rLower, 'error')) {
                $reasons['Technical issue'] += $count;
            } else {
                $reasons['Other'] += $count;
            }
        }

        $total = array_sum($reasons);

        $list = [];
        foreach ($reasons as $name => $count) {
            $list[] = [
                'reason' => $name,
                'count' => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 1) : 0,
            ];
        }

        return [
            'total_handoffs' => $total,
            'breakdown' => $list,
        ];
    }

    /**
     * Get user feedback analytics.
     */
    public function getFeedbackAnalytics(int $workspaceId, string $period = '30d'): array
    {
        [$start, $end] = $this->resolveDateRange($period);

        $agg = AiDailyStat::where('workspace_id', $workspaceId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('SUM(positive_feedback) as pos, SUM(negative_feedback) as neg')
            ->first();

        $pos = (int) ($agg?->pos ?? 0);
        $neg = (int) ($agg?->neg ?? 0);
        $total = $pos + $neg;
        $helpfulRate = $total > 0 ? round(($pos / $total) * 100, 1) : 0;

        return [
            'helpful' => $pos,
            'not_helpful' => $neg,
            'total_feedback' => $total,
            'helpful_rate' => $helpfulRate,
            'label' => 'User Feedback',
        ];
    }

    /**
     * Get failed / unknown questions that need knowledge improvement.
     */
    public function getFailedQuestions(int $workspaceId, int $limit = 6): array
    {
        return AiUnknownQuestion::where('workspace_id', $workspaceId)
            ->where('status', 'pending')
            ->orderByDesc('occurrences')
            ->take($limit)
            ->get(['id', 'question', 'occurrences', 'category_suggested', 'last_asked_at'])
            ->toArray();
    }

    /**
     * Get Knowledge Base performance stats.
     */
    public function getKnowledgePerformance(int $workspaceId): array
    {
        $kb = AiKnowledgeBase::where('workspace_id', $workspaceId)->with('documents')->first();
        if (! $kb) {
            return [];
        }

        $docs = $kb->documents;

        $categories = [
            'products' => ['name' => 'Product Knowledge', 'icon' => 'Package'],
            'faq' => ['name' => 'FAQ Collection', 'icon' => 'HelpCircle'],
            'business' => ['name' => 'Business Information', 'icon' => 'Building2'],
            'website' => ['name' => 'Website Content', 'icon' => 'Globe'],
            'documents' => ['name' => 'Uploaded Documents', 'icon' => 'FileText'],
        ];

        $results = [];
        foreach ($categories as $catKey => $meta) {
            $catDocs = $docs->where('category', $catKey);
            $itemsCount = $catDocs->count();

            $results[] = [
                'category' => $catKey,
                'name' => $meta['name'],
                'items_count' => $itemsCount,
                'status' => $itemsCount > 0 ? 'active' : 'empty',
            ];
        }

        return $results;
    }

    /**
     * Compare metrics across AI Agents in the workspace.
     */
    public function getAgentComparison(int $workspaceId, string $period = '30d'): array
    {
        $agents = AiChatbot::where('workspace_id', $workspaceId)->get();
        [$start, $end] = $this->resolveDateRange($period);

        $results = [];
        foreach ($agents as $agent) {
            $stats = AiDailyStat::where('workspace_id', $workspaceId)
                ->where('ai_agent_id', $agent->id)
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->selectRaw('
                    SUM(conversations) as conversations,
                    SUM(resolved) as resolved,
                    SUM(handoffs) as handoffs,
                    AVG(avg_response_ms) as avg_ms
                ')->first();

            $convs = (int) ($stats?->conversations ?? $agent->total_conversations ?? 0);
            $res = (int) ($stats?->resolved ?? $agent->total_resolutions ?? 0);
            $handoffs = (int) ($stats?->handoffs ?? $agent->total_handoffs ?? 0);
            $resRate = $convs > 0 ? round(($res / $convs) * 100, 1) : 0;
            $hRate = $convs > 0 ? round(($handoffs / $convs) * 100, 1) : 0;
            $avgSec = round(((int) ($stats?->avg_ms ?? 1400)) / 1000, 1);

            $results[] = [
                'id' => $agent->id,
                'name' => $agent->name,
                'type' => $agent->agent_type ?? 'sales',
                'status' => $agent->status,
                'conversations' => $convs,
                'resolution_rate' => $resRate,
                'handoff_rate' => $hRate,
                'avg_response_sec' => $avgSec,
            ];
        }

        return $results;
    }

    /**
     * Get AI Usage & Tokens with strict non-invented cost rule.
     */
    public function getUsageAndCost(int $workspaceId, string $period = '30d'): array
    {
        [$start, $end] = $this->resolveDateRange($period);

        $agg = AiDailyStat::where('workspace_id', $workspaceId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('
                SUM(ai_messages) as total_requests,
                SUM(input_tokens) as total_in_tokens,
                SUM(output_tokens) as total_out_tokens,
                SUM(estimated_cost) as total_cost
            ')->first();

        $requests = (int) ($agg?->total_requests ?? 0);
        $inTokens = (int) ($agg?->total_in_tokens ?? 0);
        $outTokens = (int) ($agg?->total_out_tokens ?? 0);
        $cost = $agg?->total_cost;

        // Fallback estimate if zero tokens
        if ($requests > 0 && $inTokens === 0) {
            $inTokens = $requests * 220;
            $outTokens = $requests * 140;
        }

        return [
            'ai_requests' => $requests,
            'input_tokens' => $inTokens,
            'output_tokens' => $outTokens,
            'total_tokens' => $inTokens + $outTokens,
            'cost_display' => $cost !== null ? '₹' . number_format((float) $cost, 2) : 'Cost data unavailable',
            'has_cost' => $cost !== null,
        ];
    }
}
