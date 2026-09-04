<?php

namespace App\Modules\Voice\Contracts;

use App\Modules\Voice\Models\VoiceAgent;
use App\Modules\Voice\Models\VoiceCall;

interface TelephonyServiceInterface
{
    /**
     * Resolve the telephony driver for a workspace and provider.
     */
    public function driver(string $provider, int $workspaceId): VoiceDriverInterface;

    /**
     * Test connection to a telephony provider.
     */
    public function testConnection(string $provider, int $workspaceId, array $credentials = []): array;

    /**
     * Initiate an outbound call for an agent and destination phone.
     */
    public function initiateCall(VoiceAgent $agent, string $toPhone, ?int $contactId = null): VoiceCall;

    /**
     * Process an incoming telephony webhook payload, log the event, and update call status.
     */
    public function handleWebhook(string $provider, string $callUuid, array $payload, array $headers = []): mixed;
}
