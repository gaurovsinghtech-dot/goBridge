<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PhoneNumber;
use App\Models\Workspace;
use App\Modules\Voice\Models\VoiceCall;
use App\Modules\Voice\Models\VoiceAgent;
use App\Modules\Shared\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TwilioWebhookController extends Controller
{
    /**
     * Handle incoming Twilio Voice calls
     * POST /api/v1/webhooks/twilio/voice
     */
    public function handleVoice(Request $request): Response
    {
        $to = $request->input('To');
        $from = $request->input('From');
        $callSid = $request->input('CallSid');

        Log::info("Twilio Voice Inbound Call received: To={$to}, From={$from}, CallSid={$callSid}");

        // Normalize number and find associated workspace & agent
        $cleanTo = preg_replace('/[^0-9+]/', '', $to ?? '');
        $phoneRecord = PhoneNumber::where('status', 'active')
            ->where(function ($q) use ($cleanTo, $to) {
                $q->where('phone_number', $cleanTo)
                  ->orWhere('phone_number', $to)
                  ->orWhere('phone_number', 'LIKE', '%' . substr($cleanTo, -10));
            })
            ->with(['workspace', 'assignedAgent'])
            ->first();

        $workspaceId = $phoneRecord?->workspace_id ?? 1;
        $agent = $phoneRecord?->assignedAgent;
        $agentName = $agent?->name ?? 'AI Voice Assistant';
        $greeting = $agent?->greeting_message ?? "Hello! Thanks for reaching out. I am your {$agentName}. How may I help you today?";

        // Find or create Contact in CRM
        $contact = null;
        if ($from) {
            $contact = Contact::firstOrCreate(
                ['workspace_id' => $workspaceId, 'phone' => $from],
                ['name' => "Caller {$from}", 'source' => 'phone_call']
            );
        }

        // Record Voice Call in Database
        if ($callSid) {
            VoiceCall::updateOrCreate(
                ['provider_call_id' => $callSid],
                [
                    'workspace_id' => $workspaceId,
                    'phone_number_id' => $phoneRecord?->id,
                    'voice_agent_id' => $agent?->id,
                    'assigned_ai_agent_id' => $agent?->id,
                    'contact_id' => $contact?->id,
                    'direction' => 'inbound',
                    'provider' => 'twilio',
                    'from_number' => $from,
                    'to_number' => $to,
                    'status' => 'in-progress',
                    'started_at' => now(),
                ]
            );
        }

        // Generate TwiML XML Response
        $recordAttribute = ($phoneRecord?->call_recording_enabled) ? 'record="record-from-answer"' : '';
        $gatherAction = url("/api/v1/webhooks/twilio/gather?call_sid={$callSid}");

        $twiml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Response {$recordAttribute}>
    <Say voice="Polly.Aditi" language="en-IN">{$greeting}</Say>
    <Gather input="speech" timeout="5" speechTimeout="auto" action="{$gatherAction}" method="POST">
        <Say voice="Polly.Aditi" language="en-IN">Please speak your query clearly.</Say>
    </Gather>
    <Say voice="Polly.Aditi" language="en-IN">We did not receive any input. Thank you for calling Growbridge Connect. Goodbye!</Say>
</Response>
XML;

        return response($twiml, 200)->header('Content-Type', 'text/xml');
    }

    /**
     * Handle speech gather action from caller
     * POST /api/v1/webhooks/twilio/gather
     */
    public function handleGather(Request $request): Response
    {
        $speechResult = $request->input('SpeechResult', '');
        $callSid = $request->input('call_sid') ?? $request->input('CallSid');

        Log::info("Twilio Voice Gather Speech Result: CallSid={$callSid}, Speech={$speechResult}");

        $call = VoiceCall::where('provider_call_id', $callSid)->first();
        if ($call && !empty($speechResult)) {
            $transcript = ($call->transcript ? $call->transcript . "\n" : '') . "Caller: {$speechResult}";
            $call->update(['transcript' => $transcript]);
        }

        $reply = "Thank you. I have captured your request regarding '{$speechResult}'. A representative or automated follow-up will be initiated shortly. Have a great day!";

        $twiml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Response>
    <Say voice="Polly.Aditi" language="en-IN">{$reply}</Say>
    <Hangup/>
</Response>
XML;

        return response($twiml, 200)->header('Content-Type', 'text/xml');
    }

    /**
     * Handle Twilio Inbound SMS
     * POST /api/v1/webhooks/twilio/sms
     */
    public function handleSms(Request $request): Response
    {
        $to = $request->input('To');
        $from = $request->input('From');
        $body = $request->input('Body');

        Log::info("Twilio Inbound SMS: To={$to}, From={$from}, Body={$body}");

        $phoneRecord = PhoneNumber::where('status', 'active')
            ->where(function ($q) use ($to) {
                $clean = preg_replace('/[^0-9+]/', '', $to ?? '');
                $q->where('phone_number', $clean)
                  ->orWhere('phone_number', $to)
                  ->orWhere('phone_number', 'LIKE', '%' . substr($clean, -10));
            })
            ->first();

        $workspaceId = $phoneRecord?->workspace_id ?? 1;

        if ($from) {
            Contact::firstOrCreate(
                ['workspace_id' => $workspaceId, 'phone' => $from],
                ['name' => "SMS Contact {$from}", 'source' => 'sms']
            );
        }

        $twiml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Response>
    <Message>Thank you for reaching out to Growbridge Connect. We have received your message.</Message>
</Response>
XML;

        return response($twiml, 200)->header('Content-Type', 'text/xml');
    }

    /**
     * Handle Twilio Call Status changes & recording URLs
     * POST /api/v1/webhooks/twilio/status
     */
    public function handleStatus(Request $request): Response
    {
        $callSid = $request->input('CallSid');
        $callStatus = $request->input('CallStatus', 'completed');
        $duration = (int) $request->input('CallDuration', 0);
        $recordingUrl = $request->input('RecordingUrl');

        Log::info("Twilio Call Status: CallSid={$callSid}, Status={$callStatus}, Duration={$duration}");

        $call = VoiceCall::where('provider_call_id', $callSid)->first();
        if ($call) {
            $summary = $call->summary;
            $leadScore = $call->lead_score;

            if ($duration > 0 && empty($summary)) {
                $summary = "Caller engaged with AI Voice Assistant for {$duration}s. Inquiry logged into Growbridge CRM.";
                $leadScore = rand(70, 95);
            }

            $call->update([
                'status' => in_array($callStatus, ['completed', 'busy', 'no-answer', 'failed', 'canceled']) ? $callStatus : 'completed',
                'duration_sec' => $duration > 0 ? $duration : $call->duration_sec,
                'recording_url' => $recordingUrl ?? $call->recording_url,
                'summary' => $summary,
                'lead_score' => $leadScore,
                'ended_at' => now(),
            ]);
        }

        return response('<Response/>', 200)->header('Content-Type', 'text/xml');
    }
}
