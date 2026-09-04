<?php

namespace App\Modules\Voice\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TwilioAccount;
use App\Modules\Integrations\Models\IntegrationConfig;
use App\Modules\Voice\Models\VoiceAgent;
use App\Modules\Voice\Models\VoiceCall;
use App\Modules\Voice\Models\VoiceCampaign;
use App\Modules\Voice\Models\VoiceCampaignRecipient;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VoiceCallCenterController extends Controller
{
    private function workspaceId(Request $request): int
    {
        return (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
    }

    /**
     * AI Voice Call Center Operations Dashboard (/app/voice/call-center)
     */
    public function index(Request $request): Response
    {
        $wid = $this->workspaceId($request);
        $data = $this->gatherCallCenterData($wid);

        return Inertia::render('Voice/CallCenter/Index', $data);
    }

    /**
     * Polling endpoint for live call updates and alerts without whole-page refresh
     */
    public function liveFeed(Request $request): JsonResponse
    {
        $wid = $this->workspaceId($request);
        $data = $this->gatherCallCenterData($wid);

        return response()->json($data);
    }

    /**
     * Aggregate call center operational metrics
     */
    private function gatherCallCenterData(int $wid): array
    {
        $today = Carbon::today();

        // 1. Live Active Calls (ringing, in_progress, handoff)
        $activeCalls = VoiceCall::where('workspace_id', $wid)
            ->whereIn('status', ['initiated', 'ringing', 'in_progress', 'in-progress', 'handoff', 'transferring'])
            ->with([
                'contact:id,uuid,first_name,last_name,phone_e164',
                'agent:id,name,provider,voice_id,language,tone',
            ])
            ->latest()
            ->take(15)
            ->get()
            ->map(function ($call) {
                $durationSec = $call->started_at ? max(0, Carbon::now()->diffInSeconds($call->started_at)) : ($call->duration_sec ?: 0);
                return [
                    'id' => $call->id,
                    'uuid' => $call->uuid,
                    'contact_name' => $call->contact ? trim("{$call->contact->first_name} {$call->contact->last_name}") : 'Caller',
                    'phone' => $call->to_number ?: ($call->from_number ?: 'Anonymous'),
                    'direction' => $call->direction,
                    'agent_name' => $call->agent?->name ?: 'AI Voice Agent',
                    'provider' => $call->provider ?: 'twilio',
                    'status' => $call->status,
                    'outcome' => $call->outcome,
                    'duration_sec' => $durationSec,
                    'duration_formatted' => sprintf('%02d:%02d', floor($durationSec / 60), $durationSec % 60),
                    'transcript' => $call->transcript,
                    'started_at' => $call->started_at?->toIso8601String(),
                ];
            });

        // 2. Active Human Handoffs
        $activeHandoffs = VoiceCall::where('workspace_id', $wid)
            ->where('outcome', 'human_handoff')
            ->whereIn('status', ['initiated', 'ringing', 'in_progress', 'in-progress', 'handoff', 'transferring'])
            ->with(['contact', 'agent'])
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($call) {
                return [
                    'id' => $call->id,
                    'uuid' => $call->uuid,
                    'customer_name' => $call->contact ? trim("{$call->contact->first_name} {$call->contact->last_name}") : 'Customer',
                    'phone' => $call->from_number ?: $call->to_number,
                    'reason' => 'Customer requested live specialist / human agent.',
                    'destination' => $call->agent?->human_transfer_number ?: 'Support Team',
                    'status' => 'connected',
                    'agent_name' => $call->agent?->name ?: 'AI Assistant',
                    'time' => $call->updated_at->diffForHumans(),
                ];
            });

        // 3. AI Voice Agents Operational Status
        $agents = VoiceAgent::where('workspace_id', $wid)
            ->get()
            ->map(function ($agent) use ($wid, $today) {
                $todayAgentCalls = VoiceCall::where('workspace_id', $wid)
                    ->where('voice_agent_id', $agent->id)
                    ->whereDate('created_at', $today);

                $totalToday = (clone $todayAgentCalls)->count();
                $resolvedToday = (clone $todayAgentCalls)->whereIn('outcome', ['resolved', 'completed', 'interested', 'qualified'])->count();
                $handoffsToday = (clone $todayAgentCalls)->where('outcome', 'human_handoff')->count();
                $activeNow = VoiceCall::where('workspace_id', $wid)
                    ->where('voice_agent_id', $agent->id)
                    ->whereIn('status', ['initiated', 'ringing', 'in_progress', 'in-progress'])
                    ->count();

                $resRate = $totalToday > 0 ? round(($resolvedToday / $totalToday) * 100) : ($agent->successful_calls > 0 ? 85 : 0);
                $handoffRate = $totalToday > 0 ? round(($handoffsToday / $totalToday) * 100) : 0;

                return [
                    'id' => $agent->id,
                    'name' => $agent->name,
                    'provider' => $agent->provider ?: 'twilio',
                    'status' => $agent->status ?: 'active',
                    'language' => $agent->language ?: 'en-US',
                    'tone' => $agent->tone ?: 'professional',
                    'calls_today' => $totalToday,
                    'active_calls' => $activeNow,
                    'resolution_rate' => $resRate,
                    'handoff_rate' => $handoffRate,
                ];
            });

        // 4. Smart Queue Summary (#74)
        $baseQueue = VoiceCampaignRecipient::where('workspace_id', $wid);
        $queueSummary = [
            'ready' => (clone $baseQueue)->whereIn('status', ['pending', 'queued'])->whereNull('exclusion_reason')->count(),
            'scheduled' => (clone $baseQueue)->where('status', 'scheduled')->count(),
            'callback' => (clone $baseQueue)->where('is_callback', true)->count(),
            'calling' => (clone $baseQueue)->where('status', 'calling')->count(),
            'completed' => (clone $baseQueue)->where('status', 'completed')->count(),
            'failed' => (clone $baseQueue)->where('status', 'failed')->count(),
            'excluded' => (clone $baseQueue)->where(fn ($q) => $q->whereNotNull('exclusion_reason')->orWhere('status', 'skipped'))->count(),
        ];

        // 5. Active Voice Campaigns (#73)
        $activeCampaigns = VoiceCampaign::where('workspace_id', $wid)
            ->whereIn('status', ['running', 'paused', 'scheduled'])
            ->with('agent:id,name')
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($camp) {
                return [
                    'id' => $camp->id,
                    'uuid' => $camp->uuid,
                    'name' => $camp->name,
                    'type' => $camp->type,
                    'status' => $camp->status,
                    'total_contacts' => $camp->total_contacts,
                    'completed_calls' => $camp->completed_calls,
                    'progress_percent' => $camp->progress_percent,
                    'interested' => $camp->interested_calls,
                    'qualified' => $camp->qualified_calls,
                    'agent_name' => $camp->agent?->name ?: 'AI Assistant',
                ];
            });

        // 6. Today's Performance Analytics (#70)
        $todayCallsQuery = VoiceCall::where('workspace_id', $wid)->whereDate('created_at', $today);
        $totalTodayCount = (clone $todayCallsQuery)->count();
        $answeredToday = (clone $todayCallsQuery)->where(fn ($q) => $q->where('duration_sec', '>', 0)->orWhereIn('status', ['completed', 'in_progress', 'in-progress']))->count();
        $aiResolvedToday = (clone $todayCallsQuery)->whereIn('outcome', ['resolved', 'completed', 'interested', 'qualified'])->count();
        $humanHandoffToday = (clone $todayCallsQuery)->where('outcome', 'human_handoff')->count();
        $noAnswerToday = (clone $todayCallsQuery)->whereIn('outcome', ['no_answer', 'busy'])->count();
        $failedToday = (clone $todayCallsQuery)->where('status', 'failed')->count();

        $qualifiedLeadsToday = VoiceCampaignRecipient::where('workspace_id', $wid)
            ->whereDate('updated_at', $today)
            ->whereIn('call_outcome', ['interested', 'qualified'])
            ->count();

        $callbacksToday = VoiceCampaignRecipient::where('workspace_id', $wid)
            ->whereDate('updated_at', $today)
            ->where('is_callback', true)
            ->count();

        $todayStats = [
            'total_calls' => $totalTodayCount,
            'answered' => $answeredToday,
            'ai_resolved' => $aiResolvedToday,
            'human_handoff' => $humanHandoffToday,
            'no_answer' => $noAnswerToday,
            'failed' => $failedToday,
            'qualified_leads' => $qualifiedLeadsToday,
            'callbacks' => $callbacksToday,
        ];

        // 7. Call Outcome Breakdown
        $outcomes = (clone $todayCallsQuery)
            ->whereNotNull('outcome')
            ->selectRaw('outcome, count(*) as count')
            ->groupBy('outcome')
            ->pluck('count', 'outcome')
            ->all();

        // 8. Provider Connection Status
        $hasTwilio = TwilioAccount::where('workspace_id', $wid)->where('status', 'active')->exists()
            || IntegrationConfig::where('workspace_id', $wid)->where('provider', 'twilio')->exists()
            || ! empty(config('services.twilio.sid'));

        $providers = [
            [
                'name' => 'Twilio Voice',
                'provider' => 'twilio',
                'status' => $hasTwilio ? 'connected' : 'disconnected',
            ],
        ];

        // 9. Real-Time Activity Log
        $recentActivity = VoiceCall::where('workspace_id', $wid)
            ->with(['contact', 'agent'])
            ->latest()
            ->take(8)
            ->get()
            ->map(function ($c) {
                $contactName = $c->contact ? trim("{$c->contact->first_name} {$c->contact->last_name}") : 'Caller';
                $agentName = $c->agent?->name ?: 'AI Agent';

                $title = match ($c->outcome) {
                    'human_handoff' => "Call transferred to live agent for {$contactName}",
                    'interested' => "AI qualified lead for {$contactName}",
                    'callback_requested' => "Callback scheduled for {$contactName}",
                    default => "{$agentName} handled call with {$contactName}",
                };

                return [
                    'id' => $c->id,
                    'time' => $c->created_at->format('H:i'),
                    'message' => $title,
                    'type' => $c->outcome ?: $c->status,
                ];
            });

        // 10. Operational Alerts
        $alerts = [];
        if ($activeHandoffs->isNotEmpty()) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "{$activeHandoffs->count()} call(s) waiting for human specialist transfer.",
            ];
        }
        if (! $hasTwilio) {
            $alerts[] = [
                'type' => 'danger',
                'message' => 'No telephony provider connected. Configure Twilio Voice to receive and place calls.',
            ];
        }
        if ($failedToday >= 3 && $totalTodayCount > 0 && ($failedToday / $totalTodayCount) > 0.2) {
            $alerts[] = [
                'type' => 'danger',
                'message' => "High call failure rate detected ({$failedToday} failed calls today).",
            ];
        }

        return [
            'activeCalls' => $activeCalls,
            'activeHandoffs' => $activeHandoffs,
            'agents' => $agents,
            'queueSummary' => $queueSummary,
            'activeCampaigns' => $activeCampaigns,
            'todayStats' => $todayStats,
            'outcomes' => $outcomes,
            'providers' => $providers,
            'recentActivity' => $recentActivity,
            'alerts' => $alerts,
        ];
    }
}
