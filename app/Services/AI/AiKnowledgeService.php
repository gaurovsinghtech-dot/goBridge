<?php

namespace App\Services\AI;

use App\Modules\AI\Models\AiKbChunk;
use App\Modules\AI\Models\AiKbDocument;
use App\Modules\AI\Models\AiKnowledgeBase;
use App\Modules\AI\Services\EmbeddingStore;
use App\Modules\AI\Services\LlmGateway;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use League\HTMLToMarkdown\HtmlConverter;
use Smalot\PdfParser\Parser as PdfParser;

class AiKnowledgeService
{
    public const PRIORITY_WEIGHTS = [
        'business' => 10,
        'company' => 10,
        'pricing' => 9,
        'products' => 8,
        'services' => 8,
        'policies' => 7,
        'faq' => 6,
        'website' => 5,
        'documents' => 5,
        'locations' => 6,
        'contact' => 7,
        'general' => 4,
    ];

    public function __construct(
        protected ?LlmGateway $llmGateway = null,
        protected ?EmbeddingStore $embedStore = null,
    ) {
        $this->llmGateway = $llmGateway ?? app(LlmGateway::class);
        $this->embedStore = $embedStore ?? app(EmbeddingStore::class);
    }

    /**
     * Ingest and process a text document.
     */
    public function ingestText(
        AiKnowledgeBase $kb,
        string $title,
        string $content,
        string $category = 'general',
        int $priority = 5,
        array $assignedAgents = []
    ): AiKbDocument {
        $doc = AiKbDocument::create([
            'kb_id' => $kb->id,
            'source_type' => 'text',
            'title' => $title,
            'category' => $category,
            'priority' => $priority > 0 ? $priority : (self::PRIORITY_WEIGHTS[$category] ?? 5),
            'assigned_agents' => ! empty($assignedAgents) ? $assignedAgents : null,
            'status' => 'indexing',
        ]);

        $this->processDocumentContent($doc, $content);

        return $doc->fresh(['chunks']);
    }

    /**
     * Ingest structured FAQ (Question + Answer).
     */
    public function ingestFaq(
        AiKnowledgeBase $kb,
        string $question,
        string $answer,
        string $category = 'faq',
        int $priority = 8,
        array $assignedAgents = []
    ): AiKbDocument {
        $title = $question;
        $content = "Question: {$question}\nAnswer: {$answer}";

        $doc = AiKbDocument::create([
            'kb_id' => $kb->id,
            'source_type' => 'faq',
            'title' => Str::limit($title, 250),
            'category' => $category,
            'priority' => $priority > 0 ? $priority : 8,
            'assigned_agents' => ! empty($assignedAgents) ? $assignedAgents : null,
            'meta' => [
                'question' => $question,
                'answer' => $answer,
                'category' => $category,
            ],
            'status' => 'indexing',
        ]);

        $this->processDocumentContent($doc, $content);

        return $doc->fresh(['chunks']);
    }

    /**
     * Ingest a batch collection of FAQs into a single document.
     */
    public function ingestFaqs(
        AiKnowledgeBase $kb,
        array $faqs,
        string $title = 'FAQ Collection',
        string $category = 'faq',
        int $priority = 8,
        array $assignedAgents = []
    ): AiKbDocument {
        $lines = [];
        foreach ($faqs as $item) {
            $q = $item['q'] ?? $item['question'] ?? '';
            $a = $item['a'] ?? $item['answer'] ?? '';
            if (! empty($q) && ! empty($a)) {
                $lines[] = "Q: {$q}\nA: {$a}";
            }
        }

        $content = implode("\n\n---\n\n", $lines);

        $doc = AiKbDocument::create([
            'kb_id' => $kb->id,
            'source_type' => 'faq',
            'title' => Str::limit($title, 250),
            'category' => $category,
            'priority' => $priority > 0 ? $priority : 8,
            'assigned_agents' => ! empty($assignedAgents) ? $assignedAgents : null,
            'meta' => [
                'faq_count' => count($faqs),
                'category' => $category,
            ],
            'status' => 'indexing',
        ]);

        $this->processDocumentContent($doc, $content);

        return $doc->fresh(['chunks']);
    }

    /**
     * Ingest an uploaded document file (PDF, TXT, DOCX, CSV).
     */
    public function ingestDocumentFile(
        AiKnowledgeBase $kb,
        UploadedFile $file,
        ?string $customTitle = null,
        string $category = 'documents',
        array $assignedAgents = []
    ): AiKbDocument {
        $filename = $file->getClientOriginalName();
        $ext = strtolower($file->getClientOriginalExtension());
        $text = '';
        $errorMessage = null;

        if ($ext === 'pdf') {
            try {
                $parser = new PdfParser();
                $pdf = $parser->parseFile($file->getRealPath());
                $text = $pdf->getText();
            } catch (\Throwable $e) {
                Log::warning("PDF parsing error for [{$filename}]: " . $e->getMessage());
                $errorMessage = "PDF text extraction notice: " . $e->getMessage();
                $text = @file_get_contents($file->getRealPath()) ?: '';
            }
        } elseif ($ext === 'docx') {
            try {
                $text = $this->extractTextFromDocx($file->getRealPath());
            } catch (\Throwable $e) {
                Log::warning("DOCX parsing error for [{$filename}]: " . $e->getMessage());
                $errorMessage = "DOCX text extraction notice: " . $e->getMessage();
                $text = @file_get_contents($file->getRealPath()) ?: '';
            }
        } else {
            $text = @file_get_contents($file->getRealPath()) ?: '';
        }

        $storedFile = null;
        try {
            $uploadService = app(\App\Services\Storage\SecureUploadService::class);
            $storedFile = $uploadService->upload($file, (int) $kb->workspace_id, null, 'ai_knowledge', ['kb_id' => $kb->id]);
        } catch (\Throwable $e) {
            Log::warning("Could not persist AI document to S3 object storage: " . $e->getMessage());
        }

        $doc = AiKbDocument::create([
            'kb_id' => $kb->id,
            'source_type' => 'file',
            'source_ref' => $storedFile?->key ?? $filename,
            'title' => $customTitle ?: $filename,
            'category' => $category,
            'priority' => self::PRIORITY_WEIGHTS[$category] ?? 5,
            'assigned_agents' => ! empty($assignedAgents) ? $assignedAgents : null,
            'file_size' => $file->getSize(),
            'meta' => [
                'filename' => $filename,
                'stored_file_id' => $storedFile?->id,
                'stored_file_uuid' => $storedFile?->uuid,
                's3_key' => $storedFile?->key,
                'disk' => $storedFile?->disk ?? 's3',
                'size_bytes' => $file->getSize(),
                'extension' => $ext,
            ],
            'status' => 'indexing',
            'error_message' => $errorMessage,
        ]);

        if (! empty(trim($text))) {
            $this->processDocumentContent($doc, $text);
        } else {
            $doc->update([
                'status' => 'failed',
                'error_message' => $errorMessage ?: 'No readable text could be extracted from this file.',
            ]);
        }

        return $doc->fresh(['chunks']);
    }

    /**
     * Extract plain text from DOCX archive.
     */
    private function extractTextFromDocx(string $filePath): string
    {
        if (! class_exists('\ZipArchive')) {
            return '';
        }

        $zip = new \ZipArchive();
        if ($zip->open($filePath) === true) {
            $xmlIndex = $zip->locateName('word/document.xml');
            if ($xmlIndex !== false) {
                $xmlData = $zip->getFromIndex($xmlIndex);
                $zip->close();

                // Strip XML tags cleanly, preserving paragraph breaks
                $xmlData = str_replace(['</w:p>', '</w:tr>'], "\n\n", $xmlData);
                $clean = strip_tags($xmlData);
                return html_entity_decode($clean, ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
            $zip->close();
        }

        return '';
    }

    /**
     * Ingest website content by crawling a URL.
     */
    public function ingestWebsite(
        AiKnowledgeBase $kb,
        string $url,
        string $crawlOption = 'page',
        array $assignedAgents = []
    ): AiKbDocument {
        $url = trim($url);
        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            $url = 'https://' . $url;
        }

        $pageTitle = parse_url($url, PHP_URL_HOST) ?? $url;
        $cleanContent = '';
        $errorMessage = null;

        try {
            $response = Http::timeout(12)->withHeaders([
                'User-Agent' => 'GrowbridgeConnect-AiBot/1.0',
            ])->get($url);

            if ($response->successful()) {
                $html = $response->body();
                // Extract <title> if present
                if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
                    $pageTitle = trim(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'));
                }
                $cleanContent = $this->cleanHtml($html);
            } else {
                $errorMessage = "Failed to fetch URL. HTTP status: " . $response->status();
            }
        } catch (\Throwable $e) {
            Log::warning("Website ingest failed for [{$url}]: " . $e->getMessage());
            $errorMessage = $e->getMessage();
        }

        $doc = AiKbDocument::create([
            'kb_id' => $kb->id,
            'source_type' => 'website',
            'source_ref' => $url,
            'title' => $pageTitle,
            'category' => 'website',
            'priority' => self::PRIORITY_WEIGHTS['website'] ?? 5,
            'assigned_agents' => ! empty($assignedAgents) ? $assignedAgents : null,
            'meta' => [
                'url' => $url,
                'crawl_option' => $crawlOption,
            ],
            'status' => empty($cleanContent) ? 'failed' : 'indexing',
            'error_message' => $errorMessage,
        ]);

        if (! empty($cleanContent)) {
            $this->processDocumentContent($doc, $cleanContent);
        }

        return $doc->fresh(['chunks']);
    }

    /**
     * Ingest structured business information (Hours, Address, Policies, Description, Contact).
     */
    public function ingestBusinessProfile(AiKnowledgeBase $kb, array $data): AiKbDocument
    {
        $businessName = $data['name'] ?? $data['business_name'] ?? 'Company';
        $industry = $data['industry'] ?? '';
        $description = $data['description'] ?? '';
        $hours = $data['business_hours'] ?? $data['hours'] ?? '10:00 AM – 7:00 PM';
        $address = $data['address'] ?? $data['location'] ?? '';
        $phone = $data['phone'] ?? '';
        $email = $data['email'] ?? '';
        $website = $data['website'] ?? '';
        $returnPolicy = $data['return_policy'] ?? '';
        $shippingPolicy = $data['shipping_policy'] ?? '';
        $paymentInfo = $data['payment_info'] ?? $data['payment_methods'] ?? '';

        $lines = [
            "BUSINESS PROFILE: {$businessName}",
            $industry ? "Industry: {$industry}" : null,
            $description ? "About: {$description}" : null,
            $hours ? "Business Operating Hours: {$hours}" : null,
            $address ? "Location / Address: {$address}" : null,
            $phone ? "Contact Phone: {$phone}" : null,
            $email ? "Contact Email: {$email}" : null,
            $website ? "Official Website: {$website}" : null,
            $returnPolicy ? "Return & Refund Policy: {$returnPolicy}" : null,
            $shippingPolicy ? "Shipping & Delivery Policy: {$shippingPolicy}" : null,
            $paymentInfo ? "Accepted Payment Methods: {$paymentInfo}" : null,
        ];

        $content = implode("\n", array_filter($lines));

        // Find existing business document or create new
        $existing = AiKbDocument::where('kb_id', $kb->id)
            ->where('category', 'business')
            ->first();

        if ($existing) {
            $existing->update([
                'title' => "Business Profile — {$businessName}",
                'meta' => $data,
                'status' => 'indexing',
            ]);
            $this->processDocumentContent($existing, $content);
            return $existing->fresh(['chunks']);
        }

        $doc = AiKbDocument::create([
            'kb_id' => $kb->id,
            'source_type' => 'text',
            'title' => "Business Profile — {$businessName}",
            'category' => 'business',
            'priority' => self::PRIORITY_WEIGHTS['business'],
            'meta' => $data,
            'status' => 'indexing',
        ]);

        $this->processDocumentContent($doc, $content);

        return $doc->fresh(['chunks']);
    }

    /**
     * Ingest structured Product.
     */
    public function ingestProduct(AiKnowledgeBase $kb, array $data): AiKbDocument
    {
        $name = $data['name'] ?? 'Product';
        $price = $data['price'] ?? 'On Request';
        $currency = $data['currency'] ?? 'INR';
        $desc = $data['description'] ?? '';
        $features = $data['features'] ?? '';
        $availability = $data['availability'] ?? 'In Stock';
        $sku = $data['sku'] ?? '';

        $lines = [
            "PRODUCT: {$name}",
            "Price: {$currency} {$price}",
            "Availability: {$availability}",
            $sku ? "SKU / Code: {$sku}" : null,
            $desc ? "Description: {$desc}" : null,
            $features ? "Key Features: {$features}" : null,
        ];

        $content = implode("\n", array_filter($lines));

        $doc = AiKbDocument::create([
            'kb_id' => $kb->id,
            'source_type' => 'text',
            'title' => "Product: {$name}",
            'category' => 'products',
            'priority' => self::PRIORITY_WEIGHTS['products'],
            'meta' => $data,
            'status' => 'indexing',
        ]);

        $this->processDocumentContent($doc, $content);

        return $doc->fresh(['chunks']);
    }

    /**
     * Ingest structured Service.
     */
    public function ingestService(AiKnowledgeBase $kb, array $data): AiKbDocument
    {
        $name = $data['name'] ?? 'Service';
        $price = $data['price'] ?? 'On Request';
        $currency = $data['currency'] ?? 'INR';
        $desc = $data['description'] ?? '';
        $features = $data['features'] ?? '';
        $availability = $data['availability'] ?? 'Available';

        $lines = [
            "SERVICE: {$name}",
            "Price: {$currency} {$price}",
            "Availability: {$availability}",
            $desc ? "Description: {$desc}" : null,
            $features ? "Features & Deliverables: {$features}" : null,
        ];

        $content = implode("\n", array_filter($lines));

        $doc = AiKbDocument::create([
            'kb_id' => $kb->id,
            'source_type' => 'text',
            'title' => "Service: {$name}",
            'category' => 'services',
            'priority' => self::PRIORITY_WEIGHTS['services'],
            'meta' => $data,
            'status' => 'indexing',
        ]);

        $this->processDocumentContent($doc, $content);

        return $doc->fresh(['chunks']);
    }



    /**
     * Chunk, generate embeddings, and index document content.
     */
    public function processDocumentContent(AiKbDocument $document, string $rawText): void
    {
        $cleaned = $this->cleanText($rawText);
        $chunks = $this->chunkText($cleaned, 350);

        // Delete existing chunks if re-indexing
        AiKbChunk::where('document_id', $document->id)->delete();

        $totalTokens = 0;
        $kb = $document->knowledgeBase;
        $workspaceId = $kb?->workspace_id ?? 1;

        $createdChunks = [];
        foreach ($chunks as $index => $chunkText) {
            $tokens = (int) ceil(str_word_count($chunkText) * 1.3);
            $totalTokens += $tokens;

            $chunk = AiKbChunk::create([
                'kb_id' => $document->kb_id,
                'document_id' => $document->id,
                'ord' => $index,
                'content' => $chunkText,
                'tokens' => $tokens,
            ]);

            $createdChunks[] = $chunk;
        }

        // Try embedding in batch if LLM gateway is available
        if ($this->llmGateway && ! empty($createdChunks)) {
            try {
                $chunkTexts = array_map(fn ($c) => $c->content, $createdChunks);
                $embeddings = $this->llmGateway->embed($workspaceId, $chunkTexts);

                foreach ($createdChunks as $i => $chunk) {
                    if (isset($embeddings[$i]) && is_array($embeddings[$i])) {
                        $this->embedStore->storeEmbedding($chunk, $embeddings[$i]);
                    }
                }
            } catch (\Throwable $e) {
                Log::notice('Embedding computation fallback to keyword indexing', ['msg' => $e->getMessage()]);
            }
        }

        $document->update([
            'status' => 'ready',
            'tokens' => $totalTokens,
            'last_indexed_at' => now(),
        ]);
    }

    /**
     * Reprocess all documents in the knowledge base.
     */
    public function reprocessAll(AiKnowledgeBase $kb): array
    {
        $docs = $kb->documents;
        $count = 0;

        foreach ($docs as $doc) {
            $existingChunks = $doc->chunks;
            if ($existingChunks->isNotEmpty()) {
                $combinedText = $existingChunks->pluck('content')->implode("\n\n");
                $this->processDocumentContent($doc, $combinedText);
                $count++;
            }
        }

        $kb->update([
            'published_at' => now(),
            'version' => ($kb->version ?? 1) + 1,
        ]);

        return ['processed_documents' => $count];
    }

    /**
     * Search knowledge base with priority weighting, hybrid keyword/similarity retrieval, and agent scoping.
     */
    public function search(
        AiKnowledgeBase $kb,
        string $query,
        int $limit = 5,
        bool $strictMode = false,
        array $allowedCategories = [],
        ?int $agentId = null
    ): array {
        $query = trim($query);
        if (empty($query)) {
            return [];
        }

        $results = [];

        // 1. Try embedding search if available
        $queryEmbedding = [];
        if ($this->llmGateway) {
            try {
                $embeddings = $this->llmGateway->embed($kb->workspace_id, [$query]);
                $queryEmbedding = $embeddings[0] ?? [];
            } catch (\Throwable) {
                $queryEmbedding = [];
            }
        }

        if (! empty($queryEmbedding)) {
            try {
                $rawEmbedResults = $this->embedStore->search($kb->id, $queryEmbedding, $limit * 2);
                foreach ($rawEmbedResults as $res) {
                    $chunk = $res['chunk'] ?? null;
                    if ($chunk && $chunk->document) {
                        $doc = $chunk->document;

                        // Check status & visibility
                        if ($doc->status === 'disabled' || $doc->visibility === 'disabled') {
                            continue;
                        }

                        // Check agent assignment
                        if ($agentId !== null && ! empty($doc->assigned_agents) && ! in_array($agentId, $doc->assigned_agents, false)) {
                            continue;
                        }

                        $category = $doc->category ?? 'general';
                        if (! empty($allowedCategories) && ! in_array($category, $allowedCategories, true)) {
                            continue;
                        }

                        $priority = (int) ($doc->priority ?? 5);
                        $score = (float) ($res['score'] ?? 0.5);
                        $weightedScore = $score * (1 + ($priority / 20));
                        $results[$chunk->id] = [
                            'chunk_id' => $chunk->id,
                            'document_id' => $chunk->document_id,
                            'title' => $doc->title ?? 'Knowledge Item',
                            'source_type' => $doc->source_type ?? 'text',
                            'category' => $category,
                            'priority' => $priority,
                            'content' => $chunk->content,
                            'score' => round($weightedScore, 3),
                            'citation' => "Source: " . ($doc->title ?? 'Knowledge Base') . " (Part " . ($chunk->ord + 1) . ")",
                        ];
                    }
                }
            } catch (\Throwable) {
                // fall back to keyword search
            }
        }

        // 2. Keyword fallback / booster
        $stopWords = ['the', 'and', 'for', 'with', 'this', 'that', 'from', 'have', 'what', 'when', 'where', 'which', 'who', 'whom', 'will', 'would', 'could', 'should', 'are', 'was', 'were', 'about', 'into', 'some', 'than', 'them', 'then', 'there', 'they', 'our', 'your', 'his', 'her', 'its'];
        $keywords = array_filter(explode(' ', strtolower(preg_replace('/[^a-zA-Z0-9\s]/', '', $query))), fn ($w) => strlen($w) > 2 && ! in_array($w, $stopWords, true));
        
        $chunksQuery = AiKbChunk::with('document')
            ->where('kb_id', $kb->id);

        if (! empty($allowedCategories)) {
            $chunksQuery->whereHas('document', function ($dq) use ($allowedCategories) {
                $dq->whereIn('category', $allowedCategories);
            });
        }

        if (! empty($keywords)) {
            $chunksQuery->where(function ($q) use ($keywords) {
                foreach ($keywords as $kw) {
                    $q->orWhere('content', 'LIKE', "%{$kw}%");
                }
            });
        }

        $matchingChunks = $chunksQuery->take(25)->get();

        foreach ($matchingChunks as $chunk) {
            if (! isset($results[$chunk->id]) && $chunk->document) {
                $doc = $chunk->document;

                // Check status & visibility
                if ($doc->status === 'disabled' || $doc->visibility === 'disabled') {
                    continue;
                }

                // Check agent assignment
                if ($agentId !== null && ! empty($doc->assigned_agents) && ! in_array($agentId, $doc->assigned_agents, false)) {
                    continue;
                }

                $category = $doc->category ?? 'general';
                if (! empty($allowedCategories) && ! in_array($category, $allowedCategories, true)) {
                    continue;
                }

                $priority = (int) ($doc->priority ?? 5);
                $matchCount = 0;
                $lowerContent = strtolower($chunk->content);
                foreach ($keywords as $kw) {
                    if (str_contains($lowerContent, $kw)) {
                        $matchCount++;
                    }
                }
                if ($matchCount === 0 || (count($keywords) > 0 && ($matchCount / count($keywords)) < 0.25)) {
                    continue;
                }
                $score = count($keywords) > 0 ? ($matchCount / count($keywords)) : 0.4;
                $weightedScore = $score * (1 + ($priority / 20));

                $results[$chunk->id] = [
                    'chunk_id' => $chunk->id,
                    'document_id' => $chunk->document_id,
                    'title' => $doc->title ?? 'Knowledge Item',
                    'source_type' => $doc->source_type ?? 'text',
                    'category' => $category,
                    'priority' => $priority,
                    'content' => $chunk->content,
                    'score' => round($weightedScore, 3),
                    'citation' => "Source: " . ($doc->title ?? 'Knowledge Base') . " (Part " . ($chunk->ord + 1) . ")",
                ];
            }
        }

        // Sort by score descending
        usort($results, fn ($a, $b) => $b['score'] <=> $a['score']);

        $topResults = array_slice(array_values($results), 0, $limit);

        // If no results found and query has substance, log unknown question gap
        if (empty($topResults) && strlen($query) >= 4) {
            $this->logUnknownQuestion($kb->workspace_id, $query, $agentId);
        }

        if ($strictMode && empty($topResults)) {
            return [];
        }

        return $topResults;
    }

    /**
     * Log unknown/unanswered question into ai_unknown_questions (Knowledge Gap feedback loop).
     */
    public function logUnknownQuestion(
        int $workspaceId,
        string $question,
        ?int $agentId = null,
        ?string $categorySuggested = 'general'
    ): void {
        try {
            $existing = \App\Modules\AI\Models\AiUnknownQuestion::where('workspace_id', $workspaceId)
                ->where('question', Str::limit(trim($question), 490))
                ->where('status', 'pending')
                ->first();

            if ($existing) {
                $existing->increment('occurrences');
                $existing->update(['last_asked_at' => now()]);
            } else {
                \App\Modules\AI\Models\AiUnknownQuestion::create([
                    'workspace_id' => $workspaceId,
                    'ai_agent_id' => $agentId,
                    'question' => Str::limit(trim($question), 490),
                    'occurrences' => 1,
                    'category_suggested' => $categorySuggested,
                    'status' => 'pending',
                    'last_asked_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to log unknown question gap', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Reprocess an individual document (flush old chunks and re-embed).
     */
    public function reprocessDocument(AiKbDocument $document): AiKbDocument
    {
        $existingChunks = $document->chunks;
        $combinedText = $existingChunks->isNotEmpty()
            ? $existingChunks->pluck('content')->implode("\n\n")
            : ($document->title ?? 'Knowledge Document');

        $document->update([
            'status' => 'indexing',
            'version' => ($document->version ?? 1) + 1,
        ]);

        $this->processDocumentContent($document, $combinedText);

        return $document->fresh(['chunks']);
    }

    /**
     * Assign AI agents to a knowledge document.
     */
    public function assignAgents(AiKbDocument $document, array $agentIds): AiKbDocument
    {
        $document->update([
            'assigned_agents' => ! empty($agentIds) ? array_values(array_unique($agentIds)) : null,
        ]);

        return $document;
    }

    /**
     * Toggle document visibility / status.
     */
    public function toggleDocument(AiKbDocument $document): AiKbDocument
    {
        $newStatus = $document->status === 'disabled' ? 'ready' : 'disabled';
        $newVisibility = $newStatus === 'disabled' ? 'disabled' : 'public';

        $document->update([
            'status' => $newStatus,
            'visibility' => $newVisibility,
        ]);

        return $document;
    }

    /**
     * Delete document and all cascading chunks.
     */
    public function destroyDocument(AiKbDocument $document): bool
    {
        AiKbChunk::where('document_id', $document->id)->delete();
        return (bool) $document->delete();
    }

    /**
     * Clean and normalize raw HTML.
     */
    public function cleanHtml(string $html): string
    {
        // Remove script and style elements
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);

        try {
            $converter = new HtmlConverter(['strip_tags' => true]);
            $markdown = $converter->convert($html);
            return $this->cleanText($markdown);
        } catch (\Throwable) {
            return $this->cleanText(strip_tags($html));
        }
    }

    /**
     * Clean and normalize raw text for indexing.
     */
    public function cleanText(string $text): string
    {
        $text = strip_tags($text);
        $text = preg_replace("/\r\n|\r/", "\n", $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        $text = preg_replace("/[ \t]+/", ' ', $text);

        return trim($text);
    }

    /**
     * Split text into semantic chunks of roughly $maxWords words.
     */
    public function chunkText(string $text, int $maxWords = 350): array
    {
        $paragraphs = explode("\n\n", $text);
        $chunks = [];
        $currentChunk = '';

        foreach ($paragraphs as $para) {
            $para = trim($para);
            if (empty($para)) {
                continue;
            }

            $currentWordCount = str_word_count($currentChunk);
            $paraWordCount = str_word_count($para);

            if ($currentWordCount + $paraWordCount > $maxWords && ! empty($currentChunk)) {
                $chunks[] = trim($currentChunk);
                $currentChunk = $para;
            } else {
                $currentChunk .= (empty($currentChunk) ? '' : "\n\n") . $para;
            }
        }

        if (! empty(trim($currentChunk))) {
            $chunks[] = trim($currentChunk);
        }

        return ! empty($chunks) ? $chunks : [$text];
    }
}
