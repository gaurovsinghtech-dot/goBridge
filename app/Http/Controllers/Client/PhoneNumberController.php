<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\PhoneNumber;
use App\Models\TwilioAccount;
use App\Modules\Integrations\Services\CredentialResolver;
use App\Modules\Voice\Models\VoiceAgent;
use App\Modules\Voice\Models\VoiceCall;
use App\Modules\Whatsapp\Models\WhatsappBusinessAccount;
use App\Modules\Whatsapp\Models\WhatsappPhoneNumber;
use App\Services\Channels\ChannelStatusService;
use App\Services\TwilioService;
use App\Services\UnifiedNumberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class PhoneNumberController extends Controller
{
    public function __construct(
        protected TwilioService $twilioService,
        protected UnifiedNumberService $unifiedNumberService,
        protected ChannelStatusService $channelStatusService
    ) {}

    private function getWorkspace(Request $request): ?\App\Models\Workspace
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        $workspaceId = (int) ($request->attributes->get('current_workspace_id') ?: ($request->session()->get('current_workspace_id') ?: ($user->current_workspace_id ?? $user->workspace_id)));
        return \App\Models\Workspace::find($workspaceId) ?? $user->currentWorkspace ?? $user->workspace ?? \App\Models\Workspace::where('client_id', $user->client_id)->first();
    }

    /**
     * Display customer's Phone Numbers, Unified Business Numbers & Voice Suite
     */
    public function index(Request $request): Response
    {
        $workspace = $this->getWorkspace($request);
        
        $numbers = $workspace ? PhoneNumber::where('workspace_id', $workspace->id)
            ->with(['assignedVoiceAgent', 'assignedChatAgent', 'whatsappAccount'])
            ->latest()
            ->get() : collect();

        $agents = $workspace ? VoiceAgent::where('workspace_id', $workspace->id)
            ->where('status', 'active')
            ->get(['id', 'uuid', 'name', 'language', 'tone', 'voice_id', 'provider']) : collect();

        $wabas = $workspace ? WhatsappBusinessAccount::where('workspace_id', $workspace->id)
            ->with('phoneNumbers')
            ->get(['id', 'waba_id', 'status', 'created_at']) : collect();

        $subaccount = $workspace ? TwilioAccount::where('workspace_id', $workspace->id)->first() : null;
        
        $recentCalls = $workspace ? VoiceCall::where('workspace_id', $workspace->id)
            ->with(['phoneNumber', 'agent', 'contact'])
            ->latest()
            ->take(15)
            ->get() : collect();

        $channelStatuses = $workspace ? $this->channelStatusService->getWorkspaceChannelStatuses($workspace) : [];

        $meta = CredentialResolver::system()->meta();
        $metaAppId = $meta?->appId() ?? env('META_APP_ID', '109283749102938');

        $stats = [
            'total_numbers' => $numbers->count(),
            'active_numbers' => $numbers->where('status', 'active')->count(),
            'voice_enabled' => $numbers->where('voice_enabled', true)->count(),
            'whatsapp_connected' => $numbers->where('whatsapp_status', 'connected')->count(),
            'sms_enabled' => $numbers->where('sms_enabled', true)->count(),
            'unified_numbers' => $numbers->filter(fn ($n) => $n->isUnified())->count(),
            'total_calls' => $workspace ? VoiceCall::where('workspace_id', $workspace->id)->count() : 0,
        ];

        return Inertia::render('Voice/PhoneNumbers/Index', [
            'numbers' => $numbers,
            'agents' => $agents,
            'wabas' => $wabas,
            'stats' => $stats,
            'channelStatuses' => $channelStatuses,
            'metaAppId' => $metaAppId,
            'subaccount' => [
                'sid' => $subaccount?->twilio_account_sid ?? 'Default Master Subaccount',
                'status' => $subaccount?->status ?? 'active',
                'is_master_configured' => $this->twilioService->isConfigured(),
            ],
            'recentCalls' => $recentCalls,
        ]);
    }

    /**
     * Search available virtual phone numbers on Twilio Marketplace
     */
    public function search(Request $request): JsonResponse
    {
        $country = $request->input('country', 'IN');
        $type = $request->input('type', 'local');
        $areaCode = $request->input('area_code');
        $capabilities = [
            'voice' => $request->boolean('voice', true),
            'sms' => $request->boolean('sms', true),
            'mms' => $request->boolean('mms', false),
        ];

        try {
            $numbers = $this->twilioService->searchAvailableNumbers($country, [
                'type' => $type,
                'area_code' => $areaCode,
                'voice' => $capabilities['voice'],
                'sms' => $capabilities['sms'],
                'mms' => $capabilities['mms'],
            ]);

            return response()->json([
                'success' => true,
                'country' => $country,
                'numbers' => $numbers,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'numbers' => [],
            ], 500);
        }
    }

    /**
     * Register a Telephony Phone Number for Customer Workspace
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $workspace = $user?->currentWorkspace ?? $user?->workspaces()->first() ?? \App\Models\Workspace::first();
        if (! $workspace) {
            return back()->withErrors(['error' => 'No active workspace found.']);
        }

        $validated = $request->validate([
            'phone_number' => ['required', 'string', 'max:32'],
            'provider' => ['nullable', 'string'],
            'assigned_voice_agent_id' => ['nullable', 'integer'],
            'direction' => ['nullable', 'string'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        if (! empty($validated['is_default'])) {
            if (\Illuminate\Support\Facades\Schema::hasTable('telephony_phone_numbers')) {
                \App\Modules\Voice\Models\TelephonyPhoneNumber::where('workspace_id', $workspace->id)->update(['is_default' => false]);
            }
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('telephony_phone_numbers')) {
            \App\Modules\Voice\Models\TelephonyPhoneNumber::create([
                'workspace_id' => $workspace->id,
                'phone_number' => $validated['phone_number'],
                'provider' => $validated['provider'] ?? 'heyo',
                'assigned_voice_agent_id' => $validated['assigned_voice_agent_id'] ?? null,
                'direction' => $validated['direction'] ?? 'both',
                'is_default' => $validated['is_default'] ?? false,
                'status' => 'connected',
            ]);
        }

        return back()->with('success', 'Phone number registered successfully.');
    }

    /**
     * Purchase a Phone Number for Customer Workspace
     */
    public function purchase(Request $request): RedirectResponse
    {
        $user = $request->user();
        $workspace = $user?->currentWorkspace ?? $user?->workspaces()->first() ?? \App\Models\Workspace::first();
        if (!$workspace) {
            return back()->withErrors(['error' => 'No active workspace found for this user.']);
        }

        $validated = $request->validate([
            'phone_number' => ['required', 'string'],
            'country' => ['nullable', 'string', 'max:8'],
            'friendly_name' => ['nullable', 'string', 'max:128'],
            'voice' => ['nullable', 'boolean'],
            'sms' => ['nullable', 'boolean'],
            'mms' => ['nullable', 'boolean'],
            'call_recording' => ['nullable', 'boolean'],
            'assigned_ai_agent_id' => ['nullable'],
        ]);

        try {
            $this->twilioService->purchaseNumber($workspace, $validated['phone_number'], [
                'country' => $validated['country'] ?? 'IN',
                'friendly_name' => $validated['friendly_name'] ?? null,
                'voice' => $validated['voice'] ?? true,
                'sms' => $validated['sms'] ?? true,
                'mms' => $validated['mms'] ?? false,
                'call_recording' => $validated['call_recording'] ?? false,
                'assigned_ai_agent_id' => $validated['assigned_ai_agent_id'] ?? null,
            ]);

            return redirect()->route('client.voice.numbers.index')->with('success', 'Phone number purchased and provisioned successfully! You can now connect WhatsApp and assign AI agents.');
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Update Phone Number Settings (Voice, SMS, Recording, AI Agent)
     */
    public function update(Request $request, PhoneNumber $phoneNumber): RedirectResponse
    {
        $user = $request->user();
        $workspace = $user?->currentWorkspace ?? $user?->workspaces()->first() ?? \App\Models\Workspace::first();

        $validated = $request->validate([
            'friendly_name' => ['nullable', 'string', 'max:128'],
            'voice_enabled' => ['nullable', 'boolean'],
            'sms_enabled' => ['nullable', 'boolean'],
            'call_recording_enabled' => ['nullable', 'boolean'],
            'assigned_ai_agent_id' => ['nullable'],
            'assigned_chat_ai_agent_id' => ['nullable'],
        ]);

        try {
            $this->twilioService->configureNumber($workspace, $phoneNumber, $validated);

            if (isset($validated['assigned_chat_ai_agent_id'])) {
                $phoneNumber->update(['assigned_chat_ai_agent_id' => $validated['assigned_chat_ai_agent_id']]);
            }

            return back()->with('success', 'Phone number configuration updated successfully.');
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Connect WhatsApp Business to this Number (Unified Business Number)
     */
    public function connectWhatsapp(Request $request, PhoneNumber $phoneNumber): RedirectResponse
    {
        $workspace = $this->getWorkspace($request);

        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:128'],
            'waba_id' => ['nullable'],
            'whatsapp_phone_number_id' => ['nullable', 'string'],
        ]);

        try {
            $this->unifiedNumberService->connectWhatsapp($workspace, $phoneNumber, $validated);

            return back()->with('success', "WhatsApp Business successfully connected for {$phoneNumber->phone_number}!");
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Meta Official Embedded Signup Handler for Phone Number
     */
    public function embeddedSignupWhatsapp(Request $request, PhoneNumber $phoneNumber): JsonResponse
    {
        $workspace = $this->getWorkspace($request);

        if (! $workspace || (int) $phoneNumber->workspace_id !== (int) $workspace->id) {
            return response()->json(['message' => 'Unauthorized number access.'], 403);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:2048'],
            'waba_id' => ['required', 'string', 'max:64'],
            'phone_number_id' => ['nullable', 'string', 'max:64'],
            'display_name' => ['nullable', 'string', 'max:128'],
        ]);

        try {
            // Find or create WABA
            $waba = WhatsappBusinessAccount::firstOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'waba_id' => $validated['waba_id'],
                ],
                [
                    'status' => 'active',
                    'credentials' => ['code' => $validated['code']],
                ]
            );

            // Connect WhatsApp via Unified Service
            $this->unifiedNumberService->connectWhatsapp($workspace, $phoneNumber, [
                'display_name' => $validated['display_name'] ?? $phoneNumber->friendly_name ?? 'Official Business Line',
                'waba_id' => $waba->id,
                'whatsapp_phone_number_id' => $validated['phone_number_id'] ?? ('meta_' . $phoneNumber->id),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'WhatsApp Business successfully connected via Meta Embedded Signup!',
                'number' => $phoneNumber->fresh(['assignedVoiceAgent', 'assignedChatAgent', 'whatsappAccount']),
            ]);
        } catch (\Throwable $e) {
            Log::error('Meta Embedded Signup error for phone number', ['error' => $e->getMessage()]);
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Disconnect WhatsApp Business from this Number
     */
    public function disconnectWhatsapp(Request $request, PhoneNumber $phoneNumber): RedirectResponse
    {
        $workspace = $this->getWorkspace($request);

        try {
            $this->unifiedNumberService->disconnectWhatsapp($workspace, $phoneNumber);

            return back()->with('success', "WhatsApp disconnected from {$phoneNumber->phone_number}.");
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Assign AI Agents (Voice AI + Chat AI)
     */
    public function updateAiAgents(Request $request, PhoneNumber $phoneNumber): RedirectResponse
    {
        $user = $request->user();
        $workspace = $user?->currentWorkspace ?? $user?->workspaces()->first() ?? \App\Models\Workspace::first();

        $validated = $request->validate([
            'assigned_ai_agent_id' => ['nullable'],
            'assigned_chat_ai_agent_id' => ['nullable'],
        ]);

        try {
            $this->unifiedNumberService->assignAiAgents(
                $workspace,
                $phoneNumber,
                $validated['assigned_ai_agent_id'] ?? null,
                $validated['assigned_chat_ai_agent_id'] ?? null
            );

            return back()->with('success', 'AI Agents assigned successfully.');
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Release / Cancel a Phone Number
     */
    public function destroy(Request $request, PhoneNumber $phoneNumber): RedirectResponse
    {
        $user = $request->user();
        $workspace = $user?->currentWorkspace ?? $user?->workspaces()->first() ?? \App\Models\Workspace::first();

        try {
            $this->twilioService->releaseNumber($workspace, $phoneNumber);

            return back()->with('success', 'Phone number released successfully.');
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
