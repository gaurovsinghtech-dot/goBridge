<?php

namespace App\Services\Crm;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\ContactTimelineEvent;

class CrmAuditService
{
    /**
     * Log a sensitive CRM action.
     */
    public function log(
        Workspace $workspace,
        ?User $actor,
        string $action,
        string $entityType,
        int|string $entityId,
        array $oldValues = [],
        array $newValues = [],
        ?string $description = null
    ): void {
        try {
            // 1. Central AuditLog record
            if (class_exists(AuditLog::class)) {
                AuditLog::create([
                    'client_id' => $workspace->client_id,
                    'user_id' => $actor?->id,
                    'action' => "crm.{$action}",
                    'auditable_type' => $entityType,
                    'auditable_id' => (string) $entityId,
                    'old_values' => ! empty($oldValues) ? $oldValues : null,
                    'new_values' => ! empty($newValues) ? $newValues : null,
                    'meta' => ['workspace_id' => $workspace->id],
                    'ip' => request()->ip() ?? '127.0.0.1',
                    'user_agent' => request()->userAgent() ?? 'cli',
                ]);
            }

            // 2. If action relates to a contact, record in contact timeline
            if ($entityType === 'contact' || $entityType === Contact::class) {
                ContactTimelineEvent::create([
                    'workspace_id' => $workspace->id,
                    'contact_id' => (int) $entityId,
                    'channel' => 'crm',
                    'event_type' => "crm_{$action}",
                    'title' => ucfirst(str_replace('_', ' ', $action)),
                    'description' => $description ?: "CRM action {$action} performed by " . ($actor?->name ?: 'System'),
                    'metadata_json' => [
                        'actor_id' => $actor?->id,
                        'actor_name' => $actor?->name,
                        'old' => $oldValues,
                        'new' => $newValues,
                    ],
                    'occurred_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            // Fail safely without blocking main transaction
        }
    }
}
