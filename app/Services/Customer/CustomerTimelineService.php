<?php

namespace App\Services\Customer;

use App\Modules\Automation\Models\AutomationRun;
use App\Modules\Leads\Models\LeadActivity;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use App\Modules\Voice\Models\VoiceCall;
use App\Modules\Voice\Models\VoiceFollowUp;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerTimelineService
{
    /**
     * Build the unified chronological customer journey timeline.
     */
    public function getTimeline(Contact $contact, array $filters = []): array
    {
        $events = collect();
        $channelFilter = $filters['channel'] ?? 'all';
        $search = $filters['search'] ?? null;

        // 1. Conversations & Messages (WhatsApp, Messenger, Instagram, Email, SMS)
        $conversations = Conversation::where('workspace_id', $contact->workspace_id)
            ->where('contact_id', $contact->id)
            ->with(['messages' => fn ($q) => $q->latest('sent_at')->limit(20)])
            ->get();

        foreach ($conversations as $conv) {
            $channel = $conv->channel ?: 'whatsapp';
            $msgCount = $conv->messages->count();
            $lastMsg = $conv->messages->first();

            if ($lastMsg) {
                $events->push([
                    'id' => "conv-{$conv->id}",
                    'type' => in_array($channel, ['whatsapp', 'messenger', 'instagram', 'email']) ? $channel : 'whatsapp',
                    'channel_label' => ucfirst($channel),
                    'title' => ucfirst($channel) . " Conversation ({$msgCount} messages)",
                    'summary' => $lastMsg->content ? (mb_substr($lastMsg->content, 0, 140) . (mb_strlen($lastMsg->content) > 140 ? '...' : '')) : 'Interactive media/message exchanged.',
                    'details' => [
                        'conversation_id' => $conv->id,
                        'message_count' => $msgCount,
                        'last_message' => $lastMsg->content,
                        'last_direction' => $lastMsg->direction,
                        'ai_mode' => $conv->ai_mode,
                    ],
                    'timestamp' => $conv->last_message_at ? Carbon::parse($conv->last_message_at) : Carbon::parse($lastMsg->created_at),
                    'action_url' => route('client.inbox.index', ['conversation' => $conv->id]),
                    'action_label' => 'Open Conversation',
                    'badge' => [
                        'text' => $conv->ai_mode === 'auto' ? '🤖 AI Handled' : '👤 Human Mode',
                        'variant' => $conv->ai_mode === 'auto' ? 'brand' : 'neutral',
                    ],
                ]);
            }
        }

        // 2. AI Voice Calls & Recordings (#71, #76)
        $calls = VoiceCall::where('workspace_id', $contact->workspace_id)
            ->where(function ($q) use ($contact) {
                $q->where('contact_id', $contact->id);
                if ($contact->phone_e164) {
                    $q->orWhere('from_number', $contact->phone_e164)
                      ->orWhere('to_number', $contact->phone_e164);
                }
            })
            ->with('agent')
            ->get();

        foreach ($calls as $call) {
            $isHandoff = $call->outcome === 'human_handoff' || $call->outcome === 'transferred';
            $durationFormatted = sprintf('%02d:%02d', floor($call->duration_sec / 60), $call->duration_sec % 60);

            $events->push([
                'id' => "call-{$call->id}",
                'type' => $isHandoff ? 'human_call' : 'voice',
                'channel_label' => $isHandoff ? 'Human Call' : 'AI Voice',
                'title' => ($isHandoff ? '👤 Human Agent Call (' : '📞 AI Voice Call (') . $durationFormatted . ')',
                'summary' => $call->summary ?: "Voice call with {$call->agent?->name} ({$call->direction}). Outcome: {$call->outcome}",
                'details' => [
                    'call_uuid' => $call->uuid,
                    'agent_name' => $call->agent?->name,
                    'duration_sec' => $call->duration_sec,
                    'outcome' => $call->outcome,
                    'intent' => $call->intent,
                    'lead_interest' => $call->lead_interest,
                    'has_recording' => ! empty($call->recording_url),
                    'has_transcript' => ! empty($call->transcript),
                ],
                'timestamp' => $call->started_at ? Carbon::parse($call->started_at) : Carbon::parse($call->created_at),
                'action_url' => route('client.voice.calls.show', $call->uuid),
                'action_label' => 'View Call Intelligence',
                'badge' => [
                    'text' => ucfirst($call->outcome ?: $call->status),
                    'variant' => in_array($call->outcome, ['interested', 'qualified']) ? 'success' : 'neutral',
                ],
            ]);
        }

        // 3. Follow-ups & Callbacks (#77)
        $followUps = VoiceFollowUp::where('workspace_id', $contact->workspace_id)
            ->where('contact_id', $contact->id)
            ->with(['assignedUser', 'voiceAgent'])
            ->get();

        foreach ($followUps as $fu) {
            $events->push([
                'id' => "fu-{$fu->id}",
                'type' => $fu->type === 'callback' ? 'callback' : 'task',
                'channel_label' => $fu->type === 'callback' ? 'Callback' : 'CRM Task',
                'title' => ($fu->type === 'callback' ? '📞 Scheduled Callback: ' : '📋 CRM Task: ') . $fu->title,
                'summary' => $fu->notes ?: ($fu->type === 'callback' ? 'Voice callback scheduled for customer.' : 'Task assigned to sales team.'),
                'details' => [
                    'follow_up_uuid' => $fu->uuid,
                    'type' => $fu->type,
                    'status' => $fu->status,
                    'priority' => $fu->priority,
                    'due_at' => $fu->due_at?->toDateTimeString(),
                    'assigned_to' => $fu->assignedUser?->name,
                ],
                'timestamp' => Carbon::parse($fu->created_at),
                'action_url' => route('client.voice.follow-ups.show', $fu->uuid),
                'action_label' => 'Manage Follow-up',
                'badge' => [
                    'text' => ucfirst($fu->status),
                    'variant' => $fu->status === 'completed' ? 'success' : ($fu->status === 'scheduled' ? 'brand' : 'warning'),
                ],
            ]);
        }

        // 4. Contact Timeline Events (WhatsApp, Email, Stage Changes, Assignments, AI, Notes)
        $timelineEvents = \App\Modules\Shared\Models\ContactTimelineEvent::where('workspace_id', $contact->workspace_id)
            ->where('contact_id', $contact->id)
            ->latest('occurred_at')
            ->limit(50)
            ->get();

        foreach ($timelineEvents as $te) {
            $events->push([
                'id' => "te-{$te->id}",
                'type' => $te->channel ?: 'crm',
                'channel_label' => ucfirst($te->channel ?: 'CRM'),
                'title' => $te->title ?: 'Timeline Event',
                'summary' => $te->description ?: 'CRM activity recorded.',
                'details' => [
                    'event_id' => $te->id,
                    'event_type' => $te->event_type,
                    'metadata' => $te->metadata_json,
                ],
                'timestamp' => $te->occurred_at ? Carbon::parse($te->occurred_at) : Carbon::parse($te->created_at),
                'action_url' => null,
                'action_label' => null,
                'badge' => [
                    'text' => ucfirst(str_replace('_', ' ', $te->event_type)),
                    'variant' => str_contains($te->event_type, 'won') ? 'success' : (str_contains($te->event_type, 'lost') ? 'danger' : 'neutral'),
                ],
            ]);
        }

        // 5. CRM Tasks
        $tasks = \App\Models\Crm\CrmTask::where('workspace_id', $contact->workspace_id)
            ->where('contact_id', $contact->id)
            ->with(['assignedUser', 'deal'])
            ->latest()
            ->limit(20)
            ->get();

        foreach ($tasks as $task) {
            $events->push([
                'id' => "task-{$task->id}",
                'type' => 'task',
                'channel_label' => 'CRM Task',
                'title' => "📋 Task: {$task->title}",
                'summary' => $task->description ?: "Task status: {$task->status}. Priority: {$task->priority}",
                'details' => [
                    'task_id' => $task->id,
                    'status' => $task->status,
                    'priority' => $task->priority,
                    'due_at' => $task->due_at?->toDateTimeString(),
                    'assigned_to' => $task->assignedUser?->name,
                    'deal' => $task->deal?->name,
                ],
                'timestamp' => $task->due_at ? Carbon::parse($task->due_at) : Carbon::parse($task->created_at),
                'action_url' => null,
                'action_label' => null,
                'badge' => [
                    'text' => ucfirst($task->status),
                    'variant' => $task->status === 'completed' ? 'success' : ($task->isOverdue() ? 'danger' : 'warning'),
                ],
            ]);
        }

        // 6. CRM Notes
        $crmNotes = \App\Models\Crm\CrmNote::where('workspace_id', $contact->workspace_id)
            ->where('contact_id', $contact->id)
            ->with('user')
            ->latest()
            ->limit(20)
            ->get();

        foreach ($crmNotes as $note) {
            $events->push([
                'id' => "note-{$note->id}",
                'type' => 'note',
                'channel_label' => 'CRM Note',
                'title' => '📝 Note by ' . ($note->user?->name ?: 'Team Member'),
                'summary' => $note->content,
                'details' => [
                    'note_id' => $note->id,
                    'user_id' => $note->user_id,
                ],
                'timestamp' => Carbon::parse($note->created_at),
                'action_url' => null,
                'action_label' => null,
                'badge' => [
                    'text' => 'Note',
                    'variant' => 'neutral',
                ],
            ]);
        }

        // 7. Lead & Pipeline Activities
        if (class_exists(LeadActivity::class)) {
            $activities = LeadActivity::where('workspace_id', $contact->workspace_id)
                ->where(function ($q) use ($contact) {
                    if (! empty($contact->lead_id)) {
                        $q->where('lead_id', $contact->lead_id);
                    }
                })
                ->latest('occurred_at')
                ->limit(30)
                ->get();

            foreach ($activities as $act) {
                $events->push([
                    'id' => "act-{$act->id}",
                    'type' => $act->type === 'note' ? 'note' : 'crm',
                    'channel_label' => $act->type === 'note' ? 'Sales Note' : 'CRM Activity',
                    'title' => '📋 ' . ucfirst($act->type) . ' Event',
                    'summary' => $act->body ?: 'CRM lead activity recorded.',
                    'details' => [
                        'activity_id' => $act->id,
                        'type' => $act->type,
                        'user_id' => $act->user_id,
                    ],
                    'timestamp' => $act->occurred_at ? Carbon::parse($act->occurred_at) : Carbon::parse($act->created_at),
                    'action_url' => null,
                    'action_label' => null,
                    'badge' => [
                        'text' => ucfirst($act->type),
                        'variant' => 'neutral',
                    ],
                ]);
            }
        }

        // 8. Automation Runs
        if (class_exists(AutomationRun::class)) {
            $runs = AutomationRun::where('contact_id', $contact->id)
                ->with('automation:id,name,trigger_type')
                ->latest()
                ->limit(20)
                ->get();

            foreach ($runs as $run) {
                $events->push([
                    'id' => "auto-{$run->id}",
                    'type' => 'automation',
                    'channel_label' => 'Automation',
                    'title' => '⚙ Workflow: ' . ($run->automation?->name ?: 'Automation Triggered'),
                    'summary' => "Event '{$run->trigger_event}' executed with status: {$run->status}.",
                    'details' => [
                        'run_id' => $run->id,
                        'automation_id' => $run->automation_id,
                        'status' => $run->status,
                        'trigger_event' => $run->trigger_event,
                    ],
                    'timestamp' => $run->started_at ? Carbon::parse($run->started_at) : Carbon::parse($run->created_at),
                    'action_url' => null,
                    'action_label' => null,
                    'badge' => [
                        'text' => ucfirst($run->status),
                        'variant' => $run->status === 'completed' ? 'success' : 'neutral',
                    ],
                ]);
            }
        }

        // Filter by channel if requested
        if ($channelFilter !== 'all' && ! empty($channelFilter)) {
            $events = $events->filter(function ($e) use ($channelFilter) {
                if ($channelFilter === 'voice') {
                    return in_array($e['type'], ['voice', 'human_call']);
                }
                if ($channelFilter === 'crm') {
                    return in_array($e['type'], ['crm', 'note', 'task']);
                }
                return $e['type'] === $channelFilter;
            });
        }

        // Filter by keyword search if requested
        if ($search) {
            $searchLower = strtolower($search);
            $events = $events->filter(function ($e) use ($searchLower) {
                return str_contains(strtolower($e['title']), $searchLower)
                    || str_contains(strtolower($e['summary']), $searchLower)
                    || str_contains(strtolower(json_encode($e['details'])), $searchLower);
            });
        }

        // Sort chronologically (most recent first)
        return $events->sortByDesc(fn ($e) => $e['timestamp']->timestamp)
            ->values()
            ->map(function ($e) {
                $e['formatted_date'] = $e['timestamp']->format('d M Y');
                $e['formatted_time'] = $e['timestamp']->format('h:i A');
                return $e;
            })
            ->all();
    }

    /**
     * Get Customer 360 Journey Summary metrics.
     */
    public function getJourneySummary(Contact $contact): array
    {
        $wid = $contact->workspace_id;

        // Channels used
        $conversations = Conversation::where('workspace_id', $wid)
            ->where('contact_id', $contact->id)
            ->get();

        $channels = $conversations->pluck('channel')->unique()->filter()->values()->toArray();
        if (empty($channels) && $contact->phone_e164) {
            $channels[] = 'whatsapp';
        }

        $voiceCallsCount = VoiceCall::where('workspace_id', $wid)
            ->where(function ($q) use ($contact) {
                $q->where('contact_id', $contact->id);
                if ($contact->phone_e164) {
                    $q->orWhere('from_number', $contact->phone_e164)
                      ->orWhere('to_number', $contact->phone_e164);
                }
            })
            ->count();

        if ($voiceCallsCount > 0 && ! in_array('voice', $channels)) {
            $channels[] = 'voice';
        }

        // Message counts
        $totalMessages = Message::whereHas('conversation', fn ($q) => $q->where('contact_id', $contact->id))->count();

        // AI vs Human conversations
        $aiConversationsCount = Conversation::where('workspace_id', $wid)
            ->where('contact_id', $contact->id)
            ->where('ai_mode', 'auto')
            ->count();

        $humanConversationsCount = Conversation::where('workspace_id', $wid)
            ->where('contact_id', $contact->id)
            ->where('ai_mode', 'human')
            ->count();

        // First and Last Contact
        $firstContact = $contact->created_at ? Carbon::parse($contact->created_at)->format('d M Y') : '—';
        $lastContact = $contact->last_seen_at ? Carbon::parse($contact->last_seen_at)->format('d M Y, h:i A') : ($contact->updated_at ? Carbon::parse($contact->updated_at)->format('d M Y, h:i A') : '—');

        // Next Action (from scheduled callbacks or tasks)
        $nextActionItem = VoiceFollowUp::where('workspace_id', $wid)
            ->where('contact_id', $contact->id)
            ->whereIn('status', ['pending', 'scheduled'])
            ->orderBy('due_at', 'asc')
            ->first();

        // Check for potential duplicate contacts by phone or email
        $potentialDuplicates = collect();
        if ($contact->phone_e164 || $contact->email) {
            $potentialDuplicates = Contact::where('workspace_id', $wid)
                ->where('id', '!=', $contact->id)
                ->where(function ($q) use ($contact) {
                    if ($contact->phone_e164) {
                        $q->where('phone_e164', $contact->phone_e164);
                    }
                    if ($contact->email) {
                        $q->orWhere('email', $contact->email);
                    }
                })
                ->get(['id', 'uuid', 'first_name', 'last_name', 'phone_e164', 'email', 'created_at']);
        }

        return [
            'first_contact' => $firstContact,
            'last_contact' => $lastContact,
            'channels' => $channels,
            'total_calls' => $voiceCallsCount,
            'total_messages' => $totalMessages,
            'ai_conversations' => $aiConversationsCount,
            'human_conversations' => $humanConversationsCount,
            'next_action' => $nextActionItem ? [
                'id' => $nextActionItem->id,
                'uuid' => $nextActionItem->uuid,
                'type' => $nextActionItem->type,
                'title' => $nextActionItem->title,
                'due_at' => $nextActionItem->due_at?->format('d M, h:i A'),
                'priority' => $nextActionItem->priority,
            ] : null,
            'potential_duplicates' => $potentialDuplicates,
        ];
    }

    /**
     * Generate or return cached Customer AI Summary.
     */
    public function getAiCustomerSummary(Contact $contact): array
    {
        // Synthesize latest knowledge from call intelligence and conversations
        $latestCall = VoiceCall::where('workspace_id', $contact->workspace_id)
            ->where(function ($q) use ($contact) {
                $q->where('contact_id', $contact->id);
                if ($contact->phone_e164) {
                    $q->orWhere('from_number', $contact->phone_e164);
                }
            })
            ->whereNotNull('summary')
            ->latest()
            ->first();

        $name = trim("{$contact->first_name} {$contact->last_name}") ?: 'Customer';

        if ($latestCall) {
            return [
                'summary' => "{$name} engaged via voice. {$latestCall->summary}",
                'intent' => $latestCall->intent ?: 'Sales inquiry',
                'lead_interest' => $latestCall->lead_interest ?: 'High',
                'conversation_signal' => $latestCall->conversation_signal ?: 'Positive',
                'topics' => $latestCall->topics ?: ['WhatsApp API', 'Automation'],
                'next_action' => $latestCall->next_action ?: 'Sales team follow-up',
            ];
        }

        return [
            'summary' => "{$name} is registered as a CRM lead. Ready for omnichannel outreach.",
            'intent' => 'General inquiry',
            'lead_interest' => 'Medium',
            'conversation_signal' => 'Neutral',
            'topics' => ['Omnichannel Outreach'],
            'next_action' => 'Initiate WhatsApp or voice contact',
        ];
    }

    /**
     * Safely merge secondary contact into primary contact without losing history.
     */
    public function mergeContacts(Contact $primary, Contact $secondary): void
    {
        if ($primary->id === $secondary->id || $primary->workspace_id !== $secondary->workspace_id) {
            return;
        }

        DB::transaction(function () use ($primary, $secondary) {
            // 1. Reassign Conversations
            Conversation::where('contact_id', $secondary->id)->update(['contact_id' => $primary->id]);

            // 2. Reassign Voice Calls
            VoiceCall::where('contact_id', $secondary->id)->update(['contact_id' => $primary->id]);

            // 3. Reassign Follow-ups
            VoiceFollowUp::where('contact_id', $secondary->id)->update(['contact_id' => $primary->id]);

            // 4. Reassign Automation Runs
            if (class_exists(AutomationRun::class)) {
                AutomationRun::where('contact_id', $secondary->id)->update(['contact_id' => $primary->id]);
            }

            // 5. Merge Tags
            $secondaryTagIds = $secondary->tags()->pluck('contact_tags.id')->toArray();
            if (! empty($secondaryTagIds)) {
                $primary->tags()->syncWithoutDetaching($secondaryTagIds);
            }

            // 6. Fill missing fields on primary
            $updates = [];
            if (empty($primary->email) && ! empty($secondary->email)) {
                $updates['email'] = $secondary->email;
            }
            if (empty($primary->phone_e164) && ! empty($secondary->phone_e164)) {
                $updates['phone_e164'] = $secondary->phone_e164;
            }
            if (! empty($updates)) {
                $primary->update($updates);
            }

            // Delete secondary safely
            $secondary->delete();
        });
    }
}
