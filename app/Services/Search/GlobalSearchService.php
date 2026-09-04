<?php

namespace App\Services\Search;

use App\Models\Workspace;
use App\Modules\Automation\Models\Automation;
use App\Modules\Broadcasting\Models\Campaign;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Voice\Models\VoiceCall;
use Illuminate\Support\Collection;

class GlobalSearchService
{
    public function search(Workspace $workspace, string $query, int $limit = 5): array
    {
        $q = trim($query);
        if (strlen($q) < 2) {
            return [
                'contacts' => [],
                'conversations' => [],
                'calls' => [],
                'campaigns' => [],
                'automations' => [],
            ];
        }

        // 1. Contacts
        $contacts = Contact::where('workspace_id', $workspace->id)
            ->where(function ($sub) use ($q) {
                $sub->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('phone_e164', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            })
            ->limit($limit)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'uuid' => $c->uuid,
                'title' => trim("{$c->first_name} {$c->last_name}"),
                'subtitle' => $c->phone_e164 ?: $c->email,
                'url' => "/app/contacts/{$c->uuid}",
                'lead_score' => $c->lead_score,
                'category' => 'contacts',
            ]);

        // 2. Conversations
        $conversations = Conversation::where('workspace_id', $workspace->id)
            ->whereHas('contact', function ($c) use ($q) {
                $c->where('first_name', 'like', "%{$q}%")
                    ->orWhere('phone_e164', 'like', "%{$q}%");
            })
            ->with('contact')
            ->limit($limit)
            ->get()
            ->map(fn ($conv) => [
                'id' => $conv->id,
                'uuid' => $conv->uuid,
                'title' => $conv->contact ? trim("{$conv->contact->first_name} {$conv->contact->last_name}") : "Conversation #{$conv->id}",
                'subtitle' => "Channel: {$conv->channel} • Status: {$conv->status}",
                'url' => "/app/inbox?conversation={$conv->uuid}",
                'category' => 'conversations',
            ]);

        // 3. AI Voice Calls
        $calls = VoiceCall::where('workspace_id', $workspace->id)
            ->where(function ($sub) use ($q) {
                $sub->where('to_number', 'like', "%{$q}%")
                    ->orWhere('from_number', 'like', "%{$q}%");
            })
            ->limit($limit)
            ->get()
            ->map(fn ($cl) => [
                'id' => $cl->id,
                'uuid' => $cl->uuid,
                'title' => $cl->to_number,
                'subtitle' => "Provider: {$cl->provider} • Duration: {$cl->duration_sec}s",
                'url' => '/app/voice/calls',
                'category' => 'calls',
            ]);

        // 4. Campaigns
        $campaigns = Campaign::where('workspace_id', $workspace->id)
            ->where('name', 'like', "%{$q}%")
            ->limit($limit)
            ->get()
            ->map(fn ($cmp) => [
                'id' => $cmp->id,
                'title' => $cmp->name,
                'subtitle' => "Channel: {$cmp->channel} • Status: {$cmp->status}",
                'url' => "/app/campaigns/{$cmp->id}",
                'category' => 'campaigns',
            ]);

        // 5. Automations
        $automations = Automation::where('workspace_id', $workspace->id)
            ->where('name', 'like', "%{$q}%")
            ->limit($limit)
            ->get()
            ->map(fn ($auto) => [
                'id' => $auto->id,
                'title' => $auto->name,
                'subtitle' => "Trigger: {$auto->trigger_type} • Status: {$auto->status}",
                'url' => "/app/automations/{$auto->id}",
                'category' => 'automations',
            ]);

        return [
            'contacts' => $contacts,
            'conversations' => $conversations,
            'calls' => $calls,
            'campaigns' => $campaigns,
            'automations' => $automations,
            'total' => $contacts->count() + $conversations->count() + $calls->count() + $campaigns->count() + $automations->count(),
        ];
    }
}
