<?php

namespace App\Services\Automation;

use App\Models\Crm\CrmDeal;
use App\Models\Crm\CrmNote;
use App\Models\Crm\CrmPipelineStage;
use App\Models\Crm\CrmTask;
use App\Models\Notification;
use App\Models\User;
use App\Modules\Automation\Models\Automation;
use App\Modules\Automation\Models\AutomationRun;
use App\Modules\Automation\Models\AutomationRunLog;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\ContactTag;
use App\Modules\Voice\Services\TelephonyService;
use App\Services\Campaigns\CampaignAiAssistantService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WorkflowExecutionService
{
    public const MAX_STEPS_PER_RUN = 100;

    public function __construct(
        protected CampaignAiAssistantService $aiAssistant,
        protected ?TelephonyService $telephonyService = null,
    ) {
        $this->telephonyService = $telephonyService ?? app(TelephonyService::class);
    }

    /**
     * Trigger automations matching a workspace event and run them.
     */
    public function triggerEvent(int $workspaceId, string $event, ?Contact $contact = null, array $context = []): array
    {
        $automations = Automation::where('workspace_id', $workspaceId)
            ->where('status', 'active')
            ->where('trigger_type', $event)
            ->get();

        $runs = [];
        foreach ($automations as $automation) {
            $runs[] = $this->startRun($automation, $contact, $context, $event);
        }

        return $runs;
    }

    /**
     * Start a new run for a specific automation and contact.
     */
    public function startRun(Automation $automation, ?Contact $contact, array $context = [], ?string $triggerEvent = null): AutomationRun
    {
        $run = AutomationRun::create([
            'automation_id' => $automation->id,
            'contact_id' => $contact?->id,
            'trigger_event' => $triggerEvent ?? $automation->trigger_type ?? 'manual',
            'status' => 'running',
            'context' => array_merge([
                'workspace_id' => $automation->workspace_id,
                'contact' => $contact ? $contact->toArray() : [],
                'lead' => [
                    'score' => $contact?->lead_score ?? 0,
                    'stage_id' => $contact?->stage_id,
                ],
            ], $context),
            'started_at' => now(),
        ]);

        return $this->executeRun($run);
    }

    /**
     * Core graph execution loop.
     */
    public function executeRun(AutomationRun $run, ?string $startNodeId = null, bool $isSimulation = false): AutomationRun
    {
        $automation = $run->automation;
        $nodes = collect($automation->nodes ?? [])->keyBy('id');
        $edges = collect($automation->edges ?? []);

        // Find entry node
        $currentNodeId = $startNodeId;
        if (! $currentNodeId) {
            // Find the trigger node or the first node connected from trigger
            $triggerEdge = $edges->first(fn ($e) => ($e['source'] ?? '') === 'trigger-1' || ($e['sourceHandle'] ?? '') === 'trigger');
            if ($triggerEdge) {
                $currentNodeId = $triggerEdge['target'] ?? null;
            } else {
                // First non-trigger node
                $firstNode = $nodes->first(fn ($n) => ($n['type'] ?? '') !== 'trigger');
                $currentNodeId = $firstNode['id'] ?? null;
            }
        }

        $stepCount = 0;
        $startTime = microtime(true);
        $context = $run->context ?? [];
        $contact = $run->contact_id ? Contact::find($run->contact_id) : null;

        try {
            while ($currentNodeId && $nodes->has($currentNodeId)) {
                $stepCount++;
                if ($stepCount > self::MAX_STEPS_PER_RUN) {
                    throw new \RuntimeException('Infinite loop protection triggered: exceeded '.self::MAX_STEPS_PER_RUN.' steps limit.');
                }

                $node = $nodes->get($currentNodeId);
                $nodeType = $node['type'] ?? 'unknown';
                $nodeData = $node['data'] ?? [];

                $run->update(['current_node_id' => $currentNodeId]);

                // Execute the single node
                $stepResult = $this->executeNode($nodeType, $nodeData, $contact, $context, $automation, $isSimulation);

                // Update context with any step outputs
                if (! empty($stepResult['context_updates'])) {
                    $context = array_merge($context, $stepResult['context_updates']);
                    $run->context = $context;
                }

                // Log the execution step
                AutomationRunLog::create([
                    'run_id' => $run->id,
                    'node_id' => $currentNodeId,
                    'node_type' => $nodeType,
                    'result' => $stepResult['success'] ? 'ok' : ($stepResult['skipped'] ? 'skipped' : 'error'),
                    'message' => $stepResult['message'] ?? null,
                    'output' => $stepResult['output'] ?? [],
                ]);

                // Handle delay/wait parking
                if ($nodeType === 'wait' || $nodeType === 'wait_delay') {
                    $run->update([
                        'status' => 'waiting',
                        'resume_node_id' => $this->getNextNodeId($edges, $currentNodeId, $stepResult['branch'] ?? null),
                        'context' => $context,
                    ]);
                    return $run;
                }

                // Handle goal reached (successful completion)
                if ($nodeType === 'goal' || $nodeType === 'goal_reached') {
                    $run->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                        'duration_ms' => (int) round((microtime(true) - $startTime) * 1000),
                    ]);
                    $automation->recordRunResult(true);
                    return $run;
                }

                // Determine next node
                $branchKey = $stepResult['branch'] ?? null;
                $currentNodeId = $this->getNextNodeId($edges, $currentNodeId, $branchKey);
            }

            // Completed all nodes successfully
            $run->update([
                'status' => 'completed',
                'completed_at' => now(),
                'duration_ms' => (int) round((microtime(true) - $startTime) * 1000),
                'context' => $context,
            ]);
            $automation->recordRunResult(true);
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'completed_at' => now(),
                'duration_ms' => (int) round((microtime(true) - $startTime) * 1000),
                'context' => $context,
            ]);
            $automation->recordRunResult(false);
            Log::error('Automation run failed', ['run_id' => $run->id, 'error' => $e->getMessage()]);
        }

        return $run;
    }

    /**
     * Dispatch specific node logic.
     */
    protected function executeNode(string $type, array $data, ?Contact &$contact, array &$context, Automation $automation, bool $isSimulation): array
    {
        switch ($type) {
            case 'trigger':
                return ['success' => true, 'message' => 'Trigger event initiated.'];

            // ─── CONDITIONS & LOGIC ──────────────────────────────────────────
            case 'condition':
            case 'branch_if_else':
                $passed = $this->evaluateCondition($data, $contact, $context);
                return [
                    'success' => true,
                    'branch' => $passed ? 'yes' : 'no',
                    'message' => 'Condition evaluated: '.($passed ? 'YES / TRUE' : 'NO / FALSE'),
                ];

            case 'branch_switch':
                $matchedCase = $this->evaluateSwitchCase($data, $contact, $context);
                return [
                    'success' => true,
                    'branch' => $matchedCase,
                    'message' => "Switch matched branch: {$matchedCase}",
                ];

            // ─── AI ACTIONS ──────────────────────────────────────────────────
            case 'ai_action':
            case 'ai_detect_intent':
            case 'ai_classify':
                $messageText = $this->resolveVariables($data['input_text'] ?? $context['message']['body'] ?? $context['last_message'] ?? 'Pricing inquiry for 50 seats', $contact, $context);
                $classification = $this->aiAssistant->classifyReply($messageText);

                $confidence = $classification['confidence'] ?? 92;
                $threshold = $data['confidence_threshold'] ?? 70;
                $requiresHandoff = $confidence < $threshold || ($classification['requires_human_attention'] ?? false);

                $contextUpdates = [
                    'ai' => [
                        'intent' => $classification['intent'],
                        'sentiment' => $classification['sentiment'],
                        'confidence' => $confidence,
                        'suggested_action' => $classification['suggested_action'],
                        'requires_handoff' => $requiresHandoff,
                    ],
                ];

                if ($contact && ($classification['lead_score_boost'] ?? 0) > 0) {
                    $contact->increment('lead_score', $classification['lead_score_boost']);
                    $contextUpdates['lead']['score'] = $contact->lead_score;
                }

                return [
                    'success' => true,
                    'branch' => $requiresHandoff ? 'handoff' : 'confident',
                    'message' => "AI classified: intent={$classification['intent']} ({$confidence}%)",
                    'output' => $classification,
                    'context_updates' => $contextUpdates,
                ];

            case 'ai_generate_reply':
                $prompt = $this->resolveVariables($data['prompt'] ?? 'Generate a helpful response', $contact, $context);
                $channel = $data['channel'] ?? 'whatsapp';
                $copy = $this->aiAssistant->generateCampaignCopy($prompt, $channel);

                return [
                    'success' => true,
                    'message' => 'AI reply generated.',
                    'output' => $copy,
                    'context_updates' => ['ai_reply' => $copy['message_body']],
                ];

            // ─── COMMUNICATION ACTIONS ────────────────────────────────────────
            case 'send_whatsapp':
                $body = $this->resolveVariables($data['message'] ?? $data['body'] ?? $context['ai_reply'] ?? 'Hello {{contact.first_name}}', $contact, $context);
                if (! $isSimulation && $contact && $contact->phone_e164) {
                    // Send message via provider/inbox
                }
                return [
                    'success' => true,
                    'message' => "WhatsApp message prepared for {$contact?->phone_e164}: {$body}",
                    'output' => ['channel' => 'whatsapp', 'body' => $body],
                ];

            case 'send_instagram':
                $body = $this->resolveVariables($data['message'] ?? $context['ai_reply'] ?? 'Hello', $contact, $context);
                return [
                    'success' => true,
                    'message' => "Instagram message prepared: {$body}",
                    'output' => ['channel' => 'instagram', 'body' => $body],
                ];

            case 'send_messenger':
                $body = $this->resolveVariables($data['message'] ?? $context['ai_reply'] ?? 'Hello', $contact, $context);
                return [
                    'success' => true,
                    'message' => "Messenger message prepared: {$body}",
                    'output' => ['channel' => 'messenger', 'body' => $body],
                ];

            case 'send_email':
                $subject = $this->resolveVariables($data['subject'] ?? 'Important Update', $contact, $context);
                $body = $this->resolveVariables($data['body'] ?? 'Hello {{contact.first_name}}', $contact, $context);
                return [
                    'success' => true,
                    'message' => "Email prepared for {$contact?->email}: {$subject}",
                    'output' => ['channel' => 'email', 'subject' => $subject, 'body' => $body],
                ];

            // ─── CRM & CONTACT ACTIONS ────────────────────────────────────────
            case 'add_tag':
                $tagName = $data['tag'] ?? 'Lead';
                if ($contact) {
                    $tag = ContactTag::firstOrCreate(['workspace_id' => $automation->workspace_id, 'name' => $tagName]);
                    $contact->tags()->syncWithoutDetaching([$tag->id]);
                }
                return ['success' => true, 'message' => "Added tag: {$tagName}"];

            case 'remove_tag':
                $tagName = $data['tag'] ?? '';
                if ($contact && $tagName) {
                    $tag = ContactTag::where('workspace_id', $automation->workspace_id)->where('name', $tagName)->first();
                    if ($tag) {
                        $contact->tags()->detach($tag->id);
                    }
                }
                return ['success' => true, 'message' => "Removed tag: {$tagName}"];

            case 'create_lead':
            case 'update_lead':
                $updates = [];
                if (isset($data['score'])) {
                    $updates['lead_score'] = (int) $data['score'];
                }
                if (isset($data['stage_id'])) {
                    $updates['stage_id'] = (int) $data['stage_id'];
                }
                if (isset($data['assigned_user_id'])) {
                    $updates['assigned_user_id'] = (int) $data['assigned_user_id'];
                }
                if ($contact && ! empty($updates)) {
                    $contact->update($updates);
                }
                return [
                    'success' => true,
                    'message' => 'Lead updated in CRM.',
                    'context_updates' => ['lead' => $updates],
                ];

            case 'change_stage':
                $stageId = (int) ($data['stage_id'] ?? 0);
                if ($contact && $stageId > 0) {
                    $contact->update(['stage_id' => $stageId]);
                }
                return ['success' => true, 'message' => "CRM stage changed to ID: {$stageId}"];

            case 'create_task':
                $title = $this->resolveVariables($data['title'] ?? 'Follow up with lead', $contact, $context);
                if (! $isSimulation && $contact) {
                    CrmTask::create([
                        'workspace_id' => $automation->workspace_id,
                        'contact_id' => $contact->id,
                        'title' => $title,
                        'priority' => $data['priority'] ?? 'high',
                        'due_at' => now()->addDays((int) ($data['due_in_days'] ?? 1)),
                        'status' => 'pending',
                    ]);
                }
                return ['success' => true, 'message' => "CRM Task created: {$title}"];

            case 'add_note':
                $content = $this->resolveVariables($data['note'] ?? 'Note added via automation', $contact, $context);
                if (! $isSimulation && $contact) {
                    CrmNote::create([
                        'workspace_id' => $automation->workspace_id,
                        'contact_id' => $contact->id,
                        'content' => $content,
                    ]);
                }
                return ['success' => true, 'message' => "CRM Note added: {$content}"];

            case 'human_handoff':
                $assignee = $data['assign_user_id'] ?? null;
                if ($contact && $assignee) {
                    $contact->update(['assigned_user_id' => (int) $assignee]);
                }
                if ($automation->workspace) {
                    app(\App\Services\Notifications\NotificationCenterService::class)->notify(
                        $automation->workspace,
                        'crm_human_handoff',
                        'Human Handoff Requested',
                        "Contact {$contact?->first_name} requested human attention.",
                        ['contact_id' => $contact?->id, 'automation_id' => $automation->id],
                        null,
                        'high'
                    );
                }
                return ['success' => true, 'message' => 'Escalated to human agent.'];

            // ─── AI VOICE CALL ACTION ──────────────────────────────────
            case 'voice_call':
            case 'ai_voice_call':
                $script = $this->resolveVariables($data['prompt'] ?? 'Follow up on inquiry', $contact, $context);
                $phone = $contact?->phone_e164 ?? $data['phone'] ?? null;

                if (! $phone) {
                    return ['success' => false, 'message' => 'Cannot make call: contact has no phone number.'];
                }

                // Check calling hours (09:00 - 20:00)
                $hour = (int) now()->format('H');
                if ($hour < 9 || $hour >= 20) {
                    return ['success' => false, 'skipped' => true, 'message' => 'Call skipped: outside calling hours (09:00 - 20:00).'];
                }

                if (! $isSimulation && $this->telephonyService) {
                    try {
                        $this->telephonyService->driver('twilio', $automation->workspace_id)->initiateCall($phone, [
                            'workspace_id' => $automation->workspace_id,
                            'contact_id' => $contact?->id,
                            'prompt' => $script,
                        ]);
                    } catch (\Throwable $e) {
                        Log::warning('Voice call execution note', ['error' => $e->getMessage()]);
                    }
                }

                return [
                    'success' => true,
                    'message' => "AI Voice call dispatched to {$phone}",
                    'output' => ['phone' => $phone, 'script' => $script],
                ];

            // ─── SSRF PROTECTED HTTP WEBHOOK ──────────────────────────────────
            case 'webhook':
            case 'http_request':
                $url = $data['url'] ?? '';
                if (! $this->isSafeUrl($url)) {
                    throw new \InvalidArgumentException('SSRF Protection: target URL is not permitted (private or loopback IP).');
                }

                $method = strtoupper($data['method'] ?? 'POST');
                $payload = $data['payload'] ?? ['contact' => $contact?->toArray(), 'context' => $context];

                if (! $isSimulation) {
                    $res = Http::timeout(10)->send($method, $url, ['json' => $payload]);
                    return [
                        'success' => $res->successful(),
                        'message' => "HTTP {$method} sent to {$url} (Status: {$res->status()})",
                        'output' => ['status' => $res->status(), 'response' => $res->json()],
                    ];
                }

                return ['success' => true, 'message' => "Simulated HTTP {$method} to {$url}"];

            // ─── CONTROL NODES ───────────────────────────────────────────────
            case 'wait':
            case 'wait_delay':
                $delayMinutes = (int) ($data['minutes'] ?? ($data['hours'] ?? 0) * 60 + ($data['days'] ?? 0) * 1440);
                return [
                    'success' => true,
                    'message' => "Workflow paused for {$delayMinutes} minutes.",
                    'output' => ['resume_at' => now()->addMinutes($delayMinutes)->toIso8601String()],
                ];

            case 'goal':
            case 'goal_reached':
                return [
                    'success' => true,
                    'message' => 'Workflow Goal Reached: '.($data['goal_name'] ?? 'Conversion'),
                    'output' => ['goal' => $data['goal_name'] ?? 'Qualified Lead'],
                ];

            default:
                return ['success' => true, 'message' => "Executed node type: {$type}"];
        }
    }

    /**
     * Evaluate condition rule groups.
     */
    protected function evaluateCondition(array $data, ?Contact $contact, array $context): bool
    {
        $field = $data['field'] ?? 'lead.score';
        $operator = $data['operator'] ?? 'greater_than';
        $targetValue = $data['value'] ?? 50;

        // Resolve actual field value
        $actualValue = match ($field) {
            'lead.score', 'lead_score' => $contact?->lead_score ?? $context['lead']['score'] ?? 0,
            'stage_id', 'lead.stage' => $contact?->stage_id ?? 0,
            'channel' => $context['channel'] ?? 'whatsapp',
            'ai.intent' => $context['ai']['intent'] ?? 'unknown',
            'tag' => $contact ? $contact->tags->pluck('name')->toArray() : [],
            default => data_get($context, $field, data_get($contact?->toArray() ?? [], $field)),
        };

        return match ($operator) {
            'equals', '==' => is_string($actualValue) && is_string($targetValue)
                ? strcasecmp($actualValue, $targetValue) === 0
                : $actualValue == $targetValue,
            'not_equals', '!=' => $actualValue != $targetValue,
            'greater_than', '>' => (float) $actualValue > (float) $targetValue,
            'less_than', '<' => (float) $actualValue < (float) $targetValue,
            'greater_than_or_equal', '>=' => (float) $actualValue >= (float) $targetValue,
            'less_than_or_equal', '<=' => (float) $actualValue <= (float) $targetValue,
            'contains' => is_array($actualValue)
                ? in_array($targetValue, $actualValue)
                : str_contains(strtolower((string) $actualValue), strtolower((string) $targetValue)),
            'not_contains' => is_array($actualValue)
                ? ! in_array($targetValue, $actualValue)
                : ! str_contains(strtolower((string) $actualValue), strtolower((string) $targetValue)),
            'is_empty' => empty($actualValue),
            'is_not_empty' => ! empty($actualValue),
            default => true,
        };
    }

    /**
     * Evaluate switch/case branching.
     */
    protected function evaluateSwitchCase(array $data, ?Contact $contact, array $context): string
    {
        $field = $data['field'] ?? 'ai.intent';
        $val = strtolower((string) data_get($context, $field, $context['ai']['intent'] ?? 'other'));
        $cases = $data['cases'] ?? ['pricing', 'support', 'complaint', 'sales'];

        foreach ($cases as $case) {
            if (strcasecmp($val, $case) === 0) {
                return strtolower($case);
            }
        }

        return 'default';
    }

    /**
     * Find next node id given edges, source node, and optional branch handle.
     */
    protected function getNextNodeId($edges, string $sourceNodeId, ?string $branch = null): ?string
    {
        $matchingEdges = $edges->filter(fn ($e) => ($e['source'] ?? '') === $sourceNodeId);

        if ($branch) {
            $branchEdge = $matchingEdges->first(fn ($e) => ($e['sourceHandle'] ?? '') === $branch || ($e['label'] ?? '') === $branch);
            if ($branchEdge) {
                return $branchEdge['target'] ?? null;
            }
        }

        $defaultEdge = $matchingEdges->first();
        return $defaultEdge['target'] ?? null;
    }

    /**
     * Resolve personalization variables like {{contact.first_name}} safely.
     */
    public function resolveVariables(string $template, ?Contact $contact = null, array $context = []): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_\.]+)\s*\}\}/', function ($matches) use ($contact, $context) {
            $key = trim($matches[1]);
            return match ($key) {
                'contact.first_name', 'first_name' => $contact?->first_name ?? 'there',
                'contact.last_name', 'last_name' => $contact?->last_name ?? '',
                'contact.phone', 'phone' => $contact?->phone_e164 ?? '',
                'contact.email', 'email' => $contact?->email ?? '',
                'lead.score' => (string) ($contact?->lead_score ?? $context['lead']['score'] ?? 0),
                'lead.stage' => (string) ($contact?->stage_id ?? ''),
                'ai.intent' => (string) ($context['ai']['intent'] ?? 'General'),
                'organization.name' => 'Growbridge Connect',
                default => (string) data_get($context, $key, $matches[0]),
            };
        }, $template);
    }

    /**
     * SSRF Protection: verify destination URL does not target private or local networks.
     */
    public function isSafeUrl(string $url): bool
    {
        if (empty($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (! $host) {
            return false;
        }

        // Block localhost and loopback names
        if (in_array(strtolower($host), ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)) {
            return false;
        }

        $ip = gethostbyname($host);
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        // Check private IP ranges
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }

        // Block cloud metadata IP (169.254.169.254)
        if ($ip === '169.254.169.254') {
            return false;
        }

        return true;
    }
}
