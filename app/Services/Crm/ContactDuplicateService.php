<?php

namespace App\Services\Crm;

use App\Models\Workspace;
use App\Modules\Shared\Models\Contact;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ContactDuplicateService
{
    /**
     * Normalize a phone number to standard E.164-like string for comparison.
     */
    public function normalizePhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        // Strip everything except digits and leading +
        $cleaned = preg_replace('/[^\d+]/', '', trim($phone));
        if (empty($cleaned)) {
            return null;
        }

        // If no leading +, ensure uniform digits without leading zero
        if (! str_starts_with($cleaned, '+')) {
            $cleaned = ltrim($cleaned, '0');
        }

        return $cleaned ?: null;
    }

    /**
     * Normalize email to lowercase trimmed string.
     */
    public function normalizeEmail(?string $email): ?string
    {
        if (! $email) {
            return null;
        }

        $trimmed = strtolower(trim($email));

        return filter_var($trimmed, FILTER_VALIDATE_EMAIL) ? $trimmed : null;
    }

    /**
     * Find existing duplicate contact by phone or email within a workspace.
     */
    public function findDuplicate(int $workspaceId, ?string $phone, ?string $email, ?int $excludeContactId = null): ?Contact
    {
        $normalizedPhone = $this->normalizePhone($phone);
        $normalizedEmail = $this->normalizeEmail($email);

        if (! $normalizedPhone && ! $normalizedEmail) {
            return null;
        }

        return Contact::where('workspace_id', $workspaceId)
            ->whereNull('deleted_at')
            ->when($excludeContactId, fn (Builder $q) => $q->where('id', '!=', $excludeContactId))
            ->where(function (Builder $q) use ($normalizedPhone, $normalizedEmail) {
                if ($normalizedPhone && $normalizedEmail) {
                    $q->where(function ($sub) use ($normalizedPhone) {
                        $sub->where('phone_e164', $normalizedPhone)
                            ->orWhere('phone_e164', '+'.$normalizedPhone)
                            ->orWhere('phone_e164', ltrim($normalizedPhone, '+'));
                    })->orWhere('email', $normalizedEmail);
                } elseif ($normalizedPhone) {
                    $q->where('phone_e164', $normalizedPhone)
                        ->orWhere('phone_e164', '+'.$normalizedPhone)
                        ->orWhere('phone_e164', ltrim($normalizedPhone, '+'));
                } elseif ($normalizedEmail) {
                    $q->where('email', $normalizedEmail);
                }
            })
            ->first();
    }

    /**
     * Find all duplicate sets across an entire workspace.
     */
    public function findWorkspaceDuplicates(int $workspaceId): Collection
    {
        return Contact::where('workspace_id', $workspaceId)
            ->whereNull('deleted_at')
            ->select('phone_e164', 'email', DB::raw('count(*) as total'))
            ->groupBy('phone_e164', 'email')
            ->having('total', '>', 1)
            ->get();
    }

    /**
     * Merge duplicate contact into primary contact.
     */
    public function mergeContacts(Contact $primary, Contact $duplicate): Contact
    {
        if ($primary->id === $duplicate->id || $primary->workspace_id !== $duplicate->workspace_id) {
            return $primary;
        }

        DB::transaction(function () use ($primary, $duplicate) {
            // Fill missing primary fields from duplicate
            $fillables = ['email', 'phone_e164', 'first_name', 'last_name', 'company', 'company_id', 'deal_value', 'source'];
            $updates = [];
            foreach ($fillables as $field) {
                if (empty($primary->{$field}) && ! empty($duplicate->{$field})) {
                    $updates[$field] = $duplicate->{$field};
                }
            }

            // Merge custom fields
            $primaryCustom = $primary->custom_fields ?? [];
            $duplicateCustom = $duplicate->custom_fields ?? [];
            $mergedCustom = array_merge($duplicateCustom, $primaryCustom);
            $updates['custom_fields'] = $mergedCustom;

            $primary->update($updates);

            // Re-assign deals, tasks, notes, conversations, timeline events
            \App\Models\Crm\CrmDeal::where('contact_id', $duplicate->id)->update(['contact_id' => $primary->id]);
            \App\Models\Crm\CrmTask::where('contact_id', $duplicate->id)->update(['contact_id' => $primary->id]);
            \App\Models\Crm\CrmNote::where('contact_id', $duplicate->id)->update(['contact_id' => $primary->id]);
            \App\Modules\Shared\Models\Conversation::where('contact_id', $duplicate->id)->update(['contact_id' => $primary->id]);
            \App\Modules\Shared\Models\ContactTimelineEvent::where('contact_id', $duplicate->id)->update(['contact_id' => $primary->id]);

            // Sync tags
            $dupTags = $duplicate->tags()->pluck('id');
            $primary->tags()->syncWithoutDetaching($dupTags);

            // Soft-delete or mark duplicate
            $duplicate->update(['duplicate_of_id' => $primary->id]);
            $duplicate->delete();
        });

        return $primary->fresh();
    }
}
