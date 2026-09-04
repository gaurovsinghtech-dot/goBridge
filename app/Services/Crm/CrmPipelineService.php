<?php

namespace App\Services\Crm;

use App\Models\Crm\CrmDeal;
use App\Models\Crm\CrmPipeline;
use App\Models\Crm\CrmPipelineStage;
use App\Models\User;
use App\Modules\Shared\Models\Contact;
use App\Services\CustomerJourney\CustomerJourneyService;
use Illuminate\Support\Collection;

class CrmPipelineService
{
    public function __construct(
        private readonly CustomerJourneyService $journeyService
    ) {}

    /**
     * Ensure a default sales pipeline and stages exist for the workspace.
     */
    public function ensureDefaultPipeline(int $workspaceId): CrmPipeline
    {
        $pipeline = CrmPipeline::where('workspace_id', $workspaceId)->where('is_default', true)->first();

        if (! $pipeline) {
            $pipeline = CrmPipeline::firstOrCreate(
                ['workspace_id' => $workspaceId, 'name' => 'Sales Pipeline'],
                ['is_default' => true]
            );
        }

        if ($pipeline->stages()->count() === 0) {
            foreach (CrmPipelineStage::DEFAULTS as $idx => $stageData) {
                CrmPipelineStage::create(array_merge($stageData, [
                    'workspace_id' => $workspaceId,
                    'pipeline_id' => $pipeline->id,
                    'position' => $idx,
                ]));
            }
        }

        return $pipeline;
    }

    /**
     * Get all pipelines for a workspace.
     */
    public function getWorkspacePipelines(int $workspaceId): Collection
    {
        $this->ensureDefaultPipeline($workspaceId);

        return CrmPipeline::with(['stages'])->where('workspace_id', $workspaceId)->get();
    }

    /**
     * Build the Kanban board data structure with aggregated column values.
     */
    public function getKanbanBoard(int $workspaceId, ?int $pipelineId = null, array $filters = []): array
    {
        $pipeline = $pipelineId
            ? CrmPipeline::where('workspace_id', $workspaceId)->findOrFail($pipelineId)
            : $this->ensureDefaultPipeline($workspaceId);

        $stages = $pipeline->stages()->orderBy('position')->get();

        $query = Contact::where('workspace_id', $workspaceId)
            ->where(function ($q) use ($pipeline) {
                $q->where('pipeline_id', $pipeline->id)
                  ->orWhereNull('pipeline_id');
            })
            ->with(['assignedUser', 'tags', 'deals']);

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('first_name', 'like', $term)
                  ->orWhere('last_name', 'like', $term)
                  ->orWhere('phone_e164', 'like', $term)
                  ->orWhere('email', 'like', $term)
                  ->orWhere('company', 'like', $term);
            });
        }

        if (! empty($filters['assigned_user_id'])) {
            $query->where('assigned_user_id', $filters['assigned_user_id']);
        }

        if (! empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        if (! empty($filters['band'])) {
            $query->where('lead_score_band', $filters['band']);
        }

        if (! empty($filters['due_only'])) {
            $query->whereNotNull('next_follow_up_at')->where('next_follow_up_at', '<=', now());
        }

        $contacts = $query->get();

        $firstStageId = $stages->first()?->id;
        $columns = [];
        $totalPipelineValue = 0.0;
        $totalWeightedValue = 0.0;
        $totalDealsCount = 0;

        foreach ($stages as $stage) {
            $stageContacts = $contacts->filter(function ($c) use ($stage, $firstStageId) {
                if ($c->stage_id) {
                    return (int) $c->stage_id === (int) $stage->id;
                }
                return (int) $stage->id === (int) $firstStageId;
            })->values();

            $stageValue = $stageContacts->sum('deal_value');
            $stageWeighted = round($stageValue * ($stage->probability / 100), 2);
            $stageDealsCount = $stageContacts->sum(fn ($c) => $c->deals->count() ?: 1);

            $totalPipelineValue += $stageValue;
            $totalWeightedValue += $stageWeighted;
            $totalDealsCount += $stageContacts->count();

            $columns[] = [
                'id' => $stage->id,
                'name' => $stage->name,
                'color' => $stage->color,
                'probability' => $stage->probability,
                'position' => $stage->position,
                'is_won' => $stage->is_won,
                'is_lost' => $stage->is_lost,
                'stats' => [
                    'count' => $stageContacts->count(),
                    'total_value' => $stageValue,
                    'weighted_value' => $stageWeighted,
                ],
                'leads' => $stageContacts->map(fn ($c) => [
                    'id' => $c->id,
                    'uuid' => $c->uuid,
                    'name' => $c->full_name ?: ($c->phone_e164 ?: 'Unnamed Lead'),
                    'company' => $c->company,
                    'phone' => $c->phone_e164,
                    'email' => $c->email,
                    'source' => $c->source ?? 'manual',
                    'deal_value' => (float) $c->deal_value,
                    'score' => (int) $c->lead_score,
                    'score_band' => $c->lead_score_band ?? 'cold',
                    'priority' => $c->priority ?? 'medium',
                    'assigned_user' => $c->assignedUser ? [
                        'id' => $c->assignedUser->id,
                        'name' => $c->assignedUser->name,
                    ] : null,
                    'tags' => $c->tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'color' => $t->color]),
                    'next_follow_up_at' => $c->next_follow_up_at?->toIso8601String(),
                    'is_overdue' => $c->next_follow_up_at ? $c->next_follow_up_at->isPast() : false,
                ]),
            ];
        }

        return [
            'pipeline' => [
                'id' => $pipeline->id,
                'name' => $pipeline->name,
                'is_default' => $pipeline->is_default,
            ],
            'summary' => [
                'total_leads' => $totalDealsCount,
                'total_pipeline_value' => round($totalPipelineValue, 2),
                'total_weighted_value' => round($totalWeightedValue, 2),
            ],
            'columns' => $columns,
        ];
    }

    /**
     * Move contact between stages and record history.
     */
    public function moveContactStage(Contact $contact, int $stageId, ?string $lossReason = null, ?User $actor = null): Contact
    {
        $newStage = CrmPipelineStage::where('workspace_id', $contact->workspace_id)->findOrFail($stageId);
        $oldStageName = $contact->stage?->name ?? 'Unassigned';

        $contact->update([
            'pipeline_id' => $newStage->pipeline_id,
            'stage_id' => $newStage->id,
            'loss_reason' => $newStage->is_lost ? $lossReason : null,
        ]);

        $this->journeyService->recordEvent(
            contactId: $contact->id,
            workspaceId: $contact->workspace_id,
            eventType: 'crm_stage_change',
            channel: 'crm',
            title: "Lead moved to {$newStage->name}",
            description: "Stage changed from {$oldStageName} to {$newStage->name}".($lossReason ? " (Reason: {$lossReason})" : ''),
            metadata: [
                'old_stage' => $oldStageName,
                'new_stage' => $newStage->name,
                'loss_reason' => $lossReason,
                'actor_id' => $actor?->id,
                'actor_name' => $actor?->name,
            ]
        );

        return $contact->fresh(['stage', 'pipeline', 'assignedUser']);
    }
}
