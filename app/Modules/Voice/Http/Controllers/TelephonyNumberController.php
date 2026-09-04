<?php

namespace App\Modules\Voice\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Voice\Models\TelephonyPhoneNumber;
use App\Modules\Voice\Models\VoiceAgent;
use App\Modules\Voice\Services\TelephonyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TelephonyNumberController extends Controller
{
    public function __construct(private readonly TelephonyService $telephonyService) {}

    public function index(Request $request): Response
    {
        $workspaceId = (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);

        $numbers = TelephonyPhoneNumber::where('workspace_id', $workspaceId)
            ->with('voiceAgent:id,name,language,provider')
            ->latest()
            ->get();

        $agents = VoiceAgent::where('workspace_id', $workspaceId)
            ->get(['id', 'name', 'provider']);

        return Inertia::render('Voice/Numbers', [
            'numbers' => $numbers,
            'agents' => $agents,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workspaceId = (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);

        $validated = $request->validate([
            'phone_number' => ['required', 'string', 'max:32'],
            'provider' => ['required', 'in:heyo,exotel,twilio,plivo,custom'],
            'assigned_voice_agent_id' => ['nullable', 'exists:voice_agents,id'],
            'direction' => ['required', 'in:inbound,outbound,both'],
            'is_default' => ['boolean'],
        ]);

        if (! empty($validated['is_default'])) {
            TelephonyPhoneNumber::where('workspace_id', $workspaceId)->update(['is_default' => false]);
        }

        TelephonyPhoneNumber::create(array_merge($validated, [
            'workspace_id' => $workspaceId,
            'status' => 'connected',
        ]));

        return back()->with('success', __('Phone number added successfully.'));
    }

    public function update(Request $request, TelephonyPhoneNumber $telephonyPhoneNumber): RedirectResponse
    {
        $workspaceId = (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
        abort_if($telephonyPhoneNumber->workspace_id !== $workspaceId, 403);

        $validated = $request->validate([
            'phone_number' => ['required', 'string', 'max:32'],
            'provider' => ['required', 'in:heyo,exotel,twilio,plivo,custom'],
            'assigned_voice_agent_id' => ['nullable', 'exists:voice_agents,id'],
            'direction' => ['required', 'in:inbound,outbound,both'],
            'is_default' => ['boolean'],
            'status' => ['required', 'in:connected,disconnected,pending,error'],
        ]);

        if (! empty($validated['is_default'])) {
            TelephonyPhoneNumber::where('workspace_id', $workspaceId)
                ->where('id', '!=', $telephonyPhoneNumber->id)
                ->update(['is_default' => false]);
        }

        $telephonyPhoneNumber->update($validated);

        return back()->with('success', __('Phone number updated successfully.'));
    }

    public function toggle(Request $request, TelephonyPhoneNumber $telephonyPhoneNumber): RedirectResponse
    {
        $workspaceId = (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
        abort_if($telephonyPhoneNumber->workspace_id !== $workspaceId, 403);

        $newStatus = $telephonyPhoneNumber->status === 'connected' ? 'disconnected' : 'connected';
        $telephonyPhoneNumber->update(['status' => $newStatus]);

        return back()->with('success', __('Phone number status updated.'));
    }

    public function destroy(Request $request, TelephonyPhoneNumber $telephonyPhoneNumber): RedirectResponse
    {
        $workspaceId = (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
        abort_if($telephonyPhoneNumber->workspace_id !== $workspaceId, 403);

        $telephonyPhoneNumber->delete();

        return back()->with('success', __('Phone number removed.'));
    }
}
