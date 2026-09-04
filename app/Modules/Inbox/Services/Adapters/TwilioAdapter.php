<?php

namespace App\Modules\Inbox\Services\Adapters;

use App\Modules\Shared\Contracts\ChannelAdapterInterface;
use App\Modules\Shared\DTOs\NormalizedMessage;
use App\Modules\Shared\Models\Message;
use App\Modules\Voice\Services\TwilioProvisioningService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TwilioAdapter implements ChannelAdapterInterface
{
    public function __construct(
        private readonly ?TwilioProvisioningService $twilio = null,
    ) {}

    public function getChannelName(): string
    {
        return 'phone';
    }

    public function receive(array|Request $payload, array $context = []): NormalizedMessage
    {
        $raw = $payload instanceof Request ? $payload->all() : $payload;
        return $this->normalizeMessage($raw);
    }

    public function send(Message $message): string
    {
        // Outbound SMS or Call Trigger
        $body = $message->body ?? '';
        $providerId = 'SM' . Str::random(32);

        if ($this->twilio) {
            try {
                $contact = $message->conversation?->contact;
                if ($contact?->phone_e164) {
                    $res = $this->twilio->sendSms(
                        $message->conversation->workspace_id ?? 1,
                        $contact->phone_e164,
                        $body
                    );
                    return $res['sid'] ?? $providerId;
                }
            } catch (\Throwable $e) {
                // Fallback for simulation / mock
            }
        }

        return $providerId;
    }

    public function getStatus(string $externalMessageId): string
    {
        return 'delivered';
    }

    public function handleWebhook(Request $request): array
    {
        $normalized = $this->normalizeMessage($request->all());
        return [
            'status' => 'success',
            'channel' => 'phone',
            'normalized' => $normalized->toArray(),
        ];
    }

    public function normalizeMessage(array $rawPayload): NormalizedMessage
    {
        // Twilio SMS or Call Webhook Format
        $from = $rawPayload['From'] ?? ($rawPayload['from'] ?? ($rawPayload['caller'] ?? null));
        $to = $rawPayload['To'] ?? ($rawPayload['to'] ?? null);
        $body = $rawPayload['Body'] ?? ($rawPayload['body'] ?? ($rawPayload['SpeechResult'] ?? ''));
        $externalId = $rawPayload['MessageSid'] ?? ($rawPayload['CallSid'] ?? ($rawPayload['sid'] ?? ('phone_' . Str::uuid())));
        $isCall = isset($rawPayload['CallSid']) || isset($rawPayload['SpeechResult']) || ($rawPayload['type'] ?? '') === 'call';

        $mediaUrl = null;
        $numMedia = (int) ($rawPayload['NumMedia'] ?? 0);
        if ($numMedia > 0) {
            $mediaUrl = $rawPayload['MediaUrl0'] ?? null;
        }

        return NormalizedMessage::make(
            channel: 'phone',
            direction: 'inbound',
            senderType: 'customer',
            messageType: $isCall ? 'call' : ($mediaUrl ? 'image' : 'text'),
            body: $body ?: ($isCall ? '☎ Voice Call' : ''),
            mediaUrl: $mediaUrl,
            externalMessageId: $externalId,
            status: 'delivered',
            senderIdentifier: $from,
            senderName: $rawPayload['CallerName'] ?? null,
            recipientIdentifier: $to,
            channelAccountId: null,
            metadata: $rawPayload,
            timestamp: now(),
        );
    }
}
