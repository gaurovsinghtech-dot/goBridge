<?php

namespace App\Modules\Shared\DTOs;

use Illuminate\Support\Carbon;

class NormalizedMessage
{
    public function __construct(
        public string $channel,
        public string $direction, // 'inbound' | 'outbound'
        public string $senderType, // 'customer' | 'human' | 'ai' | 'system'
        public string $messageType, // 'text' | 'image' | 'video' | 'audio' | 'document' | 'template' | 'call' | 'interactive'
        public ?string $body = null,
        public ?string $mediaUrl = null,
        public ?string $externalMessageId = null,
        public string $status = 'received', // 'queued' | 'sent' | 'delivered' | 'read' | 'failed' | 'received'
        public ?string $senderIdentifier = null, // e.g. phone number, email, IG handle, PSID
        public ?string $senderName = null,
        public ?string $recipientIdentifier = null,
        public ?int $channelAccountId = null,
        public array $metadata = [],
        public ?Carbon $timestamp = null,
    ) {
        $this->timestamp = $timestamp ?? now();
    }

    public static function make(
        string $channel,
        string $direction,
        string $senderType,
        string $messageType,
        ?string $body = null,
        ?string $mediaUrl = null,
        ?string $externalMessageId = null,
        string $status = 'received',
        ?string $senderIdentifier = null,
        ?string $senderName = null,
        ?string $recipientIdentifier = null,
        ?int $channelAccountId = null,
        array $metadata = [],
        ?Carbon $timestamp = null,
    ): self {
        return new self(
            channel: $channel,
            direction: $direction,
            senderType: $senderType,
            messageType: $messageType,
            body: $body,
            mediaUrl: $mediaUrl,
            externalMessageId: $externalMessageId,
            status: $status,
            senderIdentifier: $senderIdentifier,
            senderName: $senderName,
            recipientIdentifier: $recipientIdentifier,
            channelAccountId: $channelAccountId,
            metadata: $metadata,
            timestamp: $timestamp,
        );
    }

    public function toArray(): array
    {
        return [
            'channel' => $this->channel,
            'direction' => $this->direction,
            'sender_type' => $this->senderType,
            'message_type' => $this->messageType,
            'body' => $this->body,
            'media_url' => $this->mediaUrl,
            'external_message_id' => $this->externalMessageId,
            'status' => $this->status,
            'sender_identifier' => $this->senderIdentifier,
            'sender_name' => $this->senderName,
            'recipient_identifier' => $this->recipientIdentifier,
            'channel_account_id' => $this->channelAccountId,
            'metadata' => $this->metadata,
            'timestamp' => $this->timestamp?->toIso8601String(),
        ];
    }
}
