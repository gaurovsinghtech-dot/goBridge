<?php

namespace App\Http\Controllers\Client\Crm;

use App\Http\Controllers\Controller;
use App\Services\Crm\CrmAnalyticsService;
use App\Services\Crm\CrmPipelineService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CrmReportController extends Controller
{
    public function __construct(
        private readonly CrmAnalyticsService $analyticsService,
        private readonly CrmPipelineService $pipelineService
    ) {}

    public function index(Request $request): Response
    {
        $workspace = $request->user()->currentWorkspace;
        $wid = $workspace->id;
        $days = (int) $request->integer('days', 30);

        $kpis = $this->analyticsService->getDashboardMetrics($wid, $days);
        $attribution = $this->analyticsService->getChannelAttribution($wid, $days);
        $funnel = $this->analyticsService->getConversionFunnel($wid);
        $pipelines = $this->pipelineService->getWorkspacePipelines($wid);

        return Inertia::render('Crm/Reports', [
            'kpis' => $kpis,
            'attribution' => $attribution,
            'funnel' => $funnel,
            'pipelines' => $pipelines,
            'days' => $days,
        ]);
    }
}
