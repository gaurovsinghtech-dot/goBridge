<?php

namespace App\Http\Controllers\Client\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmCompany;
use App\Models\Crm\CrmDeal;
use App\Models\Crm\CrmPipeline;
use App\Models\Crm\CrmTeam;
use App\Models\User;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\ContactTag;
use App\Services\Crm\AiLeadQualificationService;
use App\Services\Crm\ContactDuplicateService;
use App\Services\Crm\CrmAuditService;
use App\Services\Crm\CrmCustomFieldService;
use App\Services\Crm\CrmPipelineService;
use App\Services\Crm\LeadAssignmentService;
use App\Services\CustomerJourney\CustomerJourneyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CrmLeadController extends Controller
{
    public function __construct(
        private readonly CrmPipelineService $pipelineService,
        private readonly LeadAssignmentService $assignmentService,
        private readonly AiLeadQualificationService $qualificationService,
        private readonly CustomerJourneyService $journeyService,
        private readonly ContactDuplicateService $duplicateService,
        private readonly CrmCustomFieldService $customFieldService,
        private readonly CrmAuditService $auditService
    ) {}

    public function index(Request $request): Response|JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $leads = Contact::where('workspace_id', $workspace->id)
            ->with(['stage', 'pipeline', 'assignedUser', 'assignedTeam', 'tags', 'crmCompany'])
            ->when($request->search, function ($q) use ($request) {
                $search = $request->search;
                $q->where(function ($sub) use ($search) {
                    $sub->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('phone_e164', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('company', 'like', "%{$search}%")
                        ->orWhere('id', is_numeric($search) ? (int) $search : 0);
                });
            })
            ->when($request->owner_id, fn ($q) => $q->where('assigned_user_id', $request->owner_id))
            ->when($request->pipeline_id, fn ($q) => $q->where('pipeline_id', $request->pipeline_id))
            ->when($request->stage_id, fn ($q) => $q->where('stage_id', $request->stage_id))
            ->when($request->source, fn ($q) => $q->where('source', $request->source))
            ->when($request->priority, fn ($q) => $q->where('priority', $request->priority))
            ->when($request->min_value, fn ($q) => $q->where('deal_value', '>=', (float) $request->min_value))
            ->when($request->max_value, fn ($q) => $q->where('deal_value', '<=', (float) $request->max_value))
            ->when($request->tag, fn ($q) => $q->whereHas('tags', fn ($tq) => $tq->where('name', $request->tag)))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $leads->items(),
                'meta' => [
                    'current_page' => $leads->currentPage(),
                    'total' => $leads->total(),
                ],
            ]);
        }

        $pipelines = $this->pipelineService->getWorkspacePipelines($workspace->id);
        $teamMembers = User::where('workspace_id', $workspace->id)->where('status', 'active')->get(['id', 'name', 'email']);
        $tags = ContactTag::where('workspace_id', $workspace->id)->orderBy('name')->get();
        $customFields = $this->customFieldService->getFields($workspace->id, 'lead');

        return Inertia::render('Crm/Leads/Index', [
            'leads' => $leads,
            'pipelines' => $pipelines,
            'teamMembers' => $teamMembers,
            'tags' => $tags,
            'customFields' => $customFields,
            'filters' => $request->only('search', 'owner_id', 'pipeline_id', 'stage_id', 'source', 'priority', 'tag'),
        ]);
    }

    public function show(Request $request, string $uuid): Response|JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $contact = Contact::where('workspace_id', $workspace->id)
            ->where(function ($q) use ($uuid) {
                if (is_numeric($uuid)) {
                    $q->where('id', (int) $uuid);
                } else {
                    $q->where('uuid', $uuid);
                }
            })
            ->with([
                'stage', 'pipeline', 'assignedUser', 'assignedTeam', 'crmCompany',
                'tags', 'deals.stage', 'crmTasks.assignedUser', 'crmNotes.user',
                'conversations.messages' => fn ($q) => $q->latest()->limit(5),
                'voiceCalls' => fn ($q) => $q->latest()->limit(5),
                'timelineEvents' => fn ($q) => $q->latest('occurred_at')->limit(30),
            ])
            ->firstOrFail();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $contact,
            ]);
        }

        $pipelines = $this->pipelineService->getWorkspacePipelines($workspace->id);
        $teamMembers = User::where('workspace_id', $workspace->id)->where('status', 'active')->get(['id', 'name', 'email']);
        $teams = CrmTeam::where('workspace_id', $workspace->id)->get(['id', 'name']);
        $customFields = $this->customFieldService->getFields($workspace->id, 'lead');

        return Inertia::render('Crm/LeadDetail', [
            'lead' => $contact,
            'pipelines' => $pipelines,
            'teamMembers' => $teamMembers,
            'teams' => $teams,
            'customFields' => $customFields,
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

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
            'next_follow_up_at' => ['nullable', 'date'],
            'tags' => ['nullable', 'array'],
            'custom_fields' => ['nullable', 'array'],
        ]);

        $customFieldValues = $this->customFieldService->validateValues(
            $workspace->id,
            'lead',
            $validated['custom_fields'] ?? []
        );

        $defaultPipeline = $this->pipelineService->ensureDefaultPipeline($workspace->id);
        $stageId = $validated['stage_id'] ?? $defaultPipeline->stages()->first()?->id;

        // Normalized deduplication check
        $normalizedPhone = $this->duplicateService->normalizePhone($validated['phone_e164'] ?? null);
        $normalizedEmail = $this->duplicateService->normalizeEmail($validated['email'] ?? null);

        $contact = $this->duplicateService->findDuplicate($workspace->id, $normalizedPhone, $normalizedEmail);

        if ($contact) {
            $contact->update(array_filter([
                'company' => $validated['company'] ?? $contact->company,
                'company_id' => $validated['company_id'] ?? $contact->company_id,
                'deal_value' => $validated['deal_value'] ?? $contact->deal_value,
                'pipeline_id' => $validated['pipeline_id'] ?? $contact->pipeline_id ?? $defaultPipeline->id,
                'stage_id' => $stageId ?? $contact->stage_id,
                'assigned_user_id' => $validated['assigned_user_id'] ?? $contact->assigned_user_id,
                'priority' => $validated['priority'] ?? $contact->priority,
                'next_follow_up_at' => $validated['next_follow_up_at'] ?? $contact->next_follow_up_at,
                'custom_fields' => array_merge($contact->custom_fields ?? [], $customFieldValues),
            ]));
        } else {
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
                'stage_id' => $stageId,
                'assigned_user_id' => $validated['assigned_user_id'] ?? null,
                'source' => $validated['source'] ?? 'manual',
                'priority' => $validated['priority'] ?? 'medium',
                'next_follow_up_at' => $validated['next_follow_up_at'] ?? null,
                'custom_fields' => $customFieldValues,
            ]);
        }

        // Attach tags
        if (! empty($validated['tags'])) {
            foreach ($validated['tags'] as $tagName) {
                $tag = ContactTag::firstOrCreate(['workspace_id' => $workspace->id, 'name' => trim($tagName)]);
                $contact->tags()->syncWithoutDetaching([$tag->id]);
            }
        }

        $this->journeyService->recordEvent(
            contactId: $contact->id,
            workspaceId: $workspace->id,
            eventType: 'crm_lead_created',
            channel: 'crm',
            title: 'Lead Created in CRM',
            description: "Lead {$contact->full_name} created with initial value ₹{$contact->deal_value}",
            metadata: ['source' => $contact->source]
        );

        $this->auditService->log(
            workspace: $workspace,
            actor: $request->user(),
            action: 'lead_created',
            entityType: 'contact',
            entityId: $contact->id,
            newValues: $contact->toArray(),
            description: "Lead {$contact->full_name} created."
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Lead created successfully.',
                'data' => $contact->load(['stage', 'pipeline', 'assignedUser', 'tags']),
            ], 201);
        }

        return redirect()->route('client.crm.leads.show', $contact->uuid)->with('success', __('Lead created successfully.'));
    }

    public function update(Request $request, string $uuid): RedirectResponse|JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $contact = Contact::where('workspace_id', $workspace->id)
            ->where(function ($q) use ($uuid) {
                if (is_numeric($uuid)) {
                    $q->where('id', (int) $uuid);
                } else {
                    $q->where('uuid', $uuid);
                }
            })->firstOrFail();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:128'],
            'last_name' => ['nullable', 'string', 'max:128'],
            'company' => ['nullable', 'string', 'max:191'],
            'company_id' => ['nullable', 'exists:crm_companies,id'],
            'phone_e164' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:191'],
            'deal_value' => ['nullable', 'numeric', 'min:0'],
            'priority' => ['nullable', 'in:low,medium,high,urgent'],
            'assigned_user_id' => ['nullable', 'exists:users,id'],
            'assigned_team_id' => ['nullable', 'exists:crm_teams,id'],
            'next_follow_up_at' => ['nullable', 'date'],
            'custom_fields' => ['nullable', 'array'],
        ]);

        $oldValues = $contact->toArray();

        if (isset($validated['custom_fields'])) {
            $validated['custom_fields'] = $this->customFieldService->validateValues(
                $workspace->id,
                'lead',
                $validated['custom_fields']
            );
        }

        $contact->update($validated);

        $this->auditService->log(
            workspace: $workspace,
            actor: $request->user(),
            action: 'lead_updated',
            entityType: 'contact',
            entityId: $contact->id,
            oldValues: $oldValues,
            newValues: $contact->toArray(),
            description: "Lead {$contact->full_name} updated."
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Lead updated successfully.',
                'data' => $contact->fresh(['stage', 'pipeline', 'assignedUser', 'tags']),
            ]);
        }

        return back()->with('success', __('Lead details updated successfully.'));
    }

    public function convert(Request $request, string $uuid): JsonResponse|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $contact = Contact::where('workspace_id', $workspace->id)
            ->where(function ($q) use ($uuid) {
                if (is_numeric($uuid)) {
                    $q->where('id', (int) $uuid);
                } else {
                    $q->where('uuid', $uuid);
                }
            })->firstOrFail();

        $validated = $request->validate([
            'deal_name' => ['nullable', 'string', 'max:191'],
            'deal_value' => ['nullable', 'numeric', 'min:0'],
            'pipeline_id' => ['nullable', 'exists:crm_pipelines,id'],
            'stage_id' => ['nullable', 'exists:crm_pipeline_stages,id'],
            'company_name' => ['nullable', 'string', 'max:191'],
            'company_id' => ['nullable', 'exists:crm_companies,id'],
        ]);

        // 1. Resolve company
        $companyId = $validated['company_id'] ?? $contact->company_id;
        if (! $companyId && ! empty($validated['company_name'])) {
            $company = CrmCompany::firstOrCreate(
                ['workspace_id' => $workspace->id, 'name' => $validated['company_name']],
                ['owner_user_id' => $contact->assigned_user_id]
            );
            $companyId = $company->id;
            $contact->update(['company_id' => $companyId, 'company' => $company->name]);
        }

        // 2. Create Deal
        $pipeline = ! empty($validated['pipeline_id'])
            ? CrmPipeline::where('workspace_id', $workspace->id)->findOrFail($validated['pipeline_id'])
            : $this->pipelineService->ensureDefaultPipeline($workspace->id);

        $stageId = ! empty($validated['stage_id']) ? $validated['stage_id'] : $pipeline->stages()->first()?->id;

        $deal = CrmDeal::create([
            'workspace_id' => $workspace->id,
            'contact_id' => $contact->id,
            'company_id' => $companyId,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stageId,
            'assigned_user_id' => $contact->assigned_user_id,
            'name' => $validated['deal_name'] ?? "Deal for {$contact->full_name}",
            'value' => $validated['deal_value'] ?? $contact->deal_value ?? 0,
            'currency' => 'INR',
            'status' => 'open',
            'probability' => 20,
        ]);

        $this->journeyService->recordEvent(
            contactId: $contact->id,
            workspaceId: $workspace->id,
            eventType: 'crm_lead_converted',
            channel: 'crm',
            title: 'Lead Converted to Deal',
            description: "Lead {$contact->full_name} converted to Deal: {$deal->name} (₹{$deal->value})",
            metadata: ['deal_id' => $deal->id, 'company_id' => $companyId]
        );

        $this->auditService->log(
            workspace: $workspace,
            actor: $request->user(),
            action: 'lead_converted',
            entityType: 'contact',
            entityId: $contact->id,
            newValues: ['deal_id' => $deal->id, 'company_id' => $companyId],
            description: "Lead {$contact->full_name} converted to deal {$deal->name}."
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Lead converted successfully.',
                'data' => [
                    'contact' => $contact->fresh(['deals', 'crmCompany']),
                    'deal' => $deal->load(['stage', 'pipeline']),
                ],
            ]);
        }

        return redirect()->route('client.crm.deals.show', $deal->id)->with('success', 'Lead converted to deal successfully.');
    }

    public function bulk(Request $request): JsonResponse|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $validated = $request->validate([
            'action' => ['required', 'in:assign,stage,delete,archive'],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
            'assigned_user_id' => ['nullable', 'exists:users,id'],
            'stage_id' => ['nullable', 'exists:crm_pipeline_stages,id'],
        ]);

        $query = Contact::where('workspace_id', $workspace->id)->whereIn('id', $validated['ids']);
        $count = $query->count();

        switch ($validated['action']) {
            case 'assign':
                $userId = $validated['assigned_user_id'] ? (int) $validated['assigned_user_id'] : null;
                $query->update(['assigned_user_id' => $userId]);
                break;

            case 'stage':
                $stageId = (int) $validated['stage_id'];
                $query->update(['stage_id' => $stageId]);
                break;

            case 'archive':
            case 'delete':
                $query->delete();
                break;
        }

        $this->auditService->log(
            workspace: $workspace,
            actor: $request->user(),
            action: "bulk_{$validated['action']}",
            entityType: 'contact',
            entityId: 0,
            newValues: ['affected_count' => $count, 'action' => $validated['action']],
            description: "Bulk {$validated['action']} on {$count} leads."
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Bulk action '{$validated['action']}' completed on {$count} records.",
            ]);
        }

        return back()->with('success', "Bulk {$validated['action']} completed on {$count} records.");
    }

    public function export(Request $request): StreamedResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->auditService->log(
            workspace: $workspace,
            actor: $request->user(),
            action: 'leads_exported',
            entityType: 'export',
            entityId: 0,
            description: 'CRM leads exported to CSV.'
        );

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="crm_leads_'.date('Y_m_d_His').'.csv"',
        ];

        return response()->stream(function () use ($workspace) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'First Name', 'Last Name', 'Company', 'Phone', 'Email', 'Deal Value', 'Priority', 'Pipeline', 'Stage', 'Assigned To', 'Created At']);

            Contact::where('workspace_id', $workspace->id)
                ->with(['stage', 'pipeline', 'assignedUser'])
                ->chunk(200, function ($contacts) use ($handle) {
                    foreach ($contacts as $c) {
                        fputcsv($handle, [
                            $c->id,
                            $c->first_name,
                            $c->last_name,
                            $c->company,
                            $c->phone_e164,
                            $c->email,
                            $c->deal_value,
                            $c->priority,
                            $c->pipeline?->name,
                            $c->stage?->name,
                            $c->assignedUser?->name,
                            $c->created_at?->toDateTimeString(),
                        ]);
                    }
                });

            fclose($handle);
        }, 200, $headers);
    }

    public function updateStage(Request $request, string $uuid): JsonResponse|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $contact = Contact::where('workspace_id', $workspace->id)
            ->where(function ($q) use ($uuid) {
                if (is_numeric($uuid)) {
                    $q->where('id', (int) $uuid);
                } else {
                    $q->where('uuid', $uuid);
                }
            })->firstOrFail();

        $validated = $request->validate([
            'stage_id' => ['required', 'exists:crm_pipeline_stages,id'],
            'loss_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $this->pipelineService->moveContactStage(
            contact: $contact,
            stageId: (int) $validated['stage_id'],
            lossReason: $validated['loss_reason'] ?? null,
            actor: $request->user()
        );

        $this->auditService->log(
            workspace: $workspace,
            actor: $request->user(),
            action: 'stage_changed',
            entityType: 'contact',
            entityId: $contact->id,
            newValues: ['stage_id' => $validated['stage_id']],
            description: "Stage changed for {$contact->full_name}."
        );

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('Stage updated successfully.')]);
        }

        return back()->with('success', __('Stage updated successfully.'));
    }

    public function qualifyAi(Request $request, string $uuid): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $contact = Contact::where('workspace_id', $workspace->id)
            ->where(function ($q) use ($uuid) {
                if (is_numeric($uuid)) {
                    $q->where('id', (int) $uuid);
                } else {
                    $q->where('uuid', $uuid);
                }
            })->firstOrFail();

        $result = $this->qualificationService->qualifyContact($contact, [
            'message' => $request->input('context_message', ''),
        ]);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    public function assign(Request $request, string $uuid): RedirectResponse|JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $contact = Contact::where('workspace_id', $workspace->id)
            ->where(function ($q) use ($uuid) {
                if (is_numeric($uuid)) {
                    $q->where('id', (int) $uuid);
                } else {
                    $q->where('uuid', $uuid);
                }
            })->firstOrFail();

        $strategy = $request->input('strategy', 'manual');
        $userId = $request->input('assigned_user_id');
        $teamId = $request->input('assigned_team_id');

        if ($strategy === 'manual' && $userId) {
            $user = User::where('workspace_id', $workspace->id)->findOrFail($userId);
            $contact->update(['assigned_user_id' => $user->id, 'assigned_team_id' => $teamId]);
        } else {
            $this->assignmentService->assignLead(
                contact: $contact,
                strategy: $strategy,
                teamId: $teamId ? (int) $teamId : null,
                actor: $request->user()
            );
        }

        $this->auditService->log(
            workspace: $workspace,
            actor: $request->user(),
            action: 'lead_assigned',
            entityType: 'contact',
            entityId: $contact->id,
            newValues: ['assigned_user_id' => $userId, 'assigned_team_id' => $teamId],
            description: "Lead {$contact->full_name} assigned."
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Lead assigned successfully.',
                'data' => $contact->fresh(['assignedUser', 'assignedTeam']),
            ]);
        }

        return back()->with('success', __('Lead assigned successfully.'));
    }

    public function destroy(Request $request, string $uuid): RedirectResponse|JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $contact = Contact::where('workspace_id', $workspace->id)
            ->where(function ($q) use ($uuid) {
                if (is_numeric($uuid)) {
                    $q->where('id', (int) $uuid);
                } else {
                    $q->where('uuid', $uuid);
                }
            })->firstOrFail();

        $name = $contact->full_name;
        $id = $contact->id;
        $contact->delete();

        $this->auditService->log(
            workspace: $workspace,
            actor: $request->user(),
            action: 'lead_deleted',
            entityType: 'contact',
            entityId: $id,
            description: "Lead {$name} archived/deleted."
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Lead deleted/archived successfully.',
            ]);
        }

        return back()->with('success', 'Lead archived successfully.');
    }
}
