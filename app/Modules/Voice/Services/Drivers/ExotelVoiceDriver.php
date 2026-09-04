<?php

namespace App\Modules\Voice\Services\Drivers;

use App\Modules\Voice\Contracts\VoiceDriverInterface;
use App\Modules\Voice\Models\VoiceAgent;
use App\Modules\Voice\Models\VoiceCall;
use Illuminate\Support\Facades\Http;

class ExotelVoiceDriver implements VoiceDriverInterface
{
    public function __construct(
        private readonly ?string $apiKey = null,
        private readonly ?string $apiToken = null,
        private readonly ?string $accountSid = null,
        private readonly ?string $subdomain = 'api.exotel.com'
    ) {}

    public function testConnection(): array
    {
        $key = $this->apiKey ?? config('services.exotel.key');
        $token = $this->apiToken ?? config('services.exotel.token');
        $sid = $this->accountSid ?? config('services.exotel.sid');

        if (! $key || ! $token || ! $sid) {
            return ['success' => false, 'message' => 'Exotel API Key, Token, or SID is not configured.'];
        }

        try {
            $resp = Http::withBasicAuth($key, $token)->get("https://{$this->subdomain}/v1/Accounts/{$sid}.json");
            return [
                'success' => $resp->successful(),
                'message' => $resp->successful() ? 'Connected successfully to Exotel.' : 'Exotel authentication failed.',
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
        $key = $this->apiKey ?? config('services.exotel.key');
        $token = $this->apiToken ?? config('services.exotel.token');
        $sid = $this->accountSid ?? config('services.exotel.sid');
        $callerId = $agent->phone_number ?? config('services.exotel.caller_id');

        if (! $key || ! $token || ! $sid || ! $callerId) {
            throw new \RuntimeException('Exotel credentials or caller ID not configured for Voice Agent.');
        }

        $statusCallback = route('webhooks.voice.handle', ['provider' => 'exotel', 'call_uuid' => $call->uuid]);

        $response = Http::withBasicAuth($key, $token)
            ->asForm()
            ->post("https://{$this->subdomain}/v1/Accounts/{$sid}/Calls/connect.json", [
                'From' => $toPhone,
                'To' => $callerId,
                'CallerId' => $callerId,
                'StatusCallback' => $statusCallback,
                'Record' => 'true',
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Exotel Call Initiation Failed: '.$response->body());
        }

        $data = $response->json('Call') ?? [];

        return [
            'provider_call_id' => $data['Sid'] ?? '',
            'status' => $this->mapExotelStatus($data['Status'] ?? 'in-progress'),
        ];
    }

    public function generateCallResponse(VoiceAgent $agent, VoiceCall $call, array $webhookData): mixed
    {
        $greeting = $agent->greeting_message ?: "Namaste! Growbridge Connect Voice Assistant mein aapka swagat hai.";

        // Exotel returns JSON flow instructions or passthru text
        return response()->json([
            'select' => [
                'prompt' => $greeting,
                'timeout' => 5,
                'action' => route('webhooks.voice.gather', ['provider' => 'exotel', 'call_uuid' => $call->uuid]),
            ],
        ]);
    }

    public function parseWebhookStatus(array $payload): array
    {
        $status = $payload['Status'] ?? $payload['CallStatus'] ?? 'completed';
        $duration = (int) ($payload['Duration'] ?? $payload['DialCallDuration'] ?? 0);
        $recordingUrl = $payload['RecordingUrl'] ?? null;
        $transcript = $payload['Transcript'] ?? null;

        return [
            'status' => $this->mapExotelStatus($status),
            'duration' => $duration,
            'recording_url' => $recordingUrl,
            'transcript' => $transcript,
        ];
    }

    private function mapExotelStatus(string $status): string
    {
        return match (strtolower($status)) {
            'queued' => 'queued',
            'in-progress' => 'in-progress',
            'completed' => 'completed',
            'busy' => 'busy',
            'failed' => 'failed',
            'no-answer' => 'no-answer',
            'canceled' => 'cancelled',
            default => 'completed',
        };
    }
}
