<?php

namespace App\Modules\Voice\Services;

use App\Models\User;
use App\Modules\Leads\Models\Lead;
use App\Modules\Leads\Models\LeadActivity;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\ContactTag;
use App\Modules\Voice\Models\VoiceCall;
use App\Modules\Voice\Models\VoiceCampaignRecipient;
use App\Modules\Voice\Models\VoiceFollowUp;
use App\Modules\Voice\Models\VoiceFollowUpRule;
use App\Services\Automation\WorkflowExecutionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class VoiceFollowUpService
{
    public function __construct(
        protected SmartVoiceQueueService $queueService,
        protected ?WorkflowExecutionService $workflowService = null,
    ) {
        $this->workflowService = $workflowService ?? app(WorkflowExecutionService::class);
    }

    /**
     * Automatically evaluate and trigger follow-up actions for a completed call.
     */
    public function processCallFollowUp(VoiceCall $call): array
    {
        $workspaceId = $call->workspace_id;
        $outcome = $call->outcome ?? 'completed';
        $contact = $call->contact;

        $executedActions = [];

        // 1. Check for matching custom workspace rules
        $rules = VoiceFollowUpRule::where('workspace_id', $workspaceId)
            ->where('is_active', true)
            ->where(function ($q) use ($call, $outcome) {
                $q->where('trigger_event', $outcome)
                  ->orWhere('trigger_event', 'call_completed');
            })
            ->where(function ($q) use ($call) {
                $q->whereNull('voice_agent_id')
                  ->orWhere('voice_agent_id', $call->voice_agent_id);
            })
            ->get();

        if ($rules->isNotEmpty()) {
            foreach ($rules as $rule) {
                foreach ($rule->actions as $action) {
                    $executedActions[] = $this->executeRuleAction($action, $call, $contact);
                }
            }
            return $executedActions;
        }

        // 2. Default standard automated follow-up heuristics
        if ($outcome === 'interested') {
            // CRM Sales Task
            $followUp = VoiceFollowUp::create([
                'workspace_id' => $workspaceId,
                'voice_call_id' => $call->id,
                'contact_id' => $contact?->id,
                'voice_agent_id' => $call->voice_agent_id,
                'type' => 'crm_task',
                'status' => 'pending',
                'priority' => 'high',
                'due_at' => Carbon::now()->addHours(2),
                'title' => 'Follow up with interested lead: ' . ($contact ? "{$contact->first_name} {$contact->last_name}" : ($call->from_number ?: $call->to_number)),
                'notes' => $call->summary ?: 'Customer expressed high interest during AI voice call.',
                'outcome_trigger' => 'interested',
            ]);
            $executedActions[] = ['type' => 'crm_task', 'id' => $followUp->id];

            // Update CRM Tag & Lead Activity
            if ($contact) {
                $tag = ContactTag::firstOrCreate(['workspace_id' => $workspaceId, 'name' => 'Voice-Interested'], ['color' => '#f59e0b']);
                $contact->tags()->syncWithoutDetaching([$tag->id]);

                if (class_exists(LeadActivity::class) && ! empty($contact->lead_id)) {
                    LeadActivity::create([
                        'workspace_id' => $workspaceId,
                        'lead_id' => $contact->lead_id,
                        'type' => 'call',
                        'body' => 'Lead interested from AI voice call: ' . ($call->summary ?? 'Interested'),
                        'occurred_at' => now(),
                    ]);
                }
            }

            // Trigger Automation Engine (#61)
            try {
                $this->workflowService->triggerEvent($workspaceId, 'voice_call_interested', $contact, [
                    'voice_call_id' => $call->id,
                    'summary' => $call->summary,
                    'intent' => $call->intent,
                ]);
            } catch (\Throwable $e) {
                Log::warning('FollowUpService automation trigger error', ['error' => $e->getMessage()]);
            }
        } elseif ($outcome === 'qualified') {
            $followUp = VoiceFollowUp::create([
                'workspace_id' => $workspaceId,
                'voice_call_id' => $call->id,
                'contact_id' => $contact?->id,
                'voice_agent_id' => $call->voice_agent_id,
                'type' => 'crm_task',
                'status' => 'pending',
                'priority' => 'high',
                'due_at' => Carbon::now()->addDay(),
                'title' => 'Qualified Sales Lead: ' . ($contact ? "{$contact->first_name} {$contact->last_name}" : ($call->from_number ?: $call->to_number)),
                'notes' => 'AI qualified lead score: ' . ($call->lead_score ?? 85) . '. ' . $call->summary,
                'outcome_trigger' => 'qualified',
            ]);
            $executedActions[] = ['type' => 'crm_task', 'id' => $followUp->id];

            if ($contact) {
                $tag = ContactTag::firstOrCreate(['workspace_id' => $workspaceId, 'name' => 'Voice-Qualified'], ['color' => '#10b981']);
                $contact->tags()->syncWithoutDetaching([$tag->id]);
            }

            try {
                $this->workflowService->triggerEvent($workspaceId, 'voice_call_qualified', $contact, [
                    'voice_call_id' => $call->id,
                    'lead_score' => $call->lead_score,
                ]);
            } catch (\Throwable $e) {
                Log::warning('FollowUpService automation trigger error', ['error' => $e->getMessage()]);
            }
        } elseif ($outcome === 'callback_requested' || $call->intent === 'callback') {
            $dueAt = Carbon::now()->addHours(3);
            $followUp = $this->scheduleCallback(
                $call->workspace_id,
                $contact,
                $dueAt,
                [
                    'voice_call_id' => $call->id,
                    'voice_agent_id' => $call->voice_agent_id,
                    'title' => 'Scheduled Callback: ' . ($contact ? "{$contact->first_name} {$contact->last_name}" : ($call->from_number ?: $call->to_number)),
                    'notes' => 'Requested callback during call. Summary: ' . ($call->summary ?? 'Callback requested'),
                ]
            );
            $executedActions[] = ['type' => 'callback', 'id' => $followUp->id];
        }

        return $executedActions;
    }

    /**
     * Schedule a Voice Callback and feed it into #74 Smart Calling Queue.
     */
    public function scheduleCallback(
        int $workspaceId,
        ?Contact $contact,
        Carbon $dueAt,
        array $options = []
    ): VoiceFollowUp {
        $followUp = VoiceFollowUp::create([
            'workspace_id' => $workspaceId,
            'voice_call_id' => $options['voice_call_id'] ?? null,
            'voice_campaign_id' => $options['voice_campaign_id'] ?? null,
            'voice_agent_id' => $options['voice_agent_id'] ?? null,
            'contact_id' => $contact?->id,
            'assigned_user_id' => $options['assigned_user_id'] ?? null,
            'type' => 'callback',
            'status' => 'scheduled',
            'priority' => 'high',
            'due_at' => $dueAt,
            'timezone' => $options['timezone'] ?? 'UTC',
            'title' => $options['title'] ?? ('Callback for ' . ($contact ? "{$contact->first_name} {$contact->last_name}" : 'Customer')),
            'notes' => $options['notes'] ?? 'Scheduled callback',
            'outcome_trigger' => 'callback_requested',
        ]);

        // Push directly into #74 Smart Calling Queue if recipient or campaign exists
        if (! empty($options['voice_call_id'])) {
            $recipient = VoiceCampaignRecipient::where('voice_call_id', $options['voice_call_id'])->first();
            if ($recipient) {
                $this->queueService->scheduleCallback($recipient, $dueAt, $options['notes'] ?? 'Follow-up callback');
            }
        }

        return $followUp;
    }

    /**
     * Complete a follow-up action.
     */
    public function completeFollowUp(VoiceFollowUp $followUp, ?string $notes = null): VoiceFollowUp
    {
        $followUp->update([
            'status' => 'completed',
            'completed_at' => now(),
            'notes' => $notes ? ($followUp->notes . "\n[Completed]: " . $notes) : $followUp->notes,
        ]);

        if ($followUp->contact_id && class_exists(LeadActivity::class)) {
            $contact = $followUp->contact;
            if ($contact && ! empty($contact->lead_id)) {
                LeadActivity::create([
                    'workspace_id' => $followUp->workspace_id,
                    'lead_id' => $contact->lead_id,
                    'type' => 'note',
                    'body' => "Follow-up completed ({$followUp->type}): {$followUp->title}",
                    'occurred_at' => now(),
                ]);
            }
        }

        return $followUp;
    }

    /**
     * Reschedule a follow-up action.
     */
    public function rescheduleFollowUp(VoiceFollowUp $followUp, Carbon $newDueAt): VoiceFollowUp
    {
        $followUp->update([
            'due_at' => $newDueAt,
            'status' => 'scheduled',
        ]);

        return $followUp;
    }

    /**
     * Cancel a follow-up action.
     */
    public function cancelFollowUp(VoiceFollowUp $followUp, ?string $reason = null): VoiceFollowUp
    {
        $followUp->update([
            'status' => 'cancelled',
            'notes' => $reason ? ($followUp->notes . "\n[Cancelled]: " . $reason) : $followUp->notes,
        ]);

        return $followUp;
    }

    /**
     * Helper to execute a custom rule action.
     */
    protected function executeRuleAction(array $action, VoiceCall $call, ?Contact $contact): array
    {
        $type = $action['type'] ?? 'crm_task';
        $workspaceId = $call->workspace_id;

        switch ($type) {
            case 'schedule_callback':
                $delayMinutes = (int) ($action['delay_minutes'] ?? 180);
                $followUp = $this->scheduleCallback(
                    $workspaceId,
                    $contact,
                    Carbon::now()->addMinutes($delayMinutes),
                    [
                        'voice_call_id' => $call->id,
                        'voice_agent_id' => $call->voice_agent_id,
                        'title' => 'Scheduled Callback: ' . ($contact ? "{$contact->first_name} {$contact->last_name}" : $call->from_number),
                        'notes' => $action['notes'] ?? 'Automated callback from follow-up rule.',
                    ]
                );
                return ['type' => 'callback', 'id' => $followUp->id];

            case 'create_crm_task':
                $followUp = VoiceFollowUp::create([
                    'workspace_id' => $workspaceId,
                    'voice_call_id' => $call->id,
                    'contact_id' => $contact?->id,
                    'voice_agent_id' => $call->voice_agent_id,
                    'type' => 'crm_task',
                    'status' => 'pending',
                    'priority' => $action['priority'] ?? 'high',
                    'due_at' => Carbon::now()->addHours((int) ($action['due_hours'] ?? 24)),
                    'title' => $action['title'] ?? ('Task for ' . ($contact ? "{$contact->first_name} {$contact->last_name}" : 'Lead')),
                    'notes' => $call->summary ?: 'Automated CRM task from voice call rule.',
                ]);
                return ['type' => 'crm_task', 'id' => $followUp->id];

            case 'add_tag':
                if ($contact && ! empty($action['tag_name'])) {
                    $tag = ContactTag::firstOrCreate(
                        ['workspace_id' => $workspaceId, 'name' => $action['tag_name']],
                        ['color' => $action['tag_color'] ?? '#6366f1']
                    );
                    $contact->tags()->syncWithoutDetaching([$tag->id]);
                }
                return ['type' => 'add_tag', 'tag' => $action['tag_name'] ?? ''];

            case 'trigger_automation':
                if (! empty($action['event_name'])) {
                    $this->workflowService->triggerEvent($workspaceId, $action['event_name'], $contact, [
                        'voice_call_id' => $call->id,
                    ]);
                }
                return ['type' => 'trigger_automation'];

            default:
                return ['type' => $type, 'status' => 'logged'];
        }
    }
}
