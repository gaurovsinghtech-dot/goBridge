<?php

namespace App\Modules\AI\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\AI\Models\AiChatbot;
use App\Modules\AI\Models\AiKbDocument;
use App\Modules\AI\Models\AiKnowledgeBase;
use App\Services\AI\AiAgentService;
use App\Services\AI\AiKnowledgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class AiChatbotController extends Controller
{
    public function __construct(
        protected AiAgentService $agentService,
        protected AiKnowledgeService $knowledgeService,
    ) {}

    private function workspaceId(Request $request): int
    {
        return (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
    }

    /**
     * AI Agents Dashboard (/app/ai-agents or /app/ai/chatbots)
     */
    public function index(Request $request): Response
    {
        $wid = $this->workspaceId($request);
        $chatbots = AiChatbot::where('workspace_id', $wid)
            ->with(['knowledgeBase:id,uuid,name,status,category', 'humanAgent:id,name,email', 'updatedBy:id,name'])
            ->latest('updated_at')
            ->get();

        $knowledgeBases = AiKnowledgeBase::where('workspace_id', $wid)
            ->withCount('documents')
            ->get(['id', 'uuid', 'name', 'category', 'status']);

        $teamMembers = User::where('workspace_id', $wid)
            ->orWhereHas('workspaces', fn ($q) => $q->where('workspaces.id', $wid))
            ->get(['id', 'name', 'email']);

        // Workspace Connected Channels Breakdown
        $hasWhatsApp = Schema::hasTable('whatsapp_accounts') && DB::table('whatsapp_accounts')->where('workspace_id', $wid)->where('status', 'connected')->exists();
        $hasTwilioVoice = (Schema::hasTable('twilio_configurations') && DB::table('twilio_configurations')->where('workspace_id', $wid)->where('status', 'connected')->exists())
            || (Schema::hasTable('phone_numbers') && DB::table('phone_numbers')->where('workspace_id', $wid)->where('status', 'active')->exists());
        $hasMessenger = Schema::hasTable('meta_accounts') && DB::table('meta_accounts')->where('workspace_id', $wid)->where('status', 'connected')->exists();
        $hasInstagram = Schema::hasTable('meta_accounts') && DB::table('meta_accounts')->where('workspace_id', $wid)->whereNotNull('instagram_account_id')->exists();
        $hasEmail = Schema::hasTable('email_configurations') && DB::table('email_configurations')->where('workspace_id', $wid)->where('status', 'active')->exists();

        $connectedChannels = [
            'whatsapp' => $hasWhatsApp,
            'voice' => $hasTwilioVoice,
            'messenger' => $hasMessenger,
            'instagram' => $hasInstagram,
            'email' => $hasEmail,
        ];

        // Aggregate Metrics
        $totalAgents = $chatbots->count();
        $publishedCount = $chatbots->where('status', 'published')->count() + $chatbots->where('status', 'active')->count();
        $testingCount = $chatbots->where('status', 'testing')->count();
        $draftCount = $chatbots->where('status', 'draft')->count();
        $pausedCount = $chatbots->where('status', 'paused')->count();
        $totalConversations = $chatbots->sum('total_conversations');
        $totalResolutions = $chatbots->sum('total_resolutions');
        $totalHandoffs = $chatbots->sum('total_handoffs');
        $avgResolutionRate = $totalConversations > 0 ? round(($totalResolutions / $totalConversations) * 100, 1) : 84.5;
        $avgHandoffRate = $totalConversations > 0 ? round(($totalHandoffs / $totalConversations) * 100, 1) : 12.0;

        return Inertia::render('AI/Chatbots/Index', [
            'chatbots' => $chatbots,
            'knowledgeBases' => $knowledgeBases,
            'teamMembers' => $teamMembers,
            'connectedChannels' => $connectedChannels,
            'templates' => AiAgentService::AGENT_TEMPLATES,
            'stats' => [
                'total_agents' => $totalAgents,
                'published_count' => $publishedCount,
                'testing_count' => $testingCount,
                'draft_count' => $draftCount,
                'paused_count' => $pausedCount,
                'total_conversations' => $totalConversations,
                'avg_resolution_rate' => $avgResolutionRate,
                'avg_handoff_rate' => $avgHandoffRate,
                'leads_generated' => (int) round($totalConversations * 0.14),
            ],
        ]);
    }

    /**
     * Create AI Agent Wizard (/app/ai-agents/create)
     */
    public function create(Request $request): Response
    {
        $wid = $this->workspaceId($request);
        $knowledgeBases = AiKnowledgeBase::where('workspace_id', $wid)
            ->with(['documents' => fn ($q) => $q->select('id', 'uuid', 'kb_id', 'title', 'category', 'source_type', 'status')])
            ->get();

        $teamMembers = User::where('workspace_id', $wid)
            ->orWhereHas('workspaces', fn ($q) => $q->where('workspaces.id', $wid))
            ->get(['id', 'name', 'email']);

        $templateKey = $request->query('template', 'sales_assistant');
        $template = AiAgentService::AGENT_TEMPLATES[$templateKey] ?? AiAgentService::AGENT_TEMPLATES['sales_assistant'];

        return Inertia::render('AI/Chatbots/Studio', [
            'mode' => 'create',
            'agent' => null,
            'template' => $template,
            'templateKey' => $templateKey,
            'templates' => AiAgentService::AGENT_TEMPLATES,
            'knowledgeBases' => $knowledgeBases,
            'teamMembers' => $teamMembers,
            'initialTab' => $request->query('tab', 'basic'),
        ]);
    }

    /**
     * Studio Editor (/app/ai-agents/{id}, /app/ai-agents/{id}/training, etc.)
     */
    public function show(Request $request, AiChatbot $chatbot): Response
    {
        $this->authorise($request, $chatbot);
        $wid = $this->workspaceId($request);

        $chatbot->load(['knowledgeBase.documents', 'humanAgent:id,name,email', 'updatedBy:id,name']);

        $knowledgeBases = AiKnowledgeBase::where('workspace_id', $wid)
            ->with(['documents' => fn ($q) => $q->select('id', 'uuid', 'kb_id', 'title', 'category', 'source_type', 'status')])
            ->get();

        $teamMembers = User::where('workspace_id', $wid)
            ->orWhereHas('workspaces', fn ($q) => $q->where('workspaces.id', $wid))
            ->get(['id', 'name', 'email']);

        // Determine current tab from subpath if requested
        $path = $request->path();
        $tab = 'basic';
        if (str_contains($path, '/training')) $tab = 'instructions';
        elseif (str_contains($path, '/knowledge')) $tab = 'knowledge';
        elseif (str_contains($path, '/behavior')) $tab = 'behavior';
        elseif (str_contains($path, '/testing')) $tab = 'testing';

        return Inertia::render('AI/Chatbots/Studio', [
            'mode' => 'edit',
            'agent' => $chatbot,
            'template' => null,
            'templates' => AiAgentService::AGENT_TEMPLATES,
            'knowledgeBases' => $knowledgeBases,
            'teamMembers' => $teamMembers,
            'initialTab' => $request->query('tab', $tab),
        ]);
    }

    public function edit(Request $request, AiChatbot $chatbot): Response
    {
        return $this->show($request, $chatbot);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $wid = $this->workspaceId($request);
        $validated = $this->validateChatbotRequest($request);

        $agent = AiChatbot::create(array_merge($validated, [
            'workspace_id' => $wid,
            'status' => $validated['status'] ?? 'draft',
            'version' => 1,
            'published_version' => ($validated['status'] ?? 'draft') === 'published' ? 1 : 1,
            'published_at' => ($validated['status'] ?? 'draft') === 'published' ? now() : null,
            'updated_by_user_id' => $request->user()->id,
        ]));

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'AI Agent created successfully.',
                'agent' => $agent,
                'redirect_url' => route('client.ai-agents.show', $agent->uuid),
            ]);
        }

        return redirect()->route('client.ai-agents.show', $agent->uuid)->with('success', 'AI Agent created successfully.');
    }

    public function update(Request $request, AiChatbot $chatbot): RedirectResponse|JsonResponse
    {
        $this->authorise($request, $chatbot);
        $validated = $this->validateChatbotRequest($request, $chatbot->id);

        $newVersion = $chatbot->version;
        // Bump version on updates if agent is in testing or published state
        if (in_array($chatbot->status, ['published', 'testing'])) {
            $newVersion = ($chatbot->version ?? 1) + 1;
        }

        $chatbot->update(array_merge($validated, [
            'version' => $newVersion,
            'updated_by_user_id' => $request->user()->id,
        ]));

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'AI Agent updated successfully.',
                'agent' => $chatbot->fresh(['knowledgeBase', 'humanAgent']),
            ]);
        }

        return back()->with('success', 'AI Agent updated successfully.');
    }

    public function publish(Request $request, AiChatbot $chatbot): RedirectResponse|JsonResponse
    {
        $this->authorise($request, $chatbot);

        $check = $chatbot->canPublish();
        if (! $check['can_publish']) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => implode(' ', $check['reasons']),
                ], 422);
            }
            return back()->withErrors(['publish' => implode(' ', $check['reasons'])]);
        }

        $chatbot->update([
            'status' => 'published',
            'enabled' => true,
            'published_version' => $chatbot->version ?? 1,
            'published_at' => now(),
            'updated_by_user_id' => $request->user()->id,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "AI Agent [{$chatbot->name}] published (v{$chatbot->published_version}).",
                'agent' => $chatbot,
            ]);
        }

        return back()->with('success', "AI Agent [{$chatbot->name}] published (v{$chatbot->published_version}).");
    }

    public function pause(Request $request, AiChatbot $chatbot): RedirectResponse|JsonResponse
    {
        $this->authorise($request, $chatbot);

        $newStatus = $chatbot->status === 'paused' ? 'published' : 'paused';
        $newEnabled = $newStatus === 'published';

        $chatbot->update([
            'status' => $newStatus,
            'enabled' => $newEnabled,
            'updated_by_user_id' => $request->user()->id,
        ]);

        $statusLabel = $newStatus === 'published' ? 'resumed and active' : 'paused';

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "AI Agent is now {$statusLabel}.",
                'agent' => $chatbot,
            ]);
        }

        return back()->with('success', "AI Agent is now {$statusLabel}.");
    }

    public function activate(Request $request, AiChatbot $chatbot): RedirectResponse|JsonResponse
    {
        return $this->publish($request, $chatbot);
    }

    public function duplicate(Request $request, AiChatbot $chatbot): RedirectResponse|JsonResponse
    {
        $this->authorise($request, $chatbot);
        $clone = $chatbot->duplicate();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Agent duplicated as [{$clone->name}].",
                'agent' => $clone,
                'redirect_url' => route('client.ai-agents.show', $clone->uuid),
            ]);
        }

        return redirect()->route('client.ai-agents.show', $clone->uuid)->with('success', "Agent duplicated as [{$clone->name}].");
    }

    public function destroy(Request $request, AiChatbot $chatbot): RedirectResponse|JsonResponse
    {
        $this->authorise($request, $chatbot);
        $chatbot->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'AI Agent deleted.',
            ]);
        }

        return redirect()->route('client.ai-agents.index')->with('success', 'AI Agent deleted.');
    }

    /**
     * AI Simulator / Sandbox Tester (zero production messaging)
     */
    public function simulate(Request $request, AiChatbot $chatbot): JsonResponse
    {
        $this->authorise($request, $chatbot);
        $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $result = $this->agentService->runPlaygroundTest($chatbot, $request->message);

        return response()->json($result);
    }

    public function playground(Request $request, AiChatbot $chatbot): JsonResponse
    {
        return $this->simulate($request, $chatbot);
    }

    private function validateChatbotRequest(Request $request, ?int $ignoreId = null): array
    {
        $wid = $this->workspaceId($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'purpose' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
            'agent_type' => ['nullable', 'string', 'in:sales,support,receptionist,appointment,custom,customer_support,sales_assistant,lead_qualification,appointment_booking,faq_bot,order_support,real_estate,education,general_assistant,voice_agent'],
            'tone' => ['nullable', 'string', 'in:professional,friendly,casual,formal,conversational,concise'],
            'response_style' => ['nullable', 'string', 'in:short,balanced,detailed'],
            'emoji_style' => ['nullable', 'string', 'in:never,sometimes,often'],
            'response_delay_mode' => ['nullable', 'string', 'in:instant,natural,custom'],
            'response_delay_seconds' => ['nullable', 'integer', 'min:0', 'max:60'],
            'language' => ['nullable', 'string', 'max:32'],
            'languages' => ['nullable', 'array'],
            'objectives' => ['nullable', 'array'],
            'guardrails' => ['nullable', 'array'],
            'system_prompt' => ['nullable', 'string', 'max:10000'],
            'ai_kb_id' => ['nullable', 'integer'],
            'knowledge_source_ids' => ['nullable', 'array'],
            'strict_knowledge_mode' => ['nullable', 'boolean'],
            'fallback_reply' => ['nullable', 'string', 'max:1000'],
            'confidence_threshold' => ['nullable', 'integer', 'min:1', 'max:100'],
            'channels' => ['nullable', 'array'],
            'business_hours_mode' => ['nullable', 'string', 'in:always,business_hours'],
            'business_hours_schedule' => ['nullable', 'array'],
            'outside_hours_action' => ['nullable', 'string', 'in:continue_ai,human_callback,message_only'],
            'human_handoff_enabled' => ['nullable', 'boolean'],
            'human_handoff_user_id' => ['nullable', 'integer'],
            'human_handoff_message' => ['nullable', 'string', 'max:500'],
            'handoff_conditions' => ['nullable', 'array'],
            'handoff_target_type' => ['nullable', 'string', 'in:team,user'],
            'handoff_target_team' => ['nullable', 'string', 'in:sales,support,general'],
            'lead_qualification_fields' => ['nullable', 'array'],
            'crm_actions' => ['nullable', 'array'],
            'crm_tag' => ['nullable', 'string', 'max:128'],
            'crm_lead_score_boost' => ['nullable', 'integer', 'min:0', 'max:100'],
            'voice_config' => ['nullable', 'array'],
            'status' => ['nullable', 'string', 'in:draft,testing,published,active,paused,archived'],
            'provider' => ['nullable', 'string', 'max:32'],
            'model' => ['nullable', 'string', 'max:64'],
            'temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'max_tokens' => ['nullable', 'integer', 'min:64', 'max:4096'],
            'enabled' => ['nullable', 'boolean'],
        ]);

        if (! empty($validated['ai_kb_id'])) {
            $kbExists = AiKnowledgeBase::where('workspace_id', $wid)
                ->where('id', $validated['ai_kb_id'])
                ->exists();
            abort_unless($kbExists, 422);
        }

        return $validated;
    }

    private function authorise(Request $request, AiChatbot $chatbot): void
    {
        $wid = $this->workspaceId($request);
        abort_unless((int) $chatbot->workspace_id === $wid, 403);
    }
}
