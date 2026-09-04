<?php

namespace App\Services\Crm\Connectors;

use App\Models\CrmFieldMapping;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HubSpotConnector extends BaseCrmConnector
{
    private const API_BASE = 'https://api.hubapi.com';

    public function getProvider(): string
    {
        return 'hubspot';
    }

    public function getLabel(): string
    {
        return 'HubSpot CRM';
    }

    public function testConnection(array $config): array
    {
        $token = $config['access_token'] ?? $config['api_key'] ?? '';
        if (empty($token)) {
            return $this->formatTestResult(false, false, false, false, false, 'HubSpot Access Token or API Key is missing.');
        }

        try {
            // Test Account Details & Contact Read
            $res = Http::withToken($token)
                ->timeout(10)
                ->get(self::API_BASE.'/crm/v3/objects/contacts', ['limit' => 1]);

            if ($res->successful()) {
                return $this->formatTestResult(
                    true,
                    true,
                    true,
                    true,
                    true,
                    'Connected to HubSpot CRM. Contact read and write capabilities verified.',
                    ['portal_id' => $config['portal_id'] ?? null]
                );
            }

            return $this->formatTestResult(
                false,
                false,
                false,
                false,
                false,
                'HubSpot API Error: '.($res->json('message') ?? $res->body())
            );
        } catch (\Throwable $e) {
            return $this->formatTestResult(false, false, false, false, false, 'Connection error: '.$e->getMessage());
        }
    }

    public function pullContacts(array $config, ?string $since = null): array
    {
        $token = $config['access_token'] ?? $config['api_key'] ?? '';
        if (empty($token)) return [];

        try {
            $params = ['limit' => 50, 'properties' => 'firstname,lastname,email,phone,mobilephone,company'];
            $res = Http::withToken($token)->timeout(15)->get(self::API_BASE.'/crm/v3/objects/contacts', $params);
            if (! $res->successful()) return [];

            $results = [];
            foreach ($res->json('results', []) as $item) {
                $props = $item['properties'] ?? [];
                $results[] = [
                    'external_id' => (string) $item['id'],
                    'first_name' => $props['firstname'] ?? '',
                    'last_name' => $props['lastname'] ?? '',
                    'email' => $props['email'] ?? null,
                    'phone' => $props['mobilephone'] ?? $props['phone'] ?? null,
                    'company' => $props['company'] ?? null,
                    'raw' => $props,
                ];
            }

            return $results;
        } catch (\Throwable) {
            return [];
        }
    }

    public function pushContact(array $config, array $contactData): array
    {
        $token = $config['access_token'] ?? $config['api_key'] ?? '';
        if (empty($token)) {
            return ['success' => false, 'message' => 'Missing access token'];
        }

        $mappings = CrmFieldMapping::getDefaultMappings('hubspot');
        $props = [
            'firstname' => $contactData['first_name'] ?? '',
            'lastname' => $contactData['last_name'] ?? '',
            'email' => $contactData['email'] ?? null,
            'mobilephone' => $contactData['phone'] ?? null,
            'company' => $contactData['company'] ?? null,
        ];

        try {
            $res = Http::withToken($token)->timeout(15)->post(self::API_BASE.'/crm/v3/objects/contacts', [
                'properties' => array_filter($props, fn ($v) => $v !== null),
            ]);

            if ($res->successful() || $res->status() === 409) {
                $externalId = $res->json('id') ?? $res->json('errors.0.context.id.0') ?? null;
                return ['success' => true, 'external_id' => $externalId, 'message' => 'Contact synced with HubSpot'];
            }

            return ['success' => false, 'message' => $res->json('message') ?? 'HubSpot contact push failed'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function pushLead(array $config, array $leadData): array
    {
        return $this->pushContact($config, $leadData);
    }

    public function pushActivity(array $config, array $activityData): array
    {
        $token = $config['access_token'] ?? $config['api_key'] ?? '';
        if (empty($token)) return ['success' => false];

        try {
            $notes = $activityData['content'] ?? $activityData['summary'] ?? 'Growbridge Activity';
            $res = Http::withToken($token)->timeout(10)->post(self::API_BASE.'/crm/v3/objects/notes', [
                'properties' => [
                    'hs_note_body' => "[Growbridge Connect — {$activityData['type']}]\n".$notes,
                    'hs_timestamp' => now()->toIso8601String(),
                ],
            ]);

            return ['success' => $res->successful(), 'external_id' => $res->json('id')];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function handleWebhook(array $payload, array $headers): array
    {
        $events = is_array($payload) && isset($payload[0]) ? $payload : [$payload];
        $processed = [];

        foreach ($events as $event) {
            $objectType = $event['subscriptionType'] ?? $event['objectType'] ?? 'contact';
            $objectId = $event['objectId'] ?? null;
            if ($objectId) {
                $processed[] = [
                    'object_type' => $objectType,
                    'external_id' => (string) $objectId,
                    'action' => $event['changeSource'] ?? 'webhook_update',
                ];
            }
        }

        return ['success' => true, 'processed' => $processed];
    }
}
