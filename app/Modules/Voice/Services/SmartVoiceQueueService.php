<?php

namespace App\Modules\Voice\Services;

use App\Modules\Leads\Models\Lead;
use App\Modules\Shared\Models\Contact;
use App\Modules\Voice\Models\VoiceCampaign;
use App\Modules\Voice\Models\VoiceCampaignRecipient;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SmartVoiceQueueService
{
    /**
     * Evaluate contact eligibility for campaign calling
     */
    public function evaluateEligibility(Contact $contact, VoiceCampaign $campaign): array
    {
        // 1. Phone number validation
        $phone = preg_replace('/[^\d+]/', '', (string) $contact->phone_e164);
        if (empty($phone) || strlen($phone) < 8) {
            return ['eligible' => false, 'reason' => 'invalid_phone'];
        }

        // 2. Workspace ownership check
        if ((int) $contact->workspace_id !== (int) $campaign->workspace_id) {
            return ['eligible' => false, 'reason' => 'workspace_mismatch'];
        }

        // 3. Opt-out & Blocked status
        if (! empty($contact->marketing_opt_out) || ! empty($contact->is_blocked) || ! empty($contact->is_opted_out) || ! empty($contact->metadata['dnc']) || ! empty($contact->custom_fields['is_blocked'])) {
            return ['eligible' => false, 'reason' => 'opted_out'];
        }

        // 4. Contact Tags exclusion (e.g. Do Not Call, Unsubscribed)
        if ($contact->relationLoaded('tags') || $contact->tags()->exists()) {
            $tagNames = $contact->tags->pluck('name')->map(fn ($n) => strtolower($n))->all();
            if (in_array('do not call', $tagNames) || in_array('opt-out', $tagNames) || in_array('unsubscribed', $tagNames)) {
                return ['eligible' => false, 'reason' => 'opted_out'];
            }
        }

        return ['eligible' => true, 'reason' => null];
    }

    /**
     * Check if current time is within campaign calling days and hours
     */
    public function isWithinCallingHours(VoiceCampaign $campaign): bool
    {
        $tz = $campaign->timezone ?: 'Asia/Kolkata';
        $nowInTz = Carbon::now($tz);
        $currentDay = $nowInTz->format('l');
        $currentTime = $nowInTz->format('H:i');

        $allowedDays = $campaign->calling_days ?: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        if (! in_array($currentDay, $allowedDays)) {
            return false;
        }

        $startTime = $campaign->calling_start_time ?: '09:00';
        $endTime = $campaign->calling_end_time ?: '18:00';
        if ($currentTime < $startTime || $currentTime > $endTime) {
            return false;
        }

        return true;
    }

    /**
     * Calculate dynamic priority level, score, and trigger reason
     */
    public function calculatePriority(Contact $contact, VoiceCampaign $campaign, array $context = []): array
    {
        // 1. Scheduled Callbacks (Highest Priority: 100)
        if (! empty($context['is_callback']) || $contact->last_call_outcome === 'callback_requested') {
            return [
                'level' => 'high',
                'score' => 100,
                'reason' => 'callback_requested',
            ];
        }

        // 2. Appointment Reminders (Priority: 95)
        if ($campaign->type === 'appointment_reminder') {
            return [
                'level' => 'high',
                'score' => 95,
                'reason' => 'appointment_reminder',
            ];
        }

        // 3. Hot Leads (Score >= 80 or Tagged Hot Lead or Contact priority == high) (Priority: 90)
        $isHotLead = false;
        if ($contact->relationLoaded('tags')) {
            $isHotLead = $contact->tags->contains(fn ($t) => in_array(strtolower($t->name), ['hot lead', 'vip lead', 'high priority']));
        } else {
            $isHotLead = $contact->tags()->whereIn('name', ['Hot Lead', 'VIP Lead', 'High Priority'])->exists();
        }

        $leadScore = (int) ($contact->lead_score ?? 0);
        if ($contact->lead_id) {
            $lead = Lead::find($contact->lead_id);
            if ($lead && (int) ($lead->score ?? $lead->lead_score) > $leadScore) {
                $leadScore = (int) ($lead->score ?? $lead->lead_score);
            }
        }
        $contactLead = Lead::where('phone', $contact->phone_e164)->first();
        if ($contactLead && (int) ($contactLead->score ?? $contactLead->lead_score) > $leadScore) {
            $leadScore = (int) ($contactLead->score ?? $contactLead->lead_score);
        }

        if ($isHotLead || $leadScore >= 80 || $contact->priority === 'high') {
            return [
                'level' => 'high',
                'score' => 90,
                'reason' => 'hot_lead',
            ];
        }

        // 4. Warm Leads (Score >= 50 or New Lead) (Priority: 50)
        if ($leadScore >= 50 || $campaign->type === 'lead_followup' || $contact->priority === 'medium') {
            return [
                'level' => 'medium',
                'score' => 50,
                'reason' => 'warm_lead',
            ];
        }

        // 5. Default / Cold (Priority: 10)
        return [
            'level' => 'low',
            'score' => 10,
            'reason' => 'routine_followup',
        ];
    }

    /**
     * Calculate intelligent retry time with time variation (morning vs evening)
     */
    public function calculateNextAttemptTime(VoiceCampaignRecipient $recipient, VoiceCampaign $campaign): Carbon
    {
        $tz = $campaign->timezone ?: 'Asia/Kolkata';
        $baseTime = Carbon::now($tz)->addHours($campaign->retry_delay_hours ?: 24);

        // Retry intelligence: alternate between morning and evening
        $lastAttemptHour = $recipient->last_attempt_at ? (int) $recipient->last_attempt_at->timezone($tz)->format('H') : 10;
        if ($lastAttemptHour < 13) {
            // Last was morning -> retry in afternoon/evening (16:30)
            $baseTime->setTime(16, 30);
        } else {
            // Last was evening -> retry in morning (10:30)
            $baseTime->setTime(10, 30);
        }

        // Ensure next attempt lands on an allowed calling day
        $allowedDays = $campaign->calling_days ?: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        while (! in_array($baseTime->format('l'), $allowedDays)) {
            $baseTime->addDay();
        }

        return $baseTime;
    }

    /**
     * Schedule a priority callback for a recipient
     */
    public function scheduleCallback(VoiceCampaignRecipient $recipient, Carbon $scheduledTime, ?string $notes = null): void
    {
        $recipient->update([
            'status' => 'scheduled',
            'is_callback' => true,
            'callback_scheduled_at' => $scheduledTime,
            'next_attempt_at' => $scheduledTime,
            'priority_level' => 'high',
            'priority_score' => 100,
            'queue_reason' => 'callback_requested',
            'notes' => $notes ?: 'Customer requested callback.',
            'exclusion_reason' => null,
        ]);
    }

    /**
     * Atomically fetch and lock the next batch of highest-priority eligible queue items
     */
    public function fetchAndLockNextBatch(int $workspaceId, int $limit = 5): Collection
    {
        $workerId = Str::uuid()->toString();

        return DB::transaction(function () use ($workspaceId, $limit, $workerId) {
            $now = Carbon::now();

            $recipients = VoiceCampaignRecipient::where('voice_campaign_recipients.workspace_id', $workspaceId)
                ->join('voice_campaigns', 'voice_campaigns.id', '=', 'voice_campaign_recipients.voice_campaign_id')
                ->where('voice_campaigns.status', 'running')
                ->where(function ($q) use ($now) {
                    $q->whereIn('voice_campaign_recipients.status', ['pending', 'scheduled'])
                      ->orWhere(function ($retryQ) use ($now) {
                          $retryQ->where('voice_campaign_recipients.status', 'failed')
                                 ->whereColumn('voice_campaign_recipients.attempts_count', '<', 'voice_campaign_recipients.max_attempts')
                                 ->where(function ($dateQ) use ($now) {
                                     $dateQ->whereNull('voice_campaign_recipients.next_attempt_at')
                                           ->orWhere('voice_campaign_recipients.next_attempt_at', '<=', $now);
                                 });
                      });
                })
                ->where(function ($lockQ) use ($now) {
                    $lockQ->whereNull('voice_campaign_recipients.locked_at')
                          ->orWhere('voice_campaign_recipients.locked_at', '<', $now->copy()->subMinutes(5));
                })
                ->select('voice_campaign_recipients.*')
                ->orderBy('voice_campaign_recipients.priority_score', 'desc')
                ->orderBy('voice_campaign_recipients.is_callback', 'desc')
                ->orderBy('voice_campaign_recipients.next_attempt_at', 'asc')
                ->orderBy('voice_campaign_recipients.id', 'asc')
                ->limit($limit)
                ->lockForUpdate()
                ->get();

            if ($recipients->isNotEmpty()) {
                VoiceCampaignRecipient::whereIn('id', $recipients->pluck('id'))
                    ->update([
                        'locked_at' => $now,
                        'locked_by' => $workerId,
                    ]);
            }

            return $recipients;
        });
    }
}
