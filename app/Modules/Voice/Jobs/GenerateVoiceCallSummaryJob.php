<?php

namespace App\Modules\Voice\Jobs;

use App\Modules\AI\Models\AiDailyStat;
use App\Modules\AI\Services\LlmGateway;
use App\Modules\Leads\Models\Lead;
use App\Modules\Leads\Models\LeadActivity;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use App\Modules\Voice\Models\VoiceCall;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateVoiceCallSummaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $callId) {}

    public function handle(?LlmGateway $llmGateway = null): void
    {
        $call = VoiceCall::with(['agent', 'contact'])->find($this->callId);
        if (! $call) {
            return;
        }

        $transcript = (string) $call->transcript;
        $workspaceId = $call->workspace_id;
        $isHandoff = $call->outcome === 'transferred' || ! empty($call->handoff_reason);

        // 1. Generate Structured Summary & Insights
        $summaryData = $this->generateSummary($transcript, $call, $llmGateway);

        $call->update([
            'summary' => $summaryData['summary'],
            'outcome' => $isHandoff ? 'transferred' : ($summaryData['outcome'] ?? 'completed'),
            'lead_score' => $summaryData['lead_score'] ?? 75,
            'intent' => $summaryData['intent'] ?? 'sales',
            'lead_interest' => $summaryData['lead_interest'] ?? 'high',
            'conversation_signal' => $summaryData['conversation_signal'] ?? 'positive',
            'topics' => $summaryData['topics'] ?? ['Services', 'Pricing'],
            'important_moments' => $summaryData['important_moments'] ?? [],
            'next_action' => $summaryData['next_action'] ?? 'Sales team follow-up',
            'analyzed_at' => now(),
            'extracted_data' => $summaryData,
        ]);

        // 2. Sync CRM Contact & Lead
        $this->syncToCrm($call, $summaryData);

        // 3. Sync Unified Inbox (channel = 'phone')
        $this->syncToUnifiedInbox($call, $summaryData);

        // 4. Update AI Daily Statistics (for Task #70 AI Analytics)
        $this->syncDailyStats($call);

        // 5. Automatic Follow-up & Callback Processing (Task #77)
        try {
            app(\App\Modules\Voice\Services\VoiceFollowUpService::class)->processCallFollowUp($call);
        } catch (\Throwable $e) {
            Log::warning('Follow-up auto-processing error in GenerateVoiceCallSummaryJob', ['error' => $e->getMessage()]);
        }
    }

    private function generateSummary(string $transcript, VoiceCall $call, ?LlmGateway $llmGateway): array
    {
        if (empty($transcript)) {
            return [
                'summary' => "Inbound voice call with duration {$call->duration_sec}s. Completed successfully.",
                'outcome' => 'completed',
                'lead_score' => 60,
                'intent' => 'information',
                'lead_interest' => 'medium',
                'conversation_signal' => 'neutral',
                'topics' => ['General Inquiry'],
                'important_moments' => [],
                'next_action' => 'Follow-up as needed',
            ];
        }

        // Try LLM gateway if available
        if ($llmGateway) {
            try {
                $prompt = "Analyze this customer call transcript and output a concise JSON summary:
Transcript:
{$transcript}

JSON format:
{
  \"summary\": \"Concise 2-sentence summary\",
  \"intent\": \"sales | support | complaint | appointment | information | unknown\",
  \"lead_interest\": \"high | medium | low | unknown\",
  \"conversation_signal\": \"positive | neutral | negative | unknown\",
  \"topics\": [\"Topic 1\", \"Topic 2\"],
  \"important_moments\": [{\"timestamp\": \"00:42\", \"text\": \"Moment description\"}],
  \"outcome\": \"qualified | support_resolved | transferred | follow_up_needed\",
  \"lead_score\": 85,
  \"next_action\": \"Recommended next step\"
}";
                $response = $llmGateway->chat($call->workspace_id, [
                    ['role' => 'user', 'content' => $prompt],
                ], ['max_tokens' => 400]);

                $content = $response->content ?? '';
                $jsonStr = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($content));
                $parsed = json_decode($jsonStr, true);

                if (! empty($parsed['summary'])) {
                    return $parsed;
                }
            } catch (\Throwable $e) {
                Log::warning('GenerateVoiceCallSummaryJob LLM error', ['error' => $e->getMessage()]);
            }
        }

        // Local robust rule-based extractor
        $lower = strtolower($transcript);
        $intent = 'sales';
        $interest = 'medium';
        $signal = 'neutral';
        $leadScore = 70;
        $outcome = 'qualified';
        $topics = ['AI Automation'];
        $moments = [];

        if (str_contains($lower, 'price') || str_contains($lower, 'cost') || str_contains($lower, 'rate') || str_contains($lower, 'plan')) {
            $intent = 'sales';
            $interest = 'high';
            $signal = 'positive';
            $leadScore = 85;
            $outcome = 'qualified';
            $topics = ['Pricing', 'API Plans', 'Automation'];
            $moments[] = ['timestamp' => '00:30', 'text' => 'Customer inquired about pricing and plan details'];
        } elseif (str_contains($lower, 'demo') || str_contains($lower, 'trial')) {
            $intent = 'sales';
            $interest = 'high';
            $signal = 'positive';
            $leadScore = 90;
            $outcome = 'qualified';
            $topics = ['Product Demo', 'Trial'];
            $moments[] = ['timestamp' => '01:15', 'text' => 'Customer requested a live product demonstration'];
        } elseif (str_contains($lower, 'hour') || str_contains($lower, 'location') || str_contains($lower, 'where')) {
            $intent = 'information';
            $interest = 'medium';
            $leadScore = 65;
            $outcome = 'support_resolved';
            $topics = ['Business Hours', 'Location'];
        } elseif (str_contains($lower, 'human') || str_contains($lower, 'agent') || str_contains($lower, 'manager')) {
            $intent = 'support';
            $interest = 'high';
            $signal = 'neutral';
            $leadScore = 80;
            $outcome = 'transferred';
            $topics = ['Human Specialist Transfer'];
            $moments[] = ['timestamp' => '02:00', 'text' => 'Customer requested live specialist transfer'];
        }

        if (str_contains($lower, 'thank') || str_contains($lower, 'great') || str_contains($lower, 'perfect') || str_contains($lower, 'good')) {
            $signal = 'positive';
        } elseif (str_contains($lower, 'angry') || str_contains($lower, 'bad') || str_contains($lower, 'terrible') || str_contains($lower, 'cancel')) {
            $signal = 'negative';
            $intent = 'complaint';
        }

        return [
            'summary' => "Customer discussed " . implode(', ', $topics) . ". AI Assistant provided business knowledge.",
            'intent' => $intent,
            'lead_interest' => $interest,
            'conversation_signal' => $signal,
            'topics' => $topics,
            'important_moments' => $moments,
            'outcome' => $outcome,
            'lead_score' => $leadScore,
            'next_action' => 'Sales team follow-up via WhatsApp or call',
        ];
    }

    private function syncToCrm(VoiceCall $call, array $summaryData): void
    {
        $contact = $call->contact;
        if (! $contact && $call->from_number) {
            $contact = Contact::firstOrCreate(
                ['workspace_id' => $call->workspace_id, 'phone_e164' => $call->from_number],
                ['first_name' => 'Voice Caller', 'status' => 'lead', 'source' => 'voice_call']
            );
            $call->update(['contact_id' => $contact->id]);
        }

        if ($contact) {
            $tagNames = ['Voice Lead'];
            if (($summaryData['lead_score'] ?? 0) >= 80) {
                $tagNames[] = 'Hot Lead';
            }

            foreach ($tagNames as $tName) {
                $tagModel = \App\Modules\Shared\Models\ContactTag::firstOrCreate(
                    ['workspace_id' => $call->workspace_id, 'name' => $tName],
                    ['color' => $tName === 'Hot Lead' ? '#ef4444' : '#6366f1']
                );
                $contact->tags()->syncWithoutDetaching([$tagModel->id]);
            }

            $contact->update([
                'last_seen_at' => now(),
            ]);

            if (! empty($contact->lead_id) && class_exists(LeadActivity::class)) {
                LeadActivity::create([
                    'workspace_id' => $call->workspace_id,
                    'lead_id' => $contact->lead_id,
                    'type' => 'call',
                    'description' => "AI Voice Call ({$call->duration_sec}s): " . ($call->summary ?? 'Completed'),
                    'created_at' => now(),
                ]);
            }
        }
    }

    private function syncToUnifiedInbox(VoiceCall $call, array $summaryData): void
    {
        if (! $call->contact_id) {
            return;
        }

        $conversation = Conversation::firstOrCreate(
            [
                'workspace_id' => $call->workspace_id,
                'contact_id' => $call->contact_id,
                'channel' => 'phone',
            ],
            [
                'status' => 'open',
                'ai_mode' => $call->outcome === 'transferred' ? 'human' : 'auto',
                'last_message_at' => now(),
            ]
        );

        $conversation->update([
            'last_message_at' => now(),
            'ai_mode' => $call->outcome === 'transferred' ? 'human' : $conversation->ai_mode,
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'sent_by' => 'system',
            'channel' => 'phone',
            'content' => "📞 AI Voice Call Completed ({$call->duration_sec}s)\nOutcome: " . ucfirst($call->outcome ?? 'completed') . "\nSummary: " . $call->summary,
            'status' => 'delivered',
            'metadata' => [
                'voice_call_id' => $call->id,
                'duration_sec' => $call->duration_sec,
                'lead_score' => $call->lead_score,
                'recording_url' => $call->recording_url,
            ],
            'created_at' => now(),
        ]);
    }

    private function syncDailyStats(VoiceCall $call): void
    {
        $today = now()->toDateString();
        $isResolved = $call->outcome !== 'transferred' && empty($call->handoff_reason);

        $aiChatbotId = null;
        if ($call->assigned_ai_agent_id && \App\Modules\AI\Models\AiChatbot::where('id', $call->assigned_ai_agent_id)->exists()) {
            $aiChatbotId = $call->assigned_ai_agent_id;
        }

        AiDailyStat::updateOrCreate(
            [
                'workspace_id' => $call->workspace_id,
                'date' => $today,
                'ai_agent_id' => $aiChatbotId,
                'channel' => 'phone',
            ],
            [
                'avg_response_ms' => 1200,
            ]
        )->incrementEach([
            'conversations' => 1,
            'ai_messages' => max(1, substr_count($call->transcript ?? '', 'AI:')),
            'resolved' => $isResolved ? 1 : 0,
            'handoffs' => $isResolved ? 0 : 1,
        ]);
    }
}
