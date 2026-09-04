<?php

namespace App\Services\Crm;

use App\Models\Workspace;
use App\Modules\Shared\Models\Contact;
use App\Services\Crm\Connectors\CrmManager;
use Illuminate\Support\Facades\Log;

class CrmSyncService
{
    public function __construct(
        protected CrmManager $crmManager
    ) {}

    /**
     * Triggered when an inbound or outbound message (WhatsApp / SMS) occurs
     */
    public function onMessageReceivedOrSent(Workspace $workspace, string $phone, string $channel, string $direction, string $text, ?string $contactName = null): void
    {
        try {
            // Find or create Contact in Growbridge
            $contact = Contact::firstOrCreate(
                ['workspace_id' => $workspace->id, 'phone_e164' => $phone],
                [
                    'first_name' => $contactName ?: 'Customer',
                    'source' => "{$channel}_inbound",
                ]
            );

            // Sync contact to CRM
            $this->crmManager->syncContactToCrm($contact, $workspace);

            // Sync message activity to CRM
            $this->crmManager->syncActivityToCrm($workspace, [
                'type' => "{$channel}_{$direction}",
                'content' => "{$channel} message ({$direction}): {$text}",
                'phone' => $phone,
                'external_contact_id' => null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[CrmSyncService] onMessage error: '.$e->getMessage());
        }
    }

    /**
     * Triggered when a Voice Call completes (with duration, outcome, recording URL)
     */
    public function onCallCompleted(Workspace $workspace, string $phone, int $durationSeconds, string $outcome, ?string $recordingUrl = null): void
    {
        try {
            $summary = "Voice Call Completed. Duration: {$durationSeconds}s, Outcome: {$outcome}";
            if ($recordingUrl) {
                $summary .= "\nRecording: {$recordingUrl}";
            }

            $this->crmManager->syncActivityToCrm($workspace, [
                'type' => 'voice_call',
                'summary' => $summary,
                'content' => $summary,
                'duration' => $durationSeconds,
                'outcome' => $outcome,
                'phone' => $phone,
                'recording_url' => $recordingUrl,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[CrmSyncService] onCallCompleted error: '.$e->getMessage());
        }
    }

    /**
     * Triggered when AI Agent interacts with a customer (creates interaction summary)
     */
    public function onAiInteractionSummary(Workspace $workspace, string $phone, string $summary, string $sentiment = 'positive'): void
    {
        try {
            $this->crmManager->syncActivityToCrm($workspace, [
                'type' => 'ai_summary',
                'summary' => "[AI Assistant Summary — Sentiment: {$sentiment}]\n{$summary}",
                'content' => $summary,
                'sentiment' => $sentiment,
                'phone' => $phone,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[CrmSyncService] onAiInteractionSummary error: '.$e->getMessage());
        }
    }
}
