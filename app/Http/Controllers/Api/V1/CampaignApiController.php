<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\CampaignRecipientResource;
use App\Http\Resources\Api\V1\CampaignResource;
use App\Modules\Broadcasting\Models\Campaign;
use App\Modules\Broadcasting\Models\CampaignRecipient;
use App\Services\Campaigns\CampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CampaignApiController extends WorkspaceScopedController
{
    public function __construct(
        protected CampaignService $campaignService
    ) {}

    /**
     * GET /api/v1/campaigns
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $campaigns = $this->campaignService->getCampaigns(
            $this->workspaceId($request),
            $request->all(),
            $request->input('per_page', 25)
        );

        return CampaignResource::collection($campaigns);
    }

    /**
     * POST /api/v1/campaigns – create a draft
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'channel' => ['required', 'string', 'in:whatsapp,instagram,messenger,email,sms'],
            'whatsapp_phone_number_id' => ['nullable', 'string'],
            'channel_account_id' => ['nullable', 'integer'],
            'audience_type' => ['nullable', 'string', 'in:all_contacts,segment,tag,crm_stage,lead_score,contact_list,csv,manual'],
            'audience_ref' => ['nullable', 'string'],
            'template_ref' => ['nullable', 'array'],
            'payload_json' => ['nullable', 'array'],
            'schedule_at' => ['nullable', 'date'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'quiet_hours_enabled' => ['nullable', 'boolean'],
            'quiet_hours_start' => ['nullable', 'string', 'max:8'],
            'quiet_hours_end' => ['nullable', 'string', 'max:8'],
            'frequency_cap_days' => ['nullable', 'integer'],
            'frequency_cap_max' => ['nullable', 'integer'],
        ]);

        $validated['audience_type'] = $validated['audience_type'] ?? 'segment';

        $campaign = $this->campaignService->createCampaign(
            $this->workspaceId($request),
            $validated,
            $request->user()
        );

        return (new CampaignResource($campaign))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/v1/campaigns/{id} – with fresh stats
     */
    public function show(Request $request, int $id): CampaignResource|JsonResponse
    {
        $campaign = Campaign::where('workspace_id', $this->workspaceId($request))->find($id);

        if (! $campaign) {
            return response()->json(['error' => 'Campaign not found.'], 404);
        }

        $campaign->updateTotals();
        $campaign->refresh();

        return new CampaignResource($campaign);
    }

    /**
     * POST /api/v1/campaigns/{id}/launch OR /api/v1/campaigns/{id}/send
     */
    public function launch(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::where('workspace_id', $this->workspaceId($request))->find($id);

        if (! $campaign) {
            return response()->json(['error' => 'Campaign not found.'], 404);
        }

        try {
            $result = $this->campaignService->launchCampaign($campaign);
            return response()->json([
                'ok' => true,
                'status' => 'queued',
                'schedule_at' => optional($campaign->schedule_at)->toIso8601String(),
                'message' => $result['message'],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/v1/campaigns/{id}/pause
     */
    public function pause(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::where('workspace_id', $this->workspaceId($request))->find($id);

        if (! $campaign) {
            return response()->json(['error' => 'Campaign not found.'], 404);
        }

        try {
            $this->campaignService->pauseCampaign($campaign);
            return response()->json(['ok' => true, 'status' => 'paused']);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/v1/campaigns/{id}/resume
     */
    public function resume(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::where('workspace_id', $this->workspaceId($request))->find($id);

        if (! $campaign) {
            return response()->json(['error' => 'Campaign not found.'], 404);
        }

        try {
            $this->campaignService->resumeCampaign($campaign);
            return response()->json(['ok' => true, 'status' => 'queued']);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/v1/campaigns/{id}/cancel
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::where('workspace_id', $this->workspaceId($request))->find($id);

        if (! $campaign) {
            return response()->json(['error' => 'Campaign not found.'], 404);
        }

        try {
            $this->campaignService->cancelCampaign($campaign);
            return response()->json(['ok' => true, 'status' => 'cancelled']);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/v1/campaigns/{id}/duplicate
     */
    public function duplicate(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::where('workspace_id', $this->workspaceId($request))->find($id);

        if (! $campaign) {
            return response()->json(['error' => 'Campaign not found.'], 404);
        }

        $clone = $this->campaignService->duplicateCampaign($campaign, $request->user());

        return (new CampaignResource($clone))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * POST /api/v1/campaigns/{id}/test
     */
    public function test(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::where('workspace_id', $this->workspaceId($request))->find($id);

        if (! $campaign) {
            return response()->json(['error' => 'Campaign not found.'], 404);
        }

        $validated = $request->validate([
            'phone_e164' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        try {
            $result = $this->campaignService->testSend($campaign, $validated, $request->user());
            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * GET /api/v1/campaigns/{id}/recipients
     */
    public function recipients(Request $request, int $id): AnonymousResourceCollection|JsonResponse
    {
        $campaign = Campaign::where('workspace_id', $this->workspaceId($request))->find($id);

        if (! $campaign) {
            return response()->json(['error' => 'Campaign not found.'], 404);
        }

        $recipients = CampaignRecipient::where('campaign_id', $campaign->id)
            ->latest('id')
            ->paginate(50);

        return CampaignRecipientResource::collection($recipients);
    }

    /**
     * PATCH /api/v1/campaigns/{id} – update a draft or paused campaign.
     */
    public function update(Request $request, int $id): CampaignResource|JsonResponse
    {
        $campaign = Campaign::where('workspace_id', $this->workspaceId($request))->find($id);

        if (! $campaign) {
            return response()->json(['error' => 'Campaign not found.'], 404);
        }

        if (! in_array($campaign->status, ['draft', 'paused'], true)) {
            return response()->json(['error' => 'Only draft or paused campaigns can be edited.'], 422);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:200'],
            'channel' => ['sometimes', 'required', 'string', 'in:whatsapp,instagram,messenger,email,sms'],
            'audience_type' => ['sometimes', 'required', 'string', 'in:all_contacts,segment,tag,crm_stage,lead_score,contact_list,csv,manual'],
            'audience_ref' => ['nullable', 'string'],
            'template_ref' => ['nullable', 'array'],
            'payload_json' => ['nullable', 'array'],
            'schedule_at' => ['nullable', 'date'],
            'timezone' => ['nullable', 'string', 'max:64'],
        ]);

        $campaign->update($validated);

        return new CampaignResource($campaign->refresh());
    }

    /**
     * DELETE /api/v1/campaigns/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::where('workspace_id', $this->workspaceId($request))->find($id);

        if (! $campaign) {
            return response()->json(['error' => 'Campaign not found.'], 404);
        }

        if (! in_array($campaign->status, ['draft', 'cancelled'], true)) {
            return response()->json(['error' => 'Only draft or cancelled campaigns can be deleted.'], 422);
        }

        $campaign->delete();

        return response()->json(['ok' => true, 'message' => 'Campaign deleted successfully.']);
    }
}
