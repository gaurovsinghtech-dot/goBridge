<?php

namespace App\Modules\Automation\Services;

use App\Modules\AI\Services\LlmGateway;
use App\Modules\Automation\Models\AutomationRun;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;

class SmartFollowUpService
{
    public function __construct(private readonly LlmGateway $llmGateway) {}

    /**
     * Safety check before sending an automated follow-up:
     * Should automation halt because customer already replied, converted, opted out, or has an assigned human agent?
     */
    public function shouldHaltFollowup(AutomationRun $run): bool
    {
        $contact = Contact::find($run->contact_id);
        if (! $contact) {
            return true;
        }

        // 1. Check Opt-Out
        if ($contact->marketing_opt_out) {
            return true;
        }

        // 2. Check if customer replied since automation run started
        $runStartedAt = $run->created_at;
        $inboundReplies = Message::whereHas('conversation', function ($q) use ($contact) {
            $q->where('contact_id', $contact->id);
        })
        ->where('direction', 'in')
        ->where('created_at', '>', $runStartedAt)
        ->exists();

        if ($inboundReplies) {
            return true; // Customer already replied; do not send canned follow-up
        }

        // 3. Check if Conversation is assigned to Human or Resolved
        $activeConversation = Conversation::where('contact_id', $contact->id)
            ->latest('last_message_at')
            ->first();

        if ($activeConversation) {
            if ($activeConversation->assigned_to === 'human') {
                return true; // Human taking care of customer
            }
            if (in_array($activeConversation->status, ['resolved', 'snoozed'])) {
                return true;
            }
        }

        // 4. Check if Lead is Won / Converted
        if ($contact->lead_id) {
            $lead = \App\Modules\Leads\Models\Lead::with('stage')->find($contact->lead_id);
            if ($lead && $lead->stage?->is_won) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate context-aware AI follow-up message referencing prior conversation turns.
     */
    public function generateContextAwareFollowup(Contact $contact, int $workspaceId, ?string $objective = null): string
    {
        $lastMessages = Message::whereHas('conversation', fn ($q) => $q->where('contact_id', $contact->id))
            ->latest('created_at')
            ->take(6)
            ->get()
            ->reverse();

        $historyText = $lastMessages->map(fn ($m) => ($m->direction === 'in' ? 'Customer: ' : 'Agent: ').$m->body)->implode("\n");

        $firstName = $contact->first_name ?: 'there';
        $prompt = "You are an empathetic, professional sales and customer success assistant for Growbridge Connect.
Write a personalized 1-2 sentence follow-up message to {$firstName}.
Reference their recent inquiry and politely check in. Do NOT sound spammy.

Recent conversation history:
{$historyText}

Objective: ".($objective ?: 'Friendly follow-up regarding their inquiry and next steps');

        try {
            $response = $this->llmGateway->chat($workspaceId, [
                ['role' => 'user', 'content' => $prompt],
            ], ['max_tokens' => 120]);

            return trim($response->content ?? "Hi {$firstName}, just checking in to see if you have any questions regarding what we discussed. Let me know if I can help!");
        } catch (\Throwable) {
            return "Hi {$firstName}, just checking in to see if you have any questions regarding what we discussed. Let me know if I can help!";
        }
    }
}
