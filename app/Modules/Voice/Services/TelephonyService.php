<?php

namespace App\Modules\Voice\Services;

use App\Models\Workspace;
use App\Modules\Integrations\Models\IntegrationConfig;
use App\Modules\Integrations\Services\CredentialResolver;
use App\Modules\Shared\Models\Contact;
use App\Modules\Voice\Contracts\TelephonyServiceInterface;
use App\Modules\Voice\Contracts\VoiceDriverInterface;
use App\Modules\Voice\Jobs\GenerateVoiceCallSummaryJob;
use App\Modules\Voice\Models\TelephonyApiLog;
use App\Modules\Voice\Models\TelephonyWebhookLog;
use App\Modules\Voice\Models\VoiceAgent;
use App\Modules\Voice\Models\VoiceCall;
use App\Modules\Voice\Services\Drivers\ExotelVoiceDriver;
use App\Modules\Voice\Services\Drivers\PlivoVoiceDriver;
use App\Modules\Voice\Services\Drivers\TwilioVoiceDriver;
use Illuminate\Support\Facades\Log;

class TelephonyService implements TelephonyServiceInterface
{
    public function driver(string $provider, int $workspaceId, array $overrideCredentials = []): VoiceDriverInterface
    {
        $creds = ! empty($overrideCredentials) ? $overrideCredentials : $this->resolveCredentials($provider, $workspaceId);

        return match ($provider) {
            'exotel' => new ExotelVoiceDriver(
                apiKey: $creds['api_key'] ?? config('services.exotel.key'),
                apiToken: $creds['api_token'] ?? config('services.exotel.token'),
                accountSid: $creds['account_sid'] ?? config('services.exotel.sid'),
                subdomain: $creds['subdomain'] ?? config('services.exotel.subdomain', 'api.exotel.com')
            ),
            'plivo' => new PlivoVoiceDriver(
                authId: $creds['auth_id'] ?? config('services.plivo.auth_id'),
                authToken: $creds['auth_token'] ?? config('services.plivo.auth_token')
            ),
            default => new TwilioVoiceDriver(
                accountSid: $creds['account_sid'] ?? config('services.twilio.sid'),
                authToken: $creds['auth_token'] ?? config('services.twilio.token'),
                defaultFromNumber: $creds['from_number'] ?? config('services.twilio.from')
            ),
        };
    }

    public function testConnection(string $provider, int $workspaceId, array $credentials = []): array
    {
        $startTime = microtime(true);
        $driver = $this->driver($provider, $workspaceId, $credentials);
        $result = $driver->testConnection();
        $durationMs = (int) ((microtime(true) - $startTime) * 1000);

        TelephonyApiLog::create([
            'workspace_id' => $workspaceId,
            'provider' => $provider,
            'endpoint' => 'test_connection',
            'http_method' => 'GET',
            'status_code' => $result['success'] ? 200 : 400,
            'response_time_ms' => $durationMs,
            'success' => $result['success'],
            'request_payload' => ['provider' => $provider],
            'response_body' => $result,
        ]);

        return $result;
    }

    public function initiateCall(VoiceAgent $agent, string $toPhone, ?int $contactId = null): VoiceCall
    {
        $workspaceId = $agent->workspace_id;
        $provider = $agent->provider ?? 'twilio';

        if (! $contactId) {
            $contact = Contact::where('workspace_id', $workspaceId)
                ->where('phone_e164', $toPhone)
                ->first();
            $contactId = $contact?->id;
        }

        $call = VoiceCall::create([
            'workspace_id' => $workspaceId,
            'voice_agent_id' => $agent->id,
            'contact_id' => $contactId,
            'direction' => 'outbound',
            'provider' => $provider,
            'from_number' => $agent->phone_number,
            'to_number' => $toPhone,
            'status' => 'queued',
            'started_at' => now(),
        ]);

        $startTime = microtime(true);

        try {
            $driver = $this->driver($provider, $workspaceId);
            $dispatchResult = $driver->initiateOutboundCall($agent, $call, $toPhone);
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $call->update([
                'provider_call_id' => $dispatchResult['provider_call_id'] ?? null,
                'status' => $dispatchResult['status'] ?? 'initiated',
            ]);

            $agent->increment('total_calls');

            TelephonyApiLog::create([
                'workspace_id' => $workspaceId,
                'provider' => $provider,
                'endpoint' => 'outbound_call',
                'http_method' => 'POST',
                'status_code' => 200,
                'response_time_ms' => $durationMs,
                'success' => true,
                'request_payload' => ['to' => $toPhone, 'agent' => $agent->name],
                'response_body' => $dispatchResult,
            ]);

            return $call;
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $call->update([
                'status' => 'failed',
                'error_json' => ['error' => $e->getMessage()],
                'ended_at' => now(),
            ]);

            TelephonyApiLog::create([
                'workspace_id' => $workspaceId,
                'provider' => $provider,
                'endpoint' => 'outbound_call',
                'http_method' => 'POST',
                'status_code' => 500,
                'response_time_ms' => $durationMs,
                'success' => false,
                'request_payload' => ['to' => $toPhone, 'agent' => $agent->name],
                'response_body' => ['error' => $e->getMessage()],
            ]);

            throw $e;
        }
    }

    public function handleWebhook(string $provider, string $callUuid, array $payload, array $headers = []): mixed
    {
        $call = VoiceCall::with('agent')->where('uuid', $callUuid)->first();
        $workspaceId = $call?->workspace_id ?? 1;

        $driver = $this->driver($provider, $workspaceId);
        $isValid = $driver->verifyWebhookSignature($headers, $payload);

        $webhookLog = TelephonyWebhookLog::create([
            'workspace_id' => $workspaceId,
            'provider' => $provider,
            'event_name' => $payload['event'] ?? $payload['status'] ?? 'call.event',
            'call_id' => $callUuid,
            'payload_json' => $payload,
            'status' => $isValid ? 'received' : 'failed',
            'error_message' => $isValid ? null : 'Webhook signature verification failed',
            'received_at' => now(),
        ]);

        if (! $isValid) {
            Log::warning('telephony.webhook_unauthorized', [
                'provider' => $provider,
                'call_uuid' => $callUuid,
            ]);

            return response('Unauthorized Webhook Signature', 403);
        }

        if (! $call) {
            return response('Call not found', 404);
        }

        $statusData = $driver->parseWebhookStatus($payload);

        $call->update([
            'status' => $statusData['status'],
            'duration_sec' => $statusData['duration'] ?: $call->duration_sec,
            'recording_url' => $statusData['recording_url'] ?: $call->recording_url,
            'transcript' => $statusData['transcript'] ?: $call->transcript,
            'ended_at' => in_array($statusData['status'], ['completed', 'failed', 'busy', 'no-answer', 'cancelled']) ? now() : $call->ended_at,
        ]);

        $webhookLog->update([
            'status' => 'processed',
            'processed_at' => now(),
        ]);

        if ($statusData['status'] === 'completed') {
            $call->agent?->increment('successful_calls');
            dispatch(new GenerateVoiceCallSummaryJob($call->id))->onQueue('automation');
        }

        if (request()->isMethod('POST') && $call->agent) {
            return $driver->generateCallResponse($call->agent, $call, $payload);
        }

        return response('OK', 200);
    }

    private function resolveCredentials(string $provider, int $workspaceId): array
    {
        if ($provider === 'twilio') {
            $acc = \App\Models\TwilioAccount::where('workspace_id', $workspaceId)->first();
            if ($acc && ! empty($acc->twilio_account_sid)) {
                return [
                    'account_sid' => $acc->twilio_account_sid,
                    'auth_token' => $acc->auth_token,
                    'from_number' => $acc->metadata['from_number'] ?? null,
                ];
            }
        }

        $config = IntegrationConfig::where('workspace_id', $workspaceId)
            ->where('provider', $provider)
            ->first();

        if ($config && ! empty($config->credentials)) {
            return (array) $config->credentials;
        }

        // Global admin fallback
        $globalConfig = IntegrationConfig::whereNull('workspace_id')
            ->where('provider', $provider)
            ->first();

        if ($globalConfig && ! empty($globalConfig->credentials)) {
            return (array) $globalConfig->credentials;
        }

        return [];
    }
}
