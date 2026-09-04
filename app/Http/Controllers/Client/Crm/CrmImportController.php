<?php

namespace App\Http\Controllers\Client\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmPipeline;
use App\Models\User;
use App\Services\Crm\CrmAuditService;
use App\Services\Crm\CrmImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CrmImportController extends Controller
{
    public function __construct(
        private readonly CrmImportService $importService,
        private readonly CrmAuditService $auditService
    ) {}

    public function index(Request $request): Response
    {
        $workspace = $request->user()->currentWorkspace;
        $pipelines = CrmPipeline::where('workspace_id', $workspace->id)->with('stages')->get();
        $teamMembers = User::where('workspace_id', $workspace->id)->where('status', 'active')->get(['id', 'name', 'email']);

        return Inertia::render('Crm/Import/Index', [
            'pipelines' => $pipelines,
            'teamMembers' => $teamMembers,
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $file = $request->file('file');
        $parsed = $this->importService->parseCsv($file);

        return response()->json([
            'success' => true,
            'headers' => $parsed['headers'],
            'total_rows' => $parsed['total'],
            'sample_rows' => array_slice($parsed['rows'], 0, 5),
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'headers' => ['required', 'array'],
            'rows' => ['required', 'array'],
            'column_mapping' => ['required', 'array'],
        ]);

        $preview = $this->importService->preview(
            $validated['headers'],
            $validated['rows'],
            $validated['column_mapping']
        );

        return response()->json([
            'success' => true,
            'preview' => $preview,
        ]);
    }

    public function process(Request $request): JsonResponse|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $validated = $request->validate([
            'headers' => ['required', 'array'],
            'rows' => ['required', 'array'],
            'column_mapping' => ['required', 'array'],
            'duplicate_strategy' => ['required', 'in:skip,update,duplicate'],
            'assigned_user_id' => ['nullable', 'exists:users,id'],
            'pipeline_id' => ['nullable', 'exists:crm_pipelines,id'],
            'stage_id' => ['nullable', 'exists:crm_pipeline_stages,id'],
        ]);

        $result = $this->importService->import(
            workspace: $workspace,
            headers: $validated['headers'],
            rows: $validated['rows'],
            columnMapping: $validated['column_mapping'],
            duplicateStrategy: $validated['duplicate_strategy'],
            assignedUserId: $validated['assigned_user_id'] ? (int) $validated['assigned_user_id'] : null,
            pipelineId: $validated['pipeline_id'] ? (int) $validated['pipeline_id'] : null,
            stageId: $validated['stage_id'] ? (int) $validated['stage_id'] : null
        );

        $this->auditService->log(
            workspace: $workspace,
            actor: $request->user(),
            action: 'csv_imported',
            entityType: 'import',
            entityId: 0,
            newValues: $result,
            description: "CSV Import: {$result['imported']} imported, {$result['updated']} updated, {$result['skipped']} skipped, {$result['failed']} failed."
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Import complete. {$result['imported']} records imported.",
                'data' => $result,
            ]);
        }

        return back()->with('import_result', $result);
    }
}
