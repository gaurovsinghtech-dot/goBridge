<?php

namespace App\Modules\Voice\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AI\Models\AiKnowledgeBase;
use App\Modules\Voice\Models\VoiceAgent;
use App\Modules\Voice\Models\VoiceCall;
use App\Modules\Voice\Services\VoiceDriverManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VoiceAgentController extends Controller
{
    public function __construct(private readonly VoiceDriverManager $driverManager) {}

    public function index(Request $request): Response
    {
        $workspaceId = (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);

        $agents = VoiceAgent::where('workspace_id', $workspaceId)
            ->withCount('calls')
            ->latest()
            ->get();

        $stats = [
            'total_agents' => $agents->count(),
            'active_agents' => $agents->where('status', 'active')->count(),
            'total_calls' => VoiceCall::where('workspace_id', $workspaceId)->count(),
            'completed_calls' => VoiceCall::where('workspace_id', $workspaceId)->where('status', 'completed')->count(),
        ];

        return Inertia::render('Voice/Index', [
            'agents' => $agents,
            'stats' => $stats,
        ]);
    }

    public function create(Request $request): Response
    {
        $workspaceId = (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
        $knowledgeBases = AiKnowledgeBase::where('workspace_id', $workspaceId)->get(['id', 'name']);

        return Inertia::render('Voice/Builder', [
            'agent' => null,
            'knowledgeBases' => $knowledgeBases,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workspaceId = (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'in:active,inactive,draft'],
            'language' => ['required', 'string', 'max:32'],
            'tone' => ['required', 'string', 'max:64'],
            'voice_id' => ['nullable', 'string', 'max:128'],
            'provider' => ['required', 'in:exotel,twilio,plivo,custom'],
            'phone_number' => ['nullable', 'string', 'max:32'],
            'system_prompt' => ['nullable', 'string'],
            'greeting_message' => ['nullable', 'string'],
            'ai_kb_id' => ['nullable', 'exists:ai_knowledge_bases,id'],
            'tools_config' => ['nullable', 'array'],
            'call_flow_json' => ['nullable', 'array'],
            'working_hours_json' => ['nullable', 'array'],
            'human_transfer_number' => ['nullable', 'string', 'max:32'],
            'max_duration_sec' => ['nullable', 'integer', 'min:30', 'max:1800'],
            'ai_model' => ['nullable', 'string', 'max:64'],
        ]);

        $agent = VoiceAgent::create(array_merge($validated, [
            'workspace_id' => $workspaceId,
        ]));

        return redirect()->route('client.voice.index')->with('success', __('Voice Agent created successfully.'));
    }

    public function edit(Request $request, VoiceAgent $voiceAgent): Response
    {
        $workspaceId = (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
        abort_if($voiceAgent->workspace_id !== $workspaceId, 403);

        $knowledgeBases = AiKnowledgeBase::where('workspace_id', $workspaceId)->get(['id', 'name']);

        return Inertia::render('Voice/Builder', [
            'agent' => $voiceAgent,
            'knowledgeBases' => $knowledgeBases,
        ]);
    }

    public function update(Request $request, VoiceAgent $voiceAgent): RedirectResponse
    {
        $workspaceId = (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
        abort_if($voiceAgent->workspace_id !== $workspaceId, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'in:active,inactive,draft'],
            'language' => ['required', 'string', 'max:32'],
            'tone' => ['required', 'string', 'max:64'],
            'voice_id' => ['nullable', 'string', 'max:128'],
            'provider' => ['required', 'in:exotel,twilio,plivo,custom'],
            'phone_number' => ['nullable', 'string', 'max:32'],
            'system_prompt' => ['nullable', 'string'],
            'greeting_message' => ['nullable', 'string'],
            'ai_kb_id' => ['nullable', 'exists:ai_knowledge_bases,id'],
            'tools_config' => ['nullable', 'array'],
            'call_flow_json' => ['nullable', 'array'],
            'working_hours_json' => ['nullable', 'array'],
            'human_transfer_number' => ['nullable', 'string', 'max:32'],
            'max_duration_sec' => ['nullable', 'integer', 'min:30', 'max:1800'],
            'ai_model' => ['nullable', 'string', 'max:64'],
        ]);

        $voiceAgent->update($validated);

        return redirect()->route('client.voice.index')->with('success', __('Voice Agent updated successfully.'));
    }

    public function toggle(Request $request, VoiceAgent $voiceAgent): RedirectResponse
    {
        $workspaceId = (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
        abort_if($voiceAgent->workspace_id !== $workspaceId, 403);

        $voiceAgent->update([
            'status' => $voiceAgent->status === 'active' ? 'inactive' : 'active',
        ]);

        return back()->with('success', __('Voice Agent status updated.'));
    }

    public function destroy(Request $request, VoiceAgent $voiceAgent): RedirectResponse
    {
        $workspaceId = (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
        abort_if($voiceAgent->workspace_id !== $workspaceId, 403);

        $voiceAgent->delete();

        return redirect()->route('client.voice.index')->with('success', __('Voice Agent deleted.'));
    }
}
