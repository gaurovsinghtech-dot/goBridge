<?php

namespace App\Services\Channels;

use App\Models\PhoneNumber;
use App\Models\SmtpConfiguration;
use App\Models\SystemSetting;
use App\Models\TwilioAccount;
use App\Models\Workspace;
use App\Modules\Integrations\Services\CredentialResolver;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Social\Models\SocialAccount;
use App\Modules\Voice\Models\VoiceAgent;
use App\Modules\Whatsapp\Models\WhatsappBusinessAccount;

class ChannelStatusService
{
    public const STATUS_CONNECTED = 'connected';
    public const STATUS_SETUP_REQUIRED = 'setup_required';
    public const STATUS_CONNECTION_FAILED = 'connection_failed';
    public const STATUS_NOT_CONNECTED = 'not_connected';

    /**
     * Compute comprehensive connection status for all channels in a workspace
     *
     * @return array<string, array{
     *     key: string,
     *     name: string,
     *     status: string,
     *     status_code: string,
     *     badge_color: string,
     *     icon: string,
     *     summary: string,
     *     details: array,
     *     action_url: string,
     *     action_label: string
     * }>
     */
    public function getWorkspaceChannelStatuses(Workspace $workspace): array
    {
        return [
            'whatsapp' => $this->getWhatsappStatus($workspace),
            'instagram' => $this->getInstagramStatus($workspace),
            'messenger' => $this->getMessengerStatus($workspace),
            'email' => $this->getEmailStatus($workspace),
            'twilio' => $this->getTwilioStatus($workspace),
            'ai' => $this->getAiStatus($workspace),
        ];
    }

    /**
     * WhatsApp Channel Status
     */
    public function getWhatsappStatus(Workspace $workspace): array
    {
        $waba = WhatsappBusinessAccount::where('workspace_id', $workspace->id)->first();
        $unifiedNumber = PhoneNumber::where('workspace_id', $workspace->id)
            ->where('whatsapp_status', 'connected')
            ->first();
        $anyNumber = PhoneNumber::where('workspace_id', $workspace->id)->first();

        if ($unifiedNumber || ($waba && $waba->status === 'active')) {
            $displayName = $unifiedNumber?->whatsapp_display_name ?? 'Active Business Account';
            return [
                'key' => 'whatsapp',
                'name' => 'WhatsApp Business',
                'status' => self::STATUS_CONNECTED,
                'status_code' => 'connected',
                'badge_color' => 'emerald',
                'icon' => 'MessageSquare',
                'summary' => "Connected ({$displayName})",
                'details' => [
                    'waba_id' => $waba?->waba_id,
                    'phone_number' => $unifiedNumber?->phone_number,
                    'display_name' => $displayName,
                ],
                'action_url' => route('client.voice.numbers.index'),
                'action_label' => 'Manage WhatsApp',
            ];
        }

        if ($anyNumber || $waba) {
            return [
                'key' => 'whatsapp',
                'name' => 'WhatsApp Business',
                'status' => self::STATUS_SETUP_REQUIRED,
                'status_code' => 'setup_required',
                'badge_color' => 'amber',
                'icon' => 'MessageSquare',
                'summary' => 'Setup required (Number provisioned, WhatsApp onboarding pending)',
                'details' => [
                    'phone_number' => $anyNumber?->phone_number,
                ],
                'action_url' => route('client.voice.numbers.index'),
                'action_label' => 'Connect WhatsApp',
            ];
        }

        return [
            'key' => 'whatsapp',
            'name' => 'WhatsApp Business',
            'status' => self::STATUS_NOT_CONNECTED,
            'status_code' => 'not_connected',
            'badge_color' => 'neutral',
            'icon' => 'MessageSquare',
            'summary' => 'Not connected (No active WABA or virtual number)',
            'details' => [],
            'action_url' => route('client.voice.numbers.index'),
            'action_label' => 'Get Number & Connect',
        ];
    }

    /**
     * Instagram Channel Status
     */
    public function getInstagramStatus(Workspace $workspace): array
    {
        $account = SocialAccount::where('workspace_id', $workspace->id)
            ->where('network', 'instagram')
            ->first();

        if ($account && $account->active) {
            return [
                'key' => 'instagram',
                'name' => 'Instagram Direct',
                'status' => self::STATUS_CONNECTED,
                'status_code' => 'connected',
                'badge_color' => 'emerald',
                'icon' => 'Sparkles',
                'summary' => "Connected (@{$account->name})",
                'details' => ['username' => $account->name],
                'action_url' => url('/app/social/accounts'),
                'action_label' => 'Manage Account',
            ];
        }

        if ($account && !$account->active) {
            return [
                'key' => 'instagram',
                'name' => 'Instagram Direct',
                'status' => self::STATUS_CONNECTION_FAILED,
                'status_code' => 'connection_failed',
                'badge_color' => 'red',
                'icon' => 'Sparkles',
                'summary' => 'Connection token expired or revoked',
                'details' => [],
                'action_url' => url('/app/social/accounts'),
                'action_label' => 'Reconnect',
            ];
        }

        return [
            'key' => 'instagram',
            'name' => 'Instagram Direct',
            'status' => self::STATUS_NOT_CONNECTED,
            'status_code' => 'not_connected',
            'badge_color' => 'neutral',
            'icon' => 'Sparkles',
            'summary' => 'Not connected',
            'details' => [],
            'action_url' => url('/app/social/accounts'),
            'action_label' => 'Connect Instagram',
        ];
    }

    /**
     * Facebook Messenger Channel Status
     */
    public function getMessengerStatus(Workspace $workspace): array
    {
        $channelAccount = ChannelAccount::where('workspace_id', $workspace->id)
            ->where('channel', 'messenger')
            ->first();

        if ($channelAccount && $channelAccount->status === 'active') {
            return [
                'key' => 'messenger',
                'name' => 'Facebook Messenger',
                'status' => self::STATUS_CONNECTED,
                'status_code' => 'connected',
                'badge_color' => 'emerald',
                'icon' => 'MessageSquare',
                'summary' => "Connected ({$channelAccount->display_name})",
                'details' => ['page_name' => $channelAccount->display_name],
                'action_url' => url('/app/inbox/setup'),
                'action_label' => 'Manage Messenger',
            ];
        }

        if ($channelAccount && $channelAccount->status === 'error') {
            return [
                'key' => 'messenger',
                'name' => 'Facebook Messenger',
                'status' => self::STATUS_CONNECTION_FAILED,
                'status_code' => 'connection_failed',
                'badge_color' => 'red',
                'icon' => 'MessageSquare',
                'summary' => 'Page access revoked or token invalid',
                'details' => [],
                'action_url' => url('/app/inbox/setup'),
                'action_label' => 'Reconnect',
            ];
        }

        return [
            'key' => 'messenger',
            'name' => 'Facebook Messenger',
            'status' => self::STATUS_NOT_CONNECTED,
            'status_code' => 'not_connected',
            'badge_color' => 'neutral',
            'icon' => 'MessageSquare',
            'summary' => 'Not connected',
            'details' => [],
            'action_url' => url('/app/inbox/setup'),
            'action_label' => 'Connect Messenger',
        ];
    }

    /**
     * Email Channel Status
     */
    public function getEmailStatus(Workspace $workspace): array
    {
        $smtp = SmtpConfiguration::getActive();

        if ($smtp && $smtp->is_active) {
            return [
                'key' => 'email',
                'name' => 'Email (SMTP / Inbound)',
                'status' => self::STATUS_CONNECTED,
                'status_code' => 'connected',
                'badge_color' => 'emerald',
                'icon' => 'Mail',
                'summary' => "Connected ({$smtp->from_email})",
                'details' => ['from_email' => $smtp->from_email, 'host' => $smtp->host],
                'action_url' => url('/admin/email-system'),
                'action_label' => 'Manage SMTP',
            ];
        }

        return [
            'key' => 'email',
            'name' => 'Email (SMTP / Inbound)',
            'status' => self::STATUS_NOT_CONNECTED,
            'status_code' => 'not_connected',
            'badge_color' => 'neutral',
            'icon' => 'Mail',
            'summary' => 'System default mailer active (Custom SMTP not connected)',
            'details' => [],
            'action_url' => url('/admin/email-system'),
            'action_label' => 'Configure SMTP',
        ];
    }

    /**
     * Twilio Voice & SMS Telephony Status
     */
    public function getTwilioStatus(Workspace $workspace): array
    {
        $subaccount = TwilioAccount::where('workspace_id', $workspace->id)->first();
        $numbers = PhoneNumber::where('workspace_id', $workspace->id)
            ->where('status', 'active')
            ->count();

        if ($numbers > 0) {
            return [
                'key' => 'twilio',
                'name' => 'Twilio Voice & SMS',
                'status' => self::STATUS_CONNECTED,
                'status_code' => 'connected',
                'badge_color' => 'emerald',
                'icon' => 'PhoneCall',
                'summary' => "Connected ({$numbers} Active Lines)",
                'details' => ['active_lines' => $numbers],
                'action_url' => route('client.voice.numbers.index'),
                'action_label' => 'View Numbers',
            ];
        }

        if ($subaccount) {
            return [
                'key' => 'twilio',
                'name' => 'Twilio Voice & SMS',
                'status' => self::STATUS_SETUP_REQUIRED,
                'status_code' => 'setup_required',
                'badge_color' => 'amber',
                'icon' => 'PhoneCall',
                'summary' => 'Subaccount active, no phone numbers provisioned',
                'details' => [],
                'action_url' => route('client.voice.numbers.index'),
                'action_label' => 'Get Phone Number',
            ];
        }

        return [
            'key' => 'twilio',
            'name' => 'Twilio Voice & SMS',
            'status' => self::STATUS_NOT_CONNECTED,
            'status_code' => 'not_connected',
            'badge_color' => 'neutral',
            'icon' => 'PhoneCall',
            'summary' => 'Not provisioned',
            'details' => [],
            'action_url' => route('client.voice.numbers.index'),
            'action_label' => 'Provision Twilio',
        ];
    }

    /**
     * AI Provider & Agents Status
     */
    public function getAiStatus(Workspace $workspace): array
    {
        $activeAgents = VoiceAgent::where('workspace_id', $workspace->id)
            ->where('status', 'active')
            ->count();

        if ($activeAgents > 0) {
            return [
                'key' => 'ai',
                'name' => 'AI Provider & Agents',
                'status' => self::STATUS_CONNECTED,
                'status_code' => 'connected',
                'badge_color' => 'emerald',
                'icon' => 'Bot',
                'summary' => "Active ({$activeAgents} AI Assistants Ready)",
                'details' => ['agent_count' => $activeAgents],
                'action_url' => url('/app/voice'),
                'action_label' => 'Manage Agents',
            ];
        }

        return [
            'key' => 'ai',
            'name' => 'AI Provider & Agents',
            'status' => self::STATUS_SETUP_REQUIRED,
            'status_code' => 'setup_required',
            'badge_color' => 'amber',
            'icon' => 'Bot',
            'summary' => 'AI platform enabled, no custom agents configured',
            'details' => [],
            'action_url' => url('/app/voice/create'),
            'action_label' => 'Create AI Agent',
        ];
    }
}
