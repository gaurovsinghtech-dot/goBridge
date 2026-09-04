<?php

namespace App\Modules\AI\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PhoneNumber;
use App\Models\TwilioAccount;
use App\Modules\AI\Models\AiDailyStat;
use App\Modules\AI\Models\AiKnowledgeBase;
use App\Modules\Integrations\Models\IntegrationConfig;
use App\Modules\Voice\Models\VoiceAgent;
use App\Modules\Voice\Models\VoiceCall;
use App\Services\AI\AiKnowledgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiVoiceStudioController extends Controller
{
    public function __construct(
        private readonly AiKnowledgeService $knowledgeService
    ) {}

    private function workspaceId(Request $request): int
    {
        return (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
    }

    /**
     * Curated list of provider-supported voices
     */
    public static function supportedVoices(): array
    {
        return [
            // Twilio Polly Voices
            [
                'id' => 'Polly.Aditi',
                'name' => 'Aditi (Polly)',
                'provider' => 'twilio',
                'language' => 'hi-IN / en-IN',
                'gender' => 'Female',
                'description' => 'Warm, natural bilingual voice in Hindi and Indian English. Ideal for customer service.',
            ],
            [
                'id' => 'Polly.Kajal',
                'name' => 'Kajal (Polly Neural)',
                'provider' => 'twilio',
                'language' => 'hi-IN',
                'gender' => 'Female',
                'description' => 'Clear, high-fidelity conversational Hindi neural voice. Recommended for sales & inquiries.',
            ],
            [
                'id' => 'Polly.Raveena',
                'name' => 'Raveena (Polly)',
                'provider' => 'twilio',
                'language' => 'en-IN',
                'gender' => 'Female',
                'description' => 'Professional Indian English voice. Clear articulation for business communication.',
            ],
            [
                'id' => 'Polly.Joanna',
                'name' => 'Joanna (Polly Neural)',
                'provider' => 'twilio',
                'language' => 'en-US',
                'gender' => 'Female',
                'description' => 'Friendly, conversational US English neural voice. Great for marketing and support.',
            ],
            [
                'id' => 'Polly.Matthew',
                'name' => 'Matthew (Polly Neural)',
                'provider' => 'twilio',
                'language' => 'en-US',
                'gender' => 'Male',
                'description' => 'Authoritative, calm executive US English voice. Excellent for B2B qualification.',
            ],
            [
                'id' => 'Polly.Arthur',
                'name' => 'Arthur (Polly Neural)',
                'provider' => 'twilio',
                'language' => 'en-GB',
                'gender' => 'Male',
                'description' => 'Sophisticated, pleasant British English neural voice.',
            ],
            [
                'id' => 'Polly.Lucia',
                'name' => 'Lucia (Polly Neural)',
                'provider' => 'twilio',
                'language' => 'es-ES',
                'gender' => 'Female',
                'description' => 'Fluent Spanish voice with natural tone.',
            ],
            // Heyo / Custom Voices
            [
                'id' => 'heyo_default_female',
                'name' => 'Heyo Standard Female',
                'provider' => 'heyo',
                'language' => 'en-IN / hi-IN',
                'gender' => 'Female',
                'description' => 'Standard IVR female voice for Indian phone lines.',
            ],
            [
                'id' => 'heyo_default_male',
                'name' => 'Heyo Standard Male',
                'provider' => 'heyo',
                'language' => 'en-IN / hi-IN',
                'gender' => 'Male',
                'description' => 'Standard IVR male voice for Indian phone lines.',
            ],
        ];
    }

    /**
     * Voice Studio Main Screen (/app/ai/voice-studio)
     */
    public function index(Request $request, ?VoiceAgent $voiceAgent = null): Response
    {
        $wid = $this->workspaceId($request);

        $agents = VoiceAgent::where('workspace_id', $wid)
            ->withCount('calls')
            ->latest()
            ->get();

        // Selected agent or default template
        $selected = $voiceAgent;
        if (! $selected && $agents->isNotEmpty()) {
            $selected = $agents->first();
        }

        if ($selected) {
            abort_if($selected->workspace_id !== $wid, 403);
        }

        $phoneNumbers = PhoneNumber::where('workspace_id', $wid)->get();
        $knowledgeBases = AiKnowledgeBase::where('workspace_id', $wid)->get(['id', 'name', 'category', 'status']);

        // Check provider connection status
        $twilioAcc = TwilioAccount::where('workspace_id', $wid)->first();
        $isTwilioConnected = $twilioAcc && ! empty($twilioAcc->twilio_account_sid) && ! empty($twilioAcc->encrypted_auth_token);

        $heyoConfig = IntegrationConfig::where('workspace_id', $wid)->where('provider', 'heyo')->first();
        $isHeyoConnected = (bool) ($heyoConfig && ! empty($heyoConfig->credentials));

        // Evaluate Activation Checklist
        $assignedNumber = $selected ? PhoneNumber::where('workspace_id', $wid)
            ->where(function ($q) use ($selected) {
                $q->where('assigned_ai_agent_id', $selected->id)
                  ->orWhere('phone_number', $selected->phone_number);
            })->first() : null;

        $checklist = $this->evaluateChecklist($selected, $assignedNumber, $isTwilioConnected || $isHeyoConnected);

        // Daily voice metrics from #70 AI Analytics
        $today = now()->toDateString();
        $voiceStats = AiDailyStat::where('workspace_id', $wid)
            ->where('date', $today)
            ->where('channel', 'phone')
            ->first();

        $analyticsSummary = [
            'calls_today' => $selected ? VoiceCall::where('voice_agent_id', $selected->id)->whereDate('created_at', $today)->count() : 0,
            'resolution_rate' => $selected ? $selected->success_rate : 100,
            'total_calls' => $selected ? $selected->calls_count : 0,
        ];

        return Inertia::render('AI/VoiceStudio/Index', [
            'agents' => $agents,
            'selectedAgent' => $selected ? [
                'id' => $selected->id,
                'uuid' => $selected->uuid,
                'name' => $selected->name,
                'description' => $selected->description,
                'status' => $selected->status,
                'language' => $selected->language,
                'tone' => $selected->tone,
                'voice_id' => $selected->voice_id,
                'provider' => $selected->provider,
                'phone_number' => $selected->phone_number,
                'system_prompt' => $selected->system_prompt,
                'greeting_message' => $selected->greeting_message,
                'ai_kb_id' => $selected->ai_kb_id,
                'human_transfer_number' => $selected->human_transfer_number,
                'max_duration_sec' => $selected->max_duration_sec,
                'ai_model' => $selected->ai_model,
                'call_flow' => $selected->resolved_call_flow,
                'working_hours' => $selected->resolved_working_hours,
                'assigned_phone_number_id' => $assignedNumber?->id,
                'assigned_phone_number' => $assignedNumber?->phone_number,
            ] : null,
            'phoneNumbers' => $phoneNumbers,
            'knowledgeBases' => $knowledgeBases,
            'supportedVoices' => self::supportedVoices(),
            'providers' => [
                'twilio' => [
                    'connected' => $isTwilioConnected,
                    'label' => 'Twilio Voice',
                ],
                'heyo' => [
                    'connected' => $isHeyoConnected,
                    'label' => 'Heyo Phone',
                ],
            ],
            'checklist' => $checklist,
            'analyticsSummary' => $analyticsSummary,
        ]);
    }

    /**
     * Save Voice Agent Configuration
     */
    public function save(Request $request): RedirectResponse
    {
        $wid = $this->workspaceId($request);

        $validated = $request->validate([
            'id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:128'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'in:draft,testing,active,paused,inactive'],
            'provider' => ['required', 'string', 'in:twilio,heyo,exotel,plivo,custom'],
            'voice_id' => ['required', 'string', 'max:128'],
            'language' => ['required', 'string', 'max:32'],
            'tone' => ['required', 'string', 'max:64'],
            'greeting_message' => ['required', 'string', 'max:1000'],
            'system_prompt' => ['nullable', 'string'],
            'ai_kb_id' => ['nullable', 'exists:ai_knowledge_bases,id'],
            'human_transfer_number' => ['nullable', 'string', 'max:32'],
            'max_duration_sec' => ['required', 'integer', 'min:60', 'max:3600'],
            'phone_number_id' => ['nullable', 'exists:phone_numbers,id'],
            'call_flow' => ['nullable', 'array'],
            'working_hours' => ['nullable', 'array'],
        ]);

        $phoneNumber = ! empty($validated['phone_number_id'])
            ? PhoneNumber::where('workspace_id', $wid)->find($validated['phone_number_id'])
            : null;

        $agentData = [
            'workspace_id' => $wid,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? '',
            'status' => $validated['status'] === 'active' ? 'draft' : $validated['status'], // activation handled via explicit endpoint
            'provider' => $validated['provider'],
            'voice_id' => $validated['voice_id'],
            'language' => $validated['language'],
            'tone' => $validated['tone'],
            'greeting_message' => $validated['greeting_message'],
            'system_prompt' => $validated['system_prompt'] ?? $this->synthesizePrompt($validated),
            'ai_kb_id' => $validated['ai_kb_id'] ?? null,
            'human_transfer_number' => $validated['human_transfer_number'] ?? ($phoneNumber?->handoff_number ?? ''),
            'max_duration_sec' => $validated['max_duration_sec'],
            'phone_number' => $phoneNumber?->phone_number ?? '',
            'call_flow_json' => $validated['call_flow'] ?? [],
            'working_hours_json' => $validated['working_hours'] ?? [],
        ];

        if (! empty($validated['id'])) {
            $agent = VoiceAgent::where('workspace_id', $wid)->findOrFail($validated['id']);
            $agent->update($agentData);
        } else {
            $agent = VoiceAgent::create($agentData);
        }

        // Bind Phone Number
        if ($phoneNumber) {
            $phoneNumber->update([
                'assigned_ai_agent_id' => $agent->id,
                'handoff_number' => $agent->human_transfer_number ?: $phoneNumber->handoff_number,
                'voice_enabled' => true,
            ]);
        }

        return redirect()->route('client.ai.voice-studio.show', $agent->uuid)
            ->with('success', __('Voice Agent settings saved successfully.'));
    }

    /**
     * Activate AI Voice Agent
     */
    public function activate(Request $request, VoiceAgent $voiceAgent): RedirectResponse
    {
        $wid = $this->workspaceId($request);
        abort_if($voiceAgent->workspace_id !== $wid, 403);

        $assignedNumber = PhoneNumber::where('workspace_id', $wid)
            ->where(function ($q) use ($voiceAgent) {
                $q->where('assigned_ai_agent_id', $voiceAgent->id)
                  ->orWhere('phone_number', $voiceAgent->phone_number);
            })->first();

        $twilioAcc = TwilioAccount::where('workspace_id', $wid)->first();
        $isTwilioConnected = $twilioAcc && ! empty($twilioAcc->twilio_account_sid) && ! empty($twilioAcc->encrypted_auth_token);

        $checklist = $this->evaluateChecklist($voiceAgent, $assignedNumber, $isTwilioConnected);

        if (! $checklist['is_ready']) {
            return back()->with('error', __('Cannot activate AI Voice Agent: ') . $checklist['blocking_reason']);
        }

        $voiceAgent->update(['status' => 'active']);

        if ($assignedNumber) {
            $assignedNumber->update([
                'assigned_ai_agent_id' => $voiceAgent->id,
                'voice_enabled' => true,
                'status' => 'active',
            ]);
        }

        return back()->with('success', __('AI Voice Agent is now LIVE and answering calls.'));
    }

    /**
     * Pause AI Voice Agent
     */
    public function pause(Request $request, VoiceAgent $voiceAgent): RedirectResponse
    {
        $wid = $this->workspaceId($request);
        abort_if($voiceAgent->workspace_id !== $wid, 403);

        $voiceAgent->update(['status' => 'paused']);

        return back()->with('success', __('AI Voice Agent paused. Calls will route to fallback.'));
    }

    /**
     * Test / Simulate Voice Conversation in Studio without real telephony calls
     */
    public function simulate(Request $request, VoiceAgent $voiceAgent): JsonResponse
    {
        $wid = $this->workspaceId($request);
        abort_if($voiceAgent->workspace_id !== $wid, 403);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:500'],
        ]);

        $message = trim($validated['message']);
        $msgLower = strtolower($message);

        // 1. Check Handoff Triggers
        $isHandoff = str_contains($msgLower, 'agent')
            || str_contains($msgLower, 'human')
            || str_contains($msgLower, 'manager')
            || str_contains($msgLower, 'representative')
            || str_contains($msgLower, 'complaint')
            || str_contains($msgLower, 'talk to person');

        if ($isHandoff) {
            $transferNum = $voiceAgent->human_transfer_number ?: '+91 98765 43210';
            return response()->json([
                'success' => true,
                'response' => "Certainly. Connecting you with our human support team now at {$transferNum}. Please hold.",
                'is_handoff' => true,
                'handoff_reason' => 'Customer requested human agent',
                'source_chunks' => [],
            ]);
        }

        // 2. Search Knowledge Base
        $answer = null;
        $chunks = [];
        if ($voiceAgent->ai_kb_id) {
            $kb = $voiceAgent->knowledgeBase;
            if ($kb) {
                $chunks = $this->knowledgeService->search($kb, $message, 2);
                if (! empty($chunks)) {
                    $top = $chunks[0];
                    $sentences = preg_split('/(?<=[.?!])\s+/', trim($top['content'] ?? ''));
                    $answer = implode(' ', array_slice($sentences, 0, 2));
                }
            }
        }

        // 3. Unknown Fallback
        if (! $answer) {
            $answer = $voiceAgent->call_flow_json['fallback_message']
                ?? "I don't have that specific information in my business knowledge. Would you like me to connect you with our human team?";
        }

        return response()->json([
            'success' => true,
            'response' => $answer,
            'is_handoff' => false,
            'source_chunks' => $chunks,
        ]);
    }

    /**
     * Synthesize clean system prompt from objective & personality presets
     */
    private function synthesizePrompt(array $data): string
    {
        $name = $data['name'] ?? 'Voice Assistant';
        $tone = $data['tone'] ?? 'professional';
        $objective = $data['call_flow']['objective_description'] ?? 'Qualify incoming leads and answer questions.';

        return "You are {$name}, an AI Voice Agent for Growbridge Connect.
Tone: {$tone}.
Primary Objective: {$objective}.
Guidelines:
1. Speak concisely in natural spoken language (1-3 sentences per turn).
2. Answer queries strictly using verified business knowledge.
3. If information is not in knowledge, offer human handoff politely without hallucinating.
4. Keep the caller engaged and ask one question at a time.";
    }

    /**
     * Evaluate the readiness checklist before activation
     */
    private function evaluateChecklist(?VoiceAgent $agent, ?PhoneNumber $number, bool $providerConnected): array
    {
        if (! $agent) {
            return [
                'has_name' => false,
                'has_voice' => false,
                'has_provider' => false,
                'has_phone_number' => false,
                'has_knowledge' => false,
                'has_greeting' => false,
                'has_handoff' => false,
                'has_working_hours' => false,
                'is_ready' => false,
                'blocking_reason' => 'Create a voice agent first.',
            ];
        }

        $hasName = ! empty($agent->name);
        $hasVoice = ! empty($agent->voice_id);
        $hasProvider = $providerConnected;
        $hasNumber = ! empty($number) || ! empty($agent->phone_number);
        $hasKnowledge = ! empty($agent->ai_kb_id);
        $hasGreeting = ! empty($agent->greeting_message);
        $hasHandoff = ! empty($agent->human_transfer_number) || ! empty($agent->call_flow_json['handoff_sales_number']);
        $hasWorkingHours = ! empty($agent->working_hours_json);

        $blockingReason = null;
        if (! $hasProvider) {
            $blockingReason = 'Connect your Twilio or Voice Provider in Settings first.';
        } elseif (! $hasNumber) {
            $blockingReason = 'Assign a phone number to this agent before activation.';
        } elseif (! $hasVoice) {
            $blockingReason = 'Select a voice for your agent.';
        } elseif (! $hasKnowledge) {
            $blockingReason = 'Attach a Knowledge Base to empower your agent with business answers.';
        } elseif (! $hasGreeting) {
            $blockingReason = 'Provide a welcoming greeting message.';
        }

        return [
            'has_name' => $hasName,
            'has_voice' => $hasVoice,
            'has_provider' => $hasProvider,
            'has_phone_number' => $hasNumber,
            'has_knowledge' => $hasKnowledge,
            'has_greeting' => $hasGreeting,
            'has_handoff' => $hasHandoff,
            'has_working_hours' => $hasWorkingHours,
            'is_ready' => $hasName && $hasVoice && $hasProvider && $hasNumber && $hasKnowledge && $hasGreeting,
            'blocking_reason' => $blockingReason,
        ];
    }
}
