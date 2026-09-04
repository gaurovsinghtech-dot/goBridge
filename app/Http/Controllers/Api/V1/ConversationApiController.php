<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\ConversationResource;
use App\Http\Resources\Api\V1\MessageResource;
use App\Modules\Shared\Models\Conversation;
use App\Services\Conversation\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ConversationApiController extends WorkspaceScopedController
{
    public function __construct(
        private readonly ?ConversationService $conversationService = null,
    ) {}

    /**
     * GET /api/v1/conversations
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $wsId = $this->workspaceId($request);
        $query = Conversation::with('channelAccount')
            ->where('workspace_id', $wsId)
            ->latest('last_message_at');

        if ($request->filled('channel')) {
            $query->where(function ($q) use ($request) {
                $q->where('channel', $request->channel)
                  ->orWhereHas('channelAccount', fn ($ca) => $ca->where('channel', $request->channel));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('assigned_to')) {
            $query->where('assigned_user_id', $request->assigned_to);
        }

        return ConversationResource::collection($query->cursorPaginate(25));
    }

    /**
     * GET /api/v1/conversations/{id}/messages
     */
    public function messages(Request $request, int $id): AnonymousResourceCollection|JsonResponse
    {
        $conversation = Conversation::where('workspace_id', $this->workspaceId($request))->find($id);

        if (! $conversation) {
            return response()->json(['error' => 'Conversation not found.'], 404);
        }

        $messages = $conversation->messages()
            ->orderBy('sent_at')
            ->paginate(50);

        return MessageResource::collection($messages);
    }

    /**
     * POST /api/v1/conversations/{id}/ai/reply
     */
    public function aiReply(Request $request, int $id): JsonResponse
    {
        $conversation = Conversation::where('workspace_id', $this->workspaceId($request))->find($id);
        if (! $conversation) {
            return response()->json(['error' => 'Conversation not found.'], 404);
        }

        $service = $this->conversationService ?? app(ConversationService::class);
        $suggestion = $service->generateAiSuggestion($conversation, $request->input('prompt'));

        return response()->json([
            'success' => true,
            'data' => $suggestion,
        ]);
    }

    /**
     * POST /api/v1/conversations/{id}/ai/enable
     */
    public function aiEnable(Request $request, int $id): JsonResponse
    {
        $conversation = Conversation::where('workspace_id', $this->workspaceId($request))->find($id);
        if (! $conversation) {
            return response()->json(['error' => 'Conversation not found.'], 404);
        }

        $mode = $request->input('mode', 'auto');
        $service = $this->conversationService ?? app(ConversationService::class);
        $service->enableAi($conversation, $mode);

        return response()->json([
            'success' => true,
            'message' => 'AI Mode enabled.',
            'ai_mode' => $conversation->fresh()->ai_mode,
        ]);
    }

    /**
     * POST /api/v1/conversations/{id}/ai/disable
     */
    public function aiDisable(Request $request, int $id): JsonResponse
    {
        $conversation = Conversation::where('workspace_id', $this->workspaceId($request))->find($id);
        if (! $conversation) {
            return response()->json(['error' => 'Conversation not found.'], 404);
        }

        $service = $this->conversationService ?? app(ConversationService::class);
        $service->switchToHuman($conversation, $request->user(), 'Disabled by API request');

        return response()->json([
            'success' => true,
            'message' => 'AI disabled; Human mode active.',
            'ai_mode' => $conversation->fresh()->ai_mode,
        ]);
    }

    /**
     * POST /api/v1/conversations/{id}/handoff
     */
    public function handoff(Request $request, int $id): JsonResponse
    {
        $conversation = Conversation::where('workspace_id', $this->workspaceId($request))->find($id);
        if (! $conversation) {
            return response()->json(['error' => 'Conversation not found.'], 404);
        }

        $reason = $request->input('reason', 'Customer requested human');
        $service = $this->conversationService ?? app(ConversationService::class);
        $service->triggerHumanHandoff($conversation, $reason);

        return response()->json([
            'success' => true,
            'message' => "Handoff completed: {$reason}",
            'ai_mode' => 'human',
        ]);
    }

    /**
     * POST /api/v1/conversations/{id}/assign
     */
    public function assign(Request $request, int $id): JsonResponse
    {
        $conversation = Conversation::where('workspace_id', $this->workspaceId($request))->find($id);
        if (! $conversation) {
            return response()->json(['error' => 'Conversation not found.'], 404);
        }

        $userId = $request->input('user_id');
        $service = $this->conversationService ?? app(ConversationService::class);
        $service->assignUser($conversation, $userId ? (int) $userId : null);

        return response()->json([
            'success' => true,
            'message' => 'Conversation assigned successfully.',
            'assigned_user_id' => $conversation->fresh()->assigned_user_id,
        ]);
    }
}
