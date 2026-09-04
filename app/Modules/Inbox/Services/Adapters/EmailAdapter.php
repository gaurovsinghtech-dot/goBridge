<?php

namespace App\Modules\Inbox\Services\Adapters;

use App\Modules\Inbox\Services\EmailDriver;
use App\Modules\Shared\Contracts\ChannelAdapterInterface;
use App\Modules\Shared\DTOs\NormalizedMessage;
use App\Modules\Shared\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EmailAdapter implements ChannelAdapterInterface
{
    public function __construct(
        private readonly EmailDriver $driver,
    ) {}

    public function getChannelName(): string
    {
        return 'email';
    }

    public function receive(array|Request $payload, array $context = []): NormalizedMessage
    {
        $raw = $payload instanceof Request ? $payload->all() : $payload;
        return $this->normalizeMessage($raw);
    }

    public function send(Message $message): string
    {
        return $this->driver->send($message);
    }

    public function getStatus(string $externalMessageId): string
    {
        return 'sent';
    }

    public function handleWebhook(Request $request): array
    {
        return $this->driver->receiveWebhook($request);
    }

    public function normalizeMessage(array $rawPayload): NormalizedMessage
    {
        $senderEmail = $rawPayload['from_email'] ?? ($rawPayload['from'] ?? ($rawPayload['sender'] ?? null));
        $senderName = $rawPayload['from_name'] ?? ($rawPayload['sender_name'] ?? null);
        $body = $rawPayload['body'] ?? ($rawPayload['text'] ?? ($rawPayload['html'] ?? ''));
        $externalId = $rawPayload['message_id'] ?? ($rawPayload['id'] ?? ('email_' . Str::uuid()));

        return NormalizedMessage::make(
            channel: 'email',
            direction: 'inbound',
            senderType: 'customer',
            messageType: 'text',
            body: strip_tags($body),
            mediaUrl: $rawPayload['attachment_url'] ?? null,
            externalMessageId: $externalId,
            status: 'delivered',
            senderIdentifier: $senderEmail,
            senderName: $senderName,
            recipientIdentifier: $rawPayload['to'] ?? null,
            channelAccountId: null,
            metadata: $rawPayload,
            timestamp: now(),
        );
    }
}
