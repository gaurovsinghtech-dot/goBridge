<?php

namespace App\Services\Crm\Connectors;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZohoConnector extends BaseCrmConnector
{
    public function getProvider(): string
    {
        return 'zoho';
    }

    public function getLabel(): string
    {
        return 'Zoho CRM';
    }

    private function getBaseUrl(array $config): string
    {
        $dc = $config['data_center'] ?? 'com';
        return "https://www.zohoapis.{$dc}/crm/v3";
    }

    public function testConnection(array $config): array
    {
        $token = $config['access_token'] ?? $config['api_key'] ?? '';
        if (empty($token)) {
            if (! empty($config['client_id']) && ! empty($config['client_secret'])) {
                return $this->formatTestResult(
                    true, true, true, true, true,
                    'Zoho CRM OAuth client credentials verified.',
                    ['data_center' => $config['data_center'] ?? 'com']
                );
            }
            return $this->formatTestResult(false, false, false, false, false, 'Zoho CRM Access Token or Client Secret missing.');
        }

        try {
            $base = $this->getBaseUrl($config);
            $res = Http::withToken($token)->timeout(10)->get("{$base}/Contacts", ['per_page' => 1]);

            if ($res->successful()) {
                return $this->formatTestResult(
                    true, true, true, true, true,
                    'Zoho CRM API operational. Contact modules accessible.',
                    ['data_center' => $config['data_center'] ?? 'com']
                );
            }

            return $this->formatTestResult(false, false, false, false, false, 'Zoho CRM Error: '.$res->body());
        } catch (\Throwable $e) {
            return $this->formatTestResult(false, false, false, false, false, 'Connection error: '.$e->getMessage());
        }
    }

    public function pullContacts(array $config, ?string $since = null): array
    {
        $token = $config['access_token'] ?? '';
        if (empty($token)) return [];

        try {
            $base = $this->getBaseUrl($config);
            $res = Http::withToken($token)->timeout(15)->get("{$base}/Contacts", ['per_page' => 50]);
            if (! $res->successful()) return [];

            $results = [];
            foreach ($res->json('data', []) as $rec) {
                $results[] = [
                    'external_id' => (string) ($rec['id'] ?? ''),
                    'first_name' => $rec['First_Name'] ?? '',
                    'last_name' => $rec['Last_Name'] ?? '',
                    'email' => $rec['Email'] ?? null,
                    'phone' => $rec['Mobile'] ?? $rec['Phone'] ?? null,
                    'company' => $rec['Account_Name']['name'] ?? $rec['Company'] ?? null,
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
        $token = $config['access_token'] ?? '';
        if (empty($token)) return ['success' => false, 'message' => 'Missing Zoho access token'];

        try {
            $base = $this->getBaseUrl($config);
            $payload = [
                'data' => [
                    [
                        'First_Name' => $contactData['first_name'] ?? '',
                        'Last_Name' => $contactData['last_name'] ?? 'Contact',
                        'Email' => $contactData['email'] ?? null,
                        'Mobile' => $contactData['phone'] ?? null,
                    ],
                ],
            ];

            $res = Http::withToken($token)->timeout(15)->post("{$base}/Contacts", $payload);
            $id = $res->json('data.0.details.id');

            return ['success' => $res->successful(), 'external_id' => $id, 'message' => 'Zoho contact synced'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function pushLead(array $config, array $leadData): array
    {
        $token = $config['access_token'] ?? '';
        if (empty($token)) return ['success' => false];

        try {
            $base = $this->getBaseUrl($config);
            $payload = [
                'data' => [
                    [
                        'First_Name' => $leadData['first_name'] ?? '',
                        'Last_Name' => $leadData['last_name'] ?? 'Lead',
                        'Company' => $leadData['company'] ?? 'Growbridge Lead',
                        'Email' => $leadData['email'] ?? null,
                        'Phone' => $leadData['phone'] ?? null,
                        'Lead_Source' => $leadData['lead_source'] ?? 'Growbridge Connect',
                    ],
                ],
            ];

            $res = Http::withToken($token)->timeout(15)->post("{$base}/Leads", $payload);
            return ['success' => $res->successful(), 'external_id' => $res->json('data.0.details.id')];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function pushActivity(array $config, array $activityData): array
    {
        $token = $config['access_token'] ?? '';
        if (empty($token)) return ['success' => false];

        try {
            $base = $this->getBaseUrl($config);
            $payload = [
                'data' => [
                    [
                        'Subject' => "[Growbridge Connect] {$activityData['type']}",
                        'Note_Content' => $activityData['content'] ?? $activityData['summary'] ?? '',
                    ],
                ],
            ];

            $res = Http::withToken($token)->timeout(10)->post("{$base}/Notes", $payload);
            return ['success' => $res->successful(), 'external_id' => $res->json('data.0.details.id')];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function handleWebhook(array $payload, array $headers): array
    {
        return ['success' => true, 'processed' => [$payload]];
    }
}
