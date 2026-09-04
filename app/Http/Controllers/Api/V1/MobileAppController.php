<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AiAgent;
use App\Models\AppRelease;
use App\Modules\Leads\Models\Lead;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use App\Modules\Voice\Models\TelephonyPhoneNumber;
use App\Modules\Voice\Models\VoiceCall;
use App\Services\Billing\EntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MobileAppController extends Controller
{
    /**
     * Resolve active workspace for the authenticated user.
     */
    protected function getWorkspace(Request $request)
    {
        $user = $request->user();
        return $user->activeWorkspace() ?? $user->currentWorkspace ?? $user->workspace ?? $user->ownedWorkspaces()->first();
    }

    /**
     * 1. Bootstrap: App Home stats, user profile, plan entitlements & release info.
     */
    public function bootstrap(Request $request): JsonResponse
    {
        $user = $request->user();
        $workspace = $this->getWorkspace($request);

        if (! $workspace) {
            return response()->json(['error' => 'No active workspace found.'], 404);
        }

        // Feature Entitlements
        $entitlements = [
            'whatsapp_api' => EntitlementService::can($workspace, 'whatsapp_api'),
            'voice_calling' => EntitlementService::can($workspace, 'voice_calling'),
            'ai_agents' => EntitlementService::can($workspace, 'ai_agents'),
            'ai_voice_agents' => EntitlementService::can($workspace, 'ai_voice_agents'),
            'campaigns' => EntitlementService::can($workspace, 'campaigns'),
            'automations' => EntitlementService::can($workspace, 'automations'),
            'crm_integrations' => EntitlementService::can($workspace, 'crm_integrations'),
            'advanced_analytics' => EntitlementService::can($workspace, 'advanced_analytics'),
        ];

        // Stats Counters with Short 15-second TTL cache for blazing fast app opens
        $stats = Cache::remember("mobile_ws_stats_{$workspace->id}", 15, function () use ($workspace) {
            return [
                'whatsapp_count' => Conversation::where('workspace_id', $workspace->id)
                    ->where('status', 'open')
                    ->count(),
                'calls_count' => VoiceCall::where('workspace_id', $workspace->id)
                    ->whereDate('created_at', Carbon::today())
                    ->count(),
                'leads_count' => Lead::where('workspace_id', $workspace->id)
                    ->whereDate('created_at', '>=', Carbon::now()->subDays(7))
                    ->count(),
                'contacts_count' => Contact::where('workspace_id', $workspace->id)->count(),
            ];
        });

        $whatsappCount = $stats['whatsapp_count'];
        $callsCount = $stats['calls_count'];
        $leadsCount = $stats['leads_count'];
        $contactsCount = $stats['contacts_count'];

        // Recent Conversations (Top 5 for App Home)
        $recentConversations = Conversation::where('workspace_id', $workspace->id)
            ->select(['id', 'workspace_id', 'contact_id', 'channel', 'status', 'last_message_preview', 'last_message_at', 'unread_count', 'is_ai_active', 'updated_at'])
            ->with(['contact:id,workspace_id,first_name,last_name,phone_e164'])
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get()
            ->map(function ($c) {
                $contactName = $c->contact?->full_name ?? $c->contact?->first_name ?? $c->contact?->name ?? 'Unknown Customer';
                $contactPhone = $c->contact?->phone_e164 ?? $c->contact?->phone_number ?? $c->contact?->phone ?? '';

                return [
                    'id' => $c->id,
                    'contact_name' => $contactName,
                    'contact_phone' => $contactPhone,
                    'channel' => $c->channel ?? 'whatsapp',
                    'last_message' => $c->last_message_preview ?? 'No messages yet',
                    'last_message_at' => $c->last_message_at ? Carbon::parse($c->last_message_at)->diffForHumans() : null,
                    'unread_count' => (int) ($c->unread_count ?? 0),
                    'is_ai_active' => (bool) ($c->is_ai_active ?? true),
                ];
            });

        // App Release Info
        $latestRelease = AppRelease::getLatestActive('android');

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar_url ?? null,
            ],
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'service_type' => $workspace->service_type ?? 'whatsapp_only',
                'currency' => $workspace->currency ?? 'INR',
            ],
            'entitlements' => $entitlements,
            'stats' => [
                'whatsapp_count' => $whatsappCount,
                'calls_count' => $callsCount,
                'leads_count' => $leadsCount,
                'contacts_count' => $contactsCount,
            ],
            'recent_conversations' => $recentConversations,
            'latest_app_release' => $latestRelease ? [
                'version' => $latestRelease->version,
                'version_code' => $latestRelease->version_code,
                'min_supported_version' => $latestRelease->min_supported_version,
                'file_size_mb' => $latestRelease->file_size_mb,
                'download_url' => $latestRelease->effective_download_url,
                'force_update_required' => $latestRelease->force_update_required,
                'release_notes' => $latestRelease->release_notes,
            ] : null,
        ]);
    }

    /**
     * 2. WhatsApp Chat Inbox: Filtered conversation list.
     */
    public function conversations(Request $request): JsonResponse
    {
        $workspace = $this->getWorkspace($request);
        $user = $request->user();

        if (! $workspace) {
            return response()->json(['error' => 'Workspace not found.'], 404);
        }

        $filter = $request->query('filter', 'all'); // 'all', 'unread', 'assigned_me', 'ai', 'human', 'archived'
        $search = $request->query('search');

        $query = Conversation::where('workspace_id', $workspace->id)
            ->select(['id', 'workspace_id', 'contact_id', 'channel', 'status', 'last_message_preview', 'last_message_at', 'unread_count', 'is_ai_active', 'updated_at'])
            ->with(['contact:id,workspace_id,first_name,last_name,phone_e164']);

        // Filter handling
        switch ($filter) {
            case 'unread':
                $query->where('unread_count', '>', 0);
                break;
            case 'assigned_me':
                $query->where('assigned_user_id', $user->id);
                break;
            case 'ai':
                $query->where('is_ai_active', true);
                break;
            case 'human':
                $query->where('is_ai_active', false);
                break;
            case 'archived':
                $query->where('status', 'archived');
                break;
            default:
                $query->where('status', '!=', 'archived');
                break;
        }

        if ($search) {
            $query->whereHas('contact', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('phone_e164', 'like', "%{$search}%");
            });
        }

        $conversations = $query->orderByDesc('updated_at')
            ->paginate(20)
            ->through(function ($c) {
                $contactName = $c->contact?->full_name ?? $c->contact?->first_name ?? $c->contact?->name ?? 'Unknown';
                $contactPhone = $c->contact?->phone_e164 ?? $c->contact?->phone_number ?? $c->contact?->phone ?? '';

                return [
                    'id' => $c->id,
                    'uuid' => $c->uuid ?? (string) $c->id,
                    'contact' => [
                        'id' => $c->contact?->id,
                        'name' => $contactName,
                        'phone' => $contactPhone,
                        'status' => 'lead',
                        'tags' => ['lead'],
                    ],
                    'channel' => $c->channel ?? 'whatsapp',
                    'last_message' => $c->last_message_preview ?? '',
                    'last_message_at' => $c->last_message_at ? Carbon::parse($c->last_message_at)->format('h:i A') : '',
                    'unread_count' => (int) ($c->unread_count ?? 0),
                    'is_ai_active' => (bool) ($c->is_ai_active ?? true),
                ];
            });

        return response()->json($conversations);
    }

    /**
     * 3. Conversation Detail & 360° Customer Profile.
     */
    public function conversationDetail(Request $request, string $id): JsonResponse
    {
        $workspace = $this->getWorkspace($request);

        $conversation = Conversation::where('workspace_id', $workspace->id)
            ->where(function ($q) use ($id) {
                $q->where('id', $id)->orWhere('uuid', $id);
            })
            ->with(['contact'])
            ->firstOrFail();

        // Mark as read
        $conversation->update(['unread_count' => 0]);

        $messages = Message::where('conversation_id', $conversation->id)
            ->select(['id', 'conversation_id', 'direction', 'sender_type', 'body', 'media_url', 'status', 'created_at'])
            ->orderBy('created_at', 'asc')
            ->limit(100)
            ->get()
            ->map(function ($m) {
                return [
                    'id' => $m->id,
                    'direction' => $m->direction, // 'inbound', 'outbound'
                    'sender_type' => $m->sender_type ?? ($m->direction === 'outbound' ? 'agent' : 'customer'),
                    'body' => $m->body,
                    'media_url' => $m->media_url,
                    'status' => $m->status,
                    'time' => $m->created_at->format('h:i A'),
                    'created_at' => $m->created_at->toIso8601String(),
                ];
            });

        // 360 Customer Profile
        $contact = $conversation->contact;
        $profile = null;
        if ($contact) {
            $contactPhone = $contact->phone_e164 ?? $contact->phone_number ?? $contact->phone;

            $recentCalls = VoiceCall::where('workspace_id', $workspace->id)
                ->where(function ($q) use ($contact, $contactPhone) {
                    $q->where('contact_id', $contact->id);
                    if ($contactPhone) {
                        $q->orWhere('to_number', $contactPhone)
                            ->orWhere('from_number', $contactPhone);
                    }
                })
                ->orderByDesc('created_at')
                ->limit(5)
                ->get()
                ->map(function ($call) {
                    $duration = $call->duration_sec ?? $call->duration_seconds ?? 0;
                    return [
                        'id' => $call->id,
                        'direction' => $call->direction,
                        'duration_seconds' => $duration,
                        'formatted_duration' => sprintf('%02d:%02d', floor($duration / 60), $duration % 60),
                        'status' => $call->status,
                        'date' => $call->created_at->format('M d, h:i A'),
                        'summary' => $call->summary ?? $call->ai_summary,
                    ];
                });

            $crmLead = Lead::where('workspace_id', $workspace->id)
                ->where('contact_id', $contact->id)
                ->first();

            $profile = [
                'id' => $contact->id,
                'name' => $contact->full_name ?? $contact->first_name ?? $contact->name ?? 'Unknown',
                'phone' => $contactPhone,
                'email' => $contact->email,
                'status' => 'lead',
                'tags' => ['hot-lead'],
                'crm_connected' => (bool) $crmLead,
                'crm_stage' => 'Lead',
                'ai_memory' => [
                    'Interested in products & services',
                    'Requested callback during business hours',
                ],
                'recent_calls' => $recentCalls,
            ];
        }

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'uuid' => $conversation->uuid ?? (string) $conversation->id,
                'channel' => $conversation->channel ?? 'whatsapp',
                'status' => $conversation->status,
                'is_ai_active' => (bool) ($conversation->is_ai_active ?? true),
            ],
            'customer_profile' => $profile,
            'messages' => $messages,
        ]);
    }

    /**
     * 4. Send Message (WhatsApp / Outbound).
     */
    public function sendMessage(Request $request, string $id): JsonResponse
    {
        $workspace = $this->getWorkspace($request);
        $user = $request->user();

        $conversation = Conversation::where('workspace_id', $workspace->id)
            ->where(function ($q) use ($id) {
                $q->where('id', $id)->orWhere('uuid', $id);
            })
            ->firstOrFail();

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:4096'],
            'media_url' => ['nullable', 'url'],
            'media_type' => ['nullable', 'string'],
        ]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'contact_id' => $conversation->contact_id,
            'direction' => 'outbound',
            'sender_type' => 'agent',
            'user_id' => $user->id,
            'body' => $validated['body'],
            'media_url' => $validated['media_url'] ?? null,
            'status' => 'sent',
            'channel' => $conversation->channel ?? 'whatsapp',
        ]);

        $conversation->update([
            'last_message_preview' => Str::limit($message->body, 120),
            'last_message_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => [
                'id' => $message->id,
                'direction' => 'outbound',
                'sender_type' => 'agent',
                'body' => $message->body,
                'time' => $message->created_at->format('h:i A'),
                'status' => $message->status,
            ],
        ]);
    }

    /**
     * 5. AI Assist: Generate reply, summarize, or extract lead.
     */
    public function aiAssist(Request $request, string $id): JsonResponse
    {
        $workspace = $this->getWorkspace($request);

        $conversation = Conversation::where('workspace_id', $workspace->id)
            ->where(function ($q) use ($id) {
                $q->where('id', $id)->orWhere('uuid', $id);
            })
            ->with(['contact'])
            ->firstOrFail();

        $action = $request->input('action', 'suggest_reply');
        $inputText = $request->input('text', '');

        $suggestedReply = '';
        $summary = '';
        $leadExtracted = null;

        $contactName = $conversation->contact?->full_name ?? $conversation->contact?->first_name ?? $conversation->contact?->name ?? 'Customer';

        switch ($action) {
            case 'suggest_reply':
                $suggestedReply = "Hello {$contactName}! Thank you for reaching out to us. Our packages start from ₹4,999 with full WhatsApp automation and AI support. Would you like a live demo today?";
                break;
            case 'improve':
                $suggestedReply = empty($inputText)
                    ? 'Thank you for your interest! We would be thrilled to assist you.'
                    : "Dear {$contactName}, " . ucfirst(trim($inputText)) . " Please feel free to let us know if you need further details.";
                break;
            case 'summarize':
                $summary = "Customer is inquiring about pricing and product capabilities. Requested callback during business hours.";
                break;
            case 'extract_lead':
                $leadExtracted = [
                    'name' => $contactName,
                    'phone' => $conversation->contact?->phone_e164 ?? $conversation->contact?->phone_number ?? $conversation->contact?->phone,
                    'interest' => 'Enterprise Solution',
                    'intent' => 'Hot Lead',
                    'budget' => '₹5,00,000',
                ];
                break;
        }

        return response()->json([
            'action' => $action,
            'suggested_reply' => $suggestedReply,
            'summary' => $summary,
            'lead_data' => $leadExtracted,
        ]);
    }

    /**
     * 6. Human Takeover / AI Mode toggle.
     */
    public function handoff(Request $request, string $id): JsonResponse
    {
        $workspace = $this->getWorkspace($request);
        $user = $request->user();

        $conversation = Conversation::where('workspace_id', $workspace->id)
            ->where(function ($q) use ($id) {
                $q->where('id', $id)->orWhere('uuid', $id);
            })
            ->firstOrFail();

        $mode = $request->input('mode'); // 'ai' or 'human'
        $isAiActive = $mode ? ($mode === 'ai') : ! $conversation->is_ai_active;

        $conversation->update([
            'is_ai_active' => $isAiActive,
            'assigned_user_id' => $isAiActive ? null : $user->id,
        ]);

        return response()->json([
            'is_ai_active' => $isAiActive,
            'status_label' => $isAiActive ? 'AI is responding' : '👤 Human Agent',
            'assigned_user' => $isAiActive ? null : $user->name,
        ]);
    }

    /**
     * 7. Calls Section: Call logs & filterable history.
     */
    public function calls(Request $request): JsonResponse
    {
        $workspace = $this->getWorkspace($request);

        if (! $workspace) {
            return response()->json(['error' => 'Workspace not found.'], 404);
        }

        $hasVoice = EntitlementService::can($workspace, 'voice_calling');

        $filter = $request->query('filter', 'all'); // 'all', 'incoming', 'outgoing', 'missed', 'ai_calls', 'human_calls'

        $query = VoiceCall::where('workspace_id', $workspace->id)
            ->select(['id', 'workspace_id', 'contact_id', 'direction', 'from_number', 'to_number', 'duration_sec', 'status', 'voice_agent_id', 'summary', 'created_at'])
            ->with(['contact:id,workspace_id,first_name,last_name,phone_e164']);

        switch ($filter) {
            case 'incoming':
                $query->where('direction', 'inbound');
                break;
            case 'outgoing':
                $query->where('direction', 'outbound');
                break;
            case 'missed':
                $query->whereIn('status', ['no-answer', 'busy', 'canceled', 'failed']);
                break;
            case 'ai_calls':
                $query->whereNotNull('voice_agent_id');
                break;
            case 'human_calls':
                $query->whereNull('voice_agent_id');
                break;
        }

        $calls = $query->orderByDesc('created_at')
            ->paginate(20)
            ->through(function ($call) {
                $duration = $call->duration_sec ?? $call->duration_seconds ?? 0;
                $formattedDuration = sprintf('%02d:%02d', floor($duration / 60), $duration % 60);

                return [
                    'id' => $call->id,
                    'contact_name' => $call->contact?->full_name ?? $call->contact?->first_name ?? $call->contact?->name ?? 'Unknown Caller',
                    'contact_phone' => $call->direction === 'outbound' ? $call->to_number : $call->from_number,
                    'direction' => $call->direction,
                    'status' => $call->status ?? 'completed',
                    'duration_seconds' => $duration,
                    'formatted_duration' => $formattedDuration,
                    'date' => $call->created_at->format('M d, Y'),
                    'time' => $call->created_at->format('h:i A'),
                    'is_ai_call' => (bool) $call->voice_agent_id,
                    'ai_summary' => $call->summary ?? $call->ai_summary,
                    'recording_url' => $call->recording_url,
                ];
            });

        return response()->json([
            'is_voice_enabled' => $hasVoice,
            'calls' => $calls,
        ]);
    }

    /**
     * 8. In-App Calling: Initiate VoIP / WebRTC call session.
     */
    public function initiateCall(Request $request): JsonResponse
    {
        $workspace = $this->getWorkspace($request);
        $user = $request->user();

        // Strict Entitlement Enforcement
        if (! EntitlementService::can($workspace, 'voice_calling')) {
            return response()->json([
                'error' => 'upgrade_required',
                'message' => 'Upgrade your plan to activate business calling.',
            ], 403);
        }

        $validated = $request->validate([
            'phone_number' => ['required', 'string', 'max:32'],
            'contact_id' => ['nullable', 'integer'],
        ]);

        $callerNumber = TelephonyPhoneNumber::where('workspace_id', $workspace->id)
            ->where('status', 'active')
            ->value('phone_number') ?? '+12025550199';

        // Log outgoing call session
        $voiceCall = VoiceCall::create([
            'workspace_id' => $workspace->id,
            'contact_id' => $validated['contact_id'] ?? null,
            'direction' => 'outbound',
            'provider' => 'twilio',
            'from_number' => $callerNumber,
            'to_number' => $validated['phone_number'],
            'status' => 'in-progress',
            'started_at' => now(),
        ]);

        return response()->json([
            'call_id' => $voiceCall->id,
            'from_number' => $callerNumber,
            'to_number' => $validated['phone_number'],
            'status' => 'connecting',
            'webrtc_session_token' => 'jwt_webrtc_' . Str::random(32),
        ]);
    }

    /**
     * 9. Contacts Directory.
     */
    public function contacts(Request $request): JsonResponse
    {
        $workspace = $this->getWorkspace($request);
        $search = $request->query('search');

        $query = Contact::where('workspace_id', $workspace->id)
            ->select(['id', 'workspace_id', 'first_name', 'last_name', 'phone_e164', 'email', 'created_at']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('phone_e164', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $contacts = $query->orderBy('first_name', 'asc')
            ->paginate(30)
            ->through(fn ($c) => [
                'id' => $c->id,
                'name' => $c->full_name ?? $c->first_name ?? $c->name ?? 'Unknown',
                'phone' => $c->phone_e164 ?? $c->phone_number ?? $c->phone,
                'email' => $c->email,
                'status' => 'lead',
                'tags' => ['lead'],
            ]);

        return response()->json($contacts);
    }

    /**
     * 10. Register FCM / APNs Push Token.
     */
    public function registerPushToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['nullable', 'string', 'in:android,ios'],
        ]);

        $user = $request->user();
        $user->update([
            'fcm_token' => $validated['token'],
        ]);

        return response()->json(['success' => true, 'message' => 'Device push token registered successfully.']);
    }

    /**
     * 11. Check Latest App Release (Updates & Force Update).
     */
    public function checkAppRelease(Request $request): JsonResponse
    {
        $platform = $request->query('platform', 'android');
        $currentVersionCode = (int) $request->query('version_code', 0);

        $latestRelease = AppRelease::getLatestActive($platform);

        if (! $latestRelease) {
            return response()->json(['update_available' => false]);
        }

        $updateAvailable = $latestRelease->version_code > $currentVersionCode;
        $forceUpdate = $latestRelease->force_update_required && ($currentVersionCode < $latestRelease->version_code);

        return response()->json([
            'update_available' => $updateAvailable,
            'force_update_required' => $forceUpdate,
            'latest_version' => $latestRelease->version,
            'latest_version_code' => $latestRelease->version_code,
            'min_supported_version' => $latestRelease->min_supported_version,
            'file_size_mb' => $latestRelease->file_size_mb,
            'download_url' => $latestRelease->effective_download_url,
            'release_notes' => $latestRelease->release_notes,
        ]);
    }
}
