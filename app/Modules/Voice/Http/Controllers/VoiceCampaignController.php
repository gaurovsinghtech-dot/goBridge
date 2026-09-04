<?php

namespace App\Modules\Voice\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PhoneNumber;
use App\Modules\Leads\Models\Lead;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\ContactTag;
use App\Modules\Shared\Models\Segment;
use App\Modules\Voice\Jobs\DispatchVoiceCampaignCallsJob;
use App\Modules\Voice\Models\VoiceAgent;
use App\Modules\Voice\Models\VoiceCampaign;
use App\Modules\Voice\Models\VoiceCampaignRecipient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VoiceCampaignController extends Controller
{
    private function workspaceId(Request $request): int
    {
        return (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
    }

    public function index(Request $request): Response
    {
        $wid = $this->workspaceId($request);

        $campaigns = VoiceCampaign::where('workspace_id', $wid)
            ->with(['agent:id,name,voice_id,language', 'phoneNumber:id,phone_number'])
            ->withCount('recipients')
            ->latest()
            ->paginate(15);

        $stats = [
            'total_campaigns' => VoiceCampaign::where('workspace_id', $wid)->count(),
            'running_campaigns' => VoiceCampaign::where('workspace_id', $wid)->where('status', 'running')->count(),
            'total_contacts_queued' => VoiceCampaignRecipient::where('workspace_id', $wid)->count(),
            'total_calls_completed' => VoiceCampaign::where('workspace_id', $wid)->sum('completed_calls'),
            'total_interested' => VoiceCampaign::where('workspace_id', $wid)->sum('interested_calls'),
            'total_qualified' => VoiceCampaign::where('workspace_id', $wid)->sum('qualified_calls'),
        ];

        return Inertia::render('Voice/Campaigns/Index', [
            'campaigns' => $campaigns,
            'stats' => $stats,
        ]);
    }

    public function create(Request $request): Response
    {
        $wid = $this->workspaceId($request);

        $agents = VoiceAgent::where('workspace_id', $wid)->get(['id', 'uuid', 'name', 'tone', 'language', 'voice_id', 'status']);
        $phoneNumbers = PhoneNumber::where('workspace_id', $wid)->get(['id', 'phone_number', 'friendly_name', 'status']);
        $tags = ContactTag::where('workspace_id', $wid)->get(['id', 'name']);
        $segments = Segment::where('workspace_id', $wid)->get(['id', 'name']);
        $totalContactsCount = Contact::where('workspace_id', $wid)->whereNotNull('phone_e164')->count();

        return Inertia::render('Voice/Campaigns/Create', [
            'agents' => $agents,
            'phoneNumbers' => $phoneNumbers,
            'tags' => $tags,
            'segments' => $segments,
            'totalContactsCount' => $totalContactsCount,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $wid = $this->workspaceId($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'type' => ['required', 'string', 'in:lead_followup,appointment_reminder,survey,reengagement,payment_reminder,custom'],
            'description' => ['nullable', 'string', 'max:500'],
            'voice_agent_id' => ['required', 'exists:voice_agents,id'],
            'phone_number_id' => ['nullable', 'exists:phone_numbers,id'],
            'caller_id_number' => ['nullable', 'string', 'max:32'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date'],
            'timezone' => ['required', 'string', 'max:64'],
            'calling_days' => ['required', 'array'],
            'calling_start_time' => ['required', 'string', 'max:8'],
            'calling_end_time' => ['required', 'string', 'max:8'],
            'max_attempts' => ['required', 'integer', 'min:1', 'max:5'],
            'retry_delay_hours' => ['required', 'integer', 'min:1', 'max:72'],
            'call_timeout_sec' => ['required', 'integer', 'min:15', 'max:120'],
            'max_duration_sec' => ['required', 'integer', 'min:60', 'max:1800'],
            'concurrent_limit' => ['required', 'integer', 'min:1', 'max:10'],
            'daily_limit' => ['required', 'integer', 'min:10', 'max:1000'],
            'compliance_confirmed' => ['required', 'accepted'],
            'ai_disclosure_enabled' => ['required', 'boolean'],
            'whatsapp_followup_enabled' => ['required', 'boolean'],
            'audience_type' => ['required', 'string', 'in:all,tags,leads'],
            'selected_tags' => ['nullable', 'array'],
            'start_now' => ['nullable', 'boolean'],
        ]);

        $phoneNumber = ! empty($validated['phone_number_id'])
            ? PhoneNumber::where('workspace_id', $wid)->find($validated['phone_number_id'])
            : null;

        $callerId = $validated['caller_id_number'] ?: ($phoneNumber?->phone_number ?? '');

        $campaign = VoiceCampaign::create([
            'workspace_id' => $wid,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'description' => $validated['description'] ?? '',
            'voice_agent_id' => $validated['voice_agent_id'],
            'phone_number_id' => $validated['phone_number_id'] ?? null,
            'caller_id_number' => $callerId,
            'status' => ! empty($validated['start_now']) ? 'running' : 'draft',
            'start_at' => $validated['start_at'] ?? now(),
            'end_at' => $validated['end_at'] ?? now()->addDays(7),
            'timezone' => $validated['timezone'],
            'calling_days' => $validated['calling_days'],
            'calling_start_time' => $validated['calling_start_time'],
            'calling_end_time' => $validated['calling_end_time'],
            'max_attempts' => $validated['max_attempts'],
            'retry_delay_hours' => $validated['retry_delay_hours'],
            'call_timeout_sec' => $validated['call_timeout_sec'],
            'max_duration_sec' => $validated['max_duration_sec'],
            'concurrent_limit' => $validated['concurrent_limit'],
            'daily_limit' => $validated['daily_limit'],
            'compliance_confirmed' => true,
            'ai_disclosure_enabled' => $validated['ai_disclosure_enabled'],
            'whatsapp_followup_enabled' => $validated['whatsapp_followup_enabled'],
            'audience_filters' => [
                'audience_type' => $validated['audience_type'],
                'selected_tags' => $validated['selected_tags'] ?? [],
            ],
            'created_by' => $request->user()->id,
        ]);

        // Populate Audience Recipients
        $this->populateRecipients($campaign, $validated);

        if ($campaign->status === 'running') {
            DispatchVoiceCampaignCallsJob::dispatch($campaign->id);
        }

        return redirect()->route('client.voice.campaigns.show', $campaign->uuid)
            ->with('success', __('Voice Campaign created successfully with :count contacts.', ['count' => $campaign->total_contacts]));
    }

    public function show(Request $request, VoiceCampaign $campaign): Response
    {
        $wid = $this->workspaceId($request);
        abort_if($campaign->workspace_id !== $wid, 403);

        $campaign->load(['agent:id,name,tone,language,voice_id', 'phoneNumber:id,phone_number']);

        $recipients = VoiceCampaignRecipient::where('voice_campaign_id', $campaign->id)
            ->with('contact:id,first_name,last_name,email')
            ->latest()
            ->paginate(20);

        return Inertia::render('Voice/Campaigns/Show', [
            'campaign' => array_merge($campaign->toArray(), [
                'progress_percent' => $campaign->progress_percent,
                'answer_rate' => $campaign->answer_rate,
                'qualification_rate' => $campaign->qualification_rate,
                'interest_rate' => $campaign->interest_rate,
            ]),
            'recipients' => $recipients,
        ]);
    }

    public function start(Request $request, VoiceCampaign $campaign): RedirectResponse
    {
        $wid = $this->workspaceId($request);
        abort_if($campaign->workspace_id !== $wid, 403);

        $campaign->update(['status' => 'running']);
        DispatchVoiceCampaignCallsJob::dispatch($campaign->id);

        return back()->with('success', __('Campaign started. Calling queue is active.'));
    }

    public function pause(Request $request, VoiceCampaign $campaign): RedirectResponse
    {
        $wid = $this->workspaceId($request);
        abort_if($campaign->workspace_id !== $wid, 403);

        $campaign->update(['status' => 'paused']);

        return back()->with('success', __('Campaign paused. In-progress calls will finish safely.'));
    }

    public function stop(Request $request, VoiceCampaign $campaign): RedirectResponse
    {
        $wid = $this->workspaceId($request);
        abort_if($campaign->workspace_id !== $wid, 403);

        $campaign->update(['status' => 'cancelled']);

        VoiceCampaignRecipient::where('voice_campaign_id', $campaign->id)
            ->whereIn('status', ['pending', 'queued'])
            ->update(['status' => 'skipped', 'notes' => 'Campaign stopped by user.']);

        return back()->with('success', __('Campaign stopped. Remaining calls cancelled.'));
    }

    public function destroy(Request $request, VoiceCampaign $campaign): RedirectResponse
    {
        $wid = $this->workspaceId($request);
        abort_if($campaign->workspace_id !== $wid, 403);

        $campaign->delete();

        return redirect()->route('client.voice.campaigns.index')
            ->with('success', __('Voice Campaign deleted successfully.'));
    }

    public function analytics(Request $request, VoiceCampaign $campaign): JsonResponse
    {
        $wid = $this->workspaceId($request);
        abort_if($campaign->workspace_id !== $wid, 403);

        $outcomes = VoiceCampaignRecipient::where('voice_campaign_id', $campaign->id)
            ->whereNotNull('call_outcome')
            ->selectRaw('call_outcome, count(*) as count')
            ->groupBy('call_outcome')
            ->pluck('count', 'call_outcome');

        return response()->json([
            'total_contacts' => $campaign->total_contacts,
            'completed_calls' => $campaign->completed_calls,
            'answered_calls' => $campaign->answered_calls,
            'progress_percent' => $campaign->progress_percent,
            'answer_rate' => $campaign->answer_rate,
            'qualification_rate' => $campaign->qualification_rate,
            'outcomes' => $outcomes,
        ]);
    }

    /**
     * Populate eligible recipients from CRM Contacts / Leads with Smart Queue Priority & Eligibility
     */
    private function populateRecipients(VoiceCampaign $campaign, array $validated): void
    {
        $queueService = app(\App\Modules\Voice\Services\SmartVoiceQueueService::class);

        $query = Contact::where('workspace_id', $campaign->workspace_id)
            ->whereNotNull('phone_e164')
            ->where('phone_e164', '!=', '')
            ->with('tags');

        if ($validated['audience_type'] === 'tags' && ! empty($validated['selected_tags'])) {
            $tagIds = (array) $validated['selected_tags'];
            $query->whereHas('tags', function ($q) use ($tagIds) {
                $q->whereIn('contact_tags.id', $tagIds);
            });
        }

        $contacts = $query->limit($campaign->max_campaign_calls ?: 500)->get();

        $inserted = 0;
        foreach ($contacts as $c) {
            $phone = trim($c->phone_e164);
            if (empty($phone)) continue;

            $exists = VoiceCampaignRecipient::where('voice_campaign_id', $campaign->id)
                ->where('phone_e164', $phone)
                ->exists();

            if (! $exists) {
                $eligibility = $queueService->evaluateEligibility($c, $campaign);
                $priority = $queueService->calculatePriority($c, $campaign);

                $status = $eligibility['eligible'] ? 'pending' : 'skipped';
                $exclusionReason = $eligibility['reason'];

                VoiceCampaignRecipient::create([
                    'workspace_id' => $campaign->workspace_id,
                    'voice_campaign_id' => $campaign->id,
                    'contact_id' => $c->id,
                    'phone_e164' => $phone,
                    'contact_name' => trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? '')) ?: 'Contact',
                    'status' => $status,
                    'priority_level' => $priority['level'],
                    'priority_score' => $priority['score'],
                    'queue_reason' => $priority['reason'],
                    'exclusion_reason' => $exclusionReason,
                    'attempts_count' => 0,
                    'max_attempts' => $campaign->max_attempts,
                ]);

                if ($eligibility['eligible']) {
                    $inserted++;
                }
            }
        }

        $campaign->update(['total_contacts' => $inserted]);
    }
}
