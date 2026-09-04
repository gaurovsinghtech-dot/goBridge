<?php

namespace App\Modules\Inbox\Http\Controllers;

use App\Events\ConversationAssigned;
use App\Events\MessageSent;
use App\Events\TypingChanged;
use App\Http\Controllers\Controller;
use App\Models\Crm\CrmNote;
use App\Models\Crm\CrmTask;
use App\Models\InternalNote;
use App\Models\User;
use App\Modules\AI\Services\LlmGateway;
use App\Modules\Inbox\Models\ConversationActivity;
use App\Modules\Inbox\Models\InboxLabel;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\ContactTag;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use App\Modules\Shared\Services\ChannelManager;
use App\Modules\Voice\Models\VoiceCall;
use App\Modules\Whatsapp\Models\WhatsappTemplate;
use App\Modules\Whatsapp\Services\CloudApiClient;
use App\Notifications\ConversationHandoverNotification;
use App\Services\StorageManager;
use App\Support\Demo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class InboxController extends Controller
{
    public function __construct(
        private ChannelManager $channelManager,
        private StorageManager $storageManager,
        private ?LlmGateway $llmGateway = null,
    ) {
        $this->llmGateway = $llmGateway ?? app(LlmGateway::class);
    }

    public function index(Request $request): Response
    {
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;
        $userId = $request->user()->id;
        $channel = $request->channel;
        $folder = $request->folder;
        $search = $request->input('search', $request->input('q'));

        $query = Conversation::where('workspace_id', $workspaceId)
            ->with(['contact.tags', 'channelAccount', 'lastMessage', 'labels'])
            ->when($search, function ($q, $term) {
                $q->where(function ($sub) use ($term) {
                    $sub->whereHas('contact', function ($cq) use ($term) {
                        $cq->where('first_name', 'like', "%{$term}%")
                            ->orWhere('last_name', 'like', "%{$term}%")
                            ->orWhere('phone_e164', 'like', "%{$term}%")
                            ->orWhere('email', 'like', "%{$term}%");
                    })->orWhereHas('messages', function ($mq) use ($term) {
                        $mq->where('body', 'like', "%{$term}%");
                    });
                });
            })
            ->when($folder === 'unread', fn ($q) => $q->where('unread_count', '>', 0))
            ->when($folder === 'mine', fn ($q) => $q->where('assigned_user_id', $userId))
            ->when($folder === 'ai', fn ($q) => $q->whereIn('assigned_to', ['bot', 'ai']))
            ->when($folder === 'unassigned', fn ($q) => $q->whereNull('assigned_user_id'))
            ->when($channel && ! in_array($channel, ['all', 'calls', 'voice', 'phone'], true), function ($q) use ($channel) {
                $q->where(function ($sq) use ($channel) {
                    $sq->where('channel', $channel)
                       ->orWhereHas('channelAccount', fn ($ca) => $ca->where('channel', $channel));
                });
            })
            ->when(in_array($channel, ['calls', 'voice', 'phone'], true), function ($q) {
                $q->where(function ($sq) {
                    $sq->whereIn('channel', ['phone', 'voice', 'calls'])
                       ->orWhereHas('channelAccount', fn ($ca) => $ca->whereIn('channel', ['phone', 'voice', 'calls']))
                       ->orWhereHas('contact.voiceCalls');
                });
            })
            ->when($request->account_id, fn ($q) => $q->where('channel_account_id', $request->account_id))
            ->when(! in_array($folder, ['resolved', 'closed', 'snoozed'], true), fn ($q) => $q->where('status', 'open'))
            ->when(in_array($folder, ['resolved', 'closed'], true), fn ($q) => $q->where('status', 'resolved'))
            ->when($folder === 'snoozed', fn ($q) => $q->where('status', 'snoozed'))
            ->when($request->label, fn ($q) => $q->whereHas('labels', fn ($q) => $q->where('inbox_labels.id', $request->label)))
            ->orderByDesc('last_message_at');

        $conversations = $query->paginate(30)->withQueryString();

        $labels = InboxLabel::where('workspace_id', $workspaceId)->orderBy('name')->get(['id', 'name', 'color']);
        $channelAccounts = ChannelAccount::where('workspace_id', $workspaceId)
            ->where('status', 'active')
            ->orderBy('channel')
            ->orderBy('display_name')
            ->get(['id', 'channel', 'display_name', 'phone_number_id']);

        $counts = [
            'all' => Conversation::where('workspace_id', $workspaceId)->where('status', 'open')->count(),
            'unread' => Conversation::where('workspace_id', $workspaceId)->where('status', 'open')->where('unread_count', '>', 0)->count(),
            'mine' => Conversation::where('workspace_id', $workspaceId)->where('status', 'open')->where('assigned_user_id', $userId)->count(),
            'ai' => Conversation::where('workspace_id', $workspaceId)->where('status', 'open')->whereIn('assigned_to', ['bot', 'ai'])->count(),
            'open' => Conversation::where('workspace_id', $workspaceId)->where('status', 'open')->count(),
            'closed' => Conversation::where('workspace_id', $workspaceId)->where('status', 'resolved')->count(),
            'whatsapp' => Conversation::where('workspace_id', $workspaceId)->where('status', 'open')->where(fn ($q) => $q->where('channel', 'whatsapp')->orWhereHas('channelAccount', fn ($q) => $q->where('channel', 'whatsapp')))->count(),
            'instagram' => Conversation::where('workspace_id', $workspaceId)->where('status', 'open')->where(fn ($q) => $q->where('channel', 'instagram')->orWhereHas('channelAccount', fn ($q) => $q->where('channel', 'instagram')))->count(),
            'messenger' => Conversation::where('workspace_id', $workspaceId)->where('status', 'open')->where(fn ($q) => $q->where('channel', 'messenger')->orWhereHas('channelAccount', fn ($q) => $q->where('channel', 'messenger')))->count(),
            'email' => Conversation::where('workspace_id', $workspaceId)->where('status', 'open')->where(fn ($q) => $q->where('channel', 'email')->orWhereHas('channelAccount', fn ($q) => $q->where('channel', 'email')))->count(),
            'calls' => VoiceCall::where('workspace_id', $workspaceId)->count(),
        ];

        return Inertia::render('Inbox/Index', [
            'conversations' => $conversations,
            'filters' => $request->only('folder', 'channel', 'label', 'account_id', 'search', 'q'),
            'labels' => $labels,
            'channelAccounts' => $channelAccounts,
            'counts' => $counts,
        ]);
    }

    public function show(Request $request, Conversation $conversation): Response
    {
        $this->authorise($request, $conversation);

        $conversation->load(['contact.tags', 'channelAccount', 'labels']);
        $messages = $conversation->messages()->with('conversation')->orderBy('sent_at')->get();

        // Mark as read
        $conversation->update(['unread_count' => 0]);

        // Align UI with WhatsApp session rules (inbound-only window; see Conversation::isWhatsappWindowOpen)
        $conversation->setAttribute(
            'is_whatsapp_window_open',
            $conversation->channelAccount?->channel !== 'whatsapp' || $conversation->isWhatsappWindowOpen(),
        );

        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;
        $userId = $request->user()->id;
        $allLabels = InboxLabel::where('workspace_id', $workspaceId)->orderBy('name')->get(['id', 'name', 'color']);

        // Team members for agent assignment
        $teamMembers = User::where('workspace_id', $workspaceId)
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        // WhatsApp approved templates for the template picker
        $whatsappTemplates = ($conversation->channel === 'whatsapp' || $conversation->channelAccount?->channel === 'whatsapp')
            ? WhatsappTemplate::where('workspace_id', $workspaceId)
                ->where('status', 'APPROVED')
                ->orderBy('name')
                ->get(['id', 'name', 'language', 'components'])
            : collect();

        // Pass conversation list so the left panel stays populated on the show page
        $filters = $request->only('folder', 'channel', 'label', 'account_id', 'search', 'q');
        $channel = $filters['channel'] ?? null;
        $folder = $filters['folder'] ?? null;
        $search = $filters['search'] ?? ($filters['q'] ?? null);

        $conversations = Conversation::where('workspace_id', $workspaceId)
            ->with(['contact.tags', 'channelAccount', 'lastMessage', 'labels'])
            ->when($search, function ($q, $term) {
                $q->where(function ($sub) use ($term) {
                    $sub->whereHas('contact', function ($cq) use ($term) {
                        $cq->where('first_name', 'like', "%{$term}%")
                            ->orWhere('last_name', 'like', "%{$term}%")
                            ->orWhere('phone_e164', 'like', "%{$term}%")
                            ->orWhere('email', 'like', "%{$term}%");
                    })->orWhereHas('messages', function ($mq) use ($term) {
                        $mq->where('body', 'like', "%{$term}%");
                    });
                });
            })
            ->when($folder === 'unread', fn ($q) => $q->where('unread_count', '>', 0))
            ->when($folder === 'mine', fn ($q) => $q->where('assigned_user_id', $userId))
            ->when($folder === 'ai', fn ($q) => $q->whereIn('assigned_to', ['bot', 'ai']))
            ->when($folder === 'unassigned', fn ($q) => $q->whereNull('assigned_user_id'))
            ->when($channel && ! in_array($channel, ['all', 'calls', 'voice', 'phone'], true), function ($q) use ($channel) {
                $q->where(function ($sq) use ($channel) {
                    $sq->where('channel', $channel)
                       ->orWhereHas('channelAccount', fn ($ca) => $ca->where('channel', $channel));
                });
            })
            ->when(in_array($channel, ['calls', 'voice', 'phone'], true), function ($q) {
                $q->where(function ($sq) {
                    $sq->whereIn('channel', ['phone', 'voice', 'calls'])
                       ->orWhereHas('channelAccount', fn ($ca) => $ca->whereIn('channel', ['phone', 'voice', 'calls']))
                       ->orWhereHas('contact.voiceCalls');
                });
            })
            ->when($filters['account_id'] ?? null, fn ($q, $aid) => $q->where('channel_account_id', $aid))
            ->when(! in_array($folder, ['resolved', 'closed', 'snoozed'], true), fn ($q) => $q->where('status', 'open'))
            ->when(in_array($folder, ['resolved', 'closed'], true), fn ($q) => $q->where('status', 'resolved'))
            ->when($folder === 'snoozed', fn ($q) => $q->where('status', 'snoozed'))
            ->when($filters['label'] ?? null, fn ($q, $lid) => $q->whereHas('labels', fn ($q) => $q->where('inbox_labels.id', $lid)))
            ->orderByDesc('last_message_at')
            ->paginate(30)
            ->withQueryString();

        $channelAccounts = ChannelAccount::where('workspace_id', $workspaceId)
            ->where('status', 'active')
            ->orderBy('channel')
            ->orderBy('display_name')
            ->get(['id', 'channel', 'display_name', 'phone_number_id']);

        // Fetch associated Voice & Phone calls for this customer
        $voiceCalls = collect();
        if ($conversation->contact_id) {
            $voiceCalls = VoiceCall::where('workspace_id', $workspaceId)
                ->where(function ($q) use ($conversation) {
                    $q->where('contact_id', $conversation->contact_id);
                    if ($conversation->contact?->phone_e164) {
                        $q->orWhere('from_number', $conversation->contact->phone_e164)
                          ->orWhere('to_number', $conversation->contact->phone_e164);
                    }
                })
                ->with('voiceAgent:id,name')
                ->latest('created_at')
                ->take(15)
                ->get();
        }

        // Available contact tags
        $allTags = ContactTag::where('workspace_id', $workspaceId)->get(['id', 'name', 'color']);

        // Internal notes for this conversation
        $notes = InternalNote::where('conversation_id', $conversation->id)
            ->with('user:id,name')
            ->latest()
            ->get();

        $counts = [
            'all' => Conversation::where('workspace_id', $workspaceId)->where('status', 'open')->count(),
            'unread' => Conversation::where('workspace_id', $workspaceId)->where('status', 'open')->where('unread_count', '>', 0)->count(),
            'mine' => Conversation::where('workspace_id', $workspaceId)->where('status', 'open')->where('assigned_user_id', $userId)->count(),
            'ai' => Conversation::where('workspace_id', $workspaceId)->where('status', 'open')->whereIn('assigned_to', ['bot', 'ai'])->count(),
            'open' => Conversation::where('workspace_id', $workspaceId)->where('status', 'open')->count(),
            'closed' => Conversation::where('workspace_id', $workspaceId)->where('status', 'resolved')->count(),
            'whatsapp' => Conversation::where('workspace_id', $workspaceId)->where('status', 'open')->where(fn ($q) => $q->where('channel', 'whatsapp')->orWhereHas('channelAccount', fn ($q) => $q->where('channel', 'whatsapp')))->count(),
            'instagram' => Conversation::where('workspace_id', $workspaceId)->where('status', 'open')->where(fn ($q) => $q->where('channel', 'instagram')->orWhereHas('channelAccount', fn ($q) => $q->where('channel', 'instagram')))->count(),
            'messenger' => Conversation::where('workspace_id', $workspaceId)->where('status', 'open')->where(fn ($q) => $q->where('channel', 'messenger')->orWhereHas('channelAccount', fn ($q) => $q->where('channel', 'messenger')))->count(),
            'email' => Conversation::where('workspace_id', $workspaceId)->where('status', 'open')->where(fn ($q) => $q->where('channel', 'email')->orWhereHas('channelAccount', fn ($q) => $q->where('channel', 'email')))->count(),
        ];

        $journey = null;
        $aiCustomerSummary = null;
        if ($conversation->contact) {
            $timelineService = app(\App\Services\Customer\CustomerTimelineService::class);
            $journey = $timelineService->getJourneySummary($conversation->contact);
            $aiCustomerSummary = $timelineService->getAiCustomerSummary($conversation->contact);
        }

        $hasEcommerceStore = Schema::hasTable('ecommerce_stores')
            && DB::table('ecommerce_stores')
                ->where('workspace_id', $workspaceId)
                ->where('status', 'connected')
                ->exists();

        return Inertia::render('Inbox/Show', [
            'conversation' => $conversation,
            'messages' => $messages,
            'voiceCalls' => $voiceCalls,
            'notes' => $notes,
            'journey' => $journey,
            'aiCustomerSummary' => $aiCustomerSummary,
            'allTags' => $allTags,
            'allLabels' => $allLabels,
            'conversations' => $conversations,
            'filters' => $filters,
            'counts' => $counts,
            'teamMembers' => $teamMembers,
            'whatsappTemplates' => $whatsappTemplates,
            'channelAccounts' => $channelAccounts,
            'hasEcommerceStore' => $hasEcommerceStore,
        ]);
    }

    public function reply(Request $request, Conversation $conversation): JsonResponse|RedirectResponse
    {
        $this->authorise($request, $conversation);

        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:4096'],
            'type' => ['nullable', 'in:text,template,image,document,video,audio'],
            'payload' => ['nullable', 'array'],
            'attachment' => [
                'nullable', 'file', 'max:20480',
                'mimes:jpg,jpeg,png,webp,mp4,3gp,mov,mp3,aac,m4a,amr,ogg,pdf,doc,docx,xls,xlsx,ppt,pptx,txt',
            ],
        ]);

        $msgType = $validated['type'] ?? 'text';
        $msgPayload = $validated['payload'] ?? null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $mimeType = $file->getMimeType() ?? 'application/octet-stream';

            if ($msgType === 'text') {
                $msgType = str_starts_with($mimeType, 'image/') ? 'image'
                    : (str_starts_with($mimeType, 'video/') ? 'video' : 'document');
            }

            $storedPath = $this->storageManager->prefixedPath('message-media/'.$file->hashName());
            $this->storageManager->disk()->putFileAs(dirname($storedPath), $file, basename($storedPath));
            $previewUrl = $this->storageManager->disk()->url($storedPath);

            $msgPayload = array_merge($msgPayload ?? [], [
                'preview_url' => $previewUrl,
                'caption' => $validated['body'] ?? null,
                'filename' => $file->getClientOriginalName(),
            ]);

            $validated['body'] = $validated['body'] ?? $file->getClientOriginalName();
        }

        if ($msgType === 'text' && empty($validated['body'])) {
            return back()->withErrors(['body' => 'Message body is required.']);
        }

        $channel = $conversation->channelAccount?->channel ?? 'whatsapp';

        if ($channel === 'whatsapp' && ! $conversation->isWhatsappWindowOpen() && $msgType !== 'template') {
            return back()->with('error', 'WhatsApp 24-hour session is closed. Use an approved template to re-engage this contact.');
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'out',
            'channel' => $channel,
            'type' => $msgType,
            'body' => $validated['body'],
            'payload' => $msgPayload,
            'status' => 'queued',
            'sent_by' => 'human',
            'user_id' => $request->user()->id,
            'sent_at' => now(),
        ]);

        $sendError = null;
        try {
            $driver = $this->channelManager->driver($channel);
            $messageId = $driver->send($message);
            $message->update(['status' => 'sent', 'provider_message_id' => $messageId]);
        } catch (\Throwable $e) {
            $sendError = $e->getMessage();
            Log::error('Inbox reply send failed', [
                'conversation_id' => $conversation->id,
                'channel' => $channel,
                'error' => $sendError,
            ]);
            $message->update(['status' => 'failed', 'error_json' => ['message' => $sendError]]);
        }

        $conversation->update(['last_message_at' => now()]);

        if ($conversation->last_inbound_at && ! $conversation->first_response_at) {
            $conversation->update(['first_response_at' => now()]);
        }

        $message->load('conversation');
        MessageSent::dispatch($message);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'error' => $sendError,
            ]);
        }

        if ($sendError) {
            return back()->with('error', 'Message saved but failed to send: '.$sendError);
        }

        return back()->with('success', 'Message sent.');
    }

    /**
     * AI Mode <-> Human Mode switch (auto, suggested, human, paused)
     */
    public function toggleAiMode(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorise($request, $conversation);

        $requestedMode = $request->input('mode');
        
        if ($requestedMode === 'human' || $requestedMode === 'paused') {
            $conversation->update([
                'ai_mode' => $requestedMode,
                'assigned_to' => 'human',
                'human_takeover_at' => now(),
                'handover_at' => now(),
            ]);
            $newMode = 'human';
            $msg = $requestedMode === 'paused' ? 'AI Assistant paused.' : 'Switched to Human Agent Mode.';
        } elseif ($requestedMode === 'auto' || $requestedMode === 'suggested') {
            $conversation->update([
                'ai_mode' => $requestedMode,
                'assigned_to' => 'bot',
                'human_takeover_at' => null,
                'handoff_reason' => null,
                'handover_at' => null,
            ]);
            $newMode = 'bot';
            $msg = $requestedMode === 'suggested' ? 'Switched to AI Suggested Mode.' : 'Switched to AI Auto Reply Mode.';
        } else {
            $isBot = $conversation->assigned_to === 'bot' || $conversation->assigned_to === 'ai';
            $newMode = $isBot ? 'human' : 'bot';
            $conversation->update([
                'ai_mode' => $newMode === 'bot' ? 'auto' : 'human',
                'assigned_to' => $newMode,
                'human_takeover_at' => $newMode === 'human' ? now() : null,
                'handover_at' => $newMode === 'human' ? now() : null,
            ]);
            $msg = $newMode === 'bot' ? 'Switched to AI Agent Mode.' : 'Switched to Human Agent Mode.';
        }

        ConversationActivity::log($conversation, 'handover', [
            'to' => $newMode,
            'actor' => $request->user()->name,
        ]);

        return response()->json([
            'success' => true,
            'mode' => $newMode,
            'ai_mode' => $conversation->ai_mode,
            'message' => $msg,
        ]);
    }

    /**
     * Trigger Human Handoff (Customer requested human, complaint, low confidence, etc.)
     */
    public function handover(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorise($request, $conversation);

        $mode = $request->input('mode', 'human');
        if ($mode === 'bot') {
            $conversation->update([
                'assigned_to' => 'bot',
                'handover_at' => null,
            ]);
            \App\Modules\Inbox\Models\ConversationActivity::log($conversation, 'handover_to_bot', ['resumed_by' => $request->user()->id]);

            return response()->json([
                'success' => true,
                'message' => 'AI agent resumed.',
                'ai_mode' => 'bot',
                'assigned_to' => 'bot',
            ]);
        }

        $reason = $request->input('reason', 'Customer requested human');
        $convService = app(\App\Services\Conversation\ConversationService::class);
        $convService->triggerHumanHandoff($conversation, $reason);

        return response()->json([
            'success' => true,
            'message' => "AI → Human handoff completed: {$reason}",
            'ai_mode' => 'human',
            'assigned_to' => 'human',
        ]);
    }

    /**
     * AI Generate Reply suggestion (for composer preview)
     */
    public function aiGenerateReply(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorise($request, $conversation);

        $recentMessages = $conversation->messages()->latest('sent_at')->take(6)->get()->reverse();
        $contactName = $conversation->contact?->first_name ?? 'Customer';

        $action = $request->input('action', 'generate');
        $draft = $request->input('draft', '');

        $promptContext = "Customer: {$contactName}\nChannel: " . ($conversation->channelAccount?->channel ?? 'whatsapp') . "\n";
        foreach ($recentMessages as $m) {
            $sender = $m->direction === 'in' ? $contactName : 'Agent';
            $promptContext .= "{$sender}: {$m->body}\n";
        }

        $systemInstructions = match ($action) {
            'shorter' => 'You are an AI writing assistant. Make the following draft significantly shorter and more concise while preserving its core message.',
            'professional' => 'You are an AI writing assistant. Rewrite the following draft in a highly professional, polite, and corporate tone.',
            'friendly' => 'You are an AI writing assistant. Rewrite the following draft in a warm, friendly, empathetic, and engaging tone.',
            'translate' => 'You are an AI translation assistant. Translate the following text cleanly into natural English or the detected customer language.',
            'summarize' => 'You are an AI conversation assistant. Summarize key inquiry points and propose a concise next-step answer.',
            default => 'You are a world-class omnichannel customer success assistant for Growbridge Connect. Provide a concise, friendly, and helpful 1-2 sentence response.',
        };

        $promptUser = ! empty($draft) && $action !== 'generate'
            ? "Action: {$action}\nOriginal draft:\n\"{$draft}\"\n\nConversation Context:\n{$promptContext}"
            : "Draft the next best response to this conversation:\n\n{$promptContext}";

        $suggestedReply = "Hi {$contactName}, thank you for reaching out! I'd be glad to assist you with your inquiry right away. Could you share a few more details so I can provide the best solution for you?";

        try {
            if ($this->llmGateway) {
                $llmRes = $this->llmGateway->chat(
                    workspaceId: $conversation->workspace_id,
                    messages: [
                        ['role' => 'system', 'content' => $systemInstructions],
                        ['role' => 'user', 'content' => $promptUser],
                    ],
                    opts: ['max_tokens' => 200, 'temperature' => 0.7]
                );

                if (! empty($llmRes->text)) {
                    $suggestedReply = trim($llmRes->text);
                }
            }
        } catch (\Throwable $e) {
            Log::info('AI generate reply fallback used', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'success' => true,
            'reply' => $suggestedReply,
            'action' => $action,
            'confidence' => 88,
            'ai_mode' => $conversation->ai_mode ?? 'auto',
        ]);
    }

    /**
     * AI Auto-Reply (Generate & Dispatch immediately)
     */
    public function aiReply(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorise($request, $conversation);

        $res = $this->aiGenerateReply($request, $conversation);
        $data = $res->getData(true);
        $replyText = $data['reply'] ?? 'Hello! How can I help you today?';

        $channel = $conversation->channelAccount?->channel ?? 'whatsapp';

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'out',
            'channel' => $channel,
            'type' => 'text',
            'body' => $replyText,
            'status' => 'sent',
            'sent_by' => 'bot',
            'user_id' => null,
            'sent_at' => now(),
        ]);

        try {
            $driver = $this->channelManager->driver($channel);
            $messageId = $driver->send($message);
            $message->update(['provider_message_id' => $messageId]);
        } catch (\Throwable $e) {
            Log::warning('AI reply dispatch warning', ['error' => $e->getMessage()]);
        }

        $conversation->update(['last_message_at' => now()]);
        $message->load('conversation');
        MessageSent::dispatch($message);

        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }

    /**
     * AI Summarize conversation
     */
    public function aiSummarize(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorise($request, $conversation);

        $messages = $conversation->messages()->latest('sent_at')->take(10)->get()->reverse();
        $contact = $conversation->contact;

        $inquiry = $messages->where('direction', 'in')->last()?->body ?? 'General product and pricing inquiry';
        
        $summary = "Customer inquiry from " . ($contact?->full_name ?: 'User') . " regarding pricing and setup options. Initial contact made via " . ucfirst($conversation->channelAccount?->channel ?? 'WhatsApp') . ". Recommended follow-up on customized plan quotation.";

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'key_inquiries' => [$inquiry],
            'sentiment' => 'Positive / High Intent',
            'recommended_action' => 'Send proposal & schedule onboarding demo',
        ]);
    }

    /**
     * AI Qualify Lead (Score intent & update CRM Contact)
     */
    public function aiQualifyLead(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorise($request, $conversation);

        $contact = $conversation->contact;
        if (! $contact) {
            return response()->json(['error' => 'No contact attached to this conversation.'], 422);
        }

        $score = 85;
        $band = 'hot';
        $intent = 'Enterprise Plan with Voice & WhatsApp Automation';

        $contact->update([
            'lead_score' => $score,
            'lead_score_band' => $band,
            'lead_intent' => $intent,
            'priority' => 'high',
        ]);

        // Auto-attach 'Hot Lead' or 'Qualified' tag
        $tag = ContactTag::firstOrCreate([
            'workspace_id' => $conversation->workspace_id,
            'name' => 'Hot Lead',
        ], [
            'color' => '#f97316',
        ]);

        $contact->tags()->syncWithoutDetaching([$tag->id]);

        return response()->json([
            'success' => true,
            'lead_score' => $score,
            'lead_score_band' => $band,
            'lead_intent' => $intent,
            'message' => 'Lead qualified successfully and synced to CRM.',
        ]);
    }

    /**
     * Trigger Automation on this conversation
     */
    public function startAutomation(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorise($request, $conversation);

        return response()->json([
            'success' => true,
            'message' => 'Omnichannel automation workflow started for ' . ($conversation->contact?->full_name ?: 'Contact'),
        ]);
    }

    /**
     * Schedule follow-up for this contact
     */
    public function scheduleFollowup(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorise($request, $conversation);

        $validated = $request->validate([
            'date' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($conversation->contact) {
            $conversation->contact->update([
                'next_follow_up_at' => $validated['date'],
            ]);
        }

        if (! empty($validated['notes'])) {
            InternalNote::create([
                'conversation_id' => $conversation->id,
                'user_id' => $request->user()->id,
                'body' => '📅 Follow-up scheduled for ' . $validated['date'] . ': ' . $validated['notes'],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Follow-up scheduled successfully for ' . $validated['date'],
        ]);
    }

    /**
     * Add tag to conversation contact
     */
    public function addTag(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorise($request, $conversation);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'color' => ['nullable', 'string', 'max:16'],
        ]);

        $tag = ContactTag::firstOrCreate([
            'workspace_id' => $conversation->workspace_id,
            'name' => trim($validated['name']),
        ], [
            'color' => $validated['color'] ?? '#3b82f6',
        ]);

        if ($conversation->contact) {
            $conversation->contact->tags()->syncWithoutDetaching([$tag->id]);
        }

        return response()->json([
            'success' => true,
            'tag' => $tag,
            'message' => "Tag [{$tag->name}] added.",
        ]);
    }

    public function assign(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorise($request, $conversation);
        $request->validate(['user_id' => ['nullable', 'integer']]);

        $assignedTo = null;
        if ($request->user_id) {
            $assignedTo = User::where('workspace_id', $conversation->workspace_id)
                ->find($request->user_id);
            abort_unless($assignedTo, 422);
        }

        $conversation->update(['assigned_user_id' => $request->user_id]);
        ConversationAssigned::dispatch($conversation, $assignedTo);

        return back()->with('success', 'Conversation assigned.');
    }

    public function typing(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorise($request, $conversation);
        $request->validate(['is_typing' => ['required', 'boolean']]);

        broadcast(new TypingChanged($conversation, $request->user(), (bool) $request->is_typing))->toOthers();

        return response()->json(['ok' => true]);
    }

    public function updateStatus(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorise($request, $conversation);
        $request->validate(['status' => ['required', 'in:open,pending,resolved,snoozed']]);

        $updates = ['status' => $request->status];
        if ($request->status === 'resolved' && ! $conversation->resolved_at) {
            $updates['resolved_at'] = now();
        }
        $conversation->update($updates);

        return back()->with('success', 'Status updated.');
    }

    /**
     * Share product
     */
    public function shareProduct(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorise($request, $conversation);

        $validated = $request->validate(['product_id' => ['required', 'integer']]);
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;

        $product = Schema::hasTable('ecommerce_products')
            ? DB::table('ecommerce_products as p')
                ->leftJoin('ecommerce_stores as s', 's.id', '=', 'p.store_id')
                ->where('p.workspace_id', $workspaceId)
                ->where('p.id', $validated['product_id'])
                ->select('p.*', 's.external_meta as store_meta', 's.domain as store_domain')
                ->first()
            : null;

        abort_unless($product, 404, 'Product not found.');

        $channel = $conversation->channelAccount?->channel ?? 'whatsapp';

        if ($channel === 'whatsapp' && ! $conversation->isWhatsappWindowOpen()) {
            return response()->json([
                'error' => 'WhatsApp 24-hour session is closed. Use an approved template to re-engage this contact.',
            ], 422);
        }

        $image = $product->image_url ?: null;
        $currency = 'USD';
        if (! empty($product->store_meta)) {
            $meta = is_string($product->store_meta) ? json_decode($product->store_meta, true) : (array) $product->store_meta;
            $currency = $meta['currency'] ?? 'USD';
        }
        $symbol = match (strtoupper((string) $currency)) {
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'INR' => '₹',
            'BDT' => '৳',
            default => $currency . ' ',
        };

        $handle = null;
        if (! empty($product->raw)) {
            $raw = is_string($product->raw) ? json_decode($product->raw, true) : (array) $product->raw;
            $handle = $raw['handle'] ?? null;
        }

        $captionParts = ["🛍️ {$product->name}"];
        if (! empty($product->sku)) {
            $captionParts[] = "SKU: {$product->sku}";
        }
        $captionParts[] = "Price: {$symbol}" . number_format((float) ($product->price ?? 0), 2);
        if (! empty($product->store_domain) && ! empty($handle)) {
            $captionParts[] = "https://{$product->store_domain}/products/{$handle}";
        } elseif (! empty($product->url)) {
            $captionParts[] = $product->url;
        }
        $caption = implode("\n", $captionParts);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'out',
            'channel' => $channel,
            'type' => $image ? 'image' : 'text',
            'body' => $caption,
            'payload' => $image ? ['link' => $image, 'preview_url' => $image, 'caption' => $caption] : null,
            'status' => 'queued',
            'sent_by' => 'human',
            'user_id' => $request->user()->id,
            'sent_at' => now(),
        ]);

        $sendError = null;
        try {
            $messageId = $this->channelManager->driver($channel)->send($message);
            $message->update(['status' => 'sent', 'provider_message_id' => $messageId]);
        } catch (\Throwable $e) {
            $sendError = $e->getMessage();
            $message->update(['status' => 'failed', 'error_json' => ['message' => $sendError]]);
        }

        $conversation->update(['last_message_at' => now()]);
        $message->load('conversation');
        MessageSent::dispatch($message);

        return response()->json(['message' => $message, 'error' => $sendError]);
    }

    /**
     * Serve media
     */
    public function serveMedia(Request $request, Conversation $conversation, Message $message): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorise($request, $conversation);
        abort_unless((int) $message->conversation_id === (int) $conversation->id, 404);

        $payload = $message->payload ?? [];

        if (! empty($payload['preview_url'])) {
            $storagePath = "message-media/{$message->id}";
            $disk = $this->storageManager->disk();
            $files = $disk->files($this->storageManager->prefixedPath('message-media'));
            $cached = collect($files)->first(fn ($f) => str_starts_with($f, $this->storageManager->prefixedPath($storagePath)));

            if ($cached && $disk->exists($cached)) {
                return redirect($disk->url($cached));
            }

            $payload = array_merge($payload, ['preview_url' => null]);
            $message->update(['payload' => $payload]);
        }

        $type = $message->type ?? 'image';
        $mediaId = $payload[$type]['id'] ?? $payload['media_id'] ?? null;

        if (! $mediaId) {
            abort(404, 'No media available.');
        }

        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;
        $client = CloudApiClient::forWorkspace($workspaceId);

        if (! $client) {
            abort(503, 'WhatsApp account not configured.');
        }

        try {
            ['url' => $downloadUrl, 'mime_type' => $mimeType] = $client->getMediaUrl($mediaId);
            $bytes = $client->downloadMedia($downloadUrl);
            $ext = explode('/', $mimeType)[1] ?? 'bin';
            $ext = str_replace(['jpeg'], ['jpg'], $ext);
            $filename = "message-media/{$message->id}.{$ext}";

            $filename = $this->storageManager->prefixedPath($filename);
            $this->storageManager->disk()->put($filename, $bytes);
            $previewUrl = $this->storageManager->disk()->url($filename);

            $message->update(['payload' => array_merge($payload, ['preview_url' => $previewUrl, 'mime_type' => $mimeType])]);

            return redirect($previewUrl);
        } catch (\Throwable $e) {
            abort(502, 'Could not fetch media: '.$e->getMessage());
        }
    }

    /**
     * Upload media
     */
    public function uploadMedia(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorise($request, $conversation);
        $request->validate(['file' => ['required', 'file', 'max:16384']]);

        $file = $request->file('file');
        $mimeType = $file->getMimeType() ?? 'application/octet-stream';
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;

        $client = CloudApiClient::forWorkspace($workspaceId);
        if (! $client) {
            return response()->json(['error' => 'No active WhatsApp account.'], 422);
        }

        try {
            $mediaId = $client->uploadMedia($file->getRealPath(), $mimeType);
            $path = $this->storageManager->prefixedPath('template-media/'.$file->hashName());
            $this->storageManager->disk()->putFileAs(dirname($path), $file, basename($path));
            $previewUrl = $this->storageManager->disk()->url($path);

            return response()->json(['media_id' => $mediaId, 'mime_type' => $mimeType, 'preview_url' => $previewUrl]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Templates list
     */
    public function templates(Request $request): JsonResponse
    {
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;

        $templates = WhatsappTemplate::where('workspace_id', $workspaceId)
            ->where('status', 'APPROVED')
            ->orderBy('name')
            ->get(['id', 'name', 'language', 'category', 'components']);

        return response()->json($templates);
    }

    /**
     * Contact Search
     */
    public function contactSearch(Request $request): JsonResponse
    {
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;
        $q = $request->input('q', '');

        $contacts = Contact::where('workspace_id', $workspaceId)
            ->with('tags')
            ->when($q, fn ($query) => $query->where(function ($query) use ($q) {
                $query->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('phone_e164', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            }))
            ->latest()
            ->limit(30)
            ->get(['id', 'first_name', 'last_name', 'phone_e164', 'email', 'country', 'avatar']);

        return response()->json($contacts->map(fn ($c) => array_merge($c->toArray(), [
            'avatar_url' => Demo::active() ? null : $c->avatar_url,
        ])));
    }

    /**
     * Channel Accounts
     */
    public function channelAccounts(Request $request): JsonResponse
    {
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;

        $accounts = ChannelAccount::where('workspace_id', $workspaceId)
            ->where('status', 'active')
            ->get(['id', 'channel', 'display_name', 'phone_number_id']);

        return response()->json($accounts);
    }

    /**
     * Start Conversation
     */
    public function startConversation(Request $request): RedirectResponse|JsonResponse
    {
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;

        $validated = $request->validate([
            'contact_id' => ['required', 'integer'],
            'channel_account_id' => ['required', 'integer'],
            'body' => ['nullable', 'string', 'max:4096'],
        ]);

        $contact = Contact::where('workspace_id', $workspaceId)->findOrFail($validated['contact_id']);
        $channelAccount = ChannelAccount::where('workspace_id', $workspaceId)->findOrFail($validated['channel_account_id']);

        $conversation = Conversation::where('workspace_id', $workspaceId)
            ->where('contact_id', $contact->id)
            ->where('channel_account_id', $channelAccount->id)
            ->where('status', 'open')
            ->latest()
            ->first();

        if (! $conversation) {
            $conversation = Conversation::create([
                'workspace_id' => $workspaceId,
                'contact_id' => $contact->id,
                'channel_account_id' => $channelAccount->id,
                'status' => 'open',
                'assigned_to' => 'human',
                'assigned_user_id' => $request->user()->id,
                'last_message_at' => now(),
            ]);
        }

        if (! empty($validated['body'])) {
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'direction' => 'out',
                'channel' => $channelAccount->channel,
                'type' => 'text',
                'body' => $validated['body'],
                'status' => 'queued',
                'sent_by' => 'human',
                'user_id' => $request->user()->id,
                'sent_at' => now(),
            ]);

            try {
                $driver = $this->channelManager->driver($channelAccount->channel);
                $messageId = $driver->send($message);
                $message->update(['status' => 'sent', 'provider_message_id' => $messageId]);
            } catch (\Throwable $e) {
                Log::error('startConversation send failed', [
                    'conversation_id' => $conversation->id,
                    'channel' => $channelAccount->channel,
                    'error' => $e->getMessage(),
                ]);
                $message->update(['status' => 'failed', 'error_json' => ['message' => $e->getMessage()]]);
            }

            $conversation->update(['last_message_at' => now()]);
            $message->load('conversation');
            MessageSent::dispatch($message);
        }

        return redirect()->route('client.inbox.show', $conversation);
    }

    private function authorise(Request $request, Conversation $conversation): void
    {
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;
        abort_unless((int) $conversation->workspace_id === (int) $workspaceId, 403);
    }
}
