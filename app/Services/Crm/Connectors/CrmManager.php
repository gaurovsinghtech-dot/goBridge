<?php

namespace App\Services\Crm\Connectors;

use App\Models\CrmConnection;
use App\Models\CrmFieldMapping;
use App\Models\CrmSyncLog;
use App\Models\Workspace;
use App\Modules\Shared\Models\Contact;
use Illuminate\Support\Facades\Log;

class CrmManager
{
    /** @var array<string, CrmConnectorInterface> */
    protected array $connectors = [];

    public function __construct()
    {
        $this->register(new HubSpotConnector());
        $this->register(new SalesforceConnector());
        $this->register(new ZohoConnector());
        $this->register(new PipedriveConnector());
        $this->register(new FreshsalesConnector());
        $this->register(new DynamicsConnector());
        $this->register(new GoHighLevelConnector());
        $this->register(new CustomRestConnector());
        $this->register(new GenericWebhookConnector());
    }

    public function register(CrmConnectorInterface $connector): self
    {
        $this->connectors[$connector->getProvider()] = $connector;
        return $this;
    }

    public function driver(string $provider): ?CrmConnectorInterface
    {
        $slug = str_starts_with($provider, 'crm_') ? substr($provider, 4) : $provider;
        return $this->connectors[$slug] ?? null;
    }

    /**
     * Get all registered CRM providers with their metadata
     */
    public function getProviders(): array
    {
        $list = [];
        foreach ($this->connectors as $slug => $connector) {
            $list[$slug] = [
                'provider' => $slug,
                'key' => "crm_{$slug}",
                'label' => $connector->getLabel(),
            ];
        }
        return $list;
    }

    /**
     * Run diagnostics on a CRM configuration
     */
    public function test(string $provider, array $credentials): array
    {
        $driver = $this->driver($provider);
        if (! $driver) {
            return [
                'ok' => false,
                'message' => "Unsupported CRM provider: {$provider}",
                'checks' => [],
            ];
        }

        return $driver->testConnection($credentials);
    }

    /**
     * Synchronize a Growbridge contact to active workspace CRM(s)
     */
    public function syncContactToCrm(Contact $contact, Workspace $workspace, string $action = 'update'): array
    {
        $connections = CrmConnection::where('workspace_id', $workspace->id)
            ->where('status', 'active')
            ->whereIn('sync_direction', ['two_way', 'outbound_only'])
            ->get();

        $results = [];
        foreach ($connections as $conn) {
            $driver = $this->driver($conn->provider);
            if (! $driver) continue;

            $contactData = [
                'first_name' => $contact->first_name ?? $contact->name ?? '',
                'last_name' => $contact->last_name ?? '',
                'email' => $contact->email,
                'phone' => $contact->phone_e164 ?? $contact->phone,
                'company' => $contact->company_name ?? $contact->company ?? null,
                'lead_source' => $contact->source ?? 'Growbridge Connect',
                'tags' => $contact->tags ?? [],
            ];

            try {
                $res = $driver->pushContact($conn->credentials ?? [], $contactData);
                $this->logSync(
                    $workspace->id,
                    $conn->provider,
                    'contact',
                    $action,
                    'outbound',
                    $res['success'] ? 'success' : 'failed',
                    $res['external_id'] ?? null,
                    (string) $contact->id,
                    $res['message'] ?? null,
                    $contactData
                );
                $results[$conn->provider] = $res;
            } catch (\Throwable $e) {
                $this->logSync(
                    $workspace->id,
                    $conn->provider,
                    'contact',
                    $action,
                    'outbound',
                    'failed',
                    null,
                    (string) $contact->id,
                    $e->getMessage(),
                    $contactData
                );
                $results[$conn->provider] = ['success' => false, 'message' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Push communication activity (WhatsApp, SMS, Voice Call, AI Summary) to active workspace CRM(s)
     */
    public function syncActivityToCrm(Workspace $workspace, array $activity): array
    {
        $connections = CrmConnection::where('workspace_id', $workspace->id)
            ->where('status', 'active')
            ->whereIn('sync_direction', ['two_way', 'outbound_only'])
            ->get();

        $results = [];
        foreach ($connections as $conn) {
            $driver = $this->driver($conn->provider);
            if (! $driver) continue;

            try {
                $res = $driver->pushActivity($conn->credentials ?? [], $activity);
                $this->logSync(
                    $workspace->id,
                    $conn->provider,
                    'activity',
                    'create',
                    'outbound',
                    $res['success'] ? 'success' : 'failed',
                    $res['external_id'] ?? null,
                    $activity['id'] ?? null,
                    $res['message'] ?? null,
                    $activity
                );
                $results[$conn->provider] = $res;
            } catch (\Throwable $e) {
                $this->logSync(
                    $workspace->id,
                    $conn->provider,
                    'activity',
                    'create',
                    'outbound',
                    'failed',
                    null,
                    $activity['id'] ?? null,
                    $e->getMessage(),
                    $activity
                );
                $results[$conn->provider] = ['success' => false, 'message' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Pull contacts from connected CRM into Growbridge
     */
    public function pullFromCrm(CrmConnection $conn): array
    {
        $driver = $this->driver($conn->provider);
        if (! $driver) return ['success' => false, 'message' => 'Driver not found'];

        try {
            $contacts = $driver->pullContacts($conn->credentials ?? []);
            $syncedCount = 0;

            foreach ($contacts as $c) {
                $phone = $c['phone'] ?? null;
                $email = $c['email'] ?? null;
                if (! $phone && ! $email) continue;

                $query = Contact::where('workspace_id', $conn->workspace_id);
                if ($phone) {
                    $query->where('phone_e164', $phone);
                } elseif ($email) {
                    $query->where('email', $email);
                }

                $contact = $query->first() ?? new Contact(['workspace_id' => $conn->workspace_id]);
                $contact->fill([
                    'first_name' => $c['first_name'] ?? $contact->first_name,
                    'last_name' => $c['last_name'] ?? $contact->last_name,
                    'email' => $email ?? $contact->email,
                    'phone_e164' => $phone ?? $contact->phone_e164,
                    'source' => "CRM Sync ({$conn->provider})",
                ])->save();

                $syncedCount++;
            }

            $conn->update([
                'last_sync_at' => now(),
                'last_sync_status' => 'success',
                'last_sync_message' => "Successfully pulled {$syncedCount} contacts from {$conn->name}.",
            ]);

            return ['success' => true, 'count' => $syncedCount];
        } catch (\Throwable $e) {
            $conn->update([
                'last_sync_at' => now(),
                'last_sync_status' => 'failed',
                'last_sync_message' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Write structured sync log
     */
    public function logSync(
        int $workspaceId,
        string $provider,
        string $objectType,
        string $action,
        string $direction,
        string $status,
        ?string $externalId = null,
        ?string $internalId = null,
        ?string $error = null,
        ?array $payload = null
    ): CrmSyncLog {
        return CrmSyncLog::create([
            'workspace_id' => $workspaceId,
            'provider' => $provider,
            'object_type' => $objectType,
            'action' => $action,
            'direction' => $direction,
            'status' => $status,
            'external_record_id' => $externalId,
            'internal_record_id' => $internalId,
            'error_message' => $error,
            'payload_json' => $payload,
            'created_at' => now(),
        ]);
    }
}
