<?php

namespace App\Http\Controllers\Api\V1;

use App\Modules\AI\Jobs\IndexDocumentJob;
use App\Modules\AI\Models\AiKbDocument;
use App\Modules\AI\Models\AiKnowledgeBase;
use App\Services\AI\AiKnowledgeService;
use App\Services\StorageManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiKnowledgeBaseApiController extends WorkspaceScopedController
{
    public function __construct(
        private StorageManager $storage,
        private AiKnowledgeService $knowledgeService,
    ) {}

    /**
     * GET /api/v1/ai/knowledge-bases
     */
    public function index(Request $request): JsonResponse
    {
        $kbs = AiKnowledgeBase::where('workspace_id', $this->workspaceId($request))
            ->withCount(['documents', 'chatbots'])
            ->latest('id')
            ->get()
            ->map(fn ($kb) => $this->formatKb($kb));

        return response()->json([
            'ok' => true,
            'data' => $kbs,
        ]);
    }

    /**
     * POST /api/v1/ai/knowledge-bases
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'category' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:1000'],
            'embedding_model' => ['nullable', 'string'],
        ]);

        $kb = AiKnowledgeBase::create(array_merge($validated, [
            'workspace_id' => $this->workspaceId($request),
            'category' => $validated['category'] ?? 'company',
            'status' => 'active',
            'published_at' => now(),
        ]));

        return response()->json([
            'ok' => true,
            'message' => 'Knowledge base created successfully.',
            'data' => $this->formatKb($kb),
        ], 201);
    }

    /**
     * GET /api/v1/ai/knowledge-bases/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $kb = AiKnowledgeBase::where('workspace_id', $this->workspaceId($request))
            ->with(['documents.chunks', 'chatbots'])
            ->find($id);

        if (! $kb) {
            return response()->json(['error' => 'Knowledge base not found.'], 404);
        }

        return response()->json([
            'ok' => true,
            'data' => array_merge($this->formatKb($kb), [
                'documents' => $kb->documents->map(fn ($d) => $this->formatDoc($d)),
            ]),
        ]);
    }

    /**
     * POST /api/v1/ai/knowledge-bases/{id}/publish
     */
    public function publish(Request $request, int $id): JsonResponse
    {
        $kb = AiKnowledgeBase::where('workspace_id', $this->workspaceId($request))->find($id);

        if (! $kb) {
            return response()->json(['error' => 'Knowledge base not found.'], 404);
        }

        $kb->update([
            'status' => 'active',
            'version' => $kb->version + 1,
            'published_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Knowledge base published successfully.',
            'version' => $kb->version,
            'published_at' => $kb->published_at->toIso8601String(),
        ]);
    }

    /**
     * POST /api/v1/ai/knowledge-bases/{id}/search
     */
    public function search(Request $request, int $id): JsonResponse
    {
        $kb = AiKnowledgeBase::where('workspace_id', $this->workspaceId($request))->find($id);

        if (! $kb) {
            return response()->json(['error' => 'Knowledge base not found.'], 404);
        }

        $request->validate(['query' => ['required', 'string', 'max:500']]);
        $searchTerm = (string) $request->input('query');

        $results = $this->knowledgeService->search(
            $kb,
            $searchTerm,
            $request->integer('limit', 5),
            $request->boolean('strict', false)
        );

        return response()->json([
            'ok' => true,
            'query' => $searchTerm,
            'results' => $results,
        ]);
    }

    /**
     * POST /api/v1/ai/knowledge-bases/{id}/documents
     */
    public function addDocument(Request $request, int $id): JsonResponse
    {
        $kb = AiKnowledgeBase::where('workspace_id', $this->workspaceId($request))->find($id);

        if (! $kb) {
            return response()->json(['error' => 'Knowledge base not found.'], 404);
        }

        $validated = $request->validate([
            'source_type' => ['required', 'string', 'in:file,url,text,sitemap,faq'],
            'source_ref' => ['nullable', 'string', 'max:512'],
            'title' => ['nullable', 'string', 'max:256'],
            'category' => ['nullable', 'string', 'max:64'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:10'],
            'content' => ['nullable', 'string'],
            'faqs' => ['nullable', 'array'],
        ]);

        // Direct Text Ingestion
        if ($validated['source_type'] === 'text' && ! empty($validated['content'])) {
            $doc = $this->knowledgeService->ingestText(
                $kb,
                $validated['title'] ?? 'Text Note',
                $validated['content'],
                $validated['category'] ?? 'general',
                (int) ($validated['priority'] ?? 5)
            );
            return response()->json([
                'ok' => true,
                'message' => 'Text knowledge document added and indexed.',
                'data' => $this->formatDoc($doc),
            ], 201);
        }

        // Direct FAQ Ingestion
        if ($validated['source_type'] === 'faq' && ! empty($validated['faqs'])) {
            $doc = $this->knowledgeService->ingestFaqs(
                $kb,
                $validated['faqs'],
                $validated['title'] ?? 'FAQ Collection'
            );
            return response()->json([
                'ok' => true,
                'message' => 'FAQ knowledge collection added and indexed.',
                'data' => $this->formatDoc($doc),
            ], 201);
        }

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $this->storage->prefixedPath('kb-docs/'.$file->hashName());
            $this->storage->disk()->putFileAs(dirname($path), $file, basename($path));
            $validated['source_ref'] = $path;
            $validated['title'] = $validated['title'] ?? $file->getClientOriginalName();
        }

        if (empty($validated['source_ref']) && $validated['source_type'] !== 'text') {
            return response()->json(['error' => 'source_ref is required for source_type '.$validated['source_type'].'.'], 422);
        }

        $doc = AiKbDocument::create(array_merge($validated, [
            'kb_id' => $kb->id,
            'status' => 'pending',
            'category' => $validated['category'] ?? 'general',
            'priority' => $validated['priority'] ?? 5,
        ]));
        IndexDocumentJob::dispatch($doc->id)->onQueue('ai');

        return response()->json([
            'ok' => true,
            'message' => 'Document queued for indexing.',
            'data' => $this->formatDoc($doc),
        ], 201);
    }

    /**
     * DELETE /api/v1/ai/knowledge-bases/{kbId}/documents/{docId}
     */
    public function destroyDocument(Request $request, int $kbId, int $docId): JsonResponse
    {
        $kb = AiKnowledgeBase::where('workspace_id', $this->workspaceId($request))->find($kbId);

        if (! $kb) {
            return response()->json(['error' => 'Knowledge base not found.'], 404);
        }

        $doc = AiKbDocument::where('kb_id', $kb->id)->find($docId);
        if (! $doc) {
            return response()->json(['error' => 'Document not found.'], 404);
        }

        $doc->chunks()->delete();
        $doc->delete();

        return response()->json(['ok' => true, 'message' => 'Document deleted successfully.']);
    }

    private function formatKb(AiKnowledgeBase $kb): array
    {
        return [
            'id' => $kb->id,
            'uuid' => $kb->uuid,
            'name' => $kb->name,
            'category' => $kb->category ?? 'company',
            'description' => $kb->description,
            'version' => $kb->version,
            'embedding_model' => $kb->embedding_model,
            'status' => $kb->status,
            'workspace_id' => $kb->workspace_id,
            'documents_count' => $kb->documents_count ?? null,
            'chatbots_count' => $kb->chatbots_count ?? null,
            'published_at' => $kb->published_at?->toIso8601String(),
            'created_at' => $kb->created_at->toIso8601String(),
        ];
    }

    private function formatDoc(AiKbDocument $doc): array
    {
        return [
            'id' => $doc->id,
            'uuid' => $doc->uuid,
            'kb_id' => $doc->kb_id,
            'source_type' => $doc->source_type,
            'source_ref' => $doc->source_ref,
            'title' => $doc->title,
            'category' => $doc->category,
            'priority' => $doc->priority,
            'status' => $doc->status,
            'tokens' => $doc->tokens,
            'chunks_count' => $doc->chunks()->count(),
            'created_at' => $doc->created_at->toIso8601String(),
        ];
    }
}
