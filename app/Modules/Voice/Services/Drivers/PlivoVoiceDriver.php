<?php

namespace App\Modules\Voice\Services\Drivers;

use App\Modules\Voice\Contracts\VoiceDriverInterface;
use App\Modules\Voice\Models\VoiceAgent;
use App\Modules\Voice\Models\VoiceCall;
use Illuminate\Support\Facades\Http;

class PlivoVoiceDriver implements VoiceDriverInterface
{
    public function __construct(
        private readonly ?string $authId = null,
        private readonly ?string $authToken = null
    ) {}

    public function testConnection(): array
    {
        $authId = $this->authId ?? config('services.plivo.auth_id');
        $authToken = $this->authToken ?? config('services.plivo.auth_token');

        if (! $authId || ! $authToken) {
            return ['success' => false, 'message' => 'Plivo Auth ID or Token is not configured.'];
        }

        try {
            $resp = Http::withBasicAuth($authId, $authToken)->get("https://api.plivo.com/v1/Account/{$authId}/");
            return [
                'success' => $resp->successful(),
                'message' => $resp->successful() ? 'Connected successfully to Plivo.' : 'Plivo authentication failed.',
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function verifyWebhookSignature(array $headers, array $payload): bool
    {
        return true;
    }

    public function initiateOutboundCall(VoiceAgent $agent, VoiceCall $call, string $toPhone): array
    {
        $authId = $this->authId ?? config('services.plivo.auth_id');
        $authToken = $this->authToken ?? config('services.plivo.auth_token');
        $from = $agent->phone_number ?? config('services.plivo.from');

        if (! $authId || ! $authToken || ! $from) {
            throw new \RuntimeException('Plivo credentials or caller ID not configured for Voice Agent.');
        }

        $answerUrl = route('webhooks.voice.handle', ['provider' => 'plivo', 'call_uuid' => $call->uuid]);

        $response = Http::withBasicAuth($authId, $authToken)
            ->post("https://api.plivo.com/v1/Account/{$authId}/Call/", [
                'to' => $toPhone,
                'from' => $from,
                'answer_url' => $answerUrl,
                'answer_method' => 'POST',
                'record' => 'true',
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Plivo Call Initiation Failed: '.$response->body());
        }

        $data = $response->json();

        return [
            'provider_call_id' => $data['request_uuid'] ?? '',
            'status' => 'initiated',
        ];
    }

    public function generateCallResponse(VoiceAgent $agent, VoiceCall $call, array $webhookData): mixed
    {
        $greeting = $agent->greeting_message ?: "Hello, this is Growbridge Connect Voice Assistant.";
        $language = $agent->language === 'hi-IN' ? 'hi-IN' : 'en-US';

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<Response>';
        $xml .= '<Speak language="'.$language.'">'.htmlspecialchars($greeting).'</Speak>';
        $xml .= '</Response>';

        return response($xml, 200, ['Content-Type' => 'text/xml']);
    }

    public function parseWebhookStatus(array $payload): array
    {
        $status = $payload['CallStatus'] ?? $payload['Event'] ?? 'completed';
        $duration = (int) ($payload['Duration'] ?? $payload['BillDuration'] ?? 0);
        $recordingUrl = $payload['RecordUrl'] ?? null;

        return [
            'status' => match (strtolower($status)) {
                'ringing' => 'ringing',
                'in-progress', 'answered' => 'in-progress',
                'completed', 'hangup' => 'completed',
                'busy' => 'busy',
                'failed' => 'failed',
                'no-answer', 'timeout' => 'no-answer',
                default => 'completed',
            },
            'duration' => $duration,
            'recording_url' => $recordingUrl,
            'transcript' => $payload['Transcription'] ?? null,
        ];
    }
}
