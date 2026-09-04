<?php

namespace App\Services\Contacts;

use App\Models\User;
use App\Models\Workspace;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Voice\Models\VoiceCall;
use Illuminate\Support\Facades\DB;

class ContactMergeService
{
    public function __construct() {}

    /**
     * Find potential duplicate contacts in a workspace based on phone or email.
     */
    public function findDuplicates(Workspace $workspace): array
    {
        $duplicatePhones = Contact::where('workspace_id', $workspace->id)
            ->whereNotNull('phone_e164')
            ->select('phone_e164', DB::raw('count(*) as count'))
            ->groupBy('phone_e164')
            ->having('count', '>', 1)
            ->pluck('phone_e164');

        $duplicateEmails = Contact::where('workspace_id', $workspace->id)
            ->whereNotNull('email')
            ->select('email', DB::raw('count(*) as count'))
            ->groupBy('email')
            ->having('count', '>', 1)
            ->pluck('email');

        $contacts = Contact::where('workspace_id', $workspace->id)
            ->where(function ($q) use ($duplicatePhones, $duplicateEmails) {
                $q->whereIn('phone_e164', $duplicatePhones)
                    ->orWhereIn('email', $duplicateEmails);
            })
            ->get();

        return [
            'duplicate_phones_count' => $duplicatePhones->count(),
            'duplicate_emails_count' => $duplicateEmails->count(),
            'records' => $contacts,
        ];
    }

    /**
     * Merge secondary contact into primary contact without losing history.
     */
    public function merge(Contact $primary, Contact $secondary, ?User $performedBy = null): Contact
    {
        if ($primary->id === $secondary->id || $primary->workspace_id !== $secondary->workspace_id) {
            throw new \InvalidArgumentException('Cannot merge identical contact or cross-workspace contacts.');
        }

        DB::transaction(function () use ($primary, $secondary, $performedBy) {
            // 1. Merge tags
            if (method_exists($secondary, 'tags') && method_exists($primary, 'tags')) {
                $tagIds = $secondary->tags()->pluck('contact_tags.id');
                $primary->tags()->syncWithoutDetaching($tagIds);
            }

            // 2. Re-assign conversations
            Conversation::where('contact_id', $secondary->id)->update(['contact_id' => $primary->id]);

            // 3. Re-assign call logs
            VoiceCall::where('contact_id', $secondary->id)->update(['contact_id' => $primary->id]);

            // 4. Update primary with best available fields if missing
            $updates = [];
            if (! $primary->email && $secondary->email) {
                $updates['email'] = $secondary->email;
            }
            if (! $primary->phone_e164 && $secondary->phone_e164) {
                $updates['phone_e164'] = $secondary->phone_e164;
            }
            if (! $primary->company && $secondary->company) {
                $updates['company'] = $secondary->company;
            }
            if ($secondary->lead_score > $primary->lead_score) {
                $updates['lead_score'] = $secondary->lead_score;
            }

            if (! empty($updates)) {
                $primary->update($updates);
            }

            // 5. Delete secondary contact
            $secondary->delete();
        });

        return $primary->fresh();
    }
}
