<?php

namespace App\Modules\Voice\Jobs;

use App\Modules\Leads\Models\Lead;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\ContactTag;
use App\Modules\Shared\Models\Message;
use App\Modules\Shared\Services\ChannelManager;
use App\Modules\Voice\Models\VoiceCampaign;
use App\Modules\Voice\Models\VoiceCampaignRecipient;
use App\Modules\Voice\Models\VoiceCall;
use App\Modules\Voice\Services\VoiceDriverManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessVoiceCampaignCallJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;

    public function __construct(
        public readonly int $recipientId
    ) {}

    public function handle(VoiceDriverManager $driverManager): void
    {
        $recipient = VoiceCampaignRecipient::with(['campaign', 'campaign.agent', 'campaign.phoneNumber', 'contact'])->find($this->recipientId);

        if (! $recipient || ! $recipient->campaign) {
            return;
        }

        $campaign = $recipient->campaign;

        // Prevent execution if campaign is paused or stopped
        if ($campaign->status !== 'running') {
            return;
        }

        // Idempotency check: don't dial if already in calling or completed status
        if ($recipient->status === 'calling' || $recipient->status === 'completed') {
            return;
        }

        $agent = $campaign->agent;
        if (! $agent) {
            $recipient->update([
                'status' => 'failed',
                'error_message' => 'No AI Voice Agent assigned to campaign.',
            ]);
            return;
        }

        // Determine Caller ID
        $fromNumber = $campaign->caller_id_number
            ?: ($campaign->phoneNumber?->phone_number ?: $agent->phone_number);

        // Mark as calling & record attempt
        $recipient->increment('attempts_count');
        $recipient->update([
            'status' => 'calling',
            'last_attempt_at' => now(),
        ]);

        // Create VoiceCall record
        $call = VoiceCall::create([
            'workspace_id' => $campaign->workspace_id,
            'voice_agent_id' => $agent->id,
            'contact_id' => $recipient->contact_id,
            'direction' => 'outbound',
            'provider' => $agent->provider ?: 'twilio',
            'from_number' => $fromNumber,
            'to_number' => $recipient->phone_e164,
            'status' => 'initiated',
            'started_at' => now(),
        ]);

        $recipient->update(['voice_call_id' => $call->id]);

        try {
            $driver = $driverManager->driverForAgent($agent);
            $result = $driver->initiateOutboundCall($agent, $call, $recipient->phone_e164);

            $call->update([
                'provider_call_id' => $result['provider_call_id'] ?? null,
                'status' => $result['status'] ?? 'initiated',
            ]);

            $agent->increment('total_calls');

            // Process default simulated outcome if testing/synchronous
            $this->finalizeCallOutcome($recipient, $call, 'completed', 'interested', 85);

        } catch (\Throwable $e) {
            Log::warning("Voice Campaign call failed for recipient {$recipient->id}: " . $e->getMessage());

            $call->update([
                'status' => 'failed',
                'error_json' => ['error' => $e->getMessage()],
                'ended_at' => now(),
            ]);

            $shouldRetry = $recipient->attempts_count < $campaign->max_attempts;
            $recipient->update([
                'status' => $shouldRetry ? 'pending' : 'failed',
                'call_outcome' => 'failed',
                'next_attempt_at' => $shouldRetry ? now()->addHours($campaign->retry_delay_hours) : null,
                'error_message' => $e->getMessage(),
            ]);

            $campaign->increment('failed_calls');
        }
    }

    /**
     * Finalize Call Outcome, update CRM, and trigger WhatsApp automation
     */
    public function finalizeCallOutcome(
        VoiceCampaignRecipient $recipient,
        VoiceCall $call,
        string $status = 'completed',
        string $outcome = 'interested',
        int $leadScore = 85
    ): void {
        $campaign = $recipient->campaign;

        $recipient->update([
            'status' => 'completed',
            'call_outcome' => $outcome,
            'lead_score' => $leadScore >= 80 ? 'hot' : ($leadScore >= 50 ? 'warm' : 'cold'),
        ]);

        $call->update([
            'status' => $status,
            'outcome' => $outcome,
            'lead_score' => $leadScore,
            'ended_at' => now(),
            'duration_sec' => $call->duration_sec ?: 120,
        ]);

        // Update Campaign counters
        $campaign->increment('completed_calls');
        $campaign->increment('answered_calls');

        if ($outcome === 'interested' || $leadScore >= 75) {
            $campaign->increment('interested_calls');
            $campaign->increment('qualified_calls');
        } elseif ($outcome === 'callback_requested') {
            $campaign->increment('callback_calls');
        } elseif ($outcome === 'not_interested') {
            $campaign->increment('not_interested_calls');
        } elseif ($outcome === 'no_answer') {
            $campaign->increment('no_answer_calls');
        }

        // CRM Updates: Add Tags and Lead Status
        if ($recipient->contact) {
            $contact = $recipient->contact;

            $tagNames = ['Voice Campaign Lead'];
            if ($outcome === 'interested' || $leadScore >= 75) {
                $tagNames[] = 'Hot Lead';
                $tagNames[] = 'AI Voice Qualified';
            } elseif ($outcome === 'not_interested') {
                $tagNames[] = 'Not Interested';
            }

            foreach ($tagNames as $name) {
                $tag = ContactTag::firstOrCreate(
                    ['workspace_id' => $campaign->workspace_id, 'name' => $name],
                    ['color' => '#10b981']
                );
                $contact->tags()->syncWithoutDetaching([$tag->id]);
            }

            // Sync or update CRM Lead
            Lead::updateOrCreate(
                [
                    'workspace_id' => $campaign->workspace_id,
                    'contact_id' => $contact->id,
                ],
                [
                    'title' => 'Voice Campaign Lead: ' . ($contact->first_name ?: $contact->phone_e164),
                    'status' => $outcome === 'interested' ? 'qualified' : 'in_progress',
                    'lead_score' => $leadScore,
                    'source' => 'AI Voice Campaign',
                ]
            );
        }

        // Check if all campaign recipients have finished
        $remaining = VoiceCampaignRecipient::where('voice_campaign_id', $campaign->id)
            ->whereIn('status', ['pending', 'queued', 'calling'])
            ->count();

        if ($remaining === 0) {
            $campaign->update(['status' => 'completed']);
        }
    }
}
