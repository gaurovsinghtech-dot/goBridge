<?php

namespace App\Modules\Shared\Services;

use App\Modules\Automation\Models\AutomationRun;
use App\Modules\Shared\Models\Contact;

class OptOutComplianceService
{
    private const OPT_OUT_KEYWORDS = [
        'stop',
        'unsubscribe',
        'do not call',
        'no more messages',
        'remove me',
        'cancel',
        'opt out',
        'band karo',
        'roko',
    ];

    /**
     * Check if a message or transcript represents a customer opt-out request.
     */
    public function isOptOutRequest(string $text): bool
    {
        $normalized = strtolower(trim($text));

        foreach (self::OPT_OUT_KEYWORDS as $keyword) {
            if ($normalized === $keyword || str_starts_with($normalized, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Process an opt-out event: marks the contact, halts all active automation runs.
     */
    public function processOptOut(Contact $contact, string $channel = 'whatsapp'): void
    {
        $contact->update([
            'marketing_opt_out' => true,
            'opt_out_channel' => $channel,
            'opt_out_at' => now(),
            'opt_in_whatsapp' => false,
            'opt_in_sms' => false,
            'opt_in_email' => false,
        ]);

        // Cancel all in-flight automation runs
        AutomationRun::where('contact_id', $contact->id)
            ->whereIn('status', ['running', 'waiting'])
            ->update([
                'status' => 'cancelled',
                'completed_at' => now(),
                'error' => 'Halted due to customer opt-out request.',
            ]);

        // Log timeline event
        app(CustomerJourneyService::class)->recordEvent(
            $contact,
            $channel,
            'opt_out',
            'Customer Opted Out',
            "Opted out from marketing and automated follow-ups via {$channel}."
        );
    }
}
