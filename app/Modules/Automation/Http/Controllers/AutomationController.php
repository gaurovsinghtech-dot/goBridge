<?php

namespace App\Modules\Automation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmPipelineStage;
use App\Models\User;
use App\Modules\AI\Models\AiChatbot;
use App\Modules\Automation\Models\Automation;
use App\Modules\Automation\Models\AutomationRun;
use App\Modules\Automation\Services\AutomationEngine;
use App\Modules\Automation\Services\AutomationTemplateRegistry;
use App\Modules\Broadcasting\Models\Campaign;
use App\Modules\Ecommerce\Models\EcommerceStore;
use App\Modules\Integrations\Models\IntegrationConfig;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Whatsapp\Models\WhatsappTemplate;
use App\Services\Automation\WorkflowAiBuilderService;
use App\Services\Automation\WorkflowExecutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AutomationController extends Controller
{
    public function __construct(
        protected WorkflowExecutionService $workflowExecutionService,
        protected WorkflowAiBuilderService $workflowAiBuilderService,
    ) {}

    private function workspaceId(Request $request): int
    {
        return (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
    }

    public function index(Request $request): Response
    {
        $wid = $this->workspaceId($request);
        $automations = Automation::where('workspace_id', $wid)
            ->withCount('runs')
            ->latest()
            ->get();

        $totalRuns = AutomationRun::whereHas('automation', fn ($q) => $q->where('workspace_id', $wid))->count();
        $successfulRuns = AutomationRun::whereHas('automation', fn ($q) => $q->where('workspace_id', $wid))
            ->where('status', 'completed')->count();
        $failedRuns = AutomationRun::whereHas('automation', fn ($q) => $q->where('workspace_id', $wid))
            ->where('status', 'failed')->count();

        $stats = [
            'total' => $automations->count(),
            'active' => $automations->where('status', 'active')->count(),
            'paused' => $automations->where('status', 'paused')->count(),
            'draft' => $automations->where('status', 'draft')->count(),
            'total_runs' => $totalRuns,
            'successful_runs' => $successfulRuns,
            'failed_runs' => $failedRuns,
            'success_rate' => $totalRuns > 0 ? round(($successfulRuns / $totalRuns) * 100, 1) : 100,
        ];

        return Inertia::render('Automation/Index', [
            'automations' => $automations,
            'stats' => $stats,
            'templates' => AutomationTemplateRegistry::all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $wid = $this->workspaceId($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'description' => ['nullable', 'string', 'max:500'],
            'template_key' => ['nullable', 'string'],
        ]);

        $nodes = [
            ['id' => 'trigger-1', 'type' => 'trigger', 'position' => ['x' => 250, 'y' => 50], 'data' => ['label' => 'Trigger', 'event' => 'message.received']],
        ];
        $edges = [];
        $triggerType = 'message.received';

        // Check if template requested
        if (! empty($validated['template_key'])) {
            $templates = collect(AutomationTemplateRegistry::all())->keyBy('key');
            if ($templates->has($validated['template_key'])) {
                $tpl = $templates->get($validated['template_key']);
                $triggerType = $tpl['trigger'] ?? 'message.received';
                $aiGenerated = $this->workflowAiBuilderService->generateFromPrompt($tpl['description'] ?? $tpl['title'], $wid);
                $nodes = $aiGenerated['nodes'];
                $edges = $aiGenerated['edges'];
            }
        }

        $auto = Automation::create([
            'workspace_id' => $wid,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'status' => 'draft',
            'trigger_type' => $triggerType,
            'nodes' => $nodes,
            'edges' => $edges,
        ]);

        return redirect()->route('client.automations.edit', $auto->uuid)->with('success', 'Automation created.');
    }

    public function edit(Request $request, Automation $automation): Response
    {
        $this->authorise($request, $automation);
        $wid = (int) $automation->workspace_id;

        return Inertia::render('Automation/Builder', [
            'automation' => $automation,
            'resources' => $this->builderResources($wid, $automation->id),
        ]);
    }

    private function builderResources(int $workspaceId, int $currentAutomationId): array
    {
        return [
            'templates' => WhatsappTemplate::where('workspace_id', $workspaceId)
                ->orderByRaw("CASE WHEN status = 'APPROVED' THEN 0 ELSE 1 END")
                ->orderBy('name')
                ->get(['name', 'language', 'status', 'components'])
                ->values(),
            'campaigns' => Campaign::where('workspace_id', $workspaceId)
                ->latest()->limit(100)->get(['id', 'name'])->values(),
            'chatbots' => AiChatbot::where('workspace_id', $workspaceId)
                ->where('enabled', true)->orderBy('name')->get(['id', 'name'])->values(),
            'subflows' => Automation::where('workspace_id', $workspaceId)
                ->where('id', '!=', $currentAutomationId)
                ->orderBy('name')->get(['uuid', 'name', 'status'])->values(),
            'agents' => User::where('workspace_id', $workspaceId)
                ->orderBy('name')->get(['id', 'name'])->values(),
            'stages' => CrmPipelineStage::where('workspace_id', $workspaceId)
                ->orderBy('position')->get(['id', 'name', 'color'])->values(),
            'stores' => EcommerceStore::where('workspace_id', $workspaceId)
                ->get(['id', 'platform', 'name'])->values(),
            'contacts' => Contact::where('workspace_id', $workspaceId)
                ->latest()->limit(50)->get(['id', 'first_name', 'last_name', 'phone_e164', 'email', 'lead_score'])->values(),
            'channels' => [
                'whatsapp' => (bool) ChannelAccount::where('workspace_id', $workspaceId)->where('channel', 'whatsapp')->where('is_active', true)->exists(),
                'instagram' => (bool) ChannelAccount::where('workspace_id', $workspaceId)->where('channel', 'instagram')->where('is_active', true)->exists(),
                'messenger' => (bool) ChannelAccount::where('workspace_id', $workspaceId)->where('channel', 'messenger')->where('is_active', true)->exists(),
                'email' => true,
            ],
            'integrations' => [
                'google' => (bool) optional(IntegrationConfig::forProvider('google_workspace'))->enabled,
                'heyo' => true,
            ],
        ];
    }

    public function update(Request $request, Automation $automation): RedirectResponse
    {
        $this->authorise($request, $automation);
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:128'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['sometimes', 'in:active,paused,draft,archived'],
            'trigger_type' => ['nullable', 'string', 'max:64'],
            'trigger_config' => ['nullable', 'array'],
            'nodes' => ['nullable', 'array'],
            'edges' => ['nullable', 'array'],
        ]);

        $automation->update($validated);

        return back()->with('success', 'Automation saved.');
    }

    public function activate(Request $request, Automation $automation): JsonResponse
    {
        $this->authorise($request, $automation);
        $automation->update(['status' => 'active']);

        return response()->json(['ok' => true, 'status' => 'active', 'message' => 'Automation activated successfully.']);
    }

    public function pause(Request $request, Automation $automation): JsonResponse
    {
        $this->authorise($request, $automation);
        $automation->update(['status' => 'paused']);

        return response()->json(['ok' => true, 'status' => 'paused', 'message' => 'Automation paused.']);
    }

    public function duplicate(Request $request, Automation $automation): JsonResponse
    {
        $this->authorise($request, $automation);

        $clone = Automation::create([
            'workspace_id' => $automation->workspace_id,
            'name' => $automation->name.' (Copy)',
            'description' => $automation->description,
            'status' => 'draft',
            'trigger_type' => $automation->trigger_type,
            'trigger_config' => $automation->trigger_config,
            'nodes' => $automation->nodes,
            'edges' => $automation->edges,
        ]);

        return response()->json([
            'ok' => true,
            'data' => $clone,
            'redirect' => route('client.automations.edit', $clone->uuid),
        ]);
    }

    public function destroy(Request $request, Automation $automation): RedirectResponse
    {
        $this->authorise($request, $automation);
        $automation->delete();

        return redirect()->route('client.automations.index')->with('success', 'Automation deleted.');
    }

    public function runs(Request $request, Automation $automation): Response
    {
        $this->authorise($request, $automation);
        $runs = AutomationRun::where('automation_id', $automation->id)
            ->with(['logs', 'contact'])
            ->latest()
            ->paginate(50);

        return Inertia::render('Automation/Runs', ['automation' => $automation, 'runs' => $runs]);
    }

    public function generateToken(Request $request, Automation $automation): JsonResponse
    {
        $this->authorise($request, $automation);
        $token = $automation->generateWebhookPublicKey();

        return response()->json(['webhook_public_key' => $token]);
    }

    /**
     * Test / Dry run simulation.
     */
    public function test(Request $request, Automation $automation): JsonResponse
    {
        $this->authorise($request, $automation);
        $validated = $request->validate([
            'contact_id' => ['nullable', 'integer'],
            'is_live_test' => ['nullable', 'boolean'],
            'sample_message' => ['nullable', 'string', 'max:1000'],
        ]);

        $contact = ! empty($validated['contact_id'])
            ? Contact::where('workspace_id', $automation->workspace_id)->find($validated['contact_id'])
            : null;

        $context = [
            'last_message' => $validated['sample_message'] ?? 'Can you give me pricing for 50 users?',
            'channel' => 'whatsapp',
        ];

        $run = $this->workflowExecutionService->startRun(
            $automation,
            $contact,
            $context,
            'test_simulation'
        );

        return response()->json([
            'ok' => true,
            'run_id' => $run->id,
            'status' => $run->status,
            'logs' => $run->logs()->get(),
            'context' => $run->context,
        ]);
    }

    /**
     * AI Workflow Generation from prompt.
     */
    public function generate(Request $request): JsonResponse
    {
        $wid = $this->workspaceId($request);
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:2000'],
            'persist' => ['nullable', 'boolean'],
        ]);

        try {
            $graph = $this->workflowAiBuilderService->generateFromPrompt($validated['prompt'], $wid);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        if ($request->boolean('persist')) {
            $auto = Automation::create([
                'workspace_id' => $wid,
                'name' => $graph['name'],
                'status' => 'draft',
                'trigger_type' => $graph['trigger_type'],
                'trigger_config' => $graph['trigger_config'],
                'nodes' => $graph['nodes'],
                'edges' => $graph['edges'],
            ]);

            return response()->json(['ok' => true, 'redirect' => route('client.automations.edit', $auto->uuid)]);
        }

        return response()->json(['ok' => true, 'graph' => $graph]);
    }

    private function authorise(Request $request, Automation $automation): void
    {
        abort_unless((int) $automation->workspace_id === $this->workspaceId($request), 403);
    }
}
