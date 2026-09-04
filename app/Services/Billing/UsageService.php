<?php

namespace App\Services\Billing;

use App\Models\StoredFile;
use App\Models\Subscription;
use App\Models\Workspace;
use App\Models\WorkspaceUsage;
use App\Modules\Shared\Models\Contact;
use Illuminate\Support\Facades\Log;

class UsageService
{
    /**
     * Get or create current month usage record for a workspace.
     */
    public function getMonthlyUsage(Workspace|int $workspace): WorkspaceUsage
    {
        $workspaceId = $workspace instanceof Workspace ? $workspace->id : $workspace;
        $month = now()->startOfMonth()->format('Y-m-d');

        $usage = WorkspaceUsage::where('workspace_id', $workspaceId)
            ->whereDate('period_month', $month)
            ->first();

        if ($usage) {
            return $usage;
        }

        try {
            return WorkspaceUsage::create([
                'workspace_id' => $workspaceId,
                'period_month' => $month,
                'contacts_count' => Contact::where('workspace_id', $workspaceId)->count(),
                'messages_count' => 0,
                'ai_requests_count' => 0,
                'ai_tokens_count' => 0,
                'voice_calls_count' => 0,
                'voice_minutes_count' => 0,
                'automation_executions_count' => 0,
                'campaigns_count' => 0,
                'api_requests_count' => 0,
            ]);
        } catch (\Throwable) {
            return WorkspaceUsage::where('workspace_id', $workspaceId)->latest('id')->first();
        }
    }

    /**
     * Normalize metric names.
     */
    public static function normalizeMetric(string $metric): string
    {
        return match (strtolower($metric)) {
            'whatsapp_messages', 'whatsapp', 'messages', 'messages_count' => 'whatsapp_messages',
            'email_sent', 'emails_sent', 'email_sends', 'campaigns', 'campaigns_count' => 'email_sent',
            'ai_messages', 'ai_requests', 'ai_requests_count', 'ai_tokens' => 'ai_messages',
            'storage_bytes', 'storage_mb', 'storage', 'storage_gb' => 'storage_bytes',
            'automation_runs', 'automation_executions', 'automation_workflows' => 'automation_runs',
            'voice_minutes', 'voice_minutes_count', 'voice_calls', 'voice_calls_count' => 'voice_minutes',
            'contacts', 'contacts_count', 'leads' => 'contacts',
            'api_requests', 'api_requests_count', 'api_calls' => 'api_requests',
            default => $metric,
        };
    }

    /**
     * Check if a workspace can consume an amount of a metered metric.
     */
    public function canConsume(Workspace|int $workspace, string $metric, int $amount = 1): bool
    {
        $quota = $this->checkQuota($workspace, $metric, $amount);

        return $quota['allowed'];
    }

    /**
     * Check if workspace can execute a quota-restricted action.
     * Returns structured info including warning messages for 80%/90%/100% soft limits.
     *
     * @return array{
     *   allowed: bool,
     *   current: int,
     *   max: int,
     *   percentage: float,
     *   warning: ?string,
     *   threshold_level: ?int
     * }
     */
    public function checkQuota(Workspace|int $workspace, string $metric, int $amount = 1): array
    {
        $workspaceId = $workspace instanceof Workspace ? $workspace->id : $workspace;
        $canonical = static::normalizeMetric($metric);

        $subscription = Subscription::with('plan')->where('workspace_id', $workspaceId)->latest('id')->first();
        if (! $subscription && $workspace instanceof Workspace && $workspace->owner_id) {
            $subscription = Subscription::with('plan')->where('user_id', $workspace->owner_id)->latest('id')->first();
        }

        $plan = $subscription?->plan;
        $limits = $plan?->limits ?? [];

        // Determine plan limit for the metric
        $max = match ($canonical) {
            'whatsapp_messages' => $limits['whatsapp_messages'] ?? $limits['messages'] ?? 1000,
            'email_sent' => $limits['email_sends'] ?? $limits['email_sent'] ?? $limits['campaigns'] ?? 2000,
            'ai_messages' => $limits['ai_messages'] ?? $limits['ai_requests'] ?? 500,
            'storage_bytes' => isset($limits['storage_mb']) ? ((int) $limits['storage_mb'] * 1048576) : ($limits['storage_bytes'] ?? (500 * 1048576)),
            'automation_runs' => $limits['automation_executions'] ?? $limits['automation_workflows'] ?? $limits['automation_runs'] ?? 50,
            'voice_minutes' => $limits['voice_minutes'] ?? 0,
            'contacts' => $limits['contacts'] ?? 1000,
            'api_requests' => $limits['api_requests'] ?? $limits['api_limits'] ?? 5000,
            default => $limits[$metric] ?? -1,
        };

        // Unlimited indicator (-1)
        if ($max === -1) {
            return [
                'allowed' => true,
                'current' => 0,
                'max' => -1,
                'percentage' => 0.0,
                'warning' => null,
                'threshold_level' => null,
            ];
        }

        $usage = $this->getMonthlyUsage($workspaceId);

        $current = match ($canonical) {
            'contacts' => Contact::where('workspace_id', $workspaceId)->count(),
            'whatsapp_messages' => (int) $usage->messages_count,
            'email_sent' => (int) $usage->campaigns_count,
            'ai_messages' => (int) $usage->ai_requests_count,
            'storage_bytes' => (int) StoredFile::where('workspace_id', $workspaceId)->sum('size_bytes'),
            'automation_runs' => (int) $usage->automation_executions_count,
            'voice_minutes' => (int) $usage->voice_minutes_count,
            'api_requests' => (int) $usage->api_requests_count,
            default => 0,
        };

        $projected = $current + $amount;
        $percentage = $max > 0 ? round(($current / $max) * 100, 1) : 100.0;

        $warning = null;
        $thresholdLevel = null;

        if ($percentage >= 100 || $projected > $max) {
            $thresholdLevel = 100;
            $warning = "Usage limit reached for {$canonical} ({$current}/{$max}). Please upgrade your plan or purchase an add-on pack.";
        } elseif ($percentage >= 90) {
            $thresholdLevel = 90;
            $warning = "90% of your {$canonical} quota has been used ({$current}/{$max}).";
        } elseif ($percentage >= 80) {
            $thresholdLevel = 80;
            $warning = "You have used {$percentage}% of your {$canonical} quota ({$current}/{$max}).";
        }

        return [
            'allowed' => $projected <= $max,
            'current' => $current,
            'max' => (int) $max,
            'percentage' => $percentage,
            'warning' => $warning,
            'threshold_level' => $thresholdLevel,
        ];
    }

    /**
     * Increment usage counter for a workspace metric.
     */
    public function recordUsage(Workspace|int $workspace, string $metric, int $amount = 1): void
    {
        $usage = $this->getMonthlyUsage($workspace);
        $canonical = static::normalizeMetric($metric);

        $column = match ($canonical) {
            'whatsapp_messages' => 'messages_count',
            'email_sent' => 'campaigns_count',
            'ai_messages' => 'ai_requests_count',
            'voice_minutes' => 'voice_minutes_count',
            'automation_runs' => 'automation_executions_count',
            'api_requests' => 'api_requests_count',
            default => null,
        };

        if ($column) {
            $usage->increment($column, $amount);
        }
    }

    /**
     * Enforce quota; throws HTTP 403 / aborts if limit reached.
     */
    public function enforce(Workspace|int $workspace, string $metric, int $amount = 1): void
    {
        $check = $this->checkQuota($workspace, $metric, $amount);
        if (! $check['allowed']) {
            abort(403, $check['warning'] ?? "Monthly usage limit reached for {$metric}.");
        }
    }

    /**
     * Get aggregate dashboard usage stats for the workspace.
     */
    public function getDashboardUsage(Workspace|int $workspace): array
    {
        $workspaceId = $workspace instanceof Workspace ? $workspace->id : $workspace;
        $usage = $this->getMonthlyUsage($workspaceId);

        $subscription = Subscription::with('plan')->where('workspace_id', $workspaceId)->latest('id')->first();
        if (! $subscription && $workspace instanceof Workspace && $workspace->owner_id) {
            $subscription = Subscription::with('plan')->where('user_id', $workspace->owner_id)->latest('id')->first();
        }

        $limits = $subscription?->plan?->limits ?? [];

        return [
            'contacts' => [
                'current' => Contact::where('workspace_id', $workspaceId)->count(),
                'max' => $limits['contacts'] ?? 1000,
            ],
            'whatsapp_messages' => [
                'current' => (int) $usage->messages_count,
                'max' => $limits['whatsapp_messages'] ?? $limits['messages'] ?? 1000,
            ],
            'email_sent' => [
                'current' => (int) $usage->campaigns_count,
                'max' => $limits['email_sends'] ?? $limits['email_sent'] ?? 2000,
            ],
            'ai_messages' => [
                'current' => (int) $usage->ai_requests_count,
                'max' => $limits['ai_messages'] ?? 500,
            ],
            'voice_minutes' => [
                'current' => (int) $usage->voice_minutes_count,
                'max' => $limits['voice_minutes'] ?? 0,
            ],
            'automations' => [
                'current' => (int) $usage->automation_executions_count,
                'max' => $limits['automation_executions'] ?? $limits['automation_workflows'] ?? 50,
            ],
            'storage_bytes' => [
                'current' => (int) StoredFile::where('workspace_id', $workspaceId)->sum('size_bytes'),
                'max' => isset($limits['storage_mb']) ? ((int) $limits['storage_mb'] * 1048576) : (500 * 1048576),
            ],
        ];
    }
}
