<?php

namespace App\Modules\Inbox\Services;

use App\Models\Crm\CrmTask;
use App\Models\User;
use App\Modules\Inbox\Models\ConversationActivity;
use App\Modules\Shared\Models\ContactTag;
use App\Modules\Shared\Models\ContactTimelineEvent;
use App\Modules\Shared\Models\Conversation;
use App\Notifications\ConversationHandoverNotification;
use App\Services\Crm\LeadAssignmentService;
use App\Services\CustomerJourney\CustomerJourneyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HumanHandoffService
{
    public const HANDOFF_KEYWORDS = [
        'agent',
        'human',
        'representative',
        'talk to person',
        'talk to human',
        'talk to agent',
        'speak to human',
        'speak to agent',
        'speak with human',
        'real person',
        'live agent',
        'live support',
        'customer care',
        'manager',
        'help me',
        'salesperson',
        'sales rep',
        'executive',
        'need a human',
        'connect me',
        'transfer me',
        'baat karni hai',
        'kisi se baat karao',
    ];

    /**
     * Determine if customer text demands a human handoff.
     */
    public function isHandoffRequested(string $text): bool
    {
        $normalized = strtolower(trim($text));

        foreach (self::HANDOFF_KEYWORDS as $kw) {
            if (str_contains($normalized, $kw)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Hand over conversation from AI to a human salesperson / agent.
     * Completes the entire sequence:
     * 1. AI stops (ai_mode = 'human', assigned_to = 'human')
     * 2. Salesperson assigned (via LeadAssignmentService or provided ID)
     * 3. Follow-up task created (CrmTask scheduled with high/urgent priority)
     * 4. CRM timeline & journey updated (ContactTimelineEvent recorded)
     */
    public function executeHandoff(
        Conversation $conversation,
        ?int $assigneeUserId = null,
        string $reason = 'Customer requested human agent'
    ): array {
        return DB::transaction(function () use ($conversation, $assigneeUserId, $reason) {
            $contact = $conversation->contact;
            $workspaceId = $conversation->workspace_id;

            // 1. AI Stops: Turn off AI auto-reply mode
            $conversation->update([
                'ai_mode' => 'human',
                'assigned_to' => 'human',
                'handover_at' => now(),
                'human_takeover_at' => now(),
                'handoff_reason' => $reason,
                'status' => 'open',
            ]);

            // 2. Salesperson Assignment
            $assignedUser = null;
            if ($assigneeUserId) {
                $assignedUser = User::where('workspace_id', $workspaceId)->find($assigneeUserId);
            }

            if (! $assignedUser && $contact && $contact->assigned_user_id) {
                $assignedUser = User::where('workspace_id', $workspaceId)->find($contact->assigned_user_id);
            }

            if (! $assignedUser && $contact) {
                try {
                    $leadAssignmentService = app(LeadAssignmentService::class);
                    $assignedUser = $leadAssignmentService->assignLead($contact, 'round_robin');
                } catch (\Throwable $e) {
                    Log::warning('LeadAssignmentService handoff routing: '.$e->getMessage());
                }
            }

            if (! $assignedUser) {
                // Fallback to first active workspace user
                $assignedUser = User::where('workspace_id', $workspaceId)->where('status', 'active')->first();
            }

            if ($assignedUser) {
                $conversation->update(['assigned_user_id' => $assignedUser->id]);
                if ($contact && ! $contact->assigned_user_id) {
                    $contact->update(['assigned_user_id' => $assignedUser->id]);
                }
            }

            // 3. Follow-up Task Creation
            $task = null;
            if ($contact) {
                $contactName = $contact->full_name ?: 'Customer';
                $channelName = ucfirst($conversation->channel ?? 'WhatsApp');
                $task = CrmTask::create([
                    'workspace_id' => $workspaceId,
                    'contact_id' => $contact->id,
                    'lead_id' => $contact->lead_id,
                    'deal_id' => $contact->deals()->latest('id')->value('id'),
                    'created_by_id' => $assignedUser?->id ?? $workspaceId,
                    'assigned_user_id' => $assignedUser?->id,
                    'title' => "Live agent requested: {$contactName}",
                    'description' => "Customer requested human support on {$channelName}. Reason: {$reason}. Please contact customer immediately.",
                    'priority' => 'urgent',
                    'status' => 'pending',
                    'due_at' => now()->addMinutes(15),
                ]);

                // Tag contact as 'Human Required'
                $tag = ContactTag::firstOrCreate(
                    ['workspace_id' => $workspaceId, 'name' => 'Human Required'],
                    ['color' => '#ef4444']
                );
                $contact->tags()->syncWithoutDetaching([$tag->id]);
            }

            // 4. CRM Timeline & Journey update
            if ($contact) {
                ContactTimelineEvent::create([
                    'workspace_id' => $workspaceId,
                    'contact_id' => $contact->id,
                    'channel' => $conversation->channel ?? 'whatsapp',
                    'event_type' => 'human_handoff',
                    'title' => 'Customer Handed Over to Human Agent',
                    'description' => 'AI stopped responding. Salesperson '.($assignedUser?->name ?: 'Staff').' assigned. Urgent follow-up task scheduled.',
                    'metadata_json' => [
                        'conversation_id' => $conversation->id,
                        'assigned_user_id' => $assignedUser?->id,
                        'task_id' => $task?->id,
                        'reason' => $reason,
                    ],
                    'occurred_at' => now(),
                ]);

                try {
                    app(CustomerJourneyService::class)->recordEvent(
                        contactId: $contact->id,
                        workspaceId: $workspaceId,
                        eventType: 'human_handoff',
                        channel: $conversation->channel ?? 'whatsapp',
                        title: 'Customer Handed Over to Human Agent',
                        description: 'AI stopped responding. Assigned to '.($assignedUser?->name ?: 'Staff'),
                        metadata: [
                            'conversation_id' => $conversation->id,
                            'assigned_user_id' => $assignedUser?->id,
                            'task_id' => $task?->id,
                            'reason' => $reason,
                        ]
                    );
                } catch (\Throwable $e) {
                    // Fallback
                }
            }

            // Log Conversation Activity
            ConversationActivity::log($conversation, 'human_handoff', [
                'reason' => $reason,
                'assigned_user_id' => $assignedUser?->id,
                'task_id' => $task?->id,
            ]);

            // 5. Notify assigned salesperson
            if ($assignedUser) {
                try {
                    $assignedUser->notify(new ConversationHandoverNotification($conversation, $reason));
                } catch (\Throwable $e) {
                    // Safe notification handling
                }
            }

            return [
                'conversation' => $conversation->fresh(),
                'assigned_user' => $assignedUser,
                'task' => $task,
                'reason' => $reason,
            ];
        });
    }
}
