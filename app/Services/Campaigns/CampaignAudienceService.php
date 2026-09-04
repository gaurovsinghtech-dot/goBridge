<?php

namespace App\Services\Campaigns;

use App\Modules\Broadcasting\Models\Campaign;
use App\Modules\Broadcasting\Models\CampaignRecipient;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\ContactTag;
use App\Modules\Shared\Models\Segment;
use App\Modules\Shared\Services\SegmentResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CampaignAudienceService
{
    public function __construct(
        protected SegmentResolver $segmentResolver
    ) {}

    /**
     * Resolve all candidate contact IDs for an audience definition.
     *
     * @return array<int>
     */
    public function resolveCandidateContactIds(int $workspaceId, string $audienceType, mixed $audienceRef = null): array
    {
        $query = Contact::where('workspace_id', $workspaceId);

        // Check if audienceRef is JSON-encoded filter payload or array
        $parsedFilter = null;
        if (is_array($audienceRef)) {
            $parsedFilter = $audienceRef;
        } elseif (is_string($audienceRef) && (str_starts_with(trim($audienceRef), '{') || str_starts_with(trim($audienceRef), '['))) {
            $parsedFilter = json_decode($audienceRef, true);
        }

        if ($parsedFilter && is_array($parsedFilter) && isset($parsedFilter['filters'])) {
            $this->applyStructuredFilters($query, $parsedFilter['filters']);
            return $query->pluck('id')->all();
        }

        return match ($audienceType) {
            'all_contacts', 'all' => $query->pluck('id')->all(),
            'segment' => ! empty($audienceRef)
                ? $this->resolveSegmentContactIds($workspaceId, (string) $audienceRef)
                : [],
            'tag', 'tags' => ! empty($audienceRef)
                ? $this->resolveTagContactIds($workspaceId, (string) $audienceRef)
                : [],
            'crm_stage', 'stage', 'pipeline_stage' => ! empty($audienceRef)
                ? $query->where('stage_id', (int) $audienceRef)->pluck('id')->all()
            : [],
            'pipeline' => ! empty($audienceRef)
                ? $query->where('pipeline_id', (int) $audienceRef)->pluck('id')->all()
            : [],
            'lead_status', 'status' => ! empty($audienceRef)
                ? $query->where('status', (string) $audienceRef)->pluck('id')->all()
            : [],
            'owner', 'assigned_user' => ! empty($audienceRef)
                ? $query->where('assigned_user_id', (int) $audienceRef)->pluck('id')->all()
            : [],
            'source' => ! empty($audienceRef)
                ? $query->where('source', (string) $audienceRef)->pluck('id')->all()
            : [],
            'city' => ! empty($audienceRef)
                ? $query->where(function (Builder $q) use ($audienceRef) {
                    $q->where('city', (string) $audienceRef)
                        ->orWhere('custom_fields->city', (string) $audienceRef);
                })->pluck('id')->all()
            : [],
            'lead_score' => ! empty($audienceRef)
                ? $this->resolveLeadScoreContactIds($workspaceId, (string) $audienceRef)
                : [],
            'contact_list', 'manual' => ! empty($audienceRef)
                ? array_values(array_filter(array_map('intval', explode(',', (string) $audienceRef))))
                : $query->pluck('id')->all(),
            'csv' => ! empty($audienceRef)
                ? array_values(array_filter(array_map('intval', explode(',', (string) $audienceRef))))
                : [],
            default => [],
        };
    }

    /**
     * Apply structured multi-criteria filters securely scoped to workspace.
     */
    protected function applyStructuredFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['tags'])) {
            $tags = is_array($filters['tags']) ? $filters['tags'] : explode(',', (string) $filters['tags']);
            $query->whereHas('contactTags', fn ($t) => $t->whereIn('name', $tags)->orWhereIn('id', array_filter(array_map('intval', $tags))));
        }

        if (! empty($filters['status'])) {
            $statuses = is_array($filters['status']) ? $filters['status'] : [(string) $filters['status']];
            $query->whereIn('status', $statuses);
        }

        if (! empty($filters['pipeline_id'])) {
            $query->where('pipeline_id', (int) $filters['pipeline_id']);
        }

        if (! empty($filters['stage_id'])) {
            $query->where('stage_id', (int) $filters['stage_id']);
        }

        if (! empty($filters['assigned_user_id'])) {
            $query->where('assigned_user_id', (int) $filters['assigned_user_id']);
        }

        if (! empty($filters['source'])) {
            $query->where('source', (string) $filters['source']);
        }

        if (! empty($filters['city'])) {
            $city = (string) $filters['city'];
            $query->where('custom_fields->city', $city);
        }

        if (! empty($filters['custom_fields']) && is_array($filters['custom_fields'])) {
            foreach ($filters['custom_fields'] as $key => $value) {
                $query->where("custom_fields->{$key}", $value);
            }
        }

        if (! empty($filters['created_after'])) {
            $query->where('created_at', '>=', $filters['created_after']);
        }

        if (! empty($filters['created_before'])) {
            $query->where('created_at', '<=', $filters['created_before']);
        }

        if (! empty($filters['last_seen_after'])) {
            $query->where('last_seen_at', '>=', $filters['last_seen_after']);
        }
    }

    /**
     * Compute full suppression breakdown and deliverable count for a campaign audience.
     *
     * @return array{
     *     total_matched: int,
     *     total_audience: int,
     *     opted_out_count: int,
     *     invalid_address_count: int,
     *     frequency_capped_count: int,
     *     excluded_recipients: int,
     *     deliverable_count: int,
     *     valid_recipients: int,
     *     estimated_usage: int,
     *     estimated_cost: float,
     *     requires_confirmation: bool,
     *     sample: array
     * }
     */
    public function analyzeAudienceSuppression(
        int $workspaceId,
        string $channel,
        string $audienceType,
        mixed $audienceRef = null,
        int $frequencyCapDays = 7,
        int $frequencyCapMax = 3
    ): array {
        $candidateIds = $this->resolveCandidateContactIds($workspaceId, $audienceType, $audienceRef);
        $totalMatched = count($candidateIds);

        if ($totalMatched === 0) {
            return [
                'total_matched' => 0,
                'total_audience' => 0,
                'opted_out_count' => 0,
                'invalid_address_count' => 0,
                'frequency_capped_count' => 0,
                'excluded_recipients' => 0,
                'deliverable_count' => 0,
                'valid_recipients' => 0,
                'estimated_usage' => 0,
                'estimated_cost' => 0.0,
                'requires_confirmation' => false,
                'deliverable_ids' => [],
                'sample' => [],
            ];
        }

        $contacts = Contact::where('workspace_id', $workspaceId)
            ->whereIn('id', $candidateIds)
            ->get(['id', 'first_name', 'last_name', 'phone_e164', 'email', 'opt_in_whatsapp', 'opt_in_sms', 'opt_in_email']);

        // Check frequency capping: contacts who received >= $frequencyCapMax campaign messages in last $frequencyCapDays days
        $frequencyCappedIds = [];
        if ($frequencyCapDays > 0 && $frequencyCapMax > 0) {
            $frequencyCappedIds = CampaignRecipient::whereHas('campaign', fn ($q) => $q->where('workspace_id', $workspaceId))
                ->whereIn('contact_id', $candidateIds)
                ->where('sent_at', '>=', now()->subDays($frequencyCapDays))
                ->selectRaw('contact_id, count(*) as count')
                ->groupBy('contact_id')
                ->having('count', '>=', $frequencyCapMax)
                ->pluck('contact_id')
                ->all();
        }
        $frequencyCappedLookup = array_flip($frequencyCappedIds);

        $optedOutCount = 0;
        $invalidAddressCount = 0;
        $frequencyCappedCount = 0;
        $deliverableIds = [];
        $sample = [];

        $optInColumn = match ($channel) {
            'whatsapp' => 'opt_in_whatsapp',
            'sms' => 'opt_in_sms',
            'email' => 'opt_in_email',
            default => 'opt_in_whatsapp',
        };

        foreach ($contacts as $contact) {
            // 1. Opt-out check
            if ($channel !== 'instagram' && $channel !== 'messenger' && ! $contact->{$optInColumn}) {
                $optedOutCount++;
                continue;
            }

            // 2. Channel Address check
            $hasAddress = match ($channel) {
                'email' => ! empty($contact->email) && filter_var($contact->email, FILTER_VALIDATE_EMAIL),
                'whatsapp', 'sms' => ! empty($contact->phone_e164),
                'instagram', 'messenger' => ! empty($contact->phone_e164) || ! empty($contact->email),
                default => true,
            };

            if (! $hasAddress) {
                $invalidAddressCount++;
                continue;
            }

            // 3. Frequency Capping check
            if (isset($frequencyCappedLookup[$contact->id])) {
                $frequencyCappedCount++;
                continue;
            }

            $deliverableIds[] = $contact->id;
            if (count($sample) < 5) {
                $sample[] = [
                    'id' => $contact->id,
                    'first_name' => $contact->first_name,
                    'last_name' => $contact->last_name,
                    'phone_e164' => $contact->phone_e164,
                    'email' => $contact->email,
                ];
            }
        }

        $deliverableCount = count($deliverableIds);
        $excludedCount = $optedOutCount + $invalidAddressCount + $frequencyCappedCount;
        $costPerRecipient = match ($channel) {
            'whatsapp' => 0.05,
            'sms' => 0.02,
            'email' => 0.001,
            default => 0.0,
        };

        return [
            'total_matched' => $totalMatched,
            'total_audience' => $totalMatched,
            'opted_out_count' => $optedOutCount,
            'invalid_address_count' => $invalidAddressCount,
            'frequency_capped_count' => $frequencyCappedCount,
            'excluded_recipients' => $excludedCount,
            'deliverable_count' => $deliverableCount,
            'valid_recipients' => $deliverableCount,
            'estimated_usage' => $deliverableCount,
            'estimated_cost' => round($deliverableCount * $costPerRecipient, 4),
            'requires_confirmation' => $deliverableCount >= 500,
            'deliverable_ids' => $deliverableIds,
            'sample' => $sample,
        ];
    }

    /**
     * Resolve segment contacts using the centralized SegmentResolver.
     */
    protected function resolveSegmentContactIds(int $workspaceId, string $segmentRef): array
    {
        $segment = is_numeric($segmentRef)
            ? Segment::where('workspace_id', $workspaceId)->find((int) $segmentRef)
            : Segment::where('workspace_id', $workspaceId)->where('name', $segmentRef)->first();

        if (! $segment) {
            return [];
        }

        return $this->segmentResolver->resolveIds($segment);
    }

    /**
     * Resolve tag contacts using contact_tag links or direct contact tags.
     */
    protected function resolveTagContactIds(int $workspaceId, string $tagRef): array
    {
        $tags = explode(',', $tagRef);

        return Contact::where('workspace_id', $workspaceId)
            ->where(function (Builder $q) use ($tags) {
                $q->whereHas('contactTags', fn ($t) => $t->whereIn('name', $tags)->orWhereIn('id', array_filter(array_map('intval', $tags))))
                    ->orWhere(function ($sub) use ($tags) {
                        foreach ($tags as $t) {
                            $sub->orWhere('tags', 'like', '%"'.trim($t).'"%');
                        }
                    });
            })
            ->pluck('id')
            ->all();
    }

    /**
     * Resolve lead score band contacts (e.g. 'hot' >= 70, 'warm' 40-69, 'cold' < 40).
     */
    protected function resolveLeadScoreContactIds(int $workspaceId, string $scoreRef): array
    {
        $query = Contact::where('workspace_id', $workspaceId);

        match (strtolower(trim($scoreRef))) {
            'hot', 'very_hot' => $query->where('lead_score', '>=', 70),
            'warm' => $query->whereBetween('lead_score', [40, 69]),
            'cold' => $query->where('lead_score', '<', 40),
            default => is_numeric($scoreRef)
                ? $query->where('lead_score', '>=', (int) $scoreRef)
                : $query,
        };

        return $query->pluck('id')->all();
    }
}
