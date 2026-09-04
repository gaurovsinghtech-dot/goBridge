<?php

namespace App\Modules\Shared\Services;

use App\Modules\Inbox\Models\InternalNote;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\ContactTimelineEvent;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use App\Modules\Voice\Models\VoiceCall;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ContactIdentityService
{
    /**
     * Resolve or create a contact from omnichannel identity attributes.
     * Matches by phone (E.164), email, or channel-scoped external IDs.
     */
    public function resolveContact(int $workspaceId, array $identity): array
    {
        $phone = ! empty($identity['phone']) ? $this->normalizePhone($identity['phone']) : null;
        $email = ! empty($identity['email']) ? strtolower(trim($identity['email'])) : null;
        $channelId = $identity['channel_id'] ?? null;
        $channel = $identity['channel'] ?? null; // whatsapp, instagram, messenger

        $query = Contact::where('workspace_id', $workspaceId);

        if ($phone) {
            $query->where('phone_e164', $phone);
        } elseif ($email) {
            $query->where('email', $email);
        } elseif ($channel && $channelId) {
            $query->whereJsonContains("external_ids->{$channel}", $channelId);
        } else {
            // Insufficient identifier data
            $contact = Contact::create([
                'workspace_id' => $workspaceId,
                'first_name' => $identity['first_name'] ?? 'New',
                'last_name' => $identity['last_name'] ?? 'Contact',
                'source' => $channel ?? 'web',
            ]);

            return ['contact' => $contact, 'is_new' => true, 'possible_duplicate' => null];
        }

        $existing = $query->first();

        if ($existing) {
            // Update channel identity if newly learned
            $externalIds = $existing->external_ids ?? [];
            if ($channel && $channelId && empty($externalIds[$channel])) {
                $externalIds[$channel] = $channelId;
                $existing->update(['external_ids' => $externalIds]);
            }

            return ['contact' => $existing, 'is_new' => false, 'possible_duplicate' => null];
        }

        // Check for potential duplicate by loose match
        $potentialDuplicate = $this->findPotentialDuplicate($workspaceId, $phone, $email, $identity['first_name'] ?? '');

        $externalIds = [];
        if ($channel && $channelId) {
            $externalIds[$channel] = $channelId;
        }

        $newContact = Contact::create([
            'workspace_id' => $workspaceId,
            'phone_e164' => $phone,
            'email' => $email,
            'first_name' => $identity['first_name'] ?? null,
            'last_name' => $identity['last_name'] ?? null,
            'avatar' => $identity['avatar'] ?? null,
            'external_ids' => $externalIds,
            'source' => $channel ?? 'direct',
            'duplicate_of_id' => $potentialDuplicate?->id,
        ]);

        return [
            'contact' => $newContact,
            'is_new' => true,
            'possible_duplicate' => $potentialDuplicate,
        ];
    }

    /**
     * Merge two contact records cleanly without data loss.
     */
    public function mergeContacts(Contact $master, Contact $duplicate): bool
    {
        if ($master->id === $duplicate->id || $master->workspace_id !== $duplicate->workspace_id) {
            return false;
        }

        return DB::transaction(function () use ($master, $duplicate) {
            // 1. Reassign Conversations
            Conversation::where('contact_id', $duplicate->id)->update(['contact_id' => $master->id]);

            // 2. Reassign Voice Calls
            VoiceCall::where('contact_id', $duplicate->id)->update(['contact_id' => $master->id]);

            // 3. Reassign Timeline Events
            ContactTimelineEvent::where('contact_id', $duplicate->id)->update(['contact_id' => $master->id]);

            // 4. Merge tags
            $dupTagIds = $duplicate->tags()->pluck('tag_id')->toArray();
            $master->tags()->syncWithoutDetaching($dupTagIds);

            // 5. Fill missing master attributes
            $updates = [];
            if (! $master->email && $duplicate->email) {
                $updates['email'] = $duplicate->email;
            }
            if (! $master->phone_e164 && $duplicate->phone_e164) {
                $updates['phone_e164'] = $duplicate->phone_e164;
            }
            if (! $master->first_name && $duplicate->first_name) {
                $updates['first_name'] = $duplicate->first_name;
            }
            if (! $master->last_name && $duplicate->last_name) {
                $updates['last_name'] = $duplicate->last_name;
            }

            $mergedExternalIds = array_merge($duplicate->external_ids ?? [], $master->external_ids ?? []);
            if (! empty($mergedExternalIds)) {
                $updates['external_ids'] = $mergedExternalIds;
            }

            if (! empty($updates)) {
                $master->update($updates);
            }

            // Log timeline event on master
            app(CustomerJourneyService::class)->recordEvent(
                $master,
                'system',
                'contact_merged',
                'Contact Merged',
                "Merged duplicate contact #{$duplicate->id} ({$duplicate->full_name}) into this record."
            );

            // Soft-delete duplicate contact
            $duplicate->delete();

            return true;
        });
    }

    /**
     * Find potential duplicate contacts in a workspace for manual agent review.
     */
    public function findPotentialDuplicate(int $workspaceId, ?string $phone, ?string $email, string $name): ?Contact
    {
        if ($phone) {
            // Check suffix match (e.g. without country code)
            $suffix = substr($phone, -8);
            $match = Contact::where('workspace_id', $workspaceId)
                ->where('phone_e164', 'LIKE', "%{$suffix}")
                ->first();
            if ($match) {
                return $match;
            }
        }

        if ($email) {
            $match = Contact::where('workspace_id', $workspaceId)
                ->where('email', $email)
                ->first();
            if ($match) {
                return $match;
            }
        }

        return null;
    }

    private function normalizePhone(string $phone): string
    {
        $cleaned = preg_replace('/[^\d+]/', '', $phone);
        if (! str_starts_with($cleaned, '+') && strlen($cleaned) === 10) {
            $cleaned = '+91'.$cleaned; // Default India if 10-digit without prefix
        }

        return $cleaned;
    }
}
