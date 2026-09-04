<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Modules\Leads\Models\LeadActivity;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Segment;
use App\Services\Customer\CustomerTimelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerTimelineController extends Controller
{
    public function __construct(protected CustomerTimelineService $timelineService) {}

    private function workspaceId(Request $request): int
    {
        return (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
    }

    /**
     * Customer 360 Profile & Unified Timeline (/app/customers/{contact})
     */
    public function show(Request $request, Contact $contact): Response
    {
        $wid = $this->workspaceId($request);
        abort_if($contact->workspace_id !== $wid, 403);

        $contact->load(['tags', 'segments']);

        $channelFilter = $request->query('channel', 'all');
        $search = $request->query('search');

        $timeline = $this->timelineService->getTimeline($contact, [
            'channel' => $channelFilter,
            'search' => $search,
        ]);

        $journey = $this->timelineService->getJourneySummary($contact);
        $aiSummary = $this->timelineService->getAiCustomerSummary($contact);

        $staticSegments = Segment::where('workspace_id', $wid)->where('type', 'static')->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Contacts/Show', [
            'contact' => $contact,
            'timeline' => $timeline,
            'journey' => $journey,
            'aiSummary' => $aiSummary,
            'staticSegments' => $staticSegments,
            'filters' => [
                'channel' => $channelFilter,
                'search' => $search,
            ],
        ]);
    }

    /**
     * Timeline JSON feed for fast polling / filtering (/app/customers/{contact}/timeline)
     */
    public function timeline(Request $request, Contact $contact): JsonResponse
    {
        $wid = $this->workspaceId($request);
        abort_if($contact->workspace_id !== $wid, 403);

        $timeline = $this->timelineService->getTimeline($contact, $request->all());

        return response()->json([
            'contact_id' => $contact->id,
            'events' => $timeline,
        ]);
    }

    /**
     * Merge secondary contact into primary contact (/app/customers/{contact}/merge)
     */
    public function merge(Request $request, Contact $contact): RedirectResponse
    {
        $wid = $this->workspaceId($request);
        abort_if($contact->workspace_id !== $wid, 403);

        $validated = $request->validate([
            'secondary_contact_id' => ['required', 'exists:contacts,id'],
        ]);

        $secondary = Contact::where('workspace_id', $wid)->findOrFail($validated['secondary_contact_id']);

        $this->timelineService->mergeContacts($contact, $secondary);

        return back()->with('success', __('Contacts merged successfully without losing conversation history.'));
    }

    /**
     * Add quick sales note to customer timeline (/app/customers/{contact}/notes)
     */
    public function addNote(Request $request, Contact $contact): RedirectResponse
    {
        $wid = $this->workspaceId($request);
        abort_if($contact->workspace_id !== $wid, 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:1000'],
        ]);

        if (class_exists(LeadActivity::class)) {
            $leadId = $contact->lead_id;

            if (! $leadId && class_exists(\App\Modules\Leads\Models\Lead::class)) {
                $lead = \App\Modules\Leads\Models\Lead::create([
                    'workspace_id' => $wid,
                    'name' => trim("{$contact->first_name} {$contact->last_name}") ?: 'Contact Lead',
                    'phone' => $contact->phone_e164,
                    'email' => $contact->email,
                ]);
                $contact->update(['lead_id' => $lead->id]);
                $leadId = $lead->id;
            }

            if ($leadId) {
                LeadActivity::create([
                    'workspace_id' => $wid,
                    'lead_id' => $leadId,
                    'user_id' => $request->user()->id,
                    'type' => 'note',
                    'body' => $validated['body'],
                    'occurred_at' => now(),
                ]);
            }
        }

        return back()->with('success', __('Note added to customer timeline.'));
    }
}
