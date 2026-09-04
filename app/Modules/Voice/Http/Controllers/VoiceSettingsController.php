<?php

namespace App\Modules\Voice\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PhoneNumber;
use App\Models\TwilioAccount;
use App\Modules\Voice\Models\VoiceAgent;
use App\Modules\Voice\Services\TelephonyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VoiceSettingsController extends Controller
{
    public function __construct(
        private readonly TelephonyService $telephonyService
    ) {}

    private function workspaceId(Request $request): int
    {
        return (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
    }

    /**
     * Voice & Twilio Settings View (/app/phone/settings or /app/voice/settings)
     */
    public function index(Request $request): Response
    {
        $wid = $this->workspaceId($request);

        $account = TwilioAccount::where('workspace_id', $wid)->first();
        $meta = (array) ($account?->metadata ?? []);

        $phoneNumbers = PhoneNumber::where('workspace_id', $wid)->get();
        $agents = VoiceAgent::where('workspace_id', $wid)->get(['id', 'name', 'status', 'human_transfer_number']);

        return Inertia::render('Voice/Settings', [
            'twilioConfig' => [
                'account_sid' => $account?->twilio_account_sid ?? '',
                'has_auth_token' => ! empty($account?->encrypted_auth_token),
                'default_from_number' => $meta['from_number'] ?? '',
                'human_transfer_number' => $meta['human_transfer_number'] ?? ($phoneNumbers->first()?->handoff_number ?? ''),
                'fallback_action' => $meta['fallback_action'] ?? 'whatsapp_callback',
                'call_recording' => (bool) ($meta['call_recording'] ?? true),
                'status' => $account?->status ?? 'disconnected',
            ],
            'phoneNumbers' => $phoneNumbers,
            'agents' => $agents,
        ]);
    }

    /**
     * Update Twilio & Voice Routing Settings
     */
    public function update(Request $request): RedirectResponse
    {
        $wid = $this->workspaceId($request);

        $validated = $request->validate([
            'account_sid' => ['nullable', 'string', 'max:64'],
            'auth_token' => ['nullable', 'string', 'max:128'],
            'default_from_number' => ['nullable', 'string', 'max:32'],
            'human_transfer_number' => ['nullable', 'string', 'max:32'],
            'fallback_action' => ['required', 'string', 'in:take_message,schedule_callback,whatsapp_callback,send_sms,end_call'],
            'call_recording' => ['boolean'],
        ]);

        $account = TwilioAccount::firstOrNew(['workspace_id' => $wid]);
        if (! empty($validated['account_sid'])) {
            $account->twilio_account_sid = $validated['account_sid'];
        }
        if (! empty($validated['auth_token'])) {
            $account->auth_token = $validated['auth_token'];
        }

        $meta = (array) ($account->metadata ?? []);
        $meta['from_number'] = $validated['default_from_number'] ?? ($meta['from_number'] ?? '');
        $meta['human_transfer_number'] = $validated['human_transfer_number'] ?? ($meta['human_transfer_number'] ?? '');
        $meta['fallback_action'] = $validated['fallback_action'];
        $meta['call_recording'] = $validated['call_recording'] ?? true;

        $account->metadata = $meta;
        $account->status = (! empty($account->twilio_account_sid) && ! empty($account->encrypted_auth_token)) ? 'active' : 'pending';
        $account->save();

        // Update default human transfer number across workspace phone numbers & agents
        if (! empty($validated['human_transfer_number'])) {
            PhoneNumber::where('workspace_id', $wid)->update([
                'handoff_number' => $validated['human_transfer_number'],
                'fallback_action' => $validated['fallback_action'],
            ]);
            VoiceAgent::where('workspace_id', $wid)->update([
                'human_transfer_number' => $validated['human_transfer_number'],
            ]);
        }

        return back()->with('success', 'Voice settings and Twilio credentials saved successfully.');
    }

    /**
     * Test Twilio API Connection
     */
    public function testConnection(Request $request): JsonResponse
    {
        $wid = $this->workspaceId($request);
        $result = $this->telephonyService->testConnection('twilio', $wid);

        return response()->json($result);
    }
}
