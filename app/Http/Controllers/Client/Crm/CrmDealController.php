<?php

namespace App\Http\Controllers\Client\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmCompany;
use App\Models\Crm\CrmDeal;
use App\Models\Crm\CrmPipeline;
use App\Models\Crm\CrmPipelineStage;
use App\Models\User;
use App\Modules\Shared\Models\Contact;
use App\Services\Crm\CrmAuditService;
use App\Services\Crm\CrmCustomFieldService;
use App\Services\CustomerJourney\CustomerJourneyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CrmDealController extends Controller
{
    public function __construct(
        private readonly CustomerJourneyService $journeyService,
        private readonly CrmCustomFieldService $customFieldService,
        private readonly CrmAuditService $auditService
    ) {}

    public function index(Request $request): Response|JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $deals = CrmDeal::where('workspace_id', $workspace->id)
            ->with(['contact', 'company', 'pipeline', 'stage', 'assignedUser'])
            ->when($request->search, function ($q) use ($request) {
                $search = $request->search;
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('id', is_numeric($search) ? (int) $search : 0)
                        ->orWhereHas('contact', fn ($cq) => $cq->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"))
                        ->orWhereHas('company', fn ($compq) => $compq->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->pipeline_id, fn ($q) => $q->where('pipeline_id', $request->pipeline_id))
            ->when($request->stage_id, fn ($q) => $q->where('stage_id', $request->stage_id))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->owner_id, fn ($q) => $q->where('assigned_user_id', $request->owner_id))
            ->when($request->min_value, fn ($q) => $q->where('value', '>=', (float) $request->min_value))
            ->when($request->max_value, fn ($q) => $q->where('value', '<=', (float) $request->max_value))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $deals->items(),
                'meta' => [
                    'current_page' => $deals->currentPage(),
                    'total' => $deals->total(),
                ],
            ]);
        }

        $pipelines = CrmPipeline::where('workspace_id', $workspace->id)->with('stages')->get();
        $teamMembers = User::where('workspace_id', $workspace->id)->where('status', 'active')->get(['id', 'name', 'email']);
        $companies = CrmCompany::where('workspace_id', $workspace->id)->get(['id', 'name']);
        $customFields = $this->customFieldService->getFields($workspace->id, 'deal');

        return Inertia::render('Crm/Deals/Index', [
            'deals' => $deals,
            'pipelines' => $pipelines,
            'teamMembers' => $teamMembers,
            'companies' => $companies,
            'customFields' => $customFields,
            'filters' => $request->only('search', 'pipeline_id', 'stage_id', 'status', 'owner_id'),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $validated = $request->validate([
            'contact_id' => ['nullable', 'exists:contacts,id'],
            'company_id' => ['nullable', 'exists:crm_companies,id'],
            'name' => ['required', 'string', 'max:191'],
            'value' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'pipeline_id' => ['nullable', 'exists:crm_pipelines,id'],
            'stage_id' => ['nullable', 'exists:crm_pipeline_stages,id'],
            'probability' => ['nullable', 'integer', 'between:0,100'],
            'expected_close_date' => ['nullable', 'date'],
            'assigned_user_id' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
            'custom_fields' => ['nullable', 'array'],
        ]);

        $customFieldValues = $this->customFieldService->validateValues(
            $workspace->id,
            'deal',
            $validated['custom_fields'] ?? []
        );

        $contact = ! empty($validated['contact_id'])
            ? Contact::where('workspace_id', $workspace->id)->find($validated['contact_id'])
            : null;

        $pipeline = ! empty($validated['pipeline_id'])
            ? CrmPipeline::where('workspace_id', $workspace->id)->find($validated['pipeline_id'])
            : CrmPipeline::where('workspace_id', $workspace->id)->first();

        $stageId = $validated['stage_id'] ?? $pipeline?->stages()->first()?->id;

        $deal = CrmDeal::create([
            'workspace_id' => $workspace->id,
            'contact_id' => $contact?->id,
            'company_id' => $validated['company_id'] ?? $contact?->company_id,
            'name' => $validated['name'],
            'value' => $validated['value'],
            'currency' => $validated['currency'] ?? 'INR',
            'pipeline_id' => $pipeline?->id,
            'stage_id' => $stageId,
            'probability' => $validated['probability'] ?? 50,
            'expected_close_date' => $validated['expected_close_date'] ?? null,
            'assigned_user_id' => $validated['assigned_user_id'] ?? $contact?->assigned_user_id ?? $request->user()->id,
            'status' => 'open',
            'notes' => $validated['notes'] ?? null,
            'custom_fields' => $customFieldValues,
        ]);

        if ($contact) {
            $contact->update([
                'deal_value' => $contact->deals()->where('status', '!=', 'lost')->sum('value'),
            ]);

            $this->journeyService->recordEvent(
                contactId: $contact->id,
                workspaceId: $workspace->id,
                eventType: 'crm_deal_created',
                channel: 'crm',
                title: "New Deal: {$deal->name} (₹{$deal->value})",
                description: "Deal created with expected value of ₹{$deal->value}",
                metadata: ['deal_id' => $deal->id, 'value' => $deal->value]
            );
        }

        $this->auditService->log(
            workspace: $workspace,
            actor: $request->user(),
            action: 'deal_created',
            entityType: 'deal',
            entityId: $deal->id,
            newValues: $deal->toArray(),
            description: "Deal {$deal->name} created."
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Deal created successfully.',
                'data' => $deal->load(['stage', 'pipeline', 'contact', 'company', 'assignedUser']),
            ], 201);
        }

        return back()->with('success', __('Deal created successfully.'));
    }

    public function show(Request $request, CrmDeal $deal): Response|JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless((int) $deal->workspace_id === (int) $workspace->id, 403);

        $deal->load(['contact', 'company', 'pipeline', 'stage', 'assignedUser', 'tasks.assignedUser']);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $deal,
            ]);
        }

        return Inertia::render('Crm/Deals/Show', [
            'deal' => $deal,
        ]);
    }

    public function update(Request $request, CrmDeal $deal): RedirectResponse|JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless((int) $deal->workspace_id === (int) $workspace->id, 403);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:191'],
            'value' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'probability' => ['nullable', 'integer', 'between:0,100'],
            'expected_close_date' => ['nullable', 'date'],
            'company_id' => ['nullable', 'exists:crm_companies,id'],
            'assigned_user_id' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
            'custom_fields' => ['nullable', 'array'],
        ]);

        $oldValues = $deal->toArray();

        if (isset($validated['custom_fields'])) {
            $validated['custom_fields'] = $this->customFieldService->validateValues(
                $workspace->id,
                'deal',
                $validated['custom_fields']
            );
        }

        $deal->update($validated);

        if ($deal->contact) {
            $deal->contact->update([
                'deal_value' => $deal->contact->deals()->where('status', '!=', 'lost')->sum('value'),
            ]);
        }

        $this->auditService->log(
            workspace: $workspace,
            actor: $request->user(),
            action: 'deal_updated',
            entityType: 'deal',
            entityId: $deal->id,
            oldValues: $oldValues,
            newValues: $deal->toArray(),
            description: "Deal {$deal->name} updated."
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Deal updated successfully.',
                'data' => $deal->fresh(['stage', 'pipeline', 'contact', 'company', 'assignedUser']),
            ]);
        }

        return back()->with('success', 'Deal updated successfully.');
    }

    public function updateStage(Request $request, CrmDeal $deal): JsonResponse|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless((int) $deal->workspace_id === (int) $workspace->id, 403);

        $validated = $request->validate([
            'stage_id' => ['required', 'exists:crm_pipeline_stages,id'],
            'loss_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $stage = CrmPipelineStage::where('workspace_id', $workspace->id)->findOrFail($validated['stage_id']);

        $oldStageId = $deal->stage_id;
        $status = $stage->is_won ? 'won' : ($stage->is_lost ? 'lost' : 'open');

        $deal->update([
            'stage_id' => $stage->id,
            'status' => $status,
            'probability' => $stage->probability,
            'loss_reason' => $stage->is_lost ? ($validated['loss_reason'] ?? null) : null,
        ]);

        if ($deal->contact) {
            $deal->contact->update([
                'deal_value' => $deal->contact->deals()->where('status', '!=', 'lost')->sum('value'),
            ]);

            $this->journeyService->recordEvent(
                contactId: $deal->contact->id,
                workspaceId: $workspace->id,
                eventType: 'crm_deal_stage_changed',
                channel: 'crm',
                title: "Deal {$deal->name} moved to {$stage->name}",
                description: "Stage changed with probability {$stage->probability}%",
                metadata: ['deal_id' => $deal->id, 'stage_id' => $stage->id, 'status' => $status]
            );
        }

        $this->auditService->log(
            workspace: $workspace,
            actor: $request->user(),
            action: 'deal_stage_changed',
            entityType: 'deal',
            entityId: $deal->id,
            oldValues: ['stage_id' => $oldStageId],
            newValues: ['stage_id' => $stage->id, 'status' => $status],
            description: "Deal {$deal->name} moved to stage {$stage->name}."
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Deal stage updated.',
                'data' => $deal->fresh(['stage', 'pipeline']),
            ]);
        }

        return back()->with('success', 'Deal stage updated.');
    }

    public function updateStatus(Request $request, CrmDeal $deal): JsonResponse|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless((int) $deal->workspace_id === (int) $workspace->id, 403);

        $validated = $request->validate([
            'status' => ['required', 'in:open,won,lost'],
            'loss_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $deal->update($validated);

        if ($deal->contact) {
            $deal->contact->update([
                'deal_value' => $deal->contact->deals()->where('status', '!=', 'lost')->sum('value'),
            ]);

            $this->journeyService->recordEvent(
                contactId: $deal->contact->id,
                workspaceId: $workspace->id,
                eventType: "crm_deal_{$validated['status']}",
                channel: 'crm',
                title: "Deal {$deal->name} marked as ".strtoupper($validated['status']),
                description: "Status changed to {$validated['status']}".(! empty($validated['loss_reason']) ? " (Reason: {$validated['loss_reason']})" : ''),
                metadata: ['deal_id' => $deal->id, 'status' => $validated['status']]
            );
        }

        $this->auditService->log(
            workspace: $workspace,
            actor: $request->user(),
            action: "deal_{$validated['status']}",
            entityType: 'deal',
            entityId: $deal->id,
            newValues: $validated,
            description: "Deal {$deal->name} marked as {$validated['status']}."
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Deal status updated.',
                'data' => $deal->fresh(),
            ]);
        }

        return back()->with('success', __('Deal status updated.'));
    }

    public function destroy(Request $request, CrmDeal $deal): RedirectResponse|JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless((int) $deal->workspace_id === (int) $workspace->id, 403);

        $dealName = $deal->name;
        $dealId = $deal->id;
        $contact = $deal->contact;
        $deal->delete();

        if ($contact) {
            $contact->update([
                'deal_value' => $contact->deals()->where('status', '!=', 'lost')->sum('value'),
            ]);
        }

        $this->auditService->log(
            workspace: $workspace,
            actor: $request->user(),
            action: 'deal_deleted',
            entityType: 'deal',
            entityId: $dealId,
            description: "Deal {$dealName} deleted."
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Deal deleted successfully.',
            ]);
        }

        return back()->with('success', 'Deal deleted successfully.');
    }
}
