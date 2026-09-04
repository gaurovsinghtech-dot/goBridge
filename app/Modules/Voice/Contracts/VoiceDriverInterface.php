<?php

namespace App\Modules\Voice\Contracts;

use App\Modules\Voice\Models\VoiceAgent;
use App\Modules\Voice\Models\VoiceCall;

interface VoiceDriverInterface
{
    /**
     * Test connection credentials against the telephony provider.
     *
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array;

    /**
     * Initiate an outbound AI call to a destination phone number.
     *
     * @return array{provider_call_id: string, status: string}
     */
    public function initiateOutboundCall(VoiceAgent $agent, VoiceCall $call, string $toPhone): array;

    /**
     * Generate response instructions (IVR / XML / JSON)
     * for an inbound or ongoing call connection.
     */
    public function generateCallResponse(VoiceAgent $agent, VoiceCall $call, array $webhookData): mixed;

    /**
     * Parse webhook status update from provider.
     *
     * @return array{status: string, duration: int, recording_url: ?string, transcript: ?string}
     */
    public function parseWebhookStatus(array $payload): array;

    /**
     * Verify incoming webhook authenticity (signature / auth token).
     */
    public function verifyWebhookSignature(array $headers, array $payload): bool;
}
