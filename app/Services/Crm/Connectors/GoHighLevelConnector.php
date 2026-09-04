<?php

namespace App\Services\Crm\Connectors;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoHighLevelConnector extends BaseCrmConnector
{
    private const API_BASE = 'https://services.leadconnectorhq.com';

    public function getProvider(): string
    {
        return 'gohighlevel';
    }

    public function getLabel(): string
    {
        return 'GoHighLevel';
    }

    public function testConnection(array $config): array
    {
        $apiKey = $config['api_key'] ?? $config['access_token'] ?? '';
        $locationId = $config['location_id'] ?? '';

        if (empty($apiKey)) {
            return $this->formatTestResult(false, false, false, false, false, 'GoHighLevel API Key or OAuth Access Token missing.');
        }

        try {
            $headers = ['Authorization' => "Bearer {$apiKey}", 'Version' => '2021-07-28'];
            $params = $locationId ? ['locationId' => $locationId, 'limit' => 1] : ['limit' => 1];

            $res = Http::withHeaders($headers)->timeout(10)->get(self::API_BASE.'/contacts/', $params);

            if ($res->successful()) {
                return $this->formatTestResult(
                    true, true, true, true, true,
                    'GoHighLevel API connected. Contacts API v2 accessible.',
                    ['location_id' => $locationId]
                );
            }

            return $this->formatTestResult(false, false, false, false, false, 'GoHighLevel Error: '.$res->body());
        } catch (\Throwable $e) {
            return $this->formatTestResult(false, false, false, false, false, 'Connection error: '.$e->getMessage());
        }
    }

    public function pullContacts(array $config, ?string $since = null): array
    {
        $apiKey = $config['api_key'] ?? $config['access_token'] ?? '';
        $locationId = $config['location_id'] ?? '';
        if (empty($apiKey)) return [];

        try {
            $headers = ['Authorization' => "Bearer {$apiKey}", 'Version' => '2021-07-28'];
            $params = array_filter(['locationId' => $locationId, 'limit' => 50]);

            $res = Http::withHeaders($headers)->timeout(15)->get(self::API_BASE.'/contacts/', $params);
            if (! $res->successful()) return [];

            $results = [];
            foreach ($res->json('contacts', []) as $rec) {
                $results[] = [
                    'external_id' => (string) ($rec['id'] ?? ''),
                    'first_name' => $rec['firstName'] ?? '',
                    'last_name' => $rec['lastName'] ?? '',
                    'email' => $rec['email'] ?? null,
                    'phone' => $rec['phone'] ?? null,
                    'company' => $rec['companyName'] ?? null,
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
        $apiKey = $config['api_key'] ?? $config['access_token'] ?? '';
        $locationId = $config['location_id'] ?? '';
        if (empty($apiKey)) return ['success' => false, 'message' => 'Missing GHL token'];

        try {
            $headers = ['Authorization' => "Bearer {$apiKey}", 'Version' => '2021-07-28'];
            $payload = [
                'locationId' => $locationId,
                'firstName' => $contactData['first_name'] ?? '',
                'lastName' => $contactData['last_name'] ?? '',
                'email' => $contactData['email'] ?? null,
                'phone' => $contactData['phone'] ?? null,
                'companyName' => $contactData['company'] ?? null,
            ];

            $res = Http::withHeaders($headers)->timeout(15)->post(
                self::API_BASE.'/contacts/',
                array_filter($payload, fn ($v) => $v !== null && $v !== '')
            );

            return ['success' => $res->successful(), 'external_id' => $res->json('contact.id')];
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
        $apiKey = $config['api_key'] ?? $config['access_token'] ?? '';
        $contactId = $activityData['external_contact_id'] ?? null;
        if (empty($apiKey) || empty($contactId)) return ['success' => false];

        try {
            $headers = ['Authorization' => "Bearer {$apiKey}", 'Version' => '2021-07-28'];
            $res = Http::withHeaders($headers)->timeout(10)->post(
                self::API_BASE."/contacts/{$contactId}/notes",
                ['body' => "[Growbridge Connect] {$activityData['type']}: ".($activityData['content'] ?? $activityData['summary'] ?? '')]
            );

            return ['success' => $res->successful()];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function handleWebhook(array $payload, array $headers): array
    {
        return ['success' => true, 'processed' => [$payload]];
    }
}
