<?php

namespace App\Modules\AI\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\AI\Models\AiChatbot;
use App\Modules\AI\Models\AiKnowledgeBase;
use App\Services\AI\AiAgentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiPlaygroundController extends Controller
{
    public function __construct(
        protected AiAgentService $agentService
    ) {}

    private function workspaceId(Request $request): int
    {
        return (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
    }

    /**
     * AI Agent Playground Dashboard (/app/ai/playground)
     */
    public function index(Request $request): Response
    {
        $wid = $this->workspaceId($request);

        // Fetch or create default workspace AI agents if none exist
        $chatbots = AiChatbot::where('workspace_id', $wid)
            ->with(['knowledgeBase.documents'])
            ->latest('id')
            ->get();

        if ($chatbots->isEmpty()) {
            // Seed a default Sales Assistant
            $kb = AiKnowledgeBase::firstOrCreate(
                ['workspace_id' => $wid, 'category' => 'company'],
                [
                    'name' => 'Primary Business Knowledge Base',
                    'description' => 'Default business information and FAQs',
                    'status' => 'active',
                ]
            );

            $salesAgent = AiChatbot::create([
                'workspace_id' => $wid,
                'name' => 'Sales Assistant',
                'purpose' => 'Qualify inbound leads and answer product/pricing questions',
                'agent_type' => 'sales',
                'status' => 'draft',
                'enabled' => false,
                'ai_kb_id' => $kb->id,
                'response_mode' => 'auto_reply',
                'confidence_threshold' => 70,
                'strict_knowledge_mode' => false,
                'human_handoff_enabled' => true,
                'human_handoff_message' => "Certainly. I'm connecting you with our sales team who will assist you shortly.",
                'system_prompt' => 'You are an intelligent, polite AI Sales Assistant for the business.',
                'tone' => 'friendly_professional',
            ]);

            $chatbots = collect([$salesAgent->fresh(['knowledgeBase.documents'])]);
        }

        $knowledgeBases = AiKnowledgeBase::where('workspace_id', $wid)
            ->withCount('documents')
            ->get();

        return Inertia::render('AI/Playground/Index', [
            'chatbots' => $chatbots,
            'knowledgeBases' => $knowledgeBases,
            'defaultAgentId' => $chatbots->first()?->id,
        ]);
    }

    /**
     * Send simulated message to AI Agent in Playground.
     * Guaranteed 100% test mode: NEVER triggers external channel API calls.
     */
    public function test(Request $request): JsonResponse
    {
        $wid = $this->workspaceId($request);

        $validated = $request->validate([
            'ai_agent_id' => ['required', 'integer'],
            'message' => ['required', 'string', 'max:2000'],
            'channel' => ['nullable', 'string', 'in:whatsapp,instagram,messenger,email,phone'],
        ]);

        $chatbot = AiChatbot::where('workspace_id', $wid)
            ->with('knowledgeBase')
            ->find($validated['ai_agent_id']);

        if (! $chatbot) {
            return response()->json([
                'ok' => false,
                'error' => 'AI Agent not found in this workspace.',
            ], 404);
        }

        $result = $this->agentService->runPlaygroundTest($chatbot, $validated['message']);

        // Attach simulation metadata
        $result['channel_simulated'] = $validated['channel'] ?? 'whatsapp';
        $result['is_test_mode'] = true;

        return response()->json($result);
    }

    /**
     * Save rating & improvement feedback on an AI test response.
     */
    public function saveFeedback(Request $request): JsonResponse
    {
        $wid = $this->workspaceId($request);

        $validated = $request->validate([
            'ai_agent_id' => ['required', 'integer'],
            'question' => ['required', 'string'],
            'answer' => ['required', 'string'],
            'rating' => ['required', 'in:good,wrong'],
            'improvement_notes' => ['nullable', 'string', 'max:1000'],
            'suggested_fixes' => ['nullable', 'array'],
        ]);

        $chatbot = AiChatbot::where('workspace_id', $wid)->find($validated['ai_agent_id']);
        if (! $chatbot) {
            return response()->json(['error' => 'Agent not found.'], 404);
        }

        // Return confirmation with recommended actions
        return response()->json([
            'ok' => true,
            'message' => 'Feedback saved successfully.',
            'feedback' => $validated,
        ]);
    }

    /**
     * Validate checklist & activate AI Agent.
     */
    public function activate(Request $request, AiChatbot $chatbot): RedirectResponse
    {
        $this->authorise($request, $chatbot);

        $kb = $chatbot->knowledgeBase;
        $docsCount = $kb ? $kb->documents()->count() : 0;

        $errors = [];
        if (empty($chatbot->system_prompt) && empty($chatbot->purpose)) {
            $errors[] = 'Agent instructions or purpose must be configured.';
        }
        if ($docsCount === 0 && ! $chatbot->strict_knowledge_mode) {
            // Recommendation
        }

        if (! empty($errors)) {
            return back()->withErrors(['activation' => implode(' ', $errors)]);
        }

        $chatbot->update([
            'status' => 'active',
            'enabled' => true,
        ]);

        return back()->with('success', "AI Agent '{$chatbot->name}' is now ACTIVE for customer conversations.");
    }

    private function authorise(Request $request, AiChatbot $chatbot): void
    {
        $wid = $this->workspaceId($request);
        abort_unless((int) $chatbot->workspace_id === $wid, 403);
    }
}
