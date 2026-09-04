<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Models\Contact;
use App\Models\Workspace;
use App\Services\CustomerJourney\CustomerJourneyService;
use App\Services\CustomerJourney\OmnichannelLeadScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadApiController extends Controller
{
    public function __construct(
        private CustomerJourneyService $timelineService,
        private OmnichannelLeadScoringService $scoringService
    ) {}

    public function store(Request $request): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        if (! $workspace) {
            return response()->json(['success' => false, 'error' => ['code' => 'NO_WORKSPACE', 'message' => 'Workspace context missing.']], 422);
        }

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'tags' => ['nullable', 'array'],
            'source' => ['nullable', 'string', 'max:100'],
            'custom_attributes' => ['nullable', 'array'],
        ]);

        $contact = Contact::updateOrCreate(
            ['workspace_id' => $workspace->id, 'email' => $validated['email'] ?? null, 'phone_e164' => $validated['phone'] ?? null],
            [
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'] ?? null,
                'source' => $validated['source'] ?? 'api',
                'tags' => $validated['tags'] ?? [],
            ]
        );

        $this->scoringService->evaluateScore($contact, 'lead_form_submitted', 20);

        $this->timelineService->recordEvent($contact, 'crm_lead_created', [
            'source' => $validated['source'] ?? 'api',
            'created_via' => 'Developer REST API',
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $contact->id,
                'uuid' => $contact->uuid,
                'first_name' => $contact->first_name,
                'phone' => $contact->phone_e164,
                'email' => $contact->email,
                'lead_score' => $contact->lead_score,
                'lead_temperature' => $contact->lead_temperature,
                'created_at' => $contact->created_at->toIso8601String(),
            ],
        ], 201);
    }

    public function publicCapture(Request $request, string $workspaceToken): JsonResponse
    {
        $workspace = is_numeric($workspaceToken) 
            ? Workspace::find((int) $workspaceToken) 
            : (Workspace::where('name', $workspaceToken)->first() ?? Workspace::first());
        if (! $workspace) {
            return response()->json(['success' => false, 'message' => 'Invalid workspace token.'], 404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $contact = Contact::create([
            'workspace_id' => $workspace->id,
            'first_name' => $validated['name'],
            'phone_e164' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'source' => 'website_form',
        ]);

        $this->scoringService->evaluateScore($contact, 'website_form_submitted', 15);

        return response()->json([
            'success' => true,
            'message' => 'Lead captured successfully.',
            'contact_id' => $contact->uuid,
        ], 200);
    }
}
