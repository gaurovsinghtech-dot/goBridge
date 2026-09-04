<?php

namespace App\Modules\Shared\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Services\ContactIdentityService;
use App\Modules\Shared\Services\CustomerJourneyService;
use App\Modules\Shared\Services\OptOutComplianceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactJourneyController extends Controller
{
    public function __construct(
        private readonly CustomerJourneyService $journeyService,
        private readonly ContactIdentityService $identityService,
        private readonly OptOutComplianceService $optOutService
    ) {}

    public function timeline(Request $request, Contact $contact): JsonResponse
    {
        $workspaceId = (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
        abort_if($contact->workspace_id !== $workspaceId, 403);

        $timeline = $this->journeyService->getUnifiedTimeline($contact, 60);

        return response()->json([
            'success' => true,
            'contact' => [
                'id' => $contact->id,
                'uuid' => $contact->uuid,
                'name' => $contact->full_name,
                'phone' => $contact->phone_e164,
                'email' => $contact->email,
                'lead_score' => $contact->lead_score,
                'lead_score_band' => $contact->lead_score_band,
                'marketing_opt_out' => $contact->marketing_opt_out,
            ],
            'timeline' => $timeline,
        ]);
    }

    public function duplicates(Request $request): Response
    {
        $workspaceId = (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);

        $potentialDuplicates = Contact::where('workspace_id', $workspaceId)
            ->whereNotNull('duplicate_of_id')
            ->with(['tags'])
            ->get();

        return Inertia::render('Contacts/Duplicates', [
            'duplicates' => $potentialDuplicates,
        ]);
    }

    public function merge(Request $request, Contact $contact): RedirectResponse
    {
        $workspaceId = (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
        abort_if($contact->workspace_id !== $workspaceId, 403);

        $validated = $request->validate([
            'duplicate_contact_id' => ['required', 'exists:contacts,id'],
        ]);

        $duplicate = Contact::where('workspace_id', $workspaceId)->findOrFail($validated['duplicate_contact_id']);

        $success = $this->identityService->mergeContacts($contact, $duplicate);

        if ($success) {
            return back()->with('success', __('Contacts merged successfully. All conversation, call, and journey history unified.'));
        }

        return back()->with('error', __('Failed to merge contacts.'));
    }

    public function toggleOptOut(Request $request, Contact $contact): RedirectResponse
    {
        $workspaceId = (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
        abort_if($contact->workspace_id !== $workspaceId, 403);

        if ($contact->marketing_opt_out) {
            $contact->update([
                'marketing_opt_out' => false,
                'opt_out_at' => null,
            ]);
            $msg = __('Customer opted back in for automated messaging.');
        } else {
            $this->optOutService->processOptOut($contact, 'manual');
            $msg = __('Customer opted out. Running automations cancelled.');
        }

        return back()->with('success', $msg);
    }
}
