<?php

namespace App\Modules\Voice\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Shared\Models\Contact;
use App\Modules\Voice\Models\VoiceAgent;
use App\Modules\Voice\Models\VoiceCampaign;
use App\Modules\Voice\Models\VoiceFollowUp;
use App\Modules\Voice\Models\VoiceFollowUpRule;
use App\Modules\Voice\Services\VoiceFollowUpService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VoiceFollowUpController extends Controller
{
    public function __construct(protected VoiceFollowUpService $followUpService) {}

    private function workspaceId(Request $request): int
    {
        return (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
    }

    /**
     * Follow-Up Dashboard (/app/voice/follow-ups)
     */
    public function index(Request $request): Response
    {
        $wid = $this->workspaceId($request);

        $status = $request->query('status', 'all');
        $type = $request->query('type', 'all');
        $priority = $request->query('priority', 'all');
        $dateFilter = $request->query('date', 'all');
        $search = $request->query('search');

        $query = VoiceFollowUp::where('workspace_id', $wid)
            ->with([
                'contact:id,uuid,first_name,last_name,phone_e164',
                'voiceAgent:id,name',
                'assignedUser:id,name',
                'call:id,uuid,duration_sec,outcome',
            ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('contact', function ($cq) use ($search) {
                      $cq->where('first_name', 'like', "%{$search}%")
                         ->orWhere('last_name', 'like', "%{$search}%")
                         ->orWhere('phone_e164', 'like', "%{$search}%");
                  });
            });
        }

        if ($status !== 'all' && ! empty($status)) {
            $query->where('status', $status);
        }

        if ($type !== 'all' && ! empty($type)) {
            $query->where('type', $type);
        }

        if ($priority !== 'all' && ! empty($priority)) {
            $query->where('priority', $priority);
        }

        if ($dateFilter === 'today') {
            $query->whereDate('due_at', Carbon::today());
        } elseif ($dateFilter === 'overdue') {
            $query->where('due_at', '<', Carbon::now())
                  ->whereIn('status', ['pending', 'scheduled']);
        } elseif ($dateFilter === 'upcoming') {
            $query->where('due_at', '>', Carbon::now())
                  ->whereIn('status', ['pending', 'scheduled']);
        }

        $followUps = $query->orderBy('due_at', 'asc')->paginate(20)->withQueryString();

        // Top KPI Counts
        $dueToday = VoiceFollowUp::where('workspace_id', $wid)
            ->whereDate('due_at', Carbon::today())
            ->whereIn('status', ['pending', 'scheduled'])
            ->count();

        $scheduled = VoiceFollowUp::where('workspace_id', $wid)
            ->where('status', 'scheduled')
            ->count();

        $completed = VoiceFollowUp::where('workspace_id', $wid)
            ->where('status', 'completed')
            ->count();

        $overdue = VoiceFollowUp::where('workspace_id', $wid)
            ->where('due_at', '<', Carbon::now())
            ->whereIn('status', ['pending', 'scheduled'])
            ->count();

        return Inertia::render('Voice/FollowUps/Index', [
            'followUps' => $followUps,
            'stats' => [
                'due_today' => $dueToday,
                'scheduled' => $scheduled,
                'completed' => $completed,
                'overdue' => $overdue,
            ],
            'filters' => [
                'status' => $status,
                'type' => $type,
                'priority' => $priority,
                'date' => $dateFilter,
                'search' => $search,
            ],
        ]);
    }

    /**
     * Create Follow-up View (/app/voice/follow-ups/create)
     */
    public function create(Request $request): Response
    {
        $wid = $this->workspaceId($request);

        $contacts = Contact::where('workspace_id', $wid)->latest()->limit(50)->get(['id', 'first_name', 'last_name', 'phone_e164']);
        $agents = VoiceAgent::where('workspace_id', $wid)->get(['id', 'name']);
        $teamUsers = User::where('workspace_id', $wid)->get(['id', 'name']);

        return Inertia::render('Voice/FollowUps/Create', [
            'contacts' => $contacts,
            'agents' => $agents,
            'teamUsers' => $teamUsers,
        ]);
    }

    /**
     * Store New Follow-up (/app/voice/follow-ups)
     */
    public function store(Request $request): RedirectResponse
    {
        $wid = $this->workspaceId($request);

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:callback,crm_task,whatsapp,email,team_notify'],
            'contact_id' => ['nullable', 'exists:contacts,id'],
            'voice_agent_id' => ['nullable', 'exists:voice_agents,id'],
            'assigned_user_id' => ['nullable', 'exists:users,id'],
            'priority' => ['required', 'string', 'in:high,medium,low'],
            'due_at' => ['required', 'date'],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $contact = ! empty($validated['contact_id']) ? Contact::find($validated['contact_id']) : null;
        $dueAt = Carbon::parse($validated['due_at']);

        if ($validated['type'] === 'callback') {
            $this->followUpService->scheduleCallback($wid, $contact, $dueAt, [
                'voice_agent_id' => $validated['voice_agent_id'] ?? null,
                'assigned_user_id' => $validated['assigned_user_id'] ?? null,
                'title' => $validated['title'],
                'notes' => $validated['notes'] ?? '',
            ]);
        } else {
            VoiceFollowUp::create([
                'workspace_id' => $wid,
                'contact_id' => $contact?->id,
                'voice_agent_id' => $validated['voice_agent_id'] ?? null,
                'assigned_user_id' => $validated['assigned_user_id'] ?? null,
                'type' => $validated['type'],
                'status' => 'pending',
                'priority' => $validated['priority'],
                'due_at' => $dueAt,
                'title' => $validated['title'],
                'notes' => $validated['notes'] ?? '',
            ]);
        }

        return redirect()->route('client.voice.follow-ups.index')->with('success', __('Follow-up created successfully.'));
    }

    /**
     * Follow-Up Detail View (/app/voice/follow-ups/{followUp})
     */
    public function show(Request $request, VoiceFollowUp $followUp): Response
    {
        $wid = $this->workspaceId($request);
        abort_if($followUp->workspace_id !== $wid, 403);

        $followUp->load([
            'contact.tags',
            'voiceAgent',
            'assignedUser',
            'call',
            'campaign',
        ]);

        return Inertia::render('Voice/FollowUps/Show', [
            'followUp' => $followUp,
        ]);
    }

    /**
     * Mark Follow-up Completed
     */
    public function complete(Request $request, VoiceFollowUp $followUp): RedirectResponse
    {
        $wid = $this->workspaceId($request);
        abort_if($followUp->workspace_id !== $wid, 403);

        $this->followUpService->completeFollowUp($followUp, $request->input('notes'));

        return back()->with('success', __('Follow-up marked as completed.'));
    }

    /**
     * Reschedule Follow-up
     */
    public function reschedule(Request $request, VoiceFollowUp $followUp): RedirectResponse
    {
        $wid = $this->workspaceId($request);
        abort_if($followUp->workspace_id !== $wid, 403);

        $validated = $request->validate([
            'due_at' => ['required', 'date'],
        ]);

        $this->followUpService->rescheduleFollowUp($followUp, Carbon::parse($validated['due_at']));

        return back()->with('success', __('Follow-up rescheduled.'));
    }

    /**
     * Cancel Follow-up
     */
    public function cancel(Request $request, VoiceFollowUp $followUp): RedirectResponse
    {
        $wid = $this->workspaceId($request);
        abort_if($followUp->workspace_id !== $wid, 403);

        $this->followUpService->cancelFollowUp($followUp, $request->input('reason'));

        return back()->with('success', __('Follow-up cancelled.'));
    }

    /**
     * Follow-Up Automation Rules (/app/voice/follow-ups/rules)
     */
    public function rules(Request $request): Response
    {
        $wid = $this->workspaceId($request);

        $rules = VoiceFollowUpRule::where('workspace_id', $wid)
            ->with(['agent:id,name', 'campaign:id,name'])
            ->latest()
            ->get();

        $agents = VoiceAgent::where('workspace_id', $wid)->get(['id', 'name']);
        $campaigns = VoiceCampaign::where('workspace_id', $wid)->get(['id', 'name']);

        return Inertia::render('Voice/FollowUps/Rules', [
            'rules' => $rules,
            'agents' => $agents,
            'campaigns' => $campaigns,
        ]);
    }

    /**
     * Store Rule
     */
    public function storeRule(Request $request): RedirectResponse
    {
        $wid = $this->workspaceId($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'voice_agent_id' => ['nullable', 'exists:voice_agents,id'],
            'voice_campaign_id' => ['nullable', 'exists:voice_campaigns,id'],
            'trigger_event' => ['required', 'string', 'in:call_completed,interested,qualified,callback_requested,not_interested,no_answer,human_handoff,failed'],
            'actions' => ['required', 'array', 'min:1'],
            'actions.*.type' => ['required', 'string', 'in:schedule_callback,create_crm_task,send_whatsapp,send_email,add_tag,trigger_automation'],
        ]);

        VoiceFollowUpRule::create([
            'workspace_id' => $wid,
            'name' => $validated['name'],
            'voice_agent_id' => $validated['voice_agent_id'] ?? null,
            'voice_campaign_id' => $validated['voice_campaign_id'] ?? null,
            'trigger_event' => $validated['trigger_event'],
            'actions' => $validated['actions'],
            'is_active' => true,
        ]);

        return back()->with('success', __('Follow-up automation rule created.'));
    }

    /**
     * Toggle Rule
     */
    public function toggleRule(Request $request, VoiceFollowUpRule $rule): RedirectResponse
    {
        $wid = $this->workspaceId($request);
        abort_if($rule->workspace_id !== $wid, 403);

        $rule->update(['is_active' => ! $rule->is_active]);

        return back()->with('success', __('Rule status updated.'));
    }

    /**
     * Delete Rule
     */
    public function destroyRule(Request $request, VoiceFollowUpRule $rule): RedirectResponse
    {
        $wid = $this->workspaceId($request);
        abort_if($rule->workspace_id !== $wid, 403);

        $rule->delete();

        return back()->with('success', __('Rule deleted.'));
    }
}
