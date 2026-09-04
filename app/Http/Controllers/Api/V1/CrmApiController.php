<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmCompany;
use App\Models\Crm\CrmCustomField;
use App\Models\Crm\CrmDeal;
use App\Models\Crm\CrmPipeline;
use App\Models\Crm\CrmPipelineStage;
use App\Models\Crm\CrmTask;
use App\Models\Workspace;
use App\Modules\Shared\Models\Contact;
use App\Services\Crm\ContactDuplicateService;
use App\Services\Crm\CrmAnalyticsService;
use App\Services\Crm\CrmAuditService;
use App\Services\Crm\CrmCustomFieldService;
use App\Services\Crm\CrmPipelineService;
use App\Services\Customer\CustomerTimelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmApiController extends Controller
{
    public function __construct(
        private readonly CrmPipelineService $pipelineService,
        private readonly CrmAnalyticsService $analyticsService,
        private readonly CustomerTimelineService $timelineService,
        private readonly ContactDuplicateService $duplicateService,
        private readonly CrmCustomFieldService $customFieldService,
        private readonly CrmAuditService $auditService
    ) {}

    private function resolveWorkspace(Request $request): Workspace
    {
        $user = $request->user();
        $workspace = $user?->currentWorkspace ?? $user?->workspace ?? $user?->client?->workspaces()->first();

        if (! $workspace) {
            abort(403, 'No workspace accessible for authenticated user.');
        }

        return $workspace;
    }

    // ── LEADS ────────────────────────────────────────────────────────

    public function listLeads(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $leads = Contact::where('workspace_id', $workspace->id)
            ->with(['stage', 'pipeline', 'assignedUser', 'crmCompany'])
            ->when($request->search, function ($q) use ($request) {
                $s = $request->search;
                $q->where(function ($sub) use ($s) {
                    $sub->where('first_name', 'like', "%{$s}%")
                        ->orWhere('last_name', 'like', "%{$s}%")
                        ->orWhere('phone_e164', 'like', "%{$s}%")
                        ->orWhere('email', 'like', "%{$s}%")
                        ->orWhere('company', 'like', "%{$s}%");
                });
            })
            ->latest('updated_at')
            ->paginate(25);

        return response()->json([
            'success' => true,
            'data' => $leads->items(),
            'meta' => [
                'current_page' => $leads->currentPage(),
                'total' => $leads->total(),
            ],
        ]);
    }

    public function storeLead(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:128'],
            'last_name' => ['nullable', 'string', 'max:128'],
            'company' => ['nullable', 'string', 'max:191'],
            'company_id' => ['nullable', 'exists:crm_companies,id'],
            'phone_e164' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:191'],
            'deal_value' => ['nullable', 'numeric', 'min:0'],
            'pipeline_id' => ['nullable', 'exists:crm_pipelines,id'],
            'stage_id' => ['nullable', 'exists:crm_pipeline_stages,id'],
            'assigned_user_id' => ['nullable', 'exists:users,id'],
            'source' => ['nullable', 'string', 'max:64'],
            'priority' => ['nullable', 'in:low,medium,high,urgent'],
            'custom_fields' => ['nullable', 'array'],
        ]);

        $customFields = $this->customFieldService->validateValues($workspace->id, 'lead', $validated['custom_fields'] ?? []);
        $defaultPipeline = $this->pipelineService->ensureDefaultPipeline($workspace->id);

        $normalizedPhone = $this->duplicateService->normalizePhone($validated['phone_e164'] ?? null);
        $normalizedEmail = $this->duplicateService->normalizeEmail($validated['email'] ?? null);

        $contact = Contact::create([
            'workspace_id' => $workspace->id,
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'] ?? null,
            'company' => $validated['company'] ?? null,
            'company_id' => $validated['company_id'] ?? null,
            'phone_e164' => $normalizedPhone,
            'email' => $normalizedEmail,
            'deal_value' => $validated['deal_value'] ?? 0,
            'pipeline_id' => $validated['pipeline_id'] ?? $defaultPipeline->id,
            'stage_id' => $validated['stage_id'] ?? $defaultPipeline->stages()->first()?->id,
            'assigned_user_id' => $validated['assigned_user_id'] ?? $request->user()->id,
            'source' => $validated['source'] ?? 'api',
            'priority' => $validated['priority'] ?? 'medium',
            'custom_fields' => $customFields,
        ]);

        $this->auditService->log(
            workspace: $workspace,
            actor: $request->user(),
            action: 'api_lead_created',
            entityType: 'contact',
            entityId: $contact->id,
            newValues: $contact->toArray(),
            description: "Lead {$contact->full_name} created via API."
        );

        return response()->json([
            'success' => true,
            'message' => 'Lead created successfully.',
            'data' => $contact->load(['stage', 'pipeline', 'assignedUser']),
        ], 201);
    }

    public function getLead(Request $request, int|string $id): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $contact = Contact::where('workspace_id', $workspace->id)
            ->where(function ($q) use ($id) {
                if (is_numeric($id)) {
                    $q->where('id', (int) $id);
                } else {
                    $q->where('uuid', $id);
                }
            })
            ->with(['stage', 'pipeline', 'assignedUser', 'deals', 'crmTasks', 'crmCompany'])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $contact,
        ]);
    }

    public function updateLead(Request $request, int|string $id): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $contact = Contact::where('workspace_id', $workspace->id)
            ->where(function ($q) use ($id) {
                if (is_numeric($id)) {
                    $q->where('id', (int) $id);
                } else {
                    $q->where('uuid', $id);
                }
            })->firstOrFail();

        $validated = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:128'],
            'last_name' => ['nullable', 'string', 'max:128'],
            'company' => ['nullable', 'string', 'max:191'],
            'company_id' => ['nullable', 'exists:crm_companies,id'],
            'deal_value' => ['nullable', 'numeric', 'min:0'],
            'priority' => ['nullable', 'in:low,medium,high,urgent'],
            'assigned_user_id' => ['nullable', 'exists:users,id'],
            'custom_fields' => ['nullable', 'array'],
        ]);

        if (isset($validated['custom_fields'])) {
            $validated['custom_fields'] = $this->customFieldService->validateValues(
                $workspace->id,
                'lead',
                $validated['custom_fields']
            );
        }

        $contact->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Lead updated successfully.',
            'data' => $contact->fresh(['stage', 'pipeline', 'assignedUser']),
        ]);
    }

    public function deleteLead(Request $request, int|string $id): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $contact = Contact::where('workspace_id', $workspace->id)
            ->where(function ($q) use ($id) {
                if (is_numeric($id)) {
                    $q->where('id', (int) $id);
                } else {
                    $q->where('uuid', $id);
                }
            })->firstOrFail();

        $contact->delete();

        return response()->json([
            'success' => true,
            'message' => 'Lead deleted successfully.',
        ]);
    }

    // ── COMPANIES ────────────────────────────────────────────────────

    public function listCompanies(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $companies = CrmCompany::where('workspace_id', $workspace->id)
            ->with(['owner'])
            ->withCount(['contacts', 'deals'])
            ->latest()
            ->paginate(25);

        return response()->json([
            'success' => true,
            'data' => $companies->items(),
            'meta' => [
                'current_page' => $companies->currentPage(),
                'total' => $companies->total(),
            ],
        ]);
    }

    public function storeCompany(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'owner_user_id' => ['nullable', 'exists:users,id'],
            'industry' => ['nullable', 'string', 'max:128'],
            'website' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:191'],
            'city' => ['nullable', 'string', 'max:128'],
            'custom_fields' => ['nullable', 'array'],
        ]);

        $customFields = $this->customFieldService->validateValues($workspace->id, 'company', $validated['custom_fields'] ?? []);

        $company = CrmCompany::create([
            'workspace_id' => $workspace->id,
            'name' => $validated['name'],
            'owner_user_id' => $validated['owner_user_id'] ?? $request->user()->id,
            'industry' => $validated['industry'] ?? null,
            'website' => $validated['website'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'city' => $validated['city'] ?? null,
            'custom_fields' => $customFields,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Company created successfully.',
            'data' => $company->load('owner'),
        ], 201);
    }

    public function getCompany(Request $request, int $id): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $company = CrmCompany::where('workspace_id', $workspace->id)->with(['owner', 'contacts', 'deals'])->findOrFail($id);

        return response()->json(['success' => true, 'data' => $company]);
    }

    public function updateCompany(Request $request, int $id): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $company = CrmCompany::where('workspace_id', $workspace->id)->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:191'],
            'industry' => ['nullable', 'string', 'max:128'],
            'website' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:191'],
            'owner_user_id' => ['nullable', 'exists:users,id'],
        ]);

        $company->update($validated);

        return response()->json(['success' => true, 'data' => $company->fresh('owner')]);
    }

    public function deleteCompany(Request $request, int $id): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $company = CrmCompany::where('workspace_id', $workspace->id)->findOrFail($id);
        $company->delete();

        return response()->json(['success' => true, 'message' => 'Company deleted.']);
    }

    // ── DEALS ────────────────────────────────────────────────────────

    public function listDeals(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $deals = CrmDeal::where('workspace_id', $workspace->id)
            ->with(['contact', 'company', 'stage', 'pipeline', 'assignedUser'])
            ->latest()
            ->paginate(25);

        return response()->json([
            'success' => true,
            'data' => $deals->items(),
            'meta' => [
                'current_page' => $deals->currentPage(),
                'total' => $deals->total(),
            ],
        ]);
    }

    public function storeDeal(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'value' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'contact_id' => ['nullable', 'exists:contacts,id'],
            'company_id' => ['nullable', 'exists:crm_companies,id'],
            'pipeline_id' => ['nullable', 'exists:crm_pipelines,id'],
            'stage_id' => ['nullable', 'exists:crm_pipeline_stages,id'],
            'probability' => ['nullable', 'integer', 'between:0,100'],
            'expected_close_date' => ['nullable', 'date'],
            'assigned_user_id' => ['nullable', 'exists:users,id'],
        ]);

        $pipeline = ! empty($validated['pipeline_id'])
            ? CrmPipeline::where('workspace_id', $workspace->id)->find($validated['pipeline_id'])
            : $this->pipelineService->ensureDefaultPipeline($workspace->id);

        $stageId = $validated['stage_id'] ?? $pipeline->stages()->first()?->id;

        $deal = CrmDeal::create([
            'workspace_id' => $workspace->id,
            'name' => $validated['name'],
            'value' => $validated['value'],
            'currency' => $validated['currency'] ?? 'INR',
            'contact_id' => $validated['contact_id'] ?? null,
            'company_id' => $validated['company_id'] ?? null,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stageId,
            'probability' => $validated['probability'] ?? 20,
            'expected_close_date' => $validated['expected_close_date'] ?? null,
            'assigned_user_id' => $validated['assigned_user_id'] ?? $request->user()->id,
            'status' => 'open',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Deal created.',
            'data' => $deal->load(['stage', 'pipeline', 'contact', 'company']),
        ], 201);
    }

    public function getDeal(Request $request, int $id): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $deal = CrmDeal::where('workspace_id', $workspace->id)->with(['contact', 'company', 'stage', 'pipeline', 'assignedUser'])->findOrFail($id);

        return response()->json(['success' => true, 'data' => $deal]);
    }

    public function updateDeal(Request $request, int $id): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $deal = CrmDeal::where('workspace_id', $workspace->id)->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:191'],
            'value' => ['sometimes', 'numeric', 'min:0'],
            'probability' => ['nullable', 'integer', 'between:0,100'],
            'status' => ['nullable', 'in:open,won,lost'],
            'assigned_user_id' => ['nullable', 'exists:users,id'],
        ]);

        $deal->update($validated);

        return response()->json(['success' => true, 'data' => $deal->fresh(['stage', 'pipeline', 'contact'])]);
    }

    public function deleteDeal(Request $request, int $id): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $deal = CrmDeal::where('workspace_id', $workspace->id)->findOrFail($id);
        $deal->delete();

        return response()->json(['success' => true, 'message' => 'Deal deleted.']);
    }

    // ── TASKS ────────────────────────────────────────────────────────

    public function listTasks(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $tasks = CrmTask::where('workspace_id', $workspace->id)
            ->with(['contact', 'deal', 'assignedUser'])
            ->latest()
            ->paginate(25);

        return response()->json([
            'success' => true,
            'data' => $tasks->items(),
            'meta' => [
                'current_page' => $tasks->currentPage(),
                'total' => $tasks->total(),
            ],
        ]);
    }

    public function storeTask(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'due_at' => ['nullable', 'date'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'status' => ['nullable', 'in:pending,in_progress,completed,overdue,cancelled'],
            'contact_id' => ['nullable', 'exists:contacts,id'],
            'deal_id' => ['nullable', 'exists:crm_deals,id'],
            'assigned_user_id' => ['nullable', 'exists:users,id'],
        ]);

        $task = CrmTask::create([
            'workspace_id' => $workspace->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'due_at' => $validated['due_at'] ?? null,
            'priority' => $validated['priority'],
            'status' => $validated['status'] ?? 'pending',
            'contact_id' => $validated['contact_id'] ?? null,
            'deal_id' => $validated['deal_id'] ?? null,
            'created_by_id' => $request->user()->id,
            'assigned_user_id' => $validated['assigned_user_id'] ?? $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Task created.',
            'data' => $task->load(['contact', 'deal', 'assignedUser']),
        ], 201);
    }

    public function updateTask(Request $request, int $id): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $task = CrmTask::where('workspace_id', $workspace->id)->findOrFail($id);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'in:pending,in_progress,completed,overdue,cancelled'],
            'priority' => ['sometimes', 'in:low,medium,high,urgent'],
            'due_at' => ['nullable', 'date'],
        ]);

        $task->update($validated);

        return response()->json(['success' => true, 'data' => $task->fresh(['contact', 'deal'])]);
    }

    public function deleteTask(Request $request, int $id): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $task = CrmTask::where('workspace_id', $workspace->id)->findOrFail($id);
        $task->delete();

        return response()->json(['success' => true, 'message' => 'Task deleted.']);
    }

    // ── PIPELINES, TIMELINE, CUSTOM FIELDS, ANALYTICS ────────────────

    public function listPipelines(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $pipelines = $this->pipelineService->getWorkspacePipelines($workspace->id);

        return response()->json(['success' => true, 'data' => $pipelines]);
    }

    public function listCustomFields(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $entityType = $request->query('entity_type', 'lead');
        $fields = $this->customFieldService->getFields($workspace->id, $entityType);

        return response()->json(['success' => true, 'data' => $fields]);
    }

    public function getTimeline(Request $request, int|string $contactId): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $contact = Contact::where('workspace_id', $workspace->id)
            ->where(function ($q) use ($contactId) {
                if (is_numeric($contactId)) {
                    $q->where('id', (int) $contactId);
                } else {
                    $q->where('uuid', $contactId);
                }
            })->firstOrFail();

        $timeline = $this->timelineService->getTimeline($contact, $request->all());

        return response()->json(['success' => true, 'data' => $timeline]);
    }

    public function getAnalytics(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $days = (int) $request->query('days', 30);
        $metrics = $this->analyticsService->getDashboardMetrics($workspace->id, $days);

        return response()->json(['success' => true, 'data' => $metrics]);
    }
}
