<?php

namespace App\Http\Controllers\Client\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmPipeline;
use App\Models\Crm\CrmPipelineStage;
use App\Services\Crm\CrmAuditService;
use App\Services\Crm\CrmPipelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CrmPipelineController extends Controller
{
    public function __construct(
        private readonly CrmPipelineService $pipelineService,
        private readonly CrmAuditService $auditService
    ) {}

    public function index(Request $request): Response|JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $pipelines = $this->pipelineService->getWorkspacePipelines($workspace->id);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $pipelines,
            ]);
        }

        return Inertia::render('Crm/Pipelines', [
            'pipelines' => $pipelines,
            'colors' => CrmPipelineStage::COLORS,
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        if (! empty($validated['is_default'])) {
            CrmPipeline::where('workspace_id', $workspace->id)->update(['is_default' => false]);
        }

        $pipeline = CrmPipeline::create([
            'workspace_id' => $workspace->id,
            'name' => $validated['name'],
            'is_default' => $validated['is_default'] ?? false,
        ]);

        // Seed default stages
        foreach (CrmPipelineStage::DEFAULTS as $idx => $stageData) {
            CrmPipelineStage::create(array_merge($stageData, [
                'workspace_id' => $workspace->id,
                'pipeline_id' => $pipeline->id,
                'position' => $idx,
            ]));
        }

        $this->auditService->log(
            workspace: $workspace,
            actor: $request->user(),
            action: 'pipeline_created',
            entityType: 'pipeline',
            entityId: $pipeline->id,
            description: "Pipeline '{$pipeline->name}' created."
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Pipeline created successfully.',
                'data' => $pipeline->load('stages'),
            ], 201);
        }

        return back()->with('success', __('Pipeline created successfully.'));
    }

    public function update(Request $request, CrmPipeline $pipeline): RedirectResponse|JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless((int) $pipeline->workspace_id === (int) $workspace->id, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        if (! empty($validated['is_default'])) {
            CrmPipeline::where('workspace_id', $workspace->id)->where('id', '!=', $pipeline->id)->update(['is_default' => false]);
        }

        $pipeline->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Pipeline updated successfully.',
                'data' => $pipeline->fresh('stages'),
            ]);
        }

        return back()->with('success', 'Pipeline updated successfully.');
    }

    public function destroy(Request $request, CrmPipeline $pipeline): RedirectResponse|JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless((int) $pipeline->workspace_id === (int) $workspace->id, 403);

        if ($pipeline->deals()->count() > 0 || $pipeline->contacts()->count() > 0) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Cannot delete pipeline with active deals/leads.'], 422);
            }
            return back()->with('error', 'Cannot delete pipeline with active deals/leads.');
        }

        $name = $pipeline->name;
        $id = $pipeline->id;
        $pipeline->stages()->delete();
        $pipeline->delete();

        $this->auditService->log(
            workspace: $workspace,
            actor: $request->user(),
            action: 'pipeline_deleted',
            entityType: 'pipeline',
            entityId: $id,
            description: "Pipeline '{$name}' deleted."
        );

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Pipeline deleted.']);
        }

        return back()->with('success', 'Pipeline deleted successfully.');
    }

    public function storeStage(Request $request, CrmPipeline $pipeline): RedirectResponse|JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless((int) $pipeline->workspace_id === (int) $workspace->id, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'color' => ['required', 'string', 'max:32'],
            'probability' => ['required', 'integer', 'between:0,100'],
            'is_won' => ['nullable', 'boolean'],
            'is_lost' => ['nullable', 'boolean'],
        ]);

        $maxPos = $pipeline->stages()->max('position') ?? -1;

        $stage = CrmPipelineStage::create(array_merge($validated, [
            'workspace_id' => $workspace->id,
            'pipeline_id' => $pipeline->id,
            'position' => $maxPos + 1,
            'is_won' => $validated['is_won'] ?? false,
            'is_lost' => $validated['is_lost'] ?? false,
        ]));

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Stage added successfully.',
                'data' => $stage,
            ], 201);
        }

        return back()->with('success', __('Stage added successfully.'));
    }

    public function updateStage(Request $request, CrmPipelineStage $stage): RedirectResponse|JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless((int) $stage->workspace_id === (int) $workspace->id, 403);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:128'],
            'color' => ['sometimes', 'string', 'max:32'],
            'probability' => ['sometimes', 'integer', 'between:0,100'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'is_won' => ['nullable', 'boolean'],
            'is_lost' => ['nullable', 'boolean'],
        ]);

        $stage->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Stage updated successfully.',
                'data' => $stage->fresh(),
            ]);
        }

        return back()->with('success', __('Stage updated successfully.'));
    }

    public function reorderStages(Request $request, CrmPipeline $pipeline): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless((int) $pipeline->workspace_id === (int) $workspace->id, 403);

        $validated = $request->validate([
            'stages' => ['required', 'array'],
            'stages.*.id' => ['required', 'exists:crm_pipeline_stages,id'],
            'stages.*.position' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($validated['stages'] as $item) {
            CrmPipelineStage::where('id', $item['id'])
                ->where('pipeline_id', $pipeline->id)
                ->where('workspace_id', $workspace->id)
                ->update(['position' => $item['position']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Stages reordered successfully.',
            'data' => $pipeline->stages()->orderBy('position')->get(),
        ]);
    }

    public function destroyStage(Request $request, CrmPipelineStage $stage): RedirectResponse|JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless((int) $stage->workspace_id === (int) $workspace->id, 403);

        if ($stage->contacts()->count() > 0 || $stage->deals()->count() > 0) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Cannot delete stage with active leads/deals.'], 422);
            }
            return back()->with('error', __('Cannot delete stage that contains active leads or deals. Move them first.'));
        }

        $stage->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Stage deleted successfully.']);
        }

        return back()->with('success', __('Stage deleted successfully.'));
    }
}
