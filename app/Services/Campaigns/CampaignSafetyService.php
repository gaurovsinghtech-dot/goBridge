<?php

namespace App\Services\Campaigns;

use App\Modules\Broadcasting\Models\Campaign;
use Carbon\Carbon;
use DateTimeInterface;

class CampaignSafetyService
{
    /**
     * Determine if current time falls within allowed messaging hours.
     * If quiet hours are enabled (e.g. 09:00 -> 20:00), sends are allowed inside this window.
     */
    public function isAllowedMessagingTime(Campaign $campaign, ?DateTimeInterface $time = null): bool
    {
        if (! $campaign->quiet_hours_enabled) {
            return true;
        }

        $tz = $campaign->timezone ?: 'UTC';
        $now = $time ? Carbon::instance($time)->setTimezone($tz) : Carbon::now($tz);

        $startStr = $campaign->quiet_hours_start ?: '09:00';
        $endStr = $campaign->quiet_hours_end ?: '20:00';

        [$startH, $startM] = array_pad(explode(':', $startStr), 2, 0);
        [$endH, $endM] = array_pad(explode(':', $endStr), 2, 0);

        $windowStart = $now->copy()->setTime((int) $startH, (int) $startM, 0);
        $windowEnd = $now->copy()->setTime((int) $endH, (int) $endM, 0);

        if ($windowStart->lessThanOrEqualTo($windowEnd)) {
            return $now->greaterThanOrEqualTo($windowStart) && $now->lessThanOrEqualTo($windowEnd);
        }

        // Overnight window
        return $now->greaterThanOrEqualTo($windowStart) || $now->lessThanOrEqualTo($windowEnd);
    }

    /**
     * Check if a duplicate/identical campaign was created or launched in the same workspace recently (within 2 hours).
     */
    public function detectDuplicateCampaign(int $workspaceId, array $data, ?int $excludeCampaignId = null): ?Campaign
    {
        $name = trim($data['name'] ?? '');
        $channel = $data['channel'] ?? '';
        $audienceType = $data['audience_type'] ?? '';
        $audienceRef = $data['audience_ref'] ?? null;

        return Campaign::where('workspace_id', $workspaceId)
            ->when($excludeCampaignId, fn ($q) => $q->where('id', '!=', $excludeCampaignId))
            ->where('channel', $channel)
            ->where(function ($q) use ($name, $audienceType, $audienceRef) {
                $q->where('name', $name)
                    ->orWhere(function ($sq) use ($audienceType, $audienceRef) {
                        $sq->where('audience_type', $audienceType)
                            ->where('audience_ref', $audienceRef);
                    });
            })
            ->where('created_at', '>=', now()->subHours(2))
            ->first();
    }

    /**
     * Check if campaign size warrants explicit two-step confirmation.
     */
    public function requiresTwoStepConfirmation(int $deliverableCount): bool
    {
        return $deliverableCount >= 1000;
    }
}
