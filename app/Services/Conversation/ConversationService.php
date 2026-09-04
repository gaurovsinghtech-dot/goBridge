<?php

namespace App\Services\Conversation;

use App\Events\MessageReceived;
use App\Events\MessageSent;
use App\Events\MessageStatusUpdated;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\AI\Services\LlmGateway;
use App\Modules\Inbox\Models\ConversationActivity;
use App\Modules\Shared\DTOs\NormalizedMessage;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\ContactTag;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use App\Modules\Shared\Services\ChannelAdapterManager;
use App\Services\AI\AiAgentService;
use App\Services\Notifications\NotificationCenterService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ConversationService
{
    public function __construct(
        private readonly ChannelAdapterManager $adapterManager,
        private readonly ?LlmGateway $llmGateway = null,
        private readonly ?AiAgentService $aiAgentService = null,
        private readonly ?NotificationCenterService $notifications = null,
    ) {}

    /**
     * Ingest an incoming normalized message from ANY channel adapter.
     */
    public function processIncomingMessage(
        NormalizedMessage $normalized,
        int $workspaceId,
        ?int $channelAccountId = null
    ): Message {
        return DB::transaction(function () use ($normalized, $workspaceId, $channelAccountId) {
            // 1. Resolve or create Contact
            $contact = $this->resolveContact($normalized, $workspaceId);

            // 2. Resolve Channel Account
            $channelAccount = $this->resolveChannelAccount($normalized, $workspaceId, $channelAccountId);

            // 3. Resolve or create Conversation
            $conversation = $this->resolveConversation($normalized, $workspaceId, $contact, $channelAccount);

            // 4. Create Normalized Message record
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'contact_id' => $contact->id,
                'channel' => $normalized->channel,
                'direction' => 'in',
                'type' => $normalized->messageType,
                'message_type' => $normalized->messageType,
                'body' => $normalized->body,
                'media_url' => $normalized->mediaUrl,
                'provider_message_id' => $normalized->externalMessageId,
                'external_message_id' => $normalized->externalMessageId,
                'status' => $normalized->status === 'received' ? 'delivered' : $normalized->status,
                'sent_by' => 'customer',
                'sender_type' => 'customer',
                'payload' => $normalized->metadata,
                'metadata' => $normalized->metadata,
                'sent_at' => $normalized->timestamp ?? now(),
            ]);

            // 5. Update Conversation stats & last activity
            $conversation->update([
                'last_message_at' => now(),
                'last_inbound_at' => now(),
                'unread_count' => ($conversation->unread_count ?? 0) + 1,
                'status' => $conversation->status === 'resolved' ? 'open' : $conversation->status,
            ]);

            // 6. Broadcast Real-time event
            try {
                broadcast(new MessageReceived($message))->toOthers();
            } catch (\Throwable $e) {
                // Ignore broadcast error
            }

            // 7. Human Handoff & AI Auto-Reply checks
            $this->evaluateHandoffOrAiReply($conversation, $message);

            return $message;
        });
    }

    /**
     * Dispatch an outbound reply to the customer via the appropriate Channel Adapter.
     */
    public function sendReply(
        Conversation $conversation,
        array $data,
        ?User $sender = null,
        string $senderType = 'human'
    ): Message {
        return DB::transaction(function () use ($conversation, $data, $sender, $senderType) {
            $channel = $conversation->channel ?? $conversation->channel_account?->channel ?? 'whatsapp';

            $message = Message::create([
                'conversation_id' => $conversation->id,
                'contact_id' => $conversation->contact_id,
                'channel' => $channel,
                'direction' => 'out',
                'type' => $data['type'] ?? 'text',
                'message_type' => $data['type'] ?? 'text',
                'body' => $data['body'] ?? '',
                'media_url' => $data['media_url'] ?? null,
                'status' => 'queued',
                'sent_by' => $senderType === 'ai' ? 'bot' : ($senderType === 'system' ? 'system' : 'human'),
                'sender_type' => $senderType,
                'user_id' => $sender?->id,
                'payload' => $data['payload'] ?? null,
                'metadata' => $data['metadata'] ?? null,
                'sent_at' => now(),
            ]);

            // Dispatch via Channel Adapter
            try {
                $adapter = $this->adapterManager->adapter($channel);
                $providerId = $adapter->send($message);

                $message->update([
                    'status' => 'sent',
                    'provider_message_id' => $providerId,
                    'external_message_id' => $providerId,
                ]);
            } catch (\Throwable $e) {
                Log::error("Failed sending outbound reply on [{$channel}]: " . $e->getMessage());
                $message->update([
                    'status' => 'failed',
                    'error_json' => ['message' => $e->getMessage()],
                ]);
            }

            // Update Conversation last activity
            $updateData = ['last_message_at' => now()];
            if (empty($conversation->first_response_at)) {
                $updateData['first_response_at'] = now();
            }
            if ($senderType === 'ai') {
                $updateData['ai_last_response_at'] = now();
            }
            $conversation->update($updateData);

            // Broadcast Real-time event
            try {
                broadcast(new MessageSent($message))->toOthers();
            } catch (\Throwable $e) {
                // Ignore broadcast error
            }

            return $message;
        });
    }

    /**
     * Evaluate incoming message for human handoff conditions or automatic AI response.
     */
    public function evaluateHandoffOrAiReply(Conversation $conversation, Message $inboundMessage): void
    {
        $body = $inboundMessage->body ?? '';
        if (empty($body)) {
            return;
        }

        // 1. Check Automatic Human Handoff triggers
        $handoffReason = $this->detectHandoffCondition($body, $conversation);
        if ($handoffReason) {
            $this->triggerHumanHandoff($conversation, $handoffReason);
            return;
        }

        // 2. If AI is active (auto mode), generate and send AI response
        if ($conversation->isAiActive()) {
            $this->triggerAiAutoReply($conversation, $inboundMessage);
        }
    }

    /**
     * Detect if message warrants human handoff.
     */
    public function detectHandoffCondition(string $body, Conversation $conversation): ?string
    {
        $lower = strtolower($body);

        // A. Explicit customer request for human
        if (preg_match('/(talk to|speak with|connect me|agent|human|manager|support person|call me|real person|representative)/i', $lower)) {
            return 'Customer requested human';
        }

        // B. Angry / frustrated complaint detected
        if (preg_match('/(angry|frustrated|terrible|worst|scam|fraud|complaint|sue|lawyer|refund now|hate)/i', $lower)) {
            return 'Complaint detected';
        }

        // C. Payment / billing dispute
        if (preg_match('/(charged twice|billing error|unauthorized charge|payment failed multiple times)/i', $lower)) {
            return 'Payment issue';
        }

        // D. Technical escalation
        if (preg_match('/(fatal error|system down|security breach|vulnerability)/i', $lower)) {
            return 'Technical support issue';
        }

        return null;
    }

    /**
     * Trigger Human Handoff: pauses AI, assigns agent, logs timeline, tags contact, and notifies team.
     */
    public function triggerHumanHandoff(
        Conversation $conversation,
        string $reason,
        ?string $customerNotice = null
    ): void {
        DB::transaction(function () use ($conversation, $reason, $customerNotice) {
            $notice = $customerNotice ?? "Certainly. I'm connecting you with a team member who will assist you shortly.";

            // 1. Central Human Handoff: Stops AI, assigns salesperson, creates CrmTask, updates CRM timeline
            $handoffService = app(\App\Modules\Inbox\Services\HumanHandoffService::class);
            $result = $handoffService->executeHandoff($conversation, null, $reason);

            // 2. Add system message in conversation stream
            Message::create([
                'conversation_id' => $conversation->id,
                'contact_id' => $conversation->contact_id,
                'channel' => $conversation->channel ?? 'whatsapp',
                'direction' => 'out',
                'type' => 'system',
                'message_type' => 'system',
                'body' => "AI → Human handoff (Reason: {$reason})",
                'status' => 'delivered',
                'sent_by' => 'system',
                'sender_type' => 'system',
                'sent_at' => now(),
            ]);

            // 3. Send handoff acknowledgment to customer
            $this->sendReply(
                $conversation,
                ['body' => $notice, 'type' => 'text'],
                null,
                'system'
            );

            // 4. Notify workspace agents
            if ($this->notifications && $conversation->workspace) {
                $contactName = $conversation->contact?->full_name ?: 'Customer';
                $this->notifications->notify(
                    $conversation->workspace,
                    'human_handoff',
                    '🚨 Human Handoff Required',
                    "{$contactName} needs assistance. Reason: {$reason}",
                    [
                        'url' => "/app/inbox/{$conversation->uuid}",
                        'conversation_id' => $conversation->id,
                        'reason' => $reason,
                    ],
                    null,
                    'high'
                );
            }
        });
    }

    /**
     * Switch conversation to Human Mode.
     */
    public function switchToHuman(Conversation $conversation, ?User $user = null, string $reason = 'Manual agent takeover'): Conversation
    {
        $conversation->update([
            'ai_mode' => 'human',
            'assigned_to' => 'human',
            'human_takeover_at' => now(),
            'handoff_reason' => $reason,
            'assigned_user_id' => $user?->id ?? $conversation->assigned_user_id,
        ]);

        ConversationActivity::log($conversation, 'human_takeover', [
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'reason' => $reason,
        ]);

        return $conversation;
    }

    /**
     * Enable AI Mode on conversation.
     */
    public function enableAi(Conversation $conversation, string $mode = 'auto'): Conversation
    {
        $conversation->update([
            'ai_mode' => $mode,
            'assigned_to' => 'bot',
            'human_takeover_at' => null,
            'handoff_reason' => null,
        ]);

        ConversationActivity::log($conversation, 'ai_enabled', [
            'mode' => $mode,
        ]);

        return $conversation;
    }

    /**
     * Set AI Mode on conversation (auto, suggested, human, paused).
     */
    public function setAiMode(Conversation $conversation, string $mode): Conversation
    {
        if (in_array($mode, ['human', 'paused'], true)) {
            return $this->switchToHuman($conversation, null, "Switched to {$mode} mode");
        }

        return $this->enableAi($conversation, $mode);
    }

    /**
     * Generate an AI suggested reply without automatically sending it.
     */
    public function generateAiSuggestion(Conversation $conversation, ?string $customPrompt = null): array
    {
        $lastInbound = $conversation->messages()->where('direction', 'in')->latest('id')->first();
        $inboundText = $customPrompt ?: ($lastInbound?->body ?? 'Hello');

        $suggestion = "Thank you for reaching out to Growbridge Connect! How can our team help your business today?";
        $confidence = 85;
        $sentiment = 'Neutral';

        if ($this->llmGateway) {
            try {
                $history = $conversation->messages()->latest('id')->take(6)->get()->reverse();
                $suggestion = $this->llmGateway->generateChatReply($conversation, $history, $inboundText);
                $confidence = 90;
            } catch (\Throwable $e) {
                Log::warning("AI suggestion generation error: " . $e->getMessage());
            }
        }

        return [
            'reply' => $suggestion,
            'confidence' => $confidence,
            'sentiment' => $sentiment,
            'ai_mode' => $conversation->ai_mode,
        ];
    }

    /**
     * Handle incoming delivery receipt / status update from webhook.
     */
    public function handleDeliveryReceipt(
        string $channel,
        string $externalMessageId,
        string $status,
        ?array $raw = null
    ): ?Message {
        $message = Message::where('provider_message_id', $externalMessageId)
            ->orWhere('external_message_id', $externalMessageId)
            ->first();

        if (! $message) {
            return null;
        }

        $message->update([
            'status' => $status,
        ]);

        try {
            broadcast(new MessageStatusUpdated($message))->toOthers();
        } catch (\Throwable $e) {
            // Ignore
        }

        return $message;
    }

    /**
     * Assign team member to conversation.
     */
    public function assignUser(Conversation $conversation, ?int $userId): Conversation
    {
        $conversation->update([
            'assigned_user_id' => $userId,
            'assigned_to' => $userId ? 'human' : $conversation->assigned_to,
        ]);

        return $conversation;
    }

    /**
     * Update conversation status.
     */
    public function updateStatus(Conversation $conversation, string $status): Conversation
    {
        $update = ['status' => $status];
        if ($status === 'resolved') {
            $update['resolved_at'] = now();
        }
        $conversation->update($update);

        return $conversation;
    }

    /**
     * Mark conversation messages as read.
     */
    public function markAsRead(Conversation $conversation): void
    {
        $conversation->update(['unread_count' => 0]);
        $conversation->messages()
            ->where('direction', 'in')
            ->where('status', '!=', 'read')
            ->update(['status' => 'read']);
    }

    /**
     * Contact resolution: matches by phone, email, or external handle.
     */
    private function resolveContact(NormalizedMessage $msg, int $workspaceId): Contact
    {
        $identifier = $msg->senderIdentifier;
        $name = $msg->senderName ?: 'Customer';

        $query = Contact::where('workspace_id', $workspaceId);

        if ($msg->channel === 'email' && filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $contact = $query->where('email', $identifier)->first();
        } elseif (in_array($msg->channel, ['whatsapp', 'phone', 'sms'], true) && $identifier) {
            $contact = $query->where('phone_e164', $identifier)->first();
        } else {
            $contact = $query->where(function ($q) use ($identifier) {
                $q->where('phone_e164', $identifier)
                  ->orWhere('email', $identifier);
            })->first();
        }

        if ($contact) {
            return $contact;
        }

        $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL);
        $nameParts = explode(' ', trim($name), 2);

        return Contact::create([
            'workspace_id' => $workspaceId,
            'first_name' => $nameParts[0] ?? 'New',
            'last_name' => $nameParts[1] ?? 'Customer',
            'phone_e164' => ! $isEmail && $identifier ? $identifier : null,
            'email' => $isEmail ? $identifier : null,
            'lead_score' => 50,
            'lead_score_band' => 'warm',
        ]);
    }

    /**
     * Channel Account resolution.
     */
    private function resolveChannelAccount(
        NormalizedMessage $msg,
        int $workspaceId,
        ?int $channelAccountId = null
    ): ChannelAccount {
        if ($channelAccountId) {
            $acc = ChannelAccount::find($channelAccountId);
            if ($acc) return $acc;
        }

        $acc = ChannelAccount::where('workspace_id', $workspaceId)
            ->where('channel', $msg->channel)
            ->where('status', 'active')
            ->first();

        if ($acc) {
            return $acc;
        }

        return ChannelAccount::firstOrCreate([
            'workspace_id' => $workspaceId,
            'channel' => $msg->channel,
        ], [
            'display_name' => ucfirst($msg->channel) . ' Channel',
            'status' => 'active',
        ]);
    }

    /**
     * Conversation resolution: finds existing active conversation or creates one.
     */
    private function resolveConversation(
        NormalizedMessage $msg,
        int $workspaceId,
        Contact $contact,
        ChannelAccount $channelAccount
    ): Conversation {
        $conv = Conversation::where('workspace_id', $workspaceId)
            ->where('contact_id', $contact->id)
            ->where('channel_account_id', $channelAccount->id)
            ->latest('id')
            ->first();

        if ($conv) {
            return $conv;
        }

        return Conversation::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $workspaceId,
            'contact_id' => $contact->id,
            'channel_account_id' => $channelAccount->id,
            'channel' => $msg->channel,
            'status' => 'open',
            'ai_mode' => 'auto',
            'assigned_to' => 'bot',
            'unread_count' => 0,
            'last_message_at' => now(),
        ]);
    }

    /**
     * Generate & dispatch AI Auto-reply when AI Mode is active.
     */
    private function triggerAiAutoReply(Conversation $conversation, Message $inboundMessage): ?Message
    {
        // Safety verification: abort if human has taken over or conversation was closed
        if ($conversation->isHumanActive() || $conversation->isPaused() || $conversation->status === 'resolved') {
            return null;
        }

        $body = $inboundMessage->body;
        if (empty($body)) {
            return null;
        }

        $replyText = "Hello! Thanks for reaching out to Growbridge Connect. Our AI assistant is ready to help you.";
        $confidence = 85;

        if ($this->llmGateway) {
            try {
                $history = $conversation->messages()->latest('id')->take(6)->get()->reverse();
                $replyText = $this->llmGateway->generateChatReply($conversation, $history, $body);
            } catch (\Throwable $e) {
                Log::warning("AI Auto-Reply generation failed: " . $e->getMessage());
            }
        }

        // Low confidence check
        if ($confidence < 60) {
            $this->triggerHumanHandoff($conversation, 'Low AI confidence');
            return null;
        }

        return $this->sendReply(
            $conversation,
            ['body' => $replyText, 'type' => 'text'],
            null,
            'ai'
        );
    }
}
