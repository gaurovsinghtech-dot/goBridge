<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Models\Contact;
use App\Modules\Voice\Models\VoiceAgent;
use App\Modules\Voice\Services\TelephonyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoiceApiController extends Controller
{
    public function __construct(private TelephonyService $telephony) {}

    public function initiateCall(Request $request): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        if (! $workspace) {
            return response()->json(['success' => false, 'error' => ['code' => 'NO_WORKSPACE', 'message' => 'Workspace context missing.']], 422);
        }

        $validated = $request->validate([
            'to' => ['required', 'string'],
            'voice_agent_id' => ['nullable', 'exists:voice_agents,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'custom_variables' => ['nullable', 'array'],
        ]);

        $voiceAgent = isset($validated['voice_agent_id'])
            ? VoiceAgent::where('workspace_id', $workspace->id)->find($validated['voice_agent_id'])
            : VoiceAgent::where('workspace_id', $workspace->id)->where('status', 'active')->first();

        if (! $voiceAgent) {
            return response()->json(['success' => false, 'error' => ['code' => 'NO_ACTIVE_AGENT', 'message' => 'No active voice agent found in workspace.']], 422);
        }

        // Resolve or create contact
        $contact = Contact::firstOrCreate(
            ['workspace_id' => $workspace->id, 'phone_e164' => $validated['to']],
            ['first_name' => $validated['customer_name'] ?? 'Customer']
        );

        $result = $this->telephony->initiateCall($voiceAgent, $contact, $validated['custom_variables'] ?? []);

        return response()->json([
            'success' => $result['success'] ?? false,
            'data' => [
                'call_uuid' => $result['call_uuid'] ?? null,
                'status' => $result['status'] ?? 'failed',
                'provider' => $voiceAgent->provider,
                'to' => $validated['to'],
            ],
            'message' => $result['message'] ?? null,
        ], $result['success'] ? 200 : 400);
    }
}
