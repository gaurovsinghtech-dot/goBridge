<?php

namespace App\Modules\Voice\Jobs;

use App\Modules\Voice\Models\VoiceCampaign;
use App\Modules\Voice\Models\VoiceCampaignRecipient;
use App\Modules\Voice\Models\VoiceCall;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DispatchVoiceCampaignCallsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $campaignId
    ) {}

    public function handle(): void
    {
        $campaign = VoiceCampaign::find($this->campaignId);

        if (! $campaign || $campaign->status !== 'running') {
            return;
        }

        // 1. Check Calling Window (Timezone & Allowed Days / Hours)
        $tz = $campaign->timezone ?: 'Asia/Kolkata';
        $nowInTz = Carbon::now($tz);
        $currentDay = $nowInTz->format('l'); // e.g. "Monday"
        $currentTime = $nowInTz->format('H:i');

        $allowedDays = $campaign->calling_days ?: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        if (! in_array($currentDay, $allowedDays)) {
            Log::info("Voice Campaign {$campaign->id} paused: {$currentDay} is not an allowed calling day.");
            return;
        }

        $startTime = $campaign->calling_start_time ?: '09:00';
        $endTime = $campaign->calling_end_time ?: '18:00';
        if ($currentTime < $startTime || $currentTime > $endTime) {
            Log::info("Voice Campaign {$campaign->id} outside calling window ({$currentTime} not between {$startTime}-{$endTime}).");
            return;
        }

        // 2. Check Daily Limit
        $todayCalls = VoiceCall::where('workspace_id', $campaign->workspace_id)
            ->whereDate('created_at', now()->toDateString())
            ->count();

        if ($todayCalls >= $campaign->daily_limit) {
            Log::info("Voice Campaign {$campaign->id} reached daily limit of {$campaign->daily_limit} calls.");
            return;
        }

        // 3. Check Concurrency Limit
        $activeCalls = VoiceCampaignRecipient::where('voice_campaign_id', $campaign->id)
            ->where('status', 'calling')
            ->count();

        $maxConcurrent = max(1, $campaign->concurrent_limit ?: 2);
        $availableSlots = $maxConcurrent - $activeCalls;

        if ($availableSlots <= 0) {
            return;
        }

        // 4. Fetch Eligible Next Batch of Recipients
        $eligibleRecipients = VoiceCampaignRecipient::where('voice_campaign_id', $campaign->id)
            ->where(function ($q) {
                $q->whereIn('status', ['pending', 'queued'])
                  ->orWhere(function ($retryQ) {
                      $retryQ->where('status', 'failed')
                             ->whereColumn('attempts_count', '<', 'max_attempts')
                             ->where(function ($dateQ) {
                                 $dateQ->whereNull('next_attempt_at')
                                       ->orWhere('next_attempt_at', '<=', now());
                             });
                  });
            })
            ->orderBy('priority_score', 'desc')
            ->orderBy('is_callback', 'desc')
            ->orderBy('next_attempt_at', 'asc')
            ->orderBy('id', 'asc')
            ->limit($availableSlots)
            ->get();

        if ($eligibleRecipients->isEmpty()) {
            // Check if any active calls are still running
            $hasIncomplete = VoiceCampaignRecipient::where('voice_campaign_id', $campaign->id)
                ->whereIn('status', ['calling'])
                ->exists();

            $totalRecipients = VoiceCampaignRecipient::where('voice_campaign_id', $campaign->id)->count();

            if (! $hasIncomplete && $totalRecipients > 0) {
                $campaign->update(['status' => 'completed']);
            }
            return;
        }

        foreach ($eligibleRecipients as $recipient) {
            $recipient->update(['status' => 'queued']);
            ProcessVoiceCampaignCallJob::dispatch($recipient->id);
        }
    }
}
