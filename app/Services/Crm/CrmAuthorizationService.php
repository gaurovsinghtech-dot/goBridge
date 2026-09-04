<?php

namespace App\Services\Crm;

use App\Models\User;
use App\Models\Workspace;
use App\Modules\Shared\Models\Contact;
use Illuminate\Auth\Access\AuthorizationException;

class CrmAuthorizationService
{
    public const ROLE_OWNER = 'owner';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_SALESPERSON = 'salesperson';

    /**
     * Resolve effective CRM role of user in the workspace.
     */
    public function getUserRole(User $user, Workspace $workspace): string
    {
        // 1. Is workspace owner
        if ((int) $workspace->owner_id === (int) $user->id) {
            return self::ROLE_OWNER;
        }

        // 2. Client role check
        if ($user->role === User::ROLE_ADMIN) {
            return self::ROLE_ADMIN;
        }

        // 3. Workspace pivot role
        $membership = $workspace->members()->where('user_id', $user->id)->first();
        if ($membership && isset($membership->pivot->role)) {
            $pivotRole = strtolower($membership->pivot->role);
            if (in_array($pivotRole, [self::ROLE_OWNER, self::ROLE_ADMIN, self::ROLE_MANAGER, self::ROLE_SALESPERSON], true)) {
                return $pivotRole;
            }
        }

        if ($user->client_role === User::CLIENT_ROLE_ADMINISTRATOR) {
            return self::ROLE_ADMIN;
        }

        return self::ROLE_SALESPERSON;
    }

    /**
     * Can user manage pipeline configurations, custom fields, and workspace settings.
     */
    public function canManageSettings(User $user, Workspace $workspace): bool
    {
        $role = $this->getUserRole($user, $workspace);

        return in_array($role, [self::ROLE_OWNER, self::ROLE_ADMIN], true);
    }

    /**
     * Can user view all contacts/leads or only their assigned records.
     */
    public function canViewAll(User $user, Workspace $workspace): bool
    {
        $role = $this->getUserRole($user, $workspace);

        return in_array($role, [self::ROLE_OWNER, self::ROLE_ADMIN, self::ROLE_MANAGER], true);
    }

    /**
     * Authorize access to a specific contact/lead/deal/task.
     *
     * @throws AuthorizationException
     */
    public function authorizeContactAccess(User $user, Contact $contact): void
    {
        $workspace = $contact->workspace;
        if (! $workspace) {
            throw new AuthorizationException('Contact workspace not found.');
        }

        // Cross-workspace check
        $userWorkspaceId = (int) ($user->current_workspace_id ?? $user->workspace_id);
        if ($userWorkspaceId && (int) $contact->workspace_id !== $userWorkspaceId) {
            throw new AuthorizationException('Cross-workspace access forbidden.');
        }

        $role = $this->getUserRole($user, $workspace);
        if (in_array($role, [self::ROLE_OWNER, self::ROLE_ADMIN, self::ROLE_MANAGER], true)) {
            return;
        }

        // Salesperson can only view unassigned or their assigned contacts
        if ($role === self::ROLE_SALESPERSON) {
            if (is_null($contact->assigned_user_id) || (int) $contact->assigned_user_id === (int) $user->id) {
                return;
            }
            throw new AuthorizationException('You are not authorized to access this assigned record.');
        }

        throw new AuthorizationException('Unauthorized CRM access.');
    }
}
