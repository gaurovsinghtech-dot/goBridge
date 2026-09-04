<?php

namespace App\Services\CustomerJourney;

use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\ContactTimelineEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerJourneyService
{
    /**
     * Record a contact timeline event.
     */
    public function recordEvent(
        Contact|int|null $contact = null,
        string $eventType = '',
        array $payload = [],
        ?int $contactId = null,
        ?int $workspaceId = null,
        string $channel = 'crm',
        ?string $title = null,
        ?string $description = null,
        array $metadata = []
    ): ?ContactTimelineEvent {
        $cId = $contact instanceof Contact ? $contact->id : ($contactId ?? (is_numeric($contact) ? (int) $contact : null));
        $wId = $contact instanceof Contact ? $contact->workspace_id : $workspaceId;
        $t = $title ?? ($payload['title'] ?? $eventType);
        $d = $description ?? ($payload['description'] ?? null);
        $meta = ! empty($metadata) ? $metadata : ($payload['metadata'] ?? $payload);

        if (! $cId) {
            return null;
        }

        try {
            return ContactTimelineEvent::create([
                'workspace_id' => $wId ?? Contact::where('id', $cId)->value('workspace_id'),
                'contact_id' => $cId,
                'channel' => $channel,
                'event_type' => $eventType,
                'title' => $t,
                'description' => $d,
                'metadata_json' => $meta,
                'occurred_at' => now(),
            ]);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get timeline of events for a contact.
     */
    public function timeline(Contact $contact, int $limit = 20): Collection
    {
        return $contact->timelineEvents()->limit($limit)->get();
    }
}
