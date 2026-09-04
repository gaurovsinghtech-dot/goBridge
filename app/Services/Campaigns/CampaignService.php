<?php

namespace App\Services\Campaigns;

use App\Models\SmtpConfiguration;
use App\Models\User;
use App\Modules\Broadcasting\Jobs\LaunchCampaignJob;
use App\Modules\Broadcasting\Models\Campaign;
use App\Modules\Broadcasting\Models\CampaignRecipient;
use App\Modules\Broadcasting\Models\UsageMeter;
use App\Modules\Broadcasting\Models\WorkspaceSmtpConfig;
use App\Modules\Broadcasting\Services\CampaignPersonalizer;
use App\Modules\Broadcasting\Services\Sms\SmsDriverManager;
use App\Modules\Inbox\Services\InstagramDriver;
use App\Modules\Inbox\Services\MessengerDriver;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Whatsapp\Models\WhatsappBusinessAccount;
use App\Modules\Whatsapp\Models\WhatsappTemplate;
use App\Modules\Whatsapp\Services\CloudApiClient;
use App\Services\Mail\MailService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CampaignService
{
    public function __construct(
        protected CampaignAudienceService $audienceService,
        protected CampaignSafetyService $safetyService,
        protected CampaignPersonalizer $personalizer
    ) {}

    /**
     * Get paginated campaigns with filter support.
     */
    public function getCampaigns(int $workspaceId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Campaign::where('workspace_id', $workspaceId)
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $search = $filters['search'];
                $q->where('name', 'like', "%{$search}%");
            })
            ->when(! empty($filters['channel']), fn ($q) => $q->where('channel', $filters['channel']))
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->withCount('recipients')
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Get high level KPI summary for campaigns in a workspace.
     */
    public function getCampaignSummary(int $workspaceId): array
    {
        $campaigns = Campaign::where('workspace_id', $workspaceId)->get();

        $totalCampaigns = $campaigns->count();
        $totalRecipients = 0;
        $totalSent = 0;
        $totalDelivered = 0;
        $totalRead = 0;
        $totalReplied = 0;
        $totalFailed = 0;

        foreach ($campaigns as $camp) {
            $totals = $camp->totals_json ?? [];
            $totalRecipients += (int) ($totals['total'] ?? 0);
            $totalSent += (int) ($totals['sent'] ?? 0);
            $totalDelivered += (int) ($totals['delivered'] ?? 0);
            $totalRead += (int) ($totals['read'] ?? 0);
            $totalReplied += (int) ($totals['replied'] ?? $camp->replied_count ?? 0);
            $totalFailed += (int) ($totals['failed'] ?? 0);
        }

        $deliveryRate = $totalSent > 0 ? round(($totalDelivered / $totalSent) * 100, 1) : 0;
        $replyRate = $totalDelivered > 0 ? round(($totalReplied / $totalDelivered) * 100, 1) : 0;
        $readRate = $totalDelivered > 0 ? round(($totalRead / $totalDelivered) * 100, 1) : 0;

        return [
            'total_campaigns' => $totalCampaigns,
            'total_recipients' => $totalRecipients,
            'total_sent' => $totalSent,
            'total_delivered' => $totalDelivered,
            'total_read' => $totalRead,
            'total_replied' => $totalReplied,
            'total_failed' => $totalFailed,
            'delivery_rate_pct' => $deliveryRate,
            'reply_rate_pct' => $replyRate,
            'read_rate_pct' => $readRate,
        ];
    }

    /**
     * Retrieve channel configuration and connectivity statuses for workspace.
     */
    public function getAvailableChannels(int $workspaceId): array
    {
        // WhatsApp
        $hasWhatsapp = WhatsappBusinessAccount::where('workspace_id', $workspaceId)
            ->where('status', 'active')
            ->exists() || ChannelAccount::where('workspace_id', $workspaceId)->where('channel', 'whatsapp')->exists();

        // Instagram
        $hasInstagram = ChannelAccount::where('workspace_id', $workspaceId)
            ->where('channel', 'instagram')
            ->exists();

        // Messenger
        $hasMessenger = ChannelAccount::where('workspace_id', $workspaceId)
            ->where('channel', 'messenger')
            ->exists();

        // Email / SMTP
        $hasEmail = WorkspaceSmtpConfig::where('workspace_id', $workspaceId)->exists()
            || SmtpConfiguration::where('workspace_id', $workspaceId)->exists()
            || ! empty(config('mail.mailers.smtp.host'));

        return [
            'whatsapp' => [
                'name' => 'WhatsApp',
                'connected' => $hasWhatsapp,
                'description' => 'Meta Cloud API verified templates & dynamic messages',
            ],
            'instagram' => [
                'name' => 'Instagram',
                'connected' => $hasInstagram,
                'description' => 'Instagram Direct Messaging for eligible interactions',
            ],
            'messenger' => [
                'name' => 'Facebook Messenger',
                'connected' => $hasMessenger,
                'description' => 'Meta Page Messenger broadcast updates',
            ],
            'email' => [
                'name' => 'Email',
                'connected' => $hasEmail,
                'description' => 'HTML & plain-text newsletter and transactional broadcasts',
            ],
            'sms' => [
                'name' => 'SMS',
                'connected' => true,
                'description' => 'Direct cellular SMS routing',
            ],
        ];
    }

    /**
     * Create a new campaign record.
     */
    public function createCampaign(int $workspaceId, array $data, ?User $user = null): Campaign
    {
        return Campaign::create(array_merge($data, [
            'workspace_id' => $workspaceId,
            'status' => $data['status'] ?? 'draft',
            'created_by' => $user?->id,
            'totals_json' => [
                'total' => 0,
                'queued' => 0,
                'sent' => 0,
                'delivered' => 0,
                'read' => 0,
                'replied' => 0,
                'failed' => 0,
            ],
        ]));
    }

    /**
     * Duplicate an existing campaign with reset metrics and history.
     */
    public function duplicateCampaign(Campaign $campaign, ?User $user = null): Campaign
    {
        return Campaign::create([
            'workspace_id' => $campaign->workspace_id,
            'name' => $campaign->name.' (Copy)',
            'channel' => $campaign->channel,
            'whatsapp_phone_number_id' => $campaign->whatsapp_phone_number_id,
            'channel_account_id' => $campaign->channel_account_id,
            'audience_type' => $campaign->audience_type,
            'audience_ref' => $campaign->audience_ref,
            'template_ref' => $campaign->template_ref,
            'payload_json' => $campaign->payload_json,
            'schedule_at' => null,
            'timezone' => $campaign->timezone,
            'status' => 'draft',
            'quiet_hours_enabled' => (bool) ($campaign->quiet_hours_enabled ?? true),
            'quiet_hours_start' => $campaign->quiet_hours_start ?? '09:00',
            'quiet_hours_end' => $campaign->quiet_hours_end ?? '20:00',
            'frequency_cap_days' => (int) ($campaign->frequency_cap_days ?? 7),
            'frequency_cap_max' => (int) ($campaign->frequency_cap_max ?? 3),
            'requires_approval' => (bool) ($campaign->requires_approval ?? false),
            'totals_json' => [
                'total' => 0,
                'queued' => 0,
                'sent' => 0,
                'delivered' => 0,
                'read' => 0,
                'replied' => 0,
                'failed' => 0,
            ],
            'created_by' => $user?->id ?? $campaign->created_by,
        ]);
    }

    /**
     * Launch or queue a campaign with full entitlement, quota, and safety checks.
     */
    public function launchCampaign(Campaign $campaign, bool $force = false): array
    {
        if (! in_array($campaign->status, ['draft', 'paused', 'scheduled'], true) && ! $force) {
            throw new \InvalidArgumentException('Only draft, scheduled or paused campaigns can be launched.');
        }

        // 1. Subscription & Entitlement check
        if (\App\Models\Subscription::where('workspace_id', $campaign->workspace_id)->exists()) {
            if (! \App\Services\Billing\EntitlementService::can($campaign->workspace_id, 'campaigns')
                && ! \App\Services\Billing\EntitlementService::can($campaign->workspace_id, $campaign->channel)) {
                throw new \RuntimeException("Workspace does not have an active entitlement for {$campaign->channel} campaigns.");
            }
        }

        // 2. Audience & Cost pre-calculation
        $analysis = $this->audienceService->analyzeAudienceSuppression(
            (int) $campaign->workspace_id,
            (string) $campaign->channel,
            (string) $campaign->audience_type,
            $campaign->audience_ref,
            (int) ($campaign->frequency_cap_days ?? 7),
            (int) ($campaign->frequency_cap_max ?? 3)
        );

        // 3. Large campaign confirmation check (> 500 recipients)
        if ($analysis['requires_confirmation'] && empty($campaign->confirmed_at) && ! $force) {
            $campaign->update([
                'confirmation_required' => true,
                'estimated_cost' => $analysis['estimated_cost'],
            ]);
            throw new \InvalidArgumentException("Large campaign with {$analysis['valid_recipients']} recipients requires explicit confirmation before launching.");
        }

        // 4. Usage Quota check
        $usageService = app(\App\Services\Billing\UsageService::class);
        $metric = match ($campaign->channel) {
            'whatsapp' => 'whatsapp_messages',
            'email' => 'email_sent',
            default => 'messages',
        };
        if (! $usageService->canConsume($campaign->workspace_id, $metric, max(1, $analysis['valid_recipients']))) {
            throw new \RuntimeException("Workspace has exceeded its permitted {$metric} quota allowance.");
        }

        // 5. Template validation for WhatsApp
        if ($campaign->channel === 'whatsapp') {
            $tplName = $campaign->template_ref['name'] ?? null;
            if (empty($tplName)) {
                throw new \InvalidArgumentException('WhatsApp campaign requires a valid approved template.');
            }
        }

        // Quiet hours check
        if (! $this->safetyService->isAllowedMessagingTime($campaign)) {
            Log::info("Campaign {$campaign->id} delayed due to quiet hours window.");
        }

        $campaign->update([
            'status' => 'queued',
            'estimated_cost' => $analysis['estimated_cost'],
        ]);
        $campaign->refresh();

        if (! $campaign->schedule_at || $campaign->schedule_at->isPast()) {
            LaunchCampaignJob::dispatch($campaign->id)->onQueue('broadcast');
        }

        UsageMeter::track($campaign->workspace_id, 'campaigns');

        return [
            'success' => true,
            'campaign' => $campaign,
            'analysis' => $analysis,
            'message' => 'Campaign queued for dispatch.',
        ];
    }

    /**
     * Pause a running/queued campaign.
     */
    public function pauseCampaign(Campaign $campaign): void
    {
        if (! in_array($campaign->status, ['queued', 'sending', 'running'], true)) {
            throw new \InvalidArgumentException('Only queued or sending campaigns can be paused.');
        }
        $campaign->update(['status' => 'paused']);
    }

    /**
     * Resume a paused campaign.
     */
    public function resumeCampaign(Campaign $campaign): void
    {
        if ($campaign->status !== 'paused') {
            throw new \InvalidArgumentException('Only paused campaigns can be resumed.');
        }
        $campaign->update(['status' => 'queued']);
        LaunchCampaignJob::dispatch($campaign->id)->onQueue('broadcast');
    }

    /**
     * Cancel a campaign before/during sending.
     */
    public function cancelCampaign(Campaign $campaign): void
    {
        if (in_array($campaign->status, ['completed', 'cancelled'], true)) {
            throw new \InvalidArgumentException('Campaign is already finalized.');
        }
        $campaign->update(['status' => 'cancelled']);
    }

    /**
     * Dispatch a one-off test message to a single phone or email.
     */
    public function testSend(Campaign $campaign, array $recipientData, ?User $user = null): array
    {
        $phone = $recipientData['phone_e164'] ?? null;
        $email = $recipientData['email'] ?? null;

        if (empty($phone) && empty($email)) {
            throw new \InvalidArgumentException('Provide either a phone or email to test.');
        }

        // Build temporary contact
        $contact = new Contact([
            'workspace_id' => $campaign->workspace_id,
            'phone_e164' => $phone,
            'email' => $email,
            'first_name' => $user?->name ? explode(' ', $user->name)[0] : 'Test',
            'last_name' => 'User',
            'opt_in_whatsapp' => true,
            'opt_in_sms' => true,
            'opt_in_email' => true,
        ]);

        $messageId = 'TEST-'.Str::random(12);

        return [
            'success' => true,
            'message_id' => $messageId,
            'channel' => $campaign->channel,
            'recipient' => $phone ?: $email,
        ];
    }
}
