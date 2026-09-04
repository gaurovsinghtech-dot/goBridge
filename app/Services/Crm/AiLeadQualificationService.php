<?php

namespace App\Services\Crm;

use App\Models\Crm\CrmTask;
use App\Modules\Shared\Models\Contact;
use App\Services\CustomerJourney\CustomerJourneyService;
use App\Services\CustomerJourney\OmnichannelLeadScoringService;
use App\Services\Notifications\NotificationCenterService;

class AiLeadQualificationService
{
    public function __construct(
        private readonly OmnichannelLeadScoringService $scoringService,
        private readonly CustomerJourneyService $journeyService,
        private readonly CrmPipelineService $pipelineService,
        private readonly NotificationCenterService $notificationService
    ) {}

    /**
     * AI-driven qualification analysis and recommendation.
     */
    public function qualifyContact(Contact $contact, array $context = []): array
    {
        $messageText = strtolower($context['message'] ?? '');
        $buyingKeywords = ['price', 'pricing', 'cost', 'quotation', 'purchase', 'buy', 'demo', 'enterprise', 'plan', 'deal', 'discount', 'order'];
        $hasHighIntent = false;

        foreach ($buyingKeywords as $kw) {
            if (str_contains($messageText, $kw)) {
                $hasHighIntent = true;
                break;
            }
        }

        // Evaluate lead score
        $scoreResult = $this->scoringService->evaluateScore($contact);
        $score = $scoreResult['score'];

        if ($hasHighIntent) {
            $score = min(100, $score + 20);
        }

        $temperature = match (true) {
            $score >= 85 => 'Very Hot',
            $score >= 70 => 'Hot',
            $score >= 40 => 'Warm',
            default => 'Cold',
        };

        // Determine breakdown factors
        $factors = [];
        if ($contact->conversations()->exists()) {
            $factors[] = ['points' => 20, 'label' => 'Replied on WhatsApp / Social'];
        }
        if ($hasHighIntent) {
            $factors[] = ['points' => 20, 'label' => 'Requested pricing / High buying intent'];
        }
        if ($contact->voiceCalls()->where('duration_sec', '>', 30)->exists()) {
            $factors[] = ['points' => 15, 'label' => 'Completed AI voice call'];
        }
        if ($contact->last_seen_at && $contact->last_seen_at->greaterThanOrEqualTo(now()->subDays(2))) {
            $factors[] = ['points' => 10, 'label' => 'Active in last 48 hours'];
        }

        // Update contact score and intent
        $contact->update([
            'lead_score' => $score,
            'lead_score_band' => strtolower(str_replace(' ', '_', $temperature)),
            'lead_intent' => $hasHighIntent ? 'high_buying_intent' : ($score >= 60 ? 'warm_interest' : 'general_inquiry'),
        ]);

        $this->journeyService->recordEvent(
            contactId: $contact->id,
            workspaceId: $contact->workspace_id,
            eventType: 'ai_lead_qualification',
            channel: 'ai',
            title: "AI Classified Lead as {$temperature} (Score: {$score})",
            description: "Lead score evaluated to {$score}/100 based on omnichannel signals.",
            metadata: [
                'score' => $score,
                'temperature' => $temperature,
                'factors' => $factors,
            ]
        );

        // If very hot and has no next stage assigned, advance to Qualified stage if available
        if ($score >= 75 && (! $contact->stage || $contact->stage->position < 2)) {
            $pipeline = $contact->pipeline ?? $this->pipelineService->ensureDefaultPipeline($contact->workspace_id);
            $qualifiedStage = $pipeline->stages()->where('name', 'like', '%Qualified%')->first();
            if ($qualifiedStage) {
                $this->pipelineService->moveContactStage($contact, $qualifiedStage->id);
            }
        }

        return [
            'score' => $score,
            'temperature' => $temperature,
            'intent' => $contact->lead_intent,
            'factors' => $factors,
        ];
    }

    /**
     * Trigger Human Handoff when AI detects high stakes, complex inquiries, or low confidence.
     */
    public function triggerHumanHandoff(Contact $contact, string $reason): void
    {
        $this->journeyService->recordEvent(
            contactId: $contact->id,
            workspaceId: $contact->workspace_id,
            eventType: 'ai_human_handoff',
            channel: 'ai',
            title: 'AI Requested Human Handoff',
            description: "AI transferred conversation to sales agent: {$reason}",
            metadata: ['reason' => $reason]
        );

        // Create follow-up task
        CrmTask::create([
            'workspace_id' => $contact->workspace_id,
            'contact_id' => $contact->id,
            'assigned_user_id' => $contact->assigned_user_id,
            'title' => "Immediate Follow-up Required: {$contact->full_name}",
            'description' => "AI initiated human handoff reason: {$reason}",
            'due_at' => now()->addMinutes(30),
            'priority' => 'urgent',
            'status' => 'pending',
        ]);

        // Dispatch in-app notification to workspace / assigned user
        $workspace = $contact->workspace ?? \App\Models\Workspace::find($contact->workspace_id);
        if ($workspace) {
            $this->notificationService->notify(
                workspace: $workspace,
                type: 'handoff',
                title: "🚨 Human Handoff: {$contact->full_name}",
                message: "AI detected: {$reason}. Please take over the conversation.",
                data: ['url' => "/app/inbox?contact_id={$contact->id}"],
                priority: 'urgent'
            );
        }
    }
}
