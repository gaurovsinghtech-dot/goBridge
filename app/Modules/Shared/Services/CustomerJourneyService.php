<?php

namespace App\Modules\Shared\Services;

use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\ContactTimelineEvent;
use Illuminate\Support\Collection;

class CustomerJourneyService
{
    /**
     * Record an event on the customer's unified journey timeline.
     */
    public function recordEvent(
        Contact $contact,
        string $channel,
        string $eventType,
        string $title,
        ?string $description = null,
        array $metadata = []
    ): ContactTimelineEvent {
        return ContactTimelineEvent::create([
            'workspace_id' => $contact->workspace_id,
            'contact_id' => $contact->id,
            'channel' => $channel,
            'event_type' => $eventType,
            'title' => $title,
            'description' => $description,
            'metadata_json' => $metadata,
            'occurred_at' => now(),
        ]);
    }

    /**
     * Retrieve the unified omnichannel chronological timeline for a contact.
     * Merges messages, voice calls, notes, leads, and automation steps.
     */
    public function getUnifiedTimeline(Contact $contact, int $limit = 50): Collection
    {
        // 1. Explicit timeline events
        $timelineEvents = $contact->timelineEvents()
            ->take($limit)
            ->get()
            ->map(fn (ContactTimelineEvent $e) => [
                'id' => 'evt_'.$e->id,
                'type' => $e->event_type,
                'channel' => $e->channel,
                'title' => $e->title,
                'description' => $e->description,
                'metadata' => $e->metadata_json,
                'occurred_at' => $e->occurred_at->toIso8601String(),
                'timestamp' => $e->occurred_at->timestamp,
            ]);

        // 2. Voice Calls
        $voiceCalls = $contact->voiceCalls()
            ->take($limit)
            ->get()
            ->map(fn ($call) => [
                'id' => 'call_'.$call->id,
                'type' => 'voice_call',
                'channel' => 'phone',
                'title' => ($call->direction === 'inbound' ? 'Inbound' : 'Outbound')." AI Call ({$call->duration_sec}s)",
                'description' => $call->summary ?: "Call status: {$call->status}",
                'metadata' => [
                    'duration' => $call->duration_sec,
                    'status' => $call->status,
                    'provider' => $call->provider,
                    'outcome' => $call->outcome,
                    'recording_url' => $call->recording_url,
                    'transcript' => $call->transcript,
                ],
                'occurred_at' => ($call->started_at ?? $call->created_at)->toIso8601String(),
                'timestamp' => ($call->started_at ?? $call->created_at)->timestamp,
            ]);

        // Merge, sort newest first, and slice
        return $timelineEvents->concat($voiceCalls)
            ->sortByDesc('timestamp')
            ->values()
            ->take($limit);
    }
}
