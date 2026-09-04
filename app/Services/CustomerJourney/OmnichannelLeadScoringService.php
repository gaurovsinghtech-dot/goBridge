<?php

namespace App\Services\CustomerJourney;

use App\Modules\Shared\Models\Contact;

class OmnichannelLeadScoringService
{
    /**
     * Compute lead score for a contact based on channels and attributes.
     */
    public function scoreContact(Contact $contact): int
    {
        $score = (int) ($contact->lead_score ?? 0);
        return min(100, max(0, $score));
    }

    /**
     * Evaluate and update score on customer activity.
     */
    public function evaluateScore(Contact $contact, string $activityType = 'general_activity', int $points = 0): array
    {
        $current = $this->scoreContact($contact);
        $newScore = min(100, $current + $points);
        if ($points > 0) {
            $this->recordActivity($contact, $activityType, $points);
        }

        return [
            'score' => $newScore,
            'band' => $newScore >= 80 ? 'hot' : ($newScore >= 50 ? 'warm' : 'cold'),
        ];
    }

    /**
     * Increment or update score on customer activity.
     */
    public function recordActivity(Contact $contact, string $activityType, int $points = 10): int
    {
        $newScore = min(100, (int) ($contact->lead_score ?? 0) + $points);
        $temperature = $newScore >= 80 ? 'hot' : ($newScore >= 50 ? 'warm' : 'cold');

        $contact->update([
            'lead_score' => $newScore,
            'lead_temperature' => $temperature,
        ]);

        return $newScore;
    }
}
