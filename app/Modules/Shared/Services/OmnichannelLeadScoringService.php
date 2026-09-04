<?php

namespace App\Modules\Shared\Services;

use App\Modules\Shared\Models\Contact;

class OmnichannelLeadScoringService
{
    /**
     * Compute and update a contact's lead score & tier.
     *
     * @param array{
     *   intent?: string,
     *   reply_count?: int,
     *   call_outcome?: string,
     *   budget_mentioned?: bool,
     *   requested_demo?: bool,
     *   negative_sentiment?: bool,
     *   custom_delta?: int
     * } $signals
     */
    public function updateScore(Contact $contact, array $signals = []): Contact
    {
        $currentScore = (int) ($contact->lead_score ?? 10);
        $delta = 0;

        // 1. Intent Signals
        if (! empty($signals['intent'])) {
            $delta += match (strtolower($signals['intent'])) {
                'demo_request', 'purchase_inquiry', 'pricing_high' => 30,
                'quotation_request', 'consultation' => 20,
                'general_inquiry', 'faq' => 10,
                'complaint', 'support_issue' => -10,
                'not_interested' => -30,
                default => 5,
            };
        }

        // 2. Engagement & Replies
        if (! empty($signals['reply_count'])) {
            $delta += min(20, $signals['reply_count'] * 5);
        }

        // 3. Telephony / AI Voice Call Outcome
        if (! empty($signals['call_outcome'])) {
            $delta += match (strtolower($signals['call_outcome'])) {
                'qualified', 'appointment_booked' => 35,
                'support_resolved', 'callback_scheduled' => 15,
                'not_interested', 'wrong_number' => -25,
                default => 5,
            };
        }

        // 4. Commercial intent
        if (! empty($signals['budget_mentioned']) || ! empty($signals['requested_demo'])) {
            $delta += 25;
        }

        // 5. Negative sentiment
        if (! empty($signals['negative_sentiment'])) {
            $delta -= 20;
        }

        if (isset($signals['custom_delta'])) {
            $delta += (int) $signals['custom_delta'];
        }

        $newScore = max(0, min(100, $currentScore + $delta));
        $band = $this->resolveBand($newScore);

        $contact->update([
            'lead_score' => $newScore,
            'lead_score_band' => $band,
            'lead_intent' => $signals['intent'] ?? $contact->lead_intent,
        ]);

        return $contact;
    }

    /**
     * Map score (0-100) to human-friendly qualification band.
     */
    public function resolveBand(int $score): string
    {
        return match (true) {
            $score >= 81 => 'very_hot',
            $score >= 61 => 'hot',
            $score >= 31 => 'warm',
            default => 'cold',
        };
    }
}
