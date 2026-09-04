<?php

namespace App\Modules\Automation\Services;

use App\Modules\Automation\Models\Automation;
use App\Modules\Automation\Models\AutomationRun;
use App\Modules\Shared\Models\Contact;
use Illuminate\Support\Facades\DB;

class AutomationSafetyGuard
{
    private const MAX_RUNS_PER_CONTACT_24H = 5;
    private const MAX_CALLS_PER_CONTACT_24H = 3;

    /**
     * Verify that an automation run is safe to launch and not violating safety thresholds.
     */
    public function canExecuteAutomation(Automation $automation, Contact $contact): bool
    {
        // 1. Opt-out safety
        if ($contact->marketing_opt_out) {
            return false;
        }

        // 2. Max executions per contact per 24 hours
        $runsLast24h = AutomationRun::where('automation_id', $automation->id)
            ->where('contact_id', $contact->id)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        if ($runsLast24h >= self::MAX_RUNS_PER_CONTACT_24H) {
            return false; // Loop prevention threshold reached
        }

        return true;
    }

    /**
     * Check if an outbound call can be triggered for a contact without exceeding call limits.
     */
    public function canTriggerCall(Contact $contact): bool
    {
        if ($contact->marketing_opt_out) {
            return false;
        }

        $callsToday = \App\Modules\Voice\Models\VoiceCall::where('contact_id', $contact->id)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        return $callsToday < self::MAX_CALLS_PER_CONTACT_24H;
    }
}
