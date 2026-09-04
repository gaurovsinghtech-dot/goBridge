<?php

namespace App\Modules\Broadcasting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmPipelineStage;
use App\Modules\Broadcasting\Models\Campaign;
use App\Modules\Broadcasting\Models\CampaignRecipient;
use App\Modules\Broadcasting\Services\CampaignPersonalizer;
use App\Modules\Shared\Models\ContactTag;
use App\Modules\Shared\Models\Segment;
use App\Modules\Whatsapp\Models\WhatsappBusinessAccount;
use App\Modules\Whatsapp\Models\WhatsappTemplate;
use App\Services\Campaigns\CampaignAiAssistantService;
use App\Services\Campaigns\CampaignAudienceService;
use App\Services\Campaigns\CampaignSafetyService;
use App\Services\Campaigns\CampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    public function __construct(
        protected CampaignService $campaignService,
        protected CampaignAudienceService $audienceService,
        protected CampaignAiAssistantService $aiService,
        protected CampaignSafetyService $safetyService
    ) {}

    public function index(Request $request): Response
    {
        $workspaceId = $this->workspaceId($request);
        $campaigns = $this->campaignService->getCampaigns($workspaceId, $request->all(), 25);
        $summary = $this->campaignService->getCampaignSummary($workspaceId);
        $channels = $this->campaignService->getAvailableChannels($workspaceId);

        return Inertia::render('Broadcasting/Campaigns/Index', [
            'campaigns' => $campaigns,
            'summary' => $summary,
            'channels' => $channels,
            'filters' => $request->only('search', 'channel', 'status'),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Broadcasting/Campaigns/Wizard', $this->wizardProps($request));
    }

    public function store(Request $request): RedirectResponse
    {
        $workspaceId = $this->workspaceId($request);
        $validated = $this->validateCampaign($request);

        $campaign = $this->campaignService->createCampaign($workspaceId, $validated, $request->user());

        return redirect()->route('client.campaigns.show', $campaign)->with('success', 'Campaign created.');
    }

    /**
     * POST /campaigns/draft
     * Upserts a draft campaign with whatever fields are available at the current wizard step.
     */
    public function storeDraft(Request $request): JsonResponse
    {
        $workspaceId = $this->workspaceId($request);

        $validated = $request->validate([
            'uuid'                      => ['nullable', 'string', 'uuid'],
            'name'                      => ['required', 'string', 'max:128'],
            'channel'                   => ['required', 'in:whatsapp,instagram,messenger,email,sms'],
            'whatsapp_phone_number_id'  => ['nullable', 'string'],
            'channel_account_id'        => ['nullable', 'integer'],
            'audience_type'             => ['nullable', 'in:segment,contact_list,tag,crm_stage,lead_score,all_contacts,csv,manual'],
            'audience_ref'              => ['nullable', 'string'],
            'template_ref'              => ['nullable', 'array'],
            'payload_json'              => ['nullable', 'array'],
            'schedule_at'               => ['nullable', 'date'],
            'timezone'                  => ['nullable', 'string', 'max:64'],
            'quiet_hours_enabled'       => ['nullable', 'boolean'],
            'quiet_hours_start'         => ['nullable', 'string', 'max:8'],
            'quiet_hours_end'           => ['nullable', 'string', 'max:8'],
            'frequency_cap_days'        => ['nullable', 'integer'],
            'frequency_cap_max'         => ['nullable', 'integer'],
        ]);

        $fields = array_filter([
            'name'                     => $validated['name'],
            'channel'                  => $validated['channel'],
            'whatsapp_phone_number_id' => $validated['whatsapp_phone_number_id'] ?? null,
            'channel_account_id'       => $validated['channel_account_id'] ?? null,
            'audience_type'            => $validated['audience_type'] ?? null,
            'audience_ref'             => $validated['audience_ref'] ?? null,
            'template_ref'             => $validated['template_ref'] ?? null,
            'payload_json'             => $validated['payload_json'] ?? null,
            'schedule_at'              => $validated['schedule_at'] ?? null,
            'timezone'                 => $validated['timezone'] ?? null,
            'quiet_hours_enabled'      => $validated['quiet_hours_enabled'] ?? true,
            'quiet_hours_start'        => $validated['quiet_hours_start'] ?? '09:00',
            'quiet_hours_end'          => $validated['quiet_hours_end'] ?? '20:00',
            'frequency_cap_days'       => $validated['frequency_cap_days'] ?? 7,
            'frequency_cap_max'        => $validated['frequency_cap_max'] ?? 3,
        ], fn ($v) => $v !== null);

        if (! empty($validated['uuid'])) {
            $existing = Campaign::where('workspace_id', $workspaceId)
                ->where('uuid', $validated['uuid'])
                ->where('status', 'draft')
                ->first();

            if ($existing) {
                $existing->update($fields);
                return response()->json(['uuid' => $existing->uuid]);
            }
        }

        $campaign = Campaign::create(array_merge($fields, [
            'workspace_id'  => $workspaceId,
            'audience_type' => $fields['audience_type'] ?? 'segment',
            'status'        => 'draft',
            'created_by'    => $request->user()->id,
        ]));

        return response()->json(['uuid' => $campaign->uuid]);
    }

    public function edit(Request $request, Campaign $campaign): Response
    {
        $this->authorise($request, $campaign);
        abort_unless(in_array($campaign->status, ['draft', 'paused'], true), 422, 'Only drafts or paused campaigns can be edited.');

        return Inertia::render('Broadcasting/Campaigns/Edit', array_merge(
            $this->wizardProps($request),
            ['campaign' => $campaign->only(
                'id', 'uuid', 'name', 'channel', 'whatsapp_phone_number_id', 'channel_account_id',
                'audience_type', 'audience_ref', 'template_ref', 'payload_json', 'schedule_at',
                'timezone', 'status', 'quiet_hours_enabled', 'quiet_hours_start', 'quiet_hours_end',
                'frequency_cap_days', 'frequency_cap_max'
            )],
        ));
    }

    public function update(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->authorise($request, $campaign);
        abort_unless(in_array($campaign->status, ['draft', 'paused'], true), 422, 'Only drafts or paused campaigns can be edited.');

        $validated = $this->validateCampaign($request);
        $campaign->update($validated);

        return redirect()->route('client.campaigns.show', $campaign)->with('success', 'Campaign updated.');
    }

    public function show(Request $request, Campaign $campaign): Response
    {
        $this->authorise($request, $campaign);

        if (in_array($campaign->status, ['queued', 'sending', 'running', 'paused'], true)) {
            $campaign->updateTotals();
            $campaign->refresh();
        }

        $campaign->loadCount('recipients');

        $recipientStats = CampaignRecipient::where('campaign_id', $campaign->id)
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $sample = CampaignRecipient::where('campaign_id', $campaign->id)
            ->with(['contact:id,first_name,last_name,phone_e164,email'])
            ->orderByDesc('updated_at')
            ->limit(15)
            ->get();

        return Inertia::render('Broadcasting/Campaigns/Show', [
            'campaign' => $campaign,
            'stats' => $recipientStats,
            'sample' => $sample,
            'reportUrl' => route('client.reports.campaigns.show', $campaign->uuid),
        ]);
    }

    public function launch(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->authorise($request, $campaign);
        abort_unless(in_array($campaign->status, ['draft', 'paused', 'scheduled'], true), 422, 'Cannot launch this campaign.');

        if ($request->boolean('confirmed') || $request->boolean('force')) {
            $campaign->update(['confirmed_at' => now()]);
            $campaign->refresh();
        }

        if ($request->has('schedule_at')) {
            $scheduleAt = $request->input('schedule_at');
            $campaign->update([
                'schedule_at' => ! empty($scheduleAt) ? \Carbon\Carbon::parse($scheduleAt) : null,
            ]);
            $campaign->refresh();
        }

        $this->campaignService->launchCampaign($campaign, $request->boolean('force'));

        return back()->with('success', 'Campaign launched successfully.');
    }

    public function pause(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->authorise($request, $campaign);
        $this->campaignService->pauseCampaign($campaign);

        return back()->with('success', 'Campaign paused.');
    }

    public function resume(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->authorise($request, $campaign);
        $this->campaignService->resumeCampaign($campaign);

        return back()->with('success', 'Campaign resumed.');
    }

    public function cancel(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->authorise($request, $campaign);
        $this->campaignService->cancelCampaign($campaign);

        return back()->with('success', 'Campaign cancelled.');
    }

    public function duplicate(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->authorise($request, $campaign);
        $copy = $this->campaignService->duplicateCampaign($campaign, $request->user());

        return redirect()->route('client.campaigns.edit', $copy)->with('success', 'Campaign duplicated as draft.');
    }

    public function destroy(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->authorise($request, $campaign);
        abort_unless(in_array($campaign->status, ['draft', 'cancelled'], true), 422, 'Only draft or cancelled campaigns can be deleted.');
        $campaign->delete();

        return redirect()->route('client.campaigns.index')->with('success', 'Campaign deleted.');
    }

    /**
     * POST /campaigns/audience-preview
     * Returns full suppression matrix, deliverable count, and cost estimate.
     */
    public function audiencePreview(Request $request): JsonResponse
    {
        $workspaceId = $this->workspaceId($request);

        $validated = $request->validate([
            'audience_type' => ['required', 'string'],
            'audience_ref' => ['nullable'],
            'channel' => ['required', 'in:whatsapp,instagram,messenger,email,sms'],
            'frequency_cap_days' => ['nullable', 'integer'],
            'frequency_cap_max' => ['nullable', 'integer'],
        ]);

        $analysis = $this->audienceService->analyzeAudienceSuppression(
            $workspaceId,
            $validated['channel'],
            $validated['audience_type'],
            $validated['audience_ref'] ?? null,
            (int) ($validated['frequency_cap_days'] ?? 7),
            (int) ($validated['frequency_cap_max'] ?? 3)
        );

        return response()->json([
            'matched' => $analysis['total_matched'],
            'total_audience' => $analysis['total_audience'],
            'opted_out' => $analysis['opted_out_count'],
            'invalid_address' => $analysis['invalid_address_count'],
            'frequency_capped' => $analysis['frequency_capped_count'],
            'excluded_recipients' => $analysis['excluded_recipients'],
            'deliverable' => $analysis['deliverable_count'],
            'valid_recipients' => $analysis['valid_recipients'],
            'estimated_usage' => $analysis['estimated_usage'],
            'estimated_cost' => $analysis['estimated_cost'],
            'requires_confirmation' => $analysis['requires_confirmation'],
            'sample' => $analysis['sample'],
        ]);
    }

    /**
     * POST /campaigns/ai-generate
     * Generate structured campaign suggestions using AI.
     */
    public function aiGenerate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:500'],
            'channel' => ['nullable', 'string', 'in:whatsapp,instagram,messenger,email,sms'],
            'language' => ['nullable', 'string', 'max:10'],
            'objective' => ['nullable', 'string', 'max:128'],
        ]);

        $result = $this->aiService->generateCampaignCopy(
            $validated['prompt'],
            $validated['channel'] ?? 'whatsapp',
            $validated['language'] ?? 'en',
            $validated['objective'] ?? null
        );

        return response()->json($result);
    }

    /**
     * POST /campaigns/ai-tone
     * Adjust message tone (shorten, professional, friendly, translate, cta).
     */
    public function aiTone(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string'],
            'action' => ['required', 'string', 'in:shorten,professional,friendly,translate_es,translate_fr,translate_hi,cta'],
            'language' => ['nullable', 'string', 'max:10'],
        ]);

        $result = $this->aiService->adjustMessageTone(
            $validated['message'],
            $validated['action'],
            $validated['language'] ?? 'en'
        );

        return response()->json($result);
    }

    /**
     * POST /campaigns/{campaign}/test-send
     * Sends a one-off test message to a single phone/email.
     */
    public function testSend(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorise($request, $campaign);

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

    private function authorise(Request $request, Campaign $campaign): void
    {
        $workspaceId = $this->workspaceId($request);
        abort_unless((int) $campaign->workspace_id === (int) $workspaceId, 403);
    }

    private function workspaceId(Request $request): int
    {
        return (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
    }

    private function validateCampaign(Request $request): array
    {
        return $request->validate([
            'name'                     => ['required', 'string', 'max:128'],
            'channel'                  => ['required', 'in:whatsapp,instagram,messenger,email,sms'],
            'whatsapp_phone_number_id' => ['nullable', 'string'],
            'channel_account_id'       => ['nullable', 'integer'],
            'audience_type'            => ['required', 'in:segment,contact_list,tag,crm_stage,lead_score,all_contacts,csv,manual'],
            'audience_ref'             => ['nullable', 'string'],
            'template_ref'             => ['nullable', 'array'],
            'payload_json'             => ['nullable', 'array'],
            'schedule_at'              => ['nullable', 'date'],
            'timezone'                 => ['nullable', 'string', 'max:64'],
            'quiet_hours_enabled'      => ['nullable', 'boolean'],
            'quiet_hours_start'        => ['nullable', 'string', 'max:8'],
            'quiet_hours_end'          => ['nullable', 'string', 'max:8'],
            'frequency_cap_days'       => ['nullable', 'integer'],
            'frequency_cap_max'        => ['nullable', 'integer'],
        ]);
    }

    /**
     * Build the props the wizard / edit page need.
     */
    private function wizardProps(Request $request): array
    {
        $workspaceId = $this->workspaceId($request);

        $whatsappTemplates = WhatsappTemplate::where('workspace_id', $workspaceId)
            ->orderBy('name')
            ->orderBy('language')
            ->get(['id', 'waba_id', 'name', 'language', 'status', 'category', 'components'])
            ->sortBy(fn ($t) => match ($t->status) {
                'APPROVED' => 0,
                'PENDING' => 1,
                'PAUSED' => 2,
                default => 3,
            })
            ->values();

        $segments = Segment::where('workspace_id', $workspaceId)
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'contact_count']);

        $tags = ContactTag::where('workspace_id', $workspaceId)
            ->orderBy('name')
            ->get(['id', 'name', 'color']);

        $crmStages = CrmPipelineStage::where('workspace_id', $workspaceId)
            ->orderBy('position')
            ->get(['id', 'name', 'color', 'probability']);

        $whatsappPhoneNumbers = WhatsappBusinessAccount::where('workspace_id', $workspaceId)
            ->where('status', 'active')
            ->with('phoneNumbers')
            ->get()
            ->flatMap(fn ($waba) => $waba->phoneNumbers->map(fn ($p) => [
                'phone_number_id' => $p->phone_number_id,
                'display_phone'   => $p->display_phone,
                'verified_name'   => $p->verified_name,
                'waba_id'         => $waba->waba_id,
            ]))
            ->values();

        $availableChannels = $this->campaignService->getAvailableChannels($workspaceId);

        return [
            'whatsappTemplates'    => $whatsappTemplates,
            'whatsappPhoneNumbers' => $whatsappPhoneNumbers,
            'segments'             => $segments,
            'tags'                 => $tags,
            'crmStages'            => $crmStages,
            'availableChannels'    => $availableChannels,
            'contactTokens'        => CampaignPersonalizer::availableContactTokens(),
        ];
    }
}
