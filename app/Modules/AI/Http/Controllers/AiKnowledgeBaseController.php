<?php

namespace App\Modules\AI\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AI\Jobs\IndexDocumentJob;
use App\Modules\AI\Models\AiKbDocument;
use App\Modules\AI\Models\AiKnowledgeBase;
use App\Services\AI\AiKnowledgeService;
use App\Services\StorageManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class AiKnowledgeBaseController extends Controller
{
    public function __construct(
        private StorageManager $storage,
        private AiKnowledgeService $knowledgeService,
    ) {}

    private function workspaceId(Request $request): int
    {
        return (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
    }

    /**
     * Get or create workspace primary knowledge base.
     */
    private function getOrCreateWorkspaceKb(int $workspaceId): AiKnowledgeBase
    {
        return AiKnowledgeBase::firstOrCreate(
            ['workspace_id' => $workspaceId, 'category' => 'company'],
            [
                'name' => 'Primary Business Knowledge Base',
                'description' => 'Default business information, FAQs, products, and documents for AI Agents.',
                'status' => 'active',
                'published_at' => now(),
            ]
        );
    }

    /**
     * Unified Knowledge Base Dashboard (/app/ai/knowledge or /app/knowledge)
     */
    public function index(Request $request): Response
    {
        $workspaceId = $this->workspaceId($request);
        $kb = $this->getOrCreateWorkspaceKb($workspaceId);
        $kb->load(['documents.chunks', 'chatbots']);

        $documents = $kb->documents()->withCount('chunks')->latest('updated_at')->get();

        $businessDoc = $documents->where('category', 'business')->first();
        $products = $documents->where('category', 'products')->values();
        $services = $documents->where('category', 'services')->values();
        $faqs = $documents->where('source_type', 'faq')->values();
        $websites = $documents->where('source_type', 'website')->values();
        $files = $documents->where('source_type', 'file')->values();
        $texts = $documents->where('source_type', 'text')->whereNotIn('category', ['business'])->values();

        $stats = [
            'total_items' => $documents->count(),
            'business_ready' => (bool) $businessDoc,
            'products_count' => $products->count(),
            'services_count' => $services->count(),
            'faqs_count' => $faqs->count(),
            'websites_count' => $websites->count(),
            'documents_count' => $files->count(),
            'text_count' => $texts->count(),
            'ready_count' => $documents->where('status', 'ready')->count(),
            'processing_count' => $documents->where('status', 'indexing')->count(),
            'failed_count' => $documents->where('status', 'failed')->count(),
            'disabled_count' => $documents->where('status', 'disabled')->count(),
            'last_updated' => $kb->published_at ? $kb->published_at->toIso8601String() : $kb->updated_at->toIso8601String(),
        ];

        // Fetch AI Agents in this workspace (Chatbots & Voice Agents) for knowledge assignment
        $chatbots = \App\Modules\AI\Models\AiChatbot::where('workspace_id', $workspaceId)
            ->get(['id', 'name', 'status', 'channels'])
            ->map(fn ($c) => [
                'id' => (string) $c->id,
                'name' => $c->name,
                'type' => 'omnichannel',
                'status' => $c->status ?? 'active',
            ]);

        $voiceAgents = Schema::hasTable('voice_agents')
            ? DB::table('voice_agents')
                ->where('workspace_id', $workspaceId)
                ->get(['id', 'name', 'status'])
                ->map(fn ($v) => [
                    'id' => "voice_{$v->id}",
                    'name' => "{$v->name} (Voice)",
                    'type' => 'voice',
                    'status' => $v->status ?? 'active',
                ])
            : collect([]);

        $availableAgents = $chatbots->concat($voiceAgents)->values();

        // Knowledge Gaps (Unanswered customer questions)
        $knowledgeGaps = \App\Modules\AI\Models\AiUnknownQuestion::where('workspace_id', $workspaceId)
            ->where('status', 'pending')
            ->orderByDesc('occurrences')
            ->take(15)
            ->get();

        $stats['gaps_count'] = $knowledgeGaps->count();

        // Other knowledge bases if user has multiple
        $allKbs = AiKnowledgeBase::where('workspace_id', $workspaceId)->withCount('documents')->get();

        return Inertia::render('AI/Knowledge/Index', [
            'kb' => $kb,
            'knowledgeBases' => $allKbs,
            'allSources' => $documents,
            'business' => $businessDoc ? $businessDoc->meta : null,
            'products' => $products,
            'services' => $services,
            'faqs' => $faqs,
            'websites' => $websites,
            'documents' => $files,
            'texts' => $texts,
            'availableAgents' => $availableAgents,
            'knowledgeGaps' => $knowledgeGaps,
            'stats' => $stats,
        ]);
    }

    public function show(Request $request, AiKnowledgeBase $kb): Response
    {
        $this->authorise($request, $kb);
        $kb->load(['documents.chunks', 'chatbots']);

        return Inertia::render('AI/KnowledgeBases/Show', ['kb' => $kb]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workspaceId = $this->workspaceId($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'category' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        AiKnowledgeBase::create(array_merge($validated, [
            'workspace_id' => $workspaceId,
            'category' => $validated['category'] ?? 'company',
            'status' => 'active',
            'published_at' => now(),
        ]));

        return back()->with('success', 'Knowledge base created.');
    }

    public function update(Request $request, AiKnowledgeBase $kb): RedirectResponse
    {
        $this->authorise($request, $kb);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'category' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'string', 'in:active,indexing,error,draft,archived'],
        ]);

        $kb->update($validated);

        return back()->with('success', 'Knowledge base updated.');
    }

    /**
     * Update Knowledge Base Settings (Answer Policy, Citations, Fallback)
     */
    public function updateSettings(Request $request): RedirectResponse|JsonResponse
    {
        $workspaceId = $this->workspaceId($request);
        $kb = $this->getOrCreateWorkspaceKb($workspaceId);

        $validated = $request->validate([
            'answer_policy' => ['required', 'string', 'in:strict_kb_only,general_allowed'],
            'allow_citations' => ['required', 'boolean'],
            'fallback_message' => ['nullable', 'string', 'max:1000'],
        ]);

        $kb->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Knowledge settings updated.',
                'kb' => $kb,
            ]);
        }

        return back()->with('success', 'Knowledge settings updated.');
    }

    /**
     * Save / Update Business Profile Information
     */
    public function saveBusinessInfo(Request $request): RedirectResponse
    {
        $workspaceId = $this->workspaceId($request);
        $kb = $this->getOrCreateWorkspaceKb($workspaceId);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'industry' => ['nullable', 'string', 'max:128'],
            'description' => ['nullable', 'string', 'max:2000'],
            'business_hours' => ['nullable', 'string', 'max:256'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'string', 'email', 'max:128'],
            'website' => ['nullable', 'string', 'max:256'],
            'return_policy' => ['nullable', 'string', 'max:2000'],
            'shipping_policy' => ['nullable', 'string', 'max:2000'],
            'payment_info' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->knowledgeService->ingestBusinessProfile($kb, $validated);

        return back()->with('success', 'Business profile updated and processed.');
    }

    /**
     * Save Product Information
     */
    public function saveProduct(Request $request): RedirectResponse
    {
        $workspaceId = $this->workspaceId($request);
        $kb = $this->getOrCreateWorkspaceKb($workspaceId);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'price' => ['nullable', 'string', 'max:64'],
            'currency' => ['nullable', 'string', 'max:8'],
            'description' => ['nullable', 'string', 'max:2000'],
            'features' => ['nullable', 'string', 'max:2000'],
            'availability' => ['nullable', 'string', 'max:64'],
            'sku' => ['nullable', 'string', 'max:64'],
            'assigned_agents' => ['nullable', 'array'],
        ]);

        $this->knowledgeService->ingestProduct($kb, $validated);

        return back()->with('success', 'Product added to AI knowledge.');
    }

    /**
     * Save FAQ
     */
    public function saveFaq(Request $request): RedirectResponse|JsonResponse
    {
        $workspaceId = $this->workspaceId($request);
        $kb = $this->getOrCreateWorkspaceKb($workspaceId);

        $validated = $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'string', 'max:4000'],
            'category' => ['nullable', 'string', 'max:64'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:10'],
            'assigned_agents' => ['nullable', 'array'],
        ]);

        $doc = $this->knowledgeService->ingestFaq(
            $kb,
            $validated['question'],
            $validated['answer'],
            $validated['category'] ?? 'faq',
            (int) ($validated['priority'] ?? 8),
            $validated['assigned_agents'] ?? []
        );

        // If there was a matching pending unknown question, mark it resolved
        \App\Modules\AI\Models\AiUnknownQuestion::where('workspace_id', $workspaceId)
            ->where('question', $validated['question'])
            ->update(['status' => 'resolved']);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'FAQ added to AI knowledge.',
                'document' => $doc,
            ]);
        }

        return back()->with('success', 'FAQ added to AI knowledge.');
    }

    /**
     * Save Plain Text Knowledge
     */
    public function saveText(Request $request): RedirectResponse|JsonResponse
    {
        $workspaceId = $this->workspaceId($request);
        $kb = $this->getOrCreateWorkspaceKb($workspaceId);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:256'],
            'content' => ['required', 'string', 'max:50000'],
            'category' => ['nullable', 'string', 'max:64'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:10'],
            'assigned_agents' => ['nullable', 'array'],
        ]);

        $doc = $this->knowledgeService->ingestText(
            $kb,
            $validated['title'],
            $validated['content'],
            $validated['category'] ?? 'general',
            (int) ($validated['priority'] ?? 5),
            $validated['assigned_agents'] ?? []
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Text knowledge saved and indexed.',
                'document' => $doc,
            ]);
        }

        return back()->with('success', 'Text knowledge saved and indexed.');
    }

    /**
     * Store Document in specific Knowledge Base (multi-KB compatibility)
     */
    public function storeDocument(Request $request, AiKnowledgeBase $kb): RedirectResponse|JsonResponse
    {
        $this->authorise($request, $kb);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:256'],
            'source_type' => ['required', 'string'],
            'source_ref' => ['required', 'string'],
            'category' => ['nullable', 'string', 'max:64'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $doc = $this->knowledgeService->ingestText(
            $kb,
            $validated['title'] ?? 'Document',
            $validated['source_ref'],
            $validated['category'] ?? 'general',
            (int) ($validated['priority'] ?? 5)
        );

        if (class_exists(\App\Modules\AI\Jobs\IndexDocumentJob::class)) {
            \App\Modules\AI\Jobs\IndexDocumentJob::dispatch((int) $doc->id);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Document added to Knowledge Base.',
                'document' => $doc,
            ]);
        }

        return back()->with('success', 'Document added to Knowledge Base.');
    }

    /**
     * Import Website
     */
    public function importWebsite(Request $request): RedirectResponse|JsonResponse
    {
        $workspaceId = $this->workspaceId($request);
        $kb = $this->getOrCreateWorkspaceKb($workspaceId);

        $validated = $request->validate([
            'url' => ['required', 'url', 'max:500'],
            'crawl_option' => ['nullable', 'string', 'in:page,selected,website'],
            'assigned_agents' => ['nullable', 'array'],
        ]);

        $doc = $this->knowledgeService->ingestWebsite(
            $kb,
            $validated['url'],
            $validated['crawl_option'] ?? 'page',
            $validated['assigned_agents'] ?? []
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Website content imported and indexed.',
                'document' => $doc,
            ]);
        }

        return back()->with('success', 'Website content imported and indexed.');
    }

    /**
     * Upload Document (PDF, TXT, DOCX)
     */
    public function uploadDocument(Request $request): RedirectResponse|JsonResponse
    {
        $workspaceId = $this->workspaceId($request);
        $kb = $this->getOrCreateWorkspaceKb($workspaceId);

        $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:pdf,txt,md,csv,docx,doc'],
            'title' => ['nullable', 'string', 'max:256'],
            'category' => ['nullable', 'string', 'max:64'],
            'assigned_agents' => ['nullable', 'array'],
        ]);

        $file = $request->file('file');
        $title = $request->input('title');
        $category = $request->input('category', 'documents');
        $assignedAgents = $request->input('assigned_agents', []);

        $doc = $this->knowledgeService->ingestDocumentFile(
            $kb,
            $file,
            $title,
            $category,
            $assignedAgents
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Document uploaded and processed for AI knowledge.',
                'document' => $doc,
            ]);
        }

        return back()->with('success', 'Document uploaded and processed for AI knowledge.');
    }

    /**
     * Reprocess individual document
     */
    public function reprocessDocument(Request $request, AiKbDocument $document): RedirectResponse|JsonResponse
    {
        $kb = $document->load('knowledgeBase')->knowledgeBase;
        $this->authorise($request, $kb);

        $doc = $this->knowledgeService->reprocessDocument($document);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Source [{$document->title}] reprocessed successfully.",
                'document' => $doc,
            ]);
        }

        return back()->with('success', "Source [{$document->title}] reprocessed successfully.");
    }

    /**
     * Assign AI agents to a document
     */
    public function assignAgents(Request $request, AiKbDocument $document): RedirectResponse|JsonResponse
    {
        $kb = $document->load('knowledgeBase')->knowledgeBase;
        $this->authorise($request, $kb);

        $agentIds = $request->input('agent_ids', []);
        $doc = $this->knowledgeService->assignAgents($document, (array) $agentIds);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Agent assignments updated.',
                'document' => $doc,
            ]);
        }

        return back()->with('success', 'Agent assignments updated.');
    }

    /**
     * Toggle document visibility / active state
     */
    public function toggleDocument(Request $request, AiKbDocument $document): RedirectResponse|JsonResponse
    {
        $kb = $document->load('knowledgeBase')->knowledgeBase;
        $this->authorise($request, $kb);

        $doc = $this->knowledgeService->toggleDocument($document);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Source is now {$doc->status}.",
                'document' => $doc,
            ]);
        }

        return back()->with('success', "Source is now {$doc->status}.");
    }

    /**
     * Process / Update AI Knowledge (Reprocess all documents)
     */
    public function processKnowledge(Request $request): RedirectResponse
    {
        $workspaceId = $this->workspaceId($request);
        $kb = $this->getOrCreateWorkspaceKb($workspaceId);

        $res = $this->knowledgeService->reprocessAll($kb);

        return back()->with('success', "AI Knowledge updated ({$res['processed_documents']} sources indexed).");
    }

    /**
     * Direct document deletion
     */
    public function destroyDocument(Request $request, AiKbDocument $document): RedirectResponse|JsonResponse
    {
        $kb = $document->load('knowledgeBase')->knowledgeBase;
        $this->authorise($request, $kb);

        $this->knowledgeService->destroyDocument($document);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Knowledge source removed.',
            ]);
        }

        return back()->with('success', 'Knowledge source removed.');
    }

    /**
     * Resolve knowledge gap
     */
    public function resolveGap(Request $request, \App\Modules\AI\Models\AiUnknownQuestion $question): RedirectResponse|JsonResponse
    {
        $workspaceId = $this->workspaceId($request);
        abort_unless((int) $question->workspace_id === $workspaceId, 403);

        $question->update(['status' => 'resolved']);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Knowledge gap marked as resolved.',
            ]);
        }

        return back()->with('success', 'Knowledge gap marked as resolved.');
    }

    public function search(Request $request, ?AiKnowledgeBase $kb = null): JsonResponse
    {
        $workspaceId = $this->workspaceId($request);
        $targetKb = $kb ?? $this->getOrCreateWorkspaceKb($workspaceId);
        $this->authorise($request, $targetKb);

        $request->validate(['query' => ['required', 'string', 'max:500']]);
        $searchTerm = (string) $request->input('query');
        $agentId = $request->input('agent_id');

        $results = $this->knowledgeService->search(
            kb: $targetKb,
            query: $searchTerm,
            limit: (int) $request->input('limit', 5),
            strictMode: (bool) $request->input('strict', false),
            allowedCategories: (array) $request->input('categories', []),
            agentId: $agentId ? (int) $agentId : null
        );

        return response()->json([
            'ok' => true,
            'query' => $searchTerm,
            'results' => $results,
        ]);
    }

    public function destroy(Request $request, AiKnowledgeBase $kb): RedirectResponse
    {
        $this->authorise($request, $kb);
        $kb->delete();

        return back()->with('success', 'Knowledge base deleted.');
    }

    private function authorise(Request $request, AiKnowledgeBase $kb): void
    {
        $wid = $this->workspaceId($request);
        abort_unless((int) $kb->workspace_id === $wid, 403);
    }
}
