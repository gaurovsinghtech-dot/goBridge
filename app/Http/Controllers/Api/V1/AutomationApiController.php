<?php

namespace App\Http\Controllers\Api\V1;

use App\Modules\Automation\Models\Automation;
use App\Modules\Automation\Models\AutomationRun;
use App\Modules\Shared\Models\Contact;
use App\Services\Automation\WorkflowExecutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AutomationApiController extends WorkspaceScopedController
{
    public function __construct(
        protected WorkflowExecutionService $workflowExecutionService
    ) {}

    /**
     * GET /api/v1/automations
     */
    public function index(Request $request): JsonResponse
    {
        $automations = Automation::where('workspace_id', $this->workspaceId($request))
            ->latest('id')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'uuid' => $a->uuid,
                'name' => $a->name,
                'description' => $a->description,
                'status' => $a->status,
                'trigger_type' => $a->trigger_type,
                'run_count' => $a->run_count,
                'successful_runs' => $a->successful_runs,
                'failed_runs' => $a->failed_runs,
                'webhook_public_key' => $a->webhook_public_key,
                'last_run_at' => optional($a->last_run_at)->toIso8601String(),
                'created_at' => $a->created_at->toIso8601String(),
            ]);

        return response()->json(['data' => $automations]);
    }

    /**
     * POST /api/v1/automations
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'description' => ['nullable', 'string', 'max:500'],
            'trigger_type' => ['nullable', 'string', 'max:64'],
            'trigger_config' => ['nullable', 'array'],
            'nodes' => ['nullable', 'array'],
            'edges' => ['nullable', 'array'],
        ]);

        $automation = Automation::create(array_merge($validated, [
            'workspace_id' => $this->workspaceId($request),
            'status' => 'draft',
            'nodes' => $validated['nodes'] ?? [
                ['id' => 'trigger-1', 'type' => 'trigger', 'position' => ['x' => 250, 'y' => 50], 'data' => ['label' => 'Trigger', 'event' => $validated['trigger_type'] ?? 'message.received']],
            ],
            'edges' => $validated['edges'] ?? [],
        ]));

        return response()->json(['ok' => true, 'data' => $automation], 201);
    }

    /**
     * GET /api/v1/automations/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $automation = Automation::where('workspace_id', $this->workspaceId($request))->find($id);

        if (! $automation) {
            return response()->json(['error' => 'Automation not found.'], 404);
        }

        return response()->json(['data' => $automation]);
    }

    /**
     * PUT/PATCH /api/v1/automations/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $automation = Automation::where('workspace_id', $this->workspaceId($request))->find($id);

        if (! $automation) {
            return response()->json(['error' => 'Automation not found.'], 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:128'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['sometimes', 'in:draft,active,paused,archived'],
            'trigger_type' => ['nullable', 'string', 'max:64'],
            'trigger_config' => ['nullable', 'array'],
            'nodes' => ['nullable', 'array'],
            'edges' => ['nullable', 'array'],
        ]);

        $automation->update($validated);

        return response()->json(['ok' => true, 'data' => $automation->fresh()]);
    }

    /**
     * DELETE /api/v1/automations/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $automation = Automation::where('workspace_id', $this->workspaceId($request))->find($id);

        if (! $automation) {
            return response()->json(['error' => 'Automation not found.'], 404);
        }

        $automation->delete();

        return response()->json(['ok' => true, 'message' => 'Automation deleted successfully.']);
    }

    /**
     * POST /api/v1/automations/{id}/activate
     */
    public function activate(Request $request, int $id): JsonResponse
    {
        $automation = Automation::where('workspace_id', $this->workspaceId($request))->find($id);

        if (! $automation) {
            return response()->json(['error' => 'Automation not found.'], 404);
        }

        $automation->update(['status' => 'active']);

        return response()->json(['ok' => true, 'status' => 'active', 'message' => 'Automation activated.']);
    }

    /**
     * POST /api/v1/automations/{id}/pause
     */
    public function pause(Request $request, int $id): JsonResponse
    {
        $automation = Automation::where('workspace_id', $this->workspaceId($request))->find($id);

        if (! $automation) {
            return response()->json(['error' => 'Automation not found.'], 404);
        }

        $automation->update(['status' => 'paused']);

        return response()->json(['ok' => true, 'status' => 'paused', 'message' => 'Automation paused.']);
    }

    /**
     * POST /api/v1/automations/{id}/test
     */
    public function test(Request $request, int $id): JsonResponse
    {
        $automation = Automation::where('workspace_id', $this->workspaceId($request))->find($id);

        if (! $automation) {
            return response()->json(['error' => 'Automation not found.'], 404);
        }

        $validated = $request->validate([
            'contact_id' => ['nullable', 'integer'],
            'sample_message' => ['nullable', 'string', 'max:1000'],
            'context' => ['nullable', 'array'],
        ]);

        $contact = ! empty($validated['contact_id'])
            ? Contact::where('workspace_id', $this->workspaceId($request))->find($validated['contact_id'])
            : null;

        $run = $this->workflowExecutionService->startRun(
            $automation,
            $contact,
            $validated['context'] ?? ['message' => ['body' => $validated['sample_message'] ?? 'Pricing request']],
            'api_test'
        );

        return response()->json([
            'ok' => true,
            'run_id' => $run->id,
            'status' => $run->status,
            'logs' => $run->logs()->get(),
        ]);
    }

    /**
     * POST /api/v1/automations/{id}/trigger
     */
    public function trigger(Request $request, int $id): JsonResponse
    {
        $automation = Automation::where('workspace_id', $this->workspaceId($request))->find($id);

        if (! $automation) {
            return response()->json(['error' => 'Automation not found.'], 404);
        }

        $validated = $request->validate([
            'contact_id' => ['nullable', 'integer'],
            'sample_message' => ['nullable', 'string', 'max:1000'],
            'context' => ['nullable', 'array'],
        ]);

        if (! empty($validated['contact_id'])) {
            $contact = Contact::where('workspace_id', $this->workspaceId($request))->find($validated['contact_id']);
            if (! $contact) {
                return response()->json(['error' => 'Contact not found.'], 404);
            }
        } else {
            $contact = null;
        }

        $run = $this->workflowExecutionService->startRun(
            $automation,
            $contact,
            $validated['context'] ?? ['message' => ['body' => $validated['sample_message'] ?? 'Triggered via API']],
            'api_trigger'
        );

        if (class_exists(\App\Modules\Automation\Jobs\ExecuteAutomationRunJob::class)) {
            \App\Modules\Automation\Jobs\ExecuteAutomationRunJob::dispatch((int) $run->id);
        }

        return response()->json([
            'ok' => true,
            'automation_id' => $automation->id,
            'contact_id' => $contact?->id,
            'run_id' => $run->id,
            'status' => $run->status,
            'logs' => $run->logs()->get(),
        ], 201);
    }

    /**
     * GET /api/v1/automations/{id}/runs
     */
    public function runs(Request $request, int $id): JsonResponse
    {
        $automation = Automation::where('workspace_id', $this->workspaceId($request))->find($id);

        if (! $automation) {
            return response()->json(['error' => 'Automation not found.'], 404);
        }

        $runs = AutomationRun::where('automation_id', $automation->id)
            ->latest('id')
            ->paginate(50);

        return response()->json($runs);
    }

    /**
     * POST /api/v1/automations/webhook/{publicKey}
     * External inbound webhook trigger.
     */
    public function publicWebhook(Request $request, string $publicKey): JsonResponse
    {
        $automation = Automation::where('webhook_public_key', $publicKey)->first();

        if (! $automation || ! $automation->isActive()) {
            return response()->json(['error' => 'Invalid or inactive automation webhook key.'], 404);
        }

        $payload = $request->all();
        $contactId = $payload['contact_id'] ?? null;
        $contact = $contactId ? Contact::where('workspace_id', $automation->workspace_id)->find($contactId) : null;

        $run = $this->workflowExecutionService->startRun(
            $automation,
            $contact,
            ['webhook_payload' => $payload],
            'webhook.received'
        );

        return response()->json([
            'ok' => true,
            'run_id' => $run->id,
            'status' => $run->status,
            'message' => 'Automation triggered successfully via webhook.',
        ]);
    }
}
