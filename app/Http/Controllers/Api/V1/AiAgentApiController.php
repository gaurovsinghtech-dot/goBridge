<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\AI\Models\AiChatbot;
use App\Services\AI\AiAgentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiAgentApiController extends Controller
{
    public function __construct(
        protected AiAgentService $agentService
    ) {}

    protected function workspace(Request $request)
    {
        return $request->user()->current_workspace ?? $request->user()->workspace;
    }

    public function index(Request $request): JsonResponse
    {
        $ws = $this->workspace($request);
        $agents = AiChatbot::where('workspace_id', $ws->id)
            ->with(['knowledgeBase:id,name,status,category', 'humanAgent:id,name,email'])
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'ok' => true,
            'data' => $agents->items(),
            'pagination' => [
                'current_page' => $agents->currentPage(),
                'last_page' => $agents->lastPage(),
                'total' => $agents->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $ws = $this->workspace($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'purpose' => ['nullable', 'string', 'max:256'],
            'agent_type' => ['nullable', 'string', 'max:64'],
            'template_key' => ['nullable', 'string'],
            'language' => ['nullable', 'string', 'max:32'],
            'provider' => ['nullable', 'string', 'max:32'],
            'model' => ['nullable', 'string', 'max:64'],
            'temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'max_tokens' => ['nullable', 'integer', 'min:64', 'max:4096'],
            'status' => ['nullable', 'string', 'in:draft,active,paused,archived'],
            'response_mode' => ['nullable', 'string', 'in:auto_reply,suggested_reply,human_approval'],
            'confidence_threshold' => ['nullable', 'integer', 'min:1', 'max:100'],
            'strict_knowledge_mode' => ['nullable', 'boolean'],
            'memory_mode' => ['nullable', 'string'],
            'human_handoff_enabled' => ['nullable', 'boolean'],
            'human_handoff_user_id' => ['nullable', 'integer'],
            'human_handoff_message' => ['nullable', 'string', 'max:256'],
            'ai_kb_id' => ['nullable', 'integer'],
            'system_prompt' => ['nullable', 'string', 'max:8192'],
            'tone' => ['nullable', 'string', 'max:64'],
            'channels' => ['nullable', 'array'],
            'tools_enabled' => ['nullable', 'array'],
        ]);

        if (! empty($validated['template_key'])) {
            $agent = $this->agentService->createFromTemplate($ws->id, $validated['template_key'], $validated);
        } else {
            $agent = AiChatbot::create(array_merge($validated, [
                'workspace_id' => $ws->id,
                'status' => $validated['status'] ?? 'active',
                'enabled' => ($validated['status'] ?? 'active') === 'active',
            ]));
        }

        return response()->json([
            'ok' => true,
            'message' => 'AI Agent created successfully.',
            'data' => $agent->fresh(['knowledgeBase', 'humanAgent']),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $ws = $this->workspace($request);
        $agent = AiChatbot::where('workspace_id', $ws->id)
            ->with(['knowledgeBase', 'humanAgent'])
            ->findOrFail($id);

        return response()->json([
            'ok' => true,
            'data' => $agent,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $ws = $this->workspace($request);
        $agent = AiChatbot::where('workspace_id', $ws->id)->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:128'],
            'purpose' => ['nullable', 'string', 'max:256'],
            'agent_type' => ['nullable', 'string', 'max:64'],
            'language' => ['nullable', 'string', 'max:32'],
            'provider' => ['nullable', 'string', 'max:32'],
            'model' => ['nullable', 'string', 'max:64'],
            'temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'max_tokens' => ['nullable', 'integer', 'min:64', 'max:4096'],
            'status' => ['nullable', 'string', 'in:draft,active,paused,archived'],
            'response_mode' => ['nullable', 'string', 'in:auto_reply,suggested_reply,human_approval'],
            'confidence_threshold' => ['nullable', 'integer', 'min:1', 'max:100'],
            'strict_knowledge_mode' => ['nullable', 'boolean'],
            'human_handoff_enabled' => ['nullable', 'boolean'],
            'human_handoff_user_id' => ['nullable', 'integer'],
            'human_handoff_message' => ['nullable', 'string', 'max:256'],
            'ai_kb_id' => ['nullable', 'integer'],
            'system_prompt' => ['nullable', 'string', 'max:8192'],
            'tone' => ['nullable', 'string', 'max:64'],
            'channels' => ['nullable', 'array'],
            'tools_enabled' => ['nullable', 'array'],
        ]);

        $agent->update($validated);

        return response()->json([
            'ok' => true,
            'message' => 'AI Agent updated successfully.',
            'data' => $agent->fresh(['knowledgeBase', 'humanAgent']),
        ]);
    }

    public function activate(Request $request, int $id): JsonResponse
    {
        $ws = $this->workspace($request);
        $agent = AiChatbot::where('workspace_id', $ws->id)->findOrFail($id);
        $agent->update(['status' => 'active', 'enabled' => true]);

        return response()->json([
            'ok' => true,
            'message' => 'AI Agent activated.',
            'status' => 'active',
        ]);
    }

    public function pause(Request $request, int $id): JsonResponse
    {
        $ws = $this->workspace($request);
        $agent = AiChatbot::where('workspace_id', $ws->id)->findOrFail($id);
        $agent->update(['status' => 'paused', 'enabled' => false]);

        return response()->json([
            'ok' => true,
            'message' => 'AI Agent paused.',
            'status' => 'paused',
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $ws = $this->workspace($request);
        $agent = AiChatbot::where('workspace_id', $ws->id)->findOrFail($id);
        $agent->delete();

        return response()->json([
            'ok' => true,
            'message' => 'AI Agent deleted successfully.',
        ]);
    }

    public function test(Request $request, int $id): JsonResponse
    {
        $ws = $this->workspace($request);
        $agent = AiChatbot::where('workspace_id', $ws->id)->findOrFail($id);

        $request->validate(['message' => ['required', 'string', 'max:1000']]);

        $result = $this->agentService->runPlaygroundTest($agent, $request->message);

        return response()->json($result);
    }
}
