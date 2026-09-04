<?php

namespace App\Modules\Voice\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Voice\Jobs\ProcessVoiceCampaignCallJob;
use App\Modules\Voice\Models\VoiceCampaign;
use App\Modules\Voice\Models\VoiceCampaignRecipient;
use App\Modules\Voice\Services\SmartVoiceQueueService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SmartVoiceQueueController extends Controller
{
    public function __construct(
        private readonly SmartVoiceQueueService $queueService
    ) {}

    private function workspaceId(Request $request): int
    {
        return (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
    }

    /**
     * Smart Calling Queue Dashboard (/app/voice/queue)
     */
    public function index(Request $request): Response
    {
        $wid = $this->workspaceId($request);

        $campaignId = $request->query('campaign_id');
        $status = $request->query('status', 'all');
        $priority = $request->query('priority', 'all');
        $search = $request->query('search');

        $query = VoiceCampaignRecipient::where('voice_campaign_recipients.workspace_id', $wid)
            ->with([
                'campaign:id,uuid,name,type,status,caller_id_number',
                'contact:id,first_name,last_name,phone_e164',
                'voiceCall:id,uuid,duration_sec,status,recording_url',
            ]);

        if ($campaignId) {
            $query->where('voice_campaign_id', $campaignId);
        }

        if ($status !== 'all') {
            if ($status === 'ready') {
                $query->whereIn('voice_campaign_recipients.status', ['pending', 'queued'])
                      ->whereNull('exclusion_reason');
            } elseif ($status === 'callback') {
                $query->where('is_callback', true);
            } elseif ($status === 'excluded') {
                $query->where(function ($q) {
                    $q->whereNotNull('exclusion_reason')
                      ->orWhere('voice_campaign_recipients.status', 'skipped');
                });
            } else {
                $query->where('voice_campaign_recipients.status', $status);
            }
        }

        if ($priority !== 'all') {
            $query->where('priority_level', $priority);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('contact_name', 'like', "%{$search}%")
                  ->orWhere('phone_e164', 'like', "%{$search}%");
            });
        }

        $queueItems = $query->orderBy('priority_score', 'desc')
            ->orderBy('is_callback', 'desc')
            ->orderBy('next_attempt_at', 'asc')
            ->orderBy('id', 'asc')
            ->paginate(20)
            ->withQueryString();

        // Calculate summary counts
        $baseCounts = VoiceCampaignRecipient::where('workspace_id', $wid);
        if ($campaignId) {
            $baseCounts->where('voice_campaign_id', $campaignId);
        }

        $stats = [
            'ready' => (clone $baseCounts)->whereIn('status', ['pending', 'queued'])->whereNull('exclusion_reason')->count(),
            'calling' => (clone $baseCounts)->where('status', 'calling')->count(),
            'scheduled' => (clone $baseCounts)->where('status', 'scheduled')->count(),
            'callback' => (clone $baseCounts)->where('is_callback', true)->count(),
            'excluded' => (clone $baseCounts)->where(fn ($q) => $q->whereNotNull('exclusion_reason')->orWhere('status', 'skipped'))->count(),
            'completed' => (clone $baseCounts)->where('status', 'completed')->count(),
            'failed' => (clone $baseCounts)->where('status', 'failed')->count(),
        ];

        $campaigns = VoiceCampaign::where('workspace_id', $wid)->get(['id', 'uuid', 'name', 'status']);

        return Inertia::render('Voice/Queue/Index', [
            'queueItems' => $queueItems,
            'stats' => $stats,
            'campaigns' => $campaigns,
            'filters' => [
                'campaign_id' => $campaignId,
                'status' => $status,
                'priority' => $priority,
                'search' => $search,
            ],
        ]);
    }

    /**
     * Instantly trigger a call for a specific queue item
     */
    public function dialNow(Request $request, VoiceCampaignRecipient $recipient): RedirectResponse
    {
        $wid = $this->workspaceId($request);
        abort_if($recipient->workspace_id !== $wid, 403);

        $recipient->update([
            'status' => 'queued',
            'priority_score' => 100,
            'priority_level' => 'high',
            'locked_at' => null,
            'next_attempt_at' => now(),
            'exclusion_reason' => null,
        ]);

        ProcessVoiceCampaignCallJob::dispatch($recipient->id);

        return back()->with('success', __('Call queued for immediate dispatch to :phone.', ['phone' => $recipient->phone_e164]));
    }

    /**
     * Reschedule / schedule a high-priority callback
     */
    public function rescheduleCallback(Request $request, VoiceCampaignRecipient $recipient): RedirectResponse
    {
        return $this->scheduleCallback($request, $recipient);
    }

    public function scheduleCallback(Request $request, VoiceCampaignRecipient $recipient): RedirectResponse
    {
        $wid = $this->workspaceId($request);
        abort_if($recipient->workspace_id !== $wid, 403);

        $validated = $request->validate([
            'callback_time' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->queueService->scheduleCallback(
            $recipient,
            Carbon::parse($validated['callback_time']),
            $validated['notes'] ?? null
        );

        return back()->with('success', __('Priority callback scheduled for :time.', ['time' => $validated['callback_time']]));
    }

    /**
     * Exclude / Skip contact from calling queue
     */
    public function exclude(Request $request, VoiceCampaignRecipient $recipient): RedirectResponse
    {
        $wid = $this->workspaceId($request);
        abort_if($recipient->workspace_id !== $wid, 403);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:64'],
        ]);

        $recipient->update([
            'status' => 'skipped',
            'exclusion_reason' => $validated['reason'],
            'notes' => 'Excluded by user: ' . $validated['reason'],
        ]);

        return back()->with('success', __('Contact excluded from queue.'));
    }

    /**
     * Re-enqueue a previously skipped or failed recipient
     */
    public function requeue(Request $request, VoiceCampaignRecipient $recipient): RedirectResponse
    {
        $wid = $this->workspaceId($request);
        abort_if($recipient->workspace_id !== $wid, 403);

        $recipient->update([
            'status' => 'pending',
            'exclusion_reason' => null,
            'next_attempt_at' => now(),
            'locked_at' => null,
        ]);

        return back()->with('success', __('Contact re-enqueued for calling.'));
    }
}
