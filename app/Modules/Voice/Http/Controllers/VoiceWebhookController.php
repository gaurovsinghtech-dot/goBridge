<?php

namespace App\Modules\Voice\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PhoneNumber;
use App\Modules\AI\Models\AiChatbot;
use App\Modules\AI\Models\AiDailyStat;
use App\Modules\AI\Models\AiUnknownQuestion;
use App\Modules\Shared\Models\Contact;
use App\Modules\Voice\Jobs\GenerateVoiceCallSummaryJob;
use App\Modules\Voice\Models\VoiceAgent;
use App\Modules\Voice\Models\VoiceCall;
use App\Modules\Voice\Services\TelephonyService;
use App\Services\AI\AiKnowledgeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VoiceWebhookController extends Controller
{
    public function __construct(
        private readonly TelephonyService $telephonyService,
        private readonly AiKnowledgeService $knowledgeService
    ) {}

    /**
     * Inbound call entry point from Twilio / Voice Provider
     */
    public function incoming(Request $request, string $provider = 'twilio'): mixed
    {
        $to = (string) ($request->input('To') ?? $request->input('Called') ?? '');
        $from = (string) ($request->input('From') ?? $request->input('Caller') ?? 'Anonymous');
        $callSid = (string) ($request->input('CallSid') ?? $request->input('call_id') ?? '');

        // 1. Resolve destination Phone Number in workspace
        $phoneNumber = PhoneNumber::where('phone_number', $to)
            ->orWhere('phone_number', preg_replace('/^\+/', '', $to))
            ->orWhere('phone_number', '+'.ltrim($to, '+'))
            ->first();

        $workspaceId = $phoneNumber?->workspace_id ?? 1;

        // 2. Resolve assigned AI Voice Agent
        $voiceAgent = null;
        if ($phoneNumber?->assigned_ai_agent_id) {
            $voiceAgent = VoiceAgent::find($phoneNumber->assigned_ai_agent_id);
        }

        if (! $voiceAgent) {
            $voiceAgent = VoiceAgent::where('workspace_id', $workspaceId)
                ->where('status', 'active')
                ->first();
        }

        // 3. Resolve or create CRM Contact
        $contact = Contact::firstOrCreate(
            ['workspace_id' => $workspaceId, 'phone_e164' => $from],
            ['first_name' => 'Voice Caller', 'status' => 'lead', 'source' => 'voice_call']
        );

        // 4. Create Voice Call Session
        $call = VoiceCall::create([
            'workspace_id' => $workspaceId,
            'phone_number_id' => $phoneNumber?->id,
            'voice_agent_id' => $voiceAgent?->id,
            'contact_id' => $contact->id,
            'direction' => 'inbound',
            'provider' => $provider,
            'provider_call_id' => $callSid,
            'from_number' => $from,
            'to_number' => $to,
            'status' => 'in-progress',
            'started_at' => now(),
        ]);

        if ($voiceAgent) {
            $voiceAgent->increment('total_calls');
        }

        // 5. Generate initial TwiML Greeting + Speech Gather
        $greeting = $voiceAgent?->greeting_message ?: "Hello! Welcome to Growbridge Connect. I am your AI assistant. How can I help you today?";
        $language = $voiceAgent?->language === 'hi-IN' ? 'hi-IN' : 'en-US';
        $voice = $voiceAgent?->voice_id ?: 'Polly.Aditi';
        $gatherUrl = route('webhooks.voice.gather', ['provider' => $provider, 'call_uuid' => $call->uuid]);

        if ($provider === 'heyo') {
            return response()->json([
                'action' => 'say',
                'text' => $greeting,
                'gather_url' => $gatherUrl,
            ]);
        }

        $twiml = '<?xml version="1.0" encoding="UTF-8"?>';
        $twiml .= '<Response>';
        $twiml .= '<Say voice="'.$voice.'" language="'.$language.'">'.htmlspecialchars($greeting).'</Say>';
        $twiml .= '<Gather input="speech dtmf" timeout="5" speechTimeout="auto" action="'.$gatherUrl.'">';
        $twiml .= '<Say voice="'.$voice.'" language="'.$language.'">Please state your inquiry, or press 0 to speak with a representative.</Say>';
        $twiml .= '</Gather>';
        $twiml .= '</Response>';

        return response($twiml, 200, ['Content-Type' => 'text/xml']);
    }

    /**
     * Speech / DTMF gather interaction loop
     */
    public function gather(Request $request, string $provider, string $call_uuid): mixed
    {
        $call = VoiceCall::with(['agent.knowledgeBase', 'phoneNumber'])->where('uuid', $call_uuid)->first();
        if (! $call) {
            return response('Call not found', 404);
        }

        $speech = trim((string) ($request->input('SpeechResult') ?? $request->input('speech') ?? ''));
        $digits = trim((string) ($request->input('Digits') ?? $request->input('digits') ?? ''));

        $voice = $call->agent?->voice_id ?: 'Polly.Aditi';
        $language = $call->agent?->language === 'hi-IN' ? 'hi-IN' : 'en-US';
        $gatherUrl = route('webhooks.voice.gather', ['provider' => $provider, 'call_uuid' => $call->uuid]);

        // ─── Human Handoff Detection ──────────────────────────────────────────
        $speechLower = strtolower($speech);
        $isHandoffRequested = ($digits === '0')
            || str_contains($speechLower, 'agent')
            || str_contains($speechLower, 'human')
            || str_contains($speechLower, 'manager')
            || str_contains($speechLower, 'representative')
            || str_contains($speechLower, 'support team')
            || str_contains($speechLower, 'complaint')
            || str_contains($speechLower, 'talk to person');

        if ($isHandoffRequested) {
            $call->update([
                'outcome' => 'transferred',
                'handoff_reason' => 'Customer requested human representative',
            ]);

            $transferNumber = $call->agent?->human_transfer_number
                ?? $call->phoneNumber?->handoff_number
                ?? config('services.twilio.handoff_number');

            if ($transferNumber) {
                if ($provider === 'heyo') {
                    return response()->json([
                        'action' => 'transfer',
                        'transfer_number' => $transferNumber,
                    ]);
                }

                $twiml = '<?xml version="1.0" encoding="UTF-8"?>';
                $twiml .= '<Response>';
                $twiml .= '<Say voice="'.$voice.'" language="'.$language.'">Certainly. Connecting you with our human team right now. Please hold.</Say>';
                $twiml .= '<Dial>'.htmlspecialchars($transferNumber).'</Dial>';
                $twiml .= '</Response>';

                return response($twiml, 200, ['Content-Type' => 'text/xml']);
            }

            // Fallback when no human number configured
            $fallback = $call->phoneNumber?->fallback_action ?? 'whatsapp_callback';
            $fallbackMsg = "Our human team is currently assisting other callers. I have recorded your inquiry and our team will contact you via WhatsApp shortly.";

            $call->update([
                'outcome' => 'callback_requested',
                'summary' => 'Caller requested human handoff. No agent available, queued for WhatsApp follow-up.',
            ]);

            if ($provider === 'heyo') {
                return response()->json(['action' => 'hangup', 'message' => $fallbackMsg]);
            }

            $twiml = '<?xml version="1.0" encoding="UTF-8"?>';
            $twiml .= '<Response>';
            $twiml .= '<Say voice="'.$voice.'" language="'.$language.'">'.htmlspecialchars($fallbackMsg).'</Say>';
            $twiml .= '<Hangup/>';
            $twiml .= '</Response>';

            return response($twiml, 200, ['Content-Type' => 'text/xml']);
        }

        // ─── Knowledge Base Search & Response Generation ──────────────────────
        if (! empty($speech)) {
            $aiAnswer = null;

            // Search Knowledge Base if connected
            if ($call->agent?->ai_kb_id) {
                $kb = $call->agent->knowledgeBase;
                if ($kb) {
                    $chunks = $this->knowledgeService->search($kb, $speech, 3);
                    if (! empty($chunks)) {
                        $top = $chunks[0];
                        $aiAnswer = $this->formatSpokenResponse($top['title'] ?? '', $top['content'] ?? '', $speech);
                    }
                }
            }

            // Non-hallucination unknown fallback
            if (! $aiAnswer) {
                // Log unknown question for Task #70 Analytics & #68 Knowledge Base
                AiUnknownQuestion::updateOrCreate(
                    [
                        'workspace_id' => $call->workspace_id,
                        'question' => substr($speech, 0, 500),
                    ],
                    [
                        'status' => 'pending',
                        'last_asked_at' => now(),
                    ]
                )->increment('occurrences');

                $aiAnswer = "I don't have that specific information in my business knowledge. Would you like me to connect you with our human team?";
            }

            // Append turn to Transcript
            $newTranscript = ($call->transcript ? $call->transcript . "\n" : '')
                . "Caller: {$speech}\nAI: {$aiAnswer}";

            $call->update([
                'transcript' => $newTranscript,
                'outcome' => 'in_progress',
            ]);

            if ($provider === 'heyo') {
                return response()->json([
                    'action' => 'say_and_gather',
                    'text' => $aiAnswer,
                    'gather_url' => $gatherUrl,
                ]);
            }

            $twiml = '<?xml version="1.0" encoding="UTF-8"?>';
            $twiml .= '<Response>';
            $twiml .= '<Say voice="'.$voice.'" language="'.$language.'">'.htmlspecialchars($aiAnswer).'</Say>';
            $twiml .= '<Gather input="speech dtmf" timeout="5" speechTimeout="auto" action="'.$gatherUrl.'">';
            $twiml .= '<Say voice="'.$voice.'" language="'.$language.'">Is there anything else I can help you with?</Say>';
            $twiml .= '</Gather>';
            $twiml .= '</Response>';

            return response($twiml, 200, ['Content-Type' => 'text/xml']);
        }

        // Silence / Goodbye Fallback
        $closing = "Thank you for calling Growbridge Connect. Have a wonderful day!";

        if ($provider === 'heyo') {
            return response()->json(['action' => 'hangup', 'message' => $closing]);
        }

        $twiml = '<?xml version="1.0" encoding="UTF-8"?>';
        $twiml .= '<Response>';
        $twiml .= '<Say voice="'.$voice.'" language="'.$language.'">'.htmlspecialchars($closing).'</Say>';
        $twiml .= '<Hangup/>';
        $twiml .= '</Response>';

        return response($twiml, 200, ['Content-Type' => 'text/xml']);
    }

    /**
     * Standard status callback & webhook router
     */
    public function handle(Request $request, string $provider, string $call_uuid): mixed
    {
        return $this->telephonyService->handleWebhook(
            $provider,
            $call_uuid,
            $request->all(),
            $request->headers->all()
        );
    }

    /**
     * Synthesize a concise 1-2 sentence spoken reply from business knowledge chunk
     */
    private function formatSpokenResponse(string $title, string $content, string $question): string
    {
        $q = strtolower($question);

        if (str_contains($q, 'hour') || str_contains($q, 'time') || str_contains($q, 'open')) {
            if (preg_match('/(?:hours?|timings?):\s*([^\n\.]+)/i', $content, $m)) {
                return "Our business hours are " . trim($m[1]) . ".";
            }
        }

        if (str_contains($q, 'price') || str_contains($q, 'cost') || str_contains($q, 'rate')) {
            if (preg_match('/(?:price|cost):\s*([^\n\.]+)/i', $content, $m)) {
                return "The price is " . trim($m[1]) . ".";
            }
        }

        if (str_contains($q, 'address') || str_contains($q, 'location') || str_contains($q, 'where')) {
            if (preg_match('/(?:address|location):\s*([^\n\.]+)/i', $content, $m)) {
                return "We are located at " . trim($m[1]) . ".";
            }
        }

        // General excerpt capped at 2 sentences
        $sentences = preg_split('/(?<=[.?!])\s+/', trim($content));
        $excerpt = implode(' ', array_slice($sentences, 0, 2));

        return $excerpt ?: "Based on our business details, " . substr($content, 0, 140) . ".";
    }
}
