<?php

namespace App\Modules\Voice\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\ContactTag;
use App\Modules\Voice\Jobs\GenerateVoiceCallSummaryJob;
use App\Modules\Voice\Models\VoiceAgent;
use App\Modules\Voice\Models\VoiceCall;
use App\Modules\Voice\Models\VoiceCampaign;
use App\Modules\Voice\Models\VoiceCampaignRecipient;
use App\Modules\Voice\Services\VoiceDriverManager;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VoiceCallController extends Controller
{
    public function __construct(private readonly VoiceDriverManager $driverManager) {}

    private function workspaceId(Request $request): int
    {
        return (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
    }

    /**
     * Call History & Conversation Intelligence List (/app/voice/calls)
     */
    public function index(Request $request): Response
    {
        $wid = $this->workspaceId($request);

        $search = $request->query('search');
        $agentId = $request->query('agent_id');
        $outcome = $request->query('outcome', 'all');
        $status = $request->query('status', 'all');
        $dateRange = $request->query('date_range', 'all');
        $hasRecording = $request->query('has_recording');
        $hasTranscript = $request->query('has_transcript');
        $isHandoff = $request->query('is_handoff');

        $query = VoiceCall::where('workspace_id', $wid)
            ->with([
                'agent:id,name,provider,voice_id,language,tone',
                'contact:id,uuid,first_name,last_name,phone_e164',
            ]);

        // Search across customer name, phone, transcript, summary, and call UUID
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('from_number', 'like', "%{$search}%")
                  ->orWhere('to_number', 'like', "%{$search}%")
                  ->orWhere('uuid', 'like', "%{$search}%")
                  ->orWhere('summary', 'like', "%{$search}%")
                  ->orWhere('transcript', 'like', "%{$search}%")
                  ->orWhereHas('contact', function ($cq) use ($search) {
                      $cq->where('first_name', 'like', "%{$search}%")
                         ->orWhere('last_name', 'like', "%{$search}%")
                         ->orWhere('phone_e164', 'like', "%{$search}%");
                  });
            });
        }

        if ($agentId) {
            $query->where('voice_agent_id', $agentId);
        }

        if ($outcome !== 'all' && ! empty($outcome)) {
            $query->where('outcome', $outcome);
        }

        if ($status !== 'all' && ! empty($status)) {
            $query->where('status', $status);
        }

        if ($isHandoff === '1') {
            $query->where(function ($q) {
                $q->where('outcome', 'human_handoff')
                  ->orWhere('outcome', 'transferred')
                  ->orWhereNotNull('handoff_reason');
            });
        }

        if ($hasRecording === '1') {
            $query->whereNotNull('recording_url')->where('recording_url', '!=', '');
        }

        if ($hasTranscript === '1') {
            $query->whereNotNull('transcript')->where('transcript', '!=', '');
        }

        // Date Range
        if ($dateRange === 'today') {
            $query->whereDate('created_at', Carbon::today());
        } elseif ($dateRange === 'yesterday') {
            $query->whereDate('created_at', Carbon::yesterday());
        } elseif ($dateRange === '7days') {
            $query->where('created_at', '>=', Carbon::now()->subDays(7));
        } elseif ($dateRange === '30days') {
            $query->where('created_at', '>=', Carbon::now()->subDays(30));
        }

        $calls = $query->latest()->paginate(20)->withQueryString();

        // Summary KPI Metrics
        $totalCalls = VoiceCall::where('workspace_id', $wid)->count();
        $answeredCalls = VoiceCall::where('workspace_id', $wid)->where('duration_sec', '>', 0)->count();
        $resolvedCalls = VoiceCall::where('workspace_id', $wid)->whereIn('outcome', ['resolved', 'completed', 'interested', 'qualified'])->count();
        $avgDurationSec = (int) VoiceCall::where('workspace_id', $wid)->where('duration_sec', '>', 0)->avg('duration_sec');

        $agents = VoiceAgent::where('workspace_id', $wid)->get(['id', 'name', 'status']);
        $campaigns = VoiceCampaign::where('workspace_id', $wid)->get(['id', 'uuid', 'name']);

        return Inertia::render('Voice/Calls/Index', [
            'calls' => $calls,
            'stats' => [
                'total_calls' => $totalCalls,
                'answered_calls' => $answeredCalls,
                'resolved_calls' => $resolvedCalls,
                'avg_duration_sec' => $avgDurationSec,
                'avg_duration_formatted' => sprintf('%02d:%02d', floor($avgDurationSec / 60), $avgDurationSec % 60),
            ],
            'agents' => $agents,
            'campaigns' => $campaigns,
            'filters' => [
                'search' => $search,
                'agent_id' => $agentId,
                'outcome' => $outcome,
                'status' => $status,
                'date_range' => $dateRange,
                'has_recording' => $hasRecording,
                'has_transcript' => $hasTranscript,
                'is_handoff' => $isHandoff,
            ],
        ]);
    }

    /**
     * Call Intelligence Details View (/app/voice/calls/{call})
     */
    public function show(Request $request, VoiceCall $call): Response
    {
        $wid = $this->workspaceId($request);
        abort_if($call->workspace_id !== $wid, 403);

        $call->load([
            'agent:id,name,provider,voice_id,language,tone,human_transfer_number',
            'contact.tags',
            'phoneNumber',
        ]);

        $recipient = VoiceCampaignRecipient::where('voice_call_id', $call->id)
            ->orWhere(function ($q) use ($call) {
                if ($call->contact_id) {
                    $q->where('contact_id', $call->contact_id);
                }
            })
            ->with('campaign:id,uuid,name,type')
            ->first();

        // Parse speaker separated transcript turns
        $transcriptTurns = $this->parseTranscriptTurns($call->transcript);

        $availableTags = ContactTag::where('workspace_id', $wid)->get(['id', 'name', 'color']);

        return Inertia::render('Voice/Calls/Show', [
            'call' => $call,
            'recipient' => $recipient,
            'transcriptTurns' => $transcriptTurns,
            'availableTags' => $availableTags,
        ]);
    }

    /**
     * Transcript JSON Endpoint with search query match support (/app/voice/calls/{call}/transcript)
     */
    public function transcript(Request $request, VoiceCall $call): JsonResponse
    {
        $wid = $this->workspaceId($request);
        abort_if($call->workspace_id !== $wid, 403);

        $q = $request->query('q');
        $turns = $this->parseTranscriptTurns($call->transcript);

        if ($q) {
            $turns = array_values(array_filter($turns, function ($t) use ($q) {
                return str_contains(strtolower($t['text']), strtolower($q));
            }));
        }

        return response()->json([
            'call_id' => $call->id,
            'uuid' => $call->uuid,
            'raw_transcript' => $call->transcript,
            'turns' => $turns,
            'query' => $q,
        ]);
    }

    /**
     * Trigger On-Demand AI Conversation Analysis (/app/voice/calls/{call}/analyze)
     */
    public function analyze(Request $request, VoiceCall $call): RedirectResponse
    {
        $wid = $this->workspaceId($request);
        abort_if($call->workspace_id !== $wid, 403);

        // Run synchronously to provide immediate feedback
        $job = new GenerateVoiceCallSummaryJob($call->id);
        $job->handle(app(\App\Modules\AI\Services\LlmGateway::class));

        return back()->with('success', __('AI conversation analysis generated successfully.'));
    }

    /**
     * Execute Follow-Up Action (/app/voice/calls/{call}/follow-up)
     */
    public function followUp(Request $request, VoiceCall $call): RedirectResponse
    {
        $wid = $this->workspaceId($request);
        abort_if($call->workspace_id !== $wid, 403);

        $validated = $request->validate([
            'action_type' => ['required', 'string', 'in:tag,callback,task,whatsapp'],
            'tag_name' => ['nullable', 'string', 'max:64'],
            'callback_time' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $contact = $call->contact;

        if ($validated['action_type'] === 'tag' && ! empty($validated['tag_name']) && $contact) {
            $tag = ContactTag::firstOrCreate(
                ['workspace_id' => $wid, 'name' => $validated['tag_name']],
                ['color' => '#6366f1']
            );
            $contact->tags()->syncWithoutDetaching([$tag->id]);
            return back()->with('success', __('Tag ":tag" attached to contact.', ['tag' => $validated['tag_name']]));
        }

        if ($validated['action_type'] === 'callback' && ! empty($validated['callback_time'])) {
            $recipient = VoiceCampaignRecipient::where('voice_call_id', $call->id)->first();
            if ($recipient) {
                app(\App\Modules\Voice\Services\SmartVoiceQueueService::class)->scheduleCallback(
                    $recipient,
                    Carbon::parse($validated['callback_time']),
                    $validated['notes'] ?? 'Follow-up callback from call history.'
                );
            }
            return back()->with('success', __('Priority callback scheduled for :time.', ['time' => $validated['callback_time']]));
        }

        return back()->with('success', __('Follow-up action logged.'));
    }

    /**
     * Test call trigger
     */
    public function testCall(Request $request, VoiceAgent $voiceAgent): JsonResponse
    {
        $workspaceId = $this->workspaceId($request);
        abort_if($voiceAgent->workspace_id !== $workspaceId, 403);

        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $contact = Contact::where('workspace_id', $workspaceId)
            ->where('phone_e164', $validated['phone'])
            ->first();

        $call = VoiceCall::create([
            'workspace_id' => $workspaceId,
            'voice_agent_id' => $voiceAgent->id,
            'contact_id' => $contact?->id,
            'direction' => 'outbound',
            'provider' => $voiceAgent->provider ?: 'twilio',
            'from_number' => $voiceAgent->phone_number,
            'to_number' => $validated['phone'],
            'status' => 'queued',
            'started_at' => now(),
        ]);

        try {
            $driver = $this->driverManager->driverForAgent($voiceAgent);
            $result = $driver->initiateOutboundCall($voiceAgent, $call, $validated['phone']);

            $call->update([
                'provider_call_id' => $result['provider_call_id'] ?? null,
                'status' => $result['status'] ?? 'initiated',
            ]);

            $voiceAgent->increment('total_calls');

            return response()->json([
                'success' => true,
                'message' => __('Test call initiated successfully.'),
                'call_uuid' => $call->uuid,
            ]);
        } catch (\Throwable $e) {
            $call->update([
                'status' => 'failed',
                'error_json' => ['error' => $e->getMessage()],
                'ended_at' => now(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Helper to split transcript into speaker turns
     */
    private function parseTranscriptTurns(?string $transcript): array
    {
        if (empty($transcript)) {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($transcript));
        $turns = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            if (preg_match('/^(AI|Agent|Assistant):\s*(.+)$/i', $line, $m)) {
                $turns[] = [
                    'speaker' => 'agent',
                    'speaker_label' => 'AI Assistant',
                    'text' => trim($m[2]),
                ];
            } elseif (preg_match('/^(Caller|Customer|User|Human):\s*(.+)$/i', $line, $m)) {
                $turns[] = [
                    'speaker' => 'caller',
                    'speaker_label' => 'Customer',
                    'text' => trim($m[2]),
                ];
            } else {
                $turns[] = [
                    'speaker' => 'system',
                    'speaker_label' => 'Transcript',
                    'text' => $line,
                ];
            }
        }

        return $turns;
    }
}
