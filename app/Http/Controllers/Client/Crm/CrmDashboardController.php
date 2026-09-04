<?php

namespace App\Http\Controllers\Client\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmTeam;
use App\Models\User;
use App\Services\Crm\CrmAnalyticsService;
use App\Services\Crm\CrmPipelineService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CrmDashboardController extends Controller
{
    public function __construct(
        private readonly CrmPipelineService $pipelineService,
        private readonly CrmAnalyticsService $analyticsService
    ) {}

    public function index(Request $request): Response
    {
        $workspace = $request->user()->currentWorkspace;
        $wid = $workspace->id;

        $pipelineId = $request->input('pipeline_id') ? (int) $request->input('pipeline_id') : null;
        $filters = [
            'search' => $request->input('search'),
            'assigned_user_id' => $request->input('assigned_user_id'),
            'source' => $request->input('source'),
            'band' => $request->input('band'),
            'due_only' => $request->boolean('due_only'),
        ];

        $board = $this->pipelineService->getKanbanBoard($wid, $pipelineId, $filters);
        $pipelines = $this->pipelineService->getWorkspacePipelines($wid);
        $kpis = $this->analyticsService->getDashboardMetrics($wid);

        $teamMembers = User::where('workspace_id', $wid)
            ->where('status', 'active')
            ->get(['id', 'name', 'email']);

        $teams = CrmTeam::where('workspace_id', $wid)->get(['id', 'name']);

        return Inertia::render('Crm/Dashboard', [
            'board' => $board,
            'pipelines' => $pipelines,
            'kpis' => $kpis,
            'teamMembers' => $teamMembers,
            'teams' => $teams,
            'filters' => $filters,
        ]);
    }
}
