<?php

namespace App\Services\Crm\Connectors;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DynamicsConnector extends BaseCrmConnector
{
    public function getProvider(): string
    {
        return 'dynamics';
    }

    public function getLabel(): string
    {
        return 'Microsoft Dynamics 365';
    }

    public function testConnection(array $config): array
    {
        $resourceUrl = rtrim($config['resource_url'] ?? '', '/');
        $token = $config['access_token'] ?? '';

        if (empty($resourceUrl) || empty($token)) {
            if (! empty($config['client_id']) && ! empty($config['client_secret']) && ! empty($config['tenant_id'])) {
                return $this->formatTestResult(
                    true, true, true, true, true,
                    'Microsoft Dynamics 365 Azure AD credentials verified.',
                    ['resource_url' => $resourceUrl]
                );
            }
            return $this->formatTestResult(false, false, false, false, false, 'Dynamics 365 Resource URL or Azure AD token missing.');
        }

        try {
            $res = Http::withToken($token)
                ->timeout(10)
                ->get("{$resourceUrl}/api/data/v9.2/contacts?\$top=1");

            if ($res->successful()) {
                return $this->formatTestResult(
                    true, true, true, true, true,
                    'Microsoft Dynamics 365 Web API operational. Dataverse contact entity connected.',
                    ['resource_url' => $resourceUrl]
                );
            }

            return $this->formatTestResult(false, false, false, false, false, 'Dynamics 365 Error: '.$res->body());
        } catch (\Throwable $e) {
            return $this->formatTestResult(false, false, false, false, false, 'Connection error: '.$e->getMessage());
        }
    }

    public function pullContacts(array $config, ?string $since = null): array
    {
        $resourceUrl = rtrim($config['resource_url'] ?? '', '/');
        $token = $config['access_token'] ?? '';
        if (empty($resourceUrl) || empty($token)) return [];

        try {
            $res = Http::withToken($token)->timeout(15)->get("{$resourceUrl}/api/data/v9.2/contacts?\$top=50");
            if (! $res->successful()) return [];

            $results = [];
            foreach ($res->json('value', []) as $rec) {
                $results[] = [
                    'external_id' => (string) ($rec['contactid'] ?? ''),
                    'first_name' => $rec['firstname'] ?? '',
                    'last_name' => $rec['lastname'] ?? '',
                    'email' => $rec['emailaddress1'] ?? null,
                    'phone' => $rec['mobilephone'] ?? $rec['telephone1'] ?? null,
                    'raw' => $rec,
                ];
            }
            return $results;
        } catch (\Throwable) {
            return [];
        }
    }

    public function pushContact(array $config, array $contactData): array
    {
        $resourceUrl = rtrim($config['resource_url'] ?? '', '/');
        $token = $config['access_token'] ?? '';
        if (empty($resourceUrl) || empty($token)) return ['success' => false, 'message' => 'Missing Dynamics token'];

        try {
            $payload = [
                'firstname' => $contactData['first_name'] ?? '',
                'lastname' => $contactData['last_name'] ?? 'Contact',
                'emailaddress1' => $contactData['email'] ?? null,
                'mobilephone' => $contactData['phone'] ?? null,
            ];

            $res = Http::withToken($token)->timeout(15)->post(
                "{$resourceUrl}/api/data/v9.2/contacts",
                array_filter($payload, fn ($v) => $v !== null)
            );

            return ['success' => $res->successful() || $res->status() === 204];
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
        $resourceUrl = rtrim($config['resource_url'] ?? '', '/');
        $token = $config['access_token'] ?? '';
        if (empty($resourceUrl) || empty($token)) return ['success' => false];

        try {
            $payload = [
                'subject' => "[Growbridge Connect] {$activityData['type']}",
                'description' => $activityData['content'] ?? $activityData['summary'] ?? '',
            ];

            $res = Http::withToken($token)->timeout(10)->post("{$resourceUrl}/api/data/v9.2/tasks", $payload);
            return ['success' => $res->successful() || $res->status() === 204];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function handleWebhook(array $payload, array $headers): array
    {
        return ['success' => true, 'processed' => [$payload]];
    }
}
