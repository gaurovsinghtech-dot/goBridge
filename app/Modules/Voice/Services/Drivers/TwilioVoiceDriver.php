<?php

namespace App\Modules\Voice\Services\Drivers;

use App\Modules\Voice\Contracts\VoiceDriverInterface;
use App\Modules\Voice\Models\VoiceAgent;
use App\Modules\Voice\Models\VoiceCall;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TwilioVoiceDriver implements VoiceDriverInterface
{
    public function __construct(
        private readonly ?string $accountSid = null,
        private readonly ?string $authToken = null,
        private readonly ?string $defaultFromNumber = null
    ) {}

    public function testConnection(): array
    {
        $sid = $this->accountSid ?? config('services.twilio.sid');
        $token = $this->authToken ?? config('services.twilio.token');

        if (! $sid || ! $token) {
            return ['success' => false, 'message' => 'Twilio SID or Token is not configured.'];
        }

        try {
            $resp = Http::withBasicAuth($sid, $token)->get("https://api.twilio.com/2010-04-01/Accounts/{$sid}.json");
            return [
                'success' => $resp->successful(),
                'message' => $resp->successful() ? 'Connected successfully to Twilio.' : 'Twilio authentication failed.',
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
        $sid = $this->accountSid ?? config('services.twilio.sid');
        $token = $this->authToken ?? config('services.twilio.token');
        $from = $call->from_number ?? $agent->phone_number ?? $this->defaultFromNumber ?? config('services.twilio.from');

        if (! $sid || ! $token || ! $from) {
            throw new \RuntimeException('Twilio credentials or caller ID not configured for Voice Agent.');
        }

        $webhookUrl = route('webhooks.voice.handle', ['provider' => 'twilio', 'call_uuid' => $call->uuid]);

        $response = Http::withBasicAuth($sid, $token)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Calls.json", [
                'To' => $toPhone,
                'From' => $from,
                'Url' => $webhookUrl,
                'StatusCallback' => $webhookUrl,
                'StatusCallbackEvent' => ['initiated', 'ringing', 'answered', 'completed'],
                'Record' => 'true',
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Twilio Call Initiation Failed: '.$response->body());
        }

        $data = $response->json();

        return [
            'provider_call_id' => $data['sid'] ?? '',
            'status' => $this->mapTwilioStatus($data['status'] ?? 'queued'),
        ];
    }

    public function generateCallResponse(VoiceAgent $agent, VoiceCall $call, array $webhookData): mixed
    {
        $greeting = $agent->greeting_message ?: "Hello! I am your AI assistant from Growbridge Connect. How can I help you today?";
        $language = $agent->language === 'hi-IN' ? 'hi-IN' : 'en-US';
        $voice = $agent->voice_id ?: 'Polly.Aditi';

        // Returns TwiML response for voice rendering
        $twiml = '<?xml version="1.0" encoding="UTF-8"?>';
        $twiml .= '<Response>';
        $twiml .= '<Say voice="'.$voice.'" language="'.$language.'">'.htmlspecialchars($greeting).'</Say>';
        
        if (! empty($agent->human_transfer_number)) {
            $twiml .= '<Gather input="speech dtmf" timeout="5" action="'.route('webhooks.voice.gather', ['provider' => 'twilio', 'call_uuid' => $call->uuid]).'">';
            $twiml .= '<Say voice="'.$voice.'" language="'.$language.'">Please speak or press 0 to transfer to an agent.</Say>';
            $twiml .= '</Gather>';
        }

        $twiml .= '</Response>';

        return response($twiml, 200, ['Content-Type' => 'text/xml']);
    }

    public function parseWebhookStatus(array $payload): array
    {
        $twilioStatus = $payload['CallStatus'] ?? $payload['status'] ?? 'completed';
        $duration = (int) ($payload['CallDuration'] ?? $payload['duration'] ?? 0);
        $recordingUrl = $payload['RecordingUrl'] ?? null;
        $transcript = $payload['TranscriptionText'] ?? null;

        return [
            'status' => $this->mapTwilioStatus($twilioStatus),
            'duration' => $duration,
            'recording_url' => $recordingUrl,
            'transcript' => $transcript,
        ];
    }

    private function mapTwilioStatus(string $status): string
    {
        return match (strtolower($status)) {
            'queued' => 'queued',
            'initiated' => 'initiated',
            'ringing' => 'ringing',
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
