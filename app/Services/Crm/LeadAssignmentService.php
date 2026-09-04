<?php

namespace App\Services\Crm;

use App\Models\Crm\CrmTeam;
use App\Models\User;
use App\Modules\Shared\Models\Contact;
use App\Services\CustomerJourney\CustomerJourneyService;
use Illuminate\Support\Collection;

class LeadAssignmentService
{
    public function __construct(
        private readonly CustomerJourneyService $journeyService
    ) {}

    /**
     * Assign lead/contact to an agent using configured strategy.
     */
    public function assignLead(Contact $contact, string $strategy = 'round_robin', ?int $teamId = null, ?User $actor = null): ?User
    {
        $eligibleUsers = $this->getEligibleUsers($contact->workspace_id, $teamId);

        if ($eligibleUsers->isEmpty()) {
            return null;
        }

        $assignedUser = match ($strategy) {
            'least_assigned' => $this->assignLeastAssigned($contact->workspace_id, $eligibleUsers),
            'source_based' => $this->assignSourceBased($contact, $eligibleUsers),
            default => $this->assignRoundRobin($contact->workspace_id, $eligibleUsers),
        };

        if ($assignedUser) {
            $contact->update([
                'assigned_user_id' => $assignedUser->id,
                'assigned_team_id' => $teamId,
            ]);

            $this->journeyService->recordEvent(
                contactId: $contact->id,
                workspaceId: $contact->workspace_id,
                eventType: 'crm_lead_assigned',
                channel: 'crm',
                title: "Lead assigned to {$assignedUser->name}",
                description: "Lead routed via {$strategy} strategy to {$assignedUser->name}",
                metadata: [
                    'strategy' => $strategy,
                    'user_id' => $assignedUser->id,
                    'user_name' => $assignedUser->name,
                    'team_id' => $teamId,
                    'actor_id' => $actor?->id,
                ]
            );
        }

        return $assignedUser;
    }

    /**
     * Get active workspace users or team members.
     */
    private function getEligibleUsers(int $workspaceId, ?int $teamId = null): Collection
    {
        if ($teamId) {
            $team = CrmTeam::where('workspace_id', $workspaceId)->find($teamId);
            if ($team && $team->members()->exists()) {
                return $team->members()->where('status', 'active')->get();
            }
        }

        return User::where('workspace_id', $workspaceId)
            ->where('status', 'active')
            ->get();
    }

    /**
     * Round Robin: find user with oldest last-assigned contact.
     */
    private function assignRoundRobin(int $workspaceId, Collection $users): ?User
    {
        $userIds = $users->pluck('id')->toArray();

        $lastAssignedContact = Contact::where('workspace_id', $workspaceId)
            ->whereIn('assigned_user_id', $userIds)
            ->latest('updated_at')
            ->first();

        if (! $lastAssignedContact) {
            return $users->first();
        }

        $currentIndex = array_search($lastAssignedContact->assigned_user_id, $userIds, true);
        $nextIndex = ($currentIndex === false || $currentIndex >= count($userIds) - 1) ? 0 : $currentIndex + 1;

        return $users->values()[$nextIndex] ?? $users->first();
    }

    /**
     * Least Assigned: find user with fewest active contacts.
     */
    private function assignLeastAssigned(int $workspaceId, Collection $users): ?User
    {
        $counts = Contact::where('workspace_id', $workspaceId)
            ->whereIn('assigned_user_id', $users->pluck('id'))
            ->groupBy('assigned_user_id')
            ->selectRaw('assigned_user_id, count(*) as count')
            ->pluck('count', 'assigned_user_id');

        return $users->sortBy(fn ($user) => $counts[$user->id] ?? 0)->first();
    }

    /**
     * Source Based: route according to lead source or fallback to least assigned.
     */
    private function assignSourceBased(Contact $contact, Collection $users): ?User
    {
        return $this->assignLeastAssigned($contact->workspace_id, $users);
    }
}
