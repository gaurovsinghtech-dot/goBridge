<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationCenterService
{
    /**
     * Dispatch an omnichannel alert notification to a workspace and/or specific user.
     */
    public function notify(
        Workspace $workspace,
        string $type,
        string $title,
        string $message,
        array $data = [],
        ?User $user = null,
        string $priority = 'normal'
    ): void {
        $recipients = $user ? collect([$user]) : $workspace->users()->where('status', 'active')->get();

        $notificationData = array_merge($data, [
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'priority' => $priority,
            'workspace_id' => $workspace->id,
            'created_at' => now()->toIso8601String(),
        ]);

        foreach ($recipients as $recipient) {
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(),
                'type' => "App\\Notifications\\" . Str::studly($type) . "Notification",
                'notifiable_type' => User::class,
                'notifiable_id' => $recipient->id,
                'data' => json_encode($notificationData),
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Send instant hot lead alert.
     */
    public function notifyHotLead(Workspace $workspace, string $contactName, int $score, string $contactUuid): void
    {
        $this->notify(
            $workspace,
            'hot_lead',
            '🔥 Hot Lead Detected',
            "{$contactName} has achieved a lead score of {$score}/100.",
            ['url' => "/app/contacts/{$contactUuid}", 'contact_name' => $contactName, 'score' => $score],
            null,
            'high'
        );
    }

    /**
     * Send AI human handoff alert.
     */
    public function notifyHumanHandoff(Workspace $workspace, string $customerName, string $reason, ?string $conversationId = null): void
    {
        $this->notify(
            $workspace,
            'ai_human_handoff',
            '🤖 Human Assistance Requested',
            "AI assistant requested human handoff for {$customerName}: {$reason}",
            ['url' => '/app/inbox', 'customer_name' => $customerName, 'reason' => $reason],
            null,
            'high'
        );
    }

    /**
     * Send AI Voice call completion summary alert.
     */
    public function notifyVoiceCallCompleted(Workspace $workspace, string $customerName, string $outcome, ?string $callUuid = null): void
    {
        $this->notify(
            $workspace,
            'voice_call_completed',
            '📞 AI Voice Call Completed',
            "Call with {$customerName} ended: {$outcome}",
            ['url' => '/app/voice/logs', 'customer_name' => $customerName, 'outcome' => $outcome],
            null,
            'normal'
        );
    }

    /**
     * Send quota limit alert.
     */
    public function notifyQuotaWarning(Workspace $workspace, string $metric, int $percent): void
    {
        $this->notify(
            $workspace,
            'quota_warning',
            '⚠️ Usage Limit Warning',
            "Your workspace has reached {$percent}% of its monthly {$metric} quota.",
            ['url' => '/app/billing', 'metric' => $metric, 'percent' => $percent],
            null,
            $percent >= 100 ? 'critical' : 'high'
        );
    }
}
