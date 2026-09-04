<?php

namespace App\Modules\AI\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AI\Models\AiChatbot;
use App\Modules\AI\Models\AiUnknownQuestion;
use App\Services\AI\AiAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiAnalyticsController extends Controller
{
    public function __construct(
        protected AiAnalyticsService $analyticsService
    ) {}

    private function workspaceId(Request $request): int
    {
        return (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
    }

    /**
     * AI Analytics & Usage Dashboard (/app/ai/analytics)
     */
    public function index(Request $request): Response
    {
        $wid = $this->workspaceId($request);

        $period = (string) $request->input('period', '30d');
        $agentId = $request->filled('agent_id') ? (int) $request->input('agent_id') : null;
        $channel = (string) $request->input('channel', 'all');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // Fetch data
        $overview = $this->analyticsService->getOverview($wid, $period, $agentId, $channel, $startDate, $endDate);
        $timeseries = $this->analyticsService->getTimeseries($wid, $period, $agentId, $channel, $startDate, $endDate);
        $channelPerformance = $this->analyticsService->getChannelPerformance($wid, $period, $agentId);
        $handoffs = $this->analyticsService->getHandoffAnalytics($wid, $period, $agentId);
        $feedback = $this->analyticsService->getFeedbackAnalytics($wid, $period);
        $knowledge = $this->analyticsService->getKnowledgePerformance($wid);
        $failedQuestions = $this->analyticsService->getFailedQuestions($wid, 8);
        $agentsComparison = $this->analyticsService->getAgentComparison($wid, $period);
        $usage = $this->analyticsService->getUsageAndCost($wid, $period);

        // Filter dropdown options
        $agents = AiChatbot::where('workspace_id', $wid)->get(['id', 'name', 'status']);

        return Inertia::render('AI/Analytics/Index', [
            'overview' => $overview,
            'timeseries' => $timeseries,
            'channelPerformance' => $channelPerformance,
            'handoffs' => $handoffs,
            'feedback' => $feedback,
            'knowledge' => $knowledge,
            'failedQuestions' => $failedQuestions,
            'agentsComparison' => $agentsComparison,
            'usage' => $usage,
            'agents' => $agents,
            'filters' => [
                'period' => $period,
                'agent_id' => $agentId,
                'channel' => $channel,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }

    /**
     * Mark an unknown question as resolved after knowledge update.
     */
    public function resolveQuestion(Request $request, AiUnknownQuestion $question): RedirectResponse
    {
        $wid = $this->workspaceId($request);
        abort_unless((int) $question->workspace_id === $wid, 403);

        $question->update(['status' => 'resolved']);

        return back()->with('success', 'Question marked as resolved.');
    }

    /**
     * API JSON Overview
     */
    public function apiOverview(Request $request): JsonResponse
    {
        $wid = $this->workspaceId($request);
        $period = (string) $request->input('period', '30d');
        $agentId = $request->filled('agent_id') ? (int) $request->input('agent_id') : null;
        $channel = (string) $request->input('channel', 'all');

        $overview = $this->analyticsService->getOverview($wid, $period, $agentId, $channel);

        return response()->json([
            'ok' => true,
            'data' => $overview,
        ]);
    }
}
