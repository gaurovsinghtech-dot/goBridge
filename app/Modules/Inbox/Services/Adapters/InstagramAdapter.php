<?php

namespace App\Modules\Inbox\Services\Adapters;

use App\Modules\Inbox\Services\InstagramDriver;
use App\Modules\Shared\Contracts\ChannelAdapterInterface;
use App\Modules\Shared\DTOs\NormalizedMessage;
use App\Modules\Shared\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class InstagramAdapter implements ChannelAdapterInterface
{
    public function __construct(
        private readonly InstagramDriver $driver,
    ) {}

    public function getChannelName(): string
    {
        return 'instagram';
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
        return 'delivered';
    }

    public function handleWebhook(Request $request): array
    {
        return $this->driver->receiveWebhook($request);
    }

    public function normalizeMessage(array $rawPayload): NormalizedMessage
    {
        $entry = $rawPayload['entry'][0] ?? [];
        $messaging = $entry['messaging'][0] ?? $rawPayload;

        $senderId = $messaging['sender']['id'] ?? ($rawPayload['sender_id'] ?? ($rawPayload['from'] ?? null));
        $msg = $messaging['message'] ?? $rawPayload;
        $body = $msg['text'] ?? ($rawPayload['body'] ?? '');
        $externalId = $msg['mid'] ?? ($rawPayload['id'] ?? null);

        $attachments = $msg['attachments'] ?? [];
        $mediaUrl = null;
        $messageType = 'text';

        if (! empty($attachments)) {
            $first = $attachments[0];
            $messageType = $first['type'] ?? 'image';
            $mediaUrl = $first['payload']['url'] ?? null;
            if (empty($body)) {
                $body = "({$messageType})";
            }
        }

        $timestamp = isset($messaging['timestamp'])
            ? Carbon::createFromTimestampMs((int) $messaging['timestamp'])
            : now();

        return NormalizedMessage::make(
            channel: 'instagram',
            direction: 'inbound',
            senderType: 'customer',
            messageType: $messageType,
            body: $body,
            mediaUrl: $mediaUrl,
            externalMessageId: $externalId,
            status: 'delivered',
            senderIdentifier: $senderId,
            senderName: $rawPayload['sender_name'] ?? null,
            recipientIdentifier: $messaging['recipient']['id'] ?? null,
            channelAccountId: null,
            metadata: $messaging,
            timestamp: $timestamp,
        );
    }
}
