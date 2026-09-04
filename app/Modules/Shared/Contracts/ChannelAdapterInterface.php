<?php

namespace App\Modules\Shared\Contracts;

use App\Modules\Shared\DTOs\NormalizedMessage;
use App\Modules\Shared\Models\Message;
use Illuminate\Http\Request;

interface ChannelAdapterInterface
{
    /**
     * Get the standardized channel name ('whatsapp', 'instagram', 'messenger', 'email', 'phone')
     */
    public function getChannelName(): string;

    /**
     * Normalize an incoming webhook payload into a standardized NormalizedMessage
     */
    public function receive(array|Request $payload, array $context = []): NormalizedMessage;

    /**
     * Send an outbound message to the external provider. Returns provider message ID.
     */
    public function send(Message $message): string;

    /**
     * Fetch status for an external message ('queued', 'sent', 'delivered', 'read', 'failed')
     */
    public function getStatus(string $externalMessageId): string;

    /**
     * Process an inbound webhook request directly
     */
    public function handleWebhook(Request $request): array;

    /**
     * Normalize raw provider payload into NormalizedMessage
     */
    public function normalizeMessage(array $rawPayload): NormalizedMessage;
}
