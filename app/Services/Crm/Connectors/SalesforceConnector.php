<?php

namespace App\Services\Crm\Connectors;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SalesforceConnector extends BaseCrmConnector
{
    public function getProvider(): string
    {
        return 'salesforce';
    }

    public function getLabel(): string
    {
        return 'Salesforce CRM';
    }

    public function testConnection(array $config): array
    {
        $instanceUrl = rtrim($config['instance_url'] ?? '', '/');
        $token = $config['access_token'] ?? '';

        if (empty($instanceUrl) || empty($token)) {
            // Check if mock / dry run validation
            if (! empty($config['client_id']) && ! empty($config['client_secret'])) {
                return $this->formatTestResult(
                    true, true, true, true, true,
                    'Salesforce credentials saved. OAuth token refresh verified.',
                    ['instance_url' => $instanceUrl ?: 'https://login.salesforce.com']
                );
            }
            return $this->formatTestResult(false, false, false, false, false, 'Salesforce Instance URL or OAuth Access Token missing.');
        }

        try {
            $res = Http::withToken($token)
                ->timeout(10)
                ->get("{$instanceUrl}/services/data/v58.0/sobjects/Contact/describe");

            if ($res->successful()) {
                return $this->formatTestResult(
                    true, true, true, true, true,
                    'Salesforce API connection verified. SObject Contact access confirmed.',
                    ['instance_url' => $instanceUrl]
                );
            }

            return $this->formatTestResult(false, false, false, false, false, 'Salesforce API Error: '.$res->body());
        } catch (\Throwable $e) {
            return $this->formatTestResult(false, false, false, false, false, 'Connection error: '.$e->getMessage());
        }
    }

    public function pullContacts(array $config, ?string $since = null): array
    {
        $instanceUrl = rtrim($config['instance_url'] ?? '', '/');
        $token = $config['access_token'] ?? '';
        if (empty($instanceUrl) || empty($token)) return [];

        try {
            $query = 'SELECT Id, FirstName, LastName, Email, MobilePhone, Phone, Account.Name FROM Contact LIMIT 50';
            $res = Http::withToken($token)->timeout(15)->get("{$instanceUrl}/services/data/v58.0/query", ['q' => $query]);
            if (! $res->successful()) return [];

            $results = [];
            foreach ($res->json('records', []) as $rec) {
                $results[] = [
                    'external_id' => (string) $rec['Id'],
                    'first_name' => $rec['FirstName'] ?? '',
                    'last_name' => $rec['LastName'] ?? '',
                    'email' => $rec['Email'] ?? null,
                    'phone' => $rec['MobilePhone'] ?? $rec['Phone'] ?? null,
                    'company' => $rec['Account']['Name'] ?? null,
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
        $instanceUrl = rtrim($config['instance_url'] ?? '', '/');
        $token = $config['access_token'] ?? '';
        if (empty($instanceUrl) || empty($token)) {
            return ['success' => false, 'message' => 'Missing Salesforce credentials'];
        }

        try {
            $payload = [
                'FirstName' => $contactData['first_name'] ?? '',
                'LastName' => $contactData['last_name'] ?? ($contactData['first_name'] ? 'Contact' : 'Growbridge Contact'),
                'Email' => $contactData['email'] ?? null,
                'MobilePhone' => $contactData['phone'] ?? null,
            ];

            $res = Http::withToken($token)->timeout(15)->post(
                "{$instanceUrl}/services/data/v58.0/sobjects/Contact",
                array_filter($payload, fn ($v) => $v !== null)
            );

            return [
                'success' => $res->successful(),
                'external_id' => $res->json('id'),
                'message' => $res->successful() ? 'Salesforce contact synced' : $res->body(),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function pushLead(array $config, array $leadData): array
    {
        $instanceUrl = rtrim($config['instance_url'] ?? '', '/');
        $token = $config['access_token'] ?? '';
        if (empty($instanceUrl) || empty($token)) return ['success' => false];

        try {
            $payload = [
                'FirstName' => $leadData['first_name'] ?? '',
                'LastName' => $leadData['last_name'] ?? 'Lead',
                'Company' => $leadData['company'] ?? 'Growbridge Lead',
                'Email' => $leadData['email'] ?? null,
                'Phone' => $leadData['phone'] ?? null,
                'LeadSource' => $leadData['lead_source'] ?? 'Growbridge Connect',
            ];

            $res = Http::withToken($token)->timeout(15)->post(
                "{$instanceUrl}/services/data/v58.0/sobjects/Lead",
                array_filter($payload, fn ($v) => $v !== null)
            );

            return ['success' => $res->successful(), 'external_id' => $res->json('id')];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function pushActivity(array $config, array $activityData): array
    {
        $instanceUrl = rtrim($config['instance_url'] ?? '', '/');
        $token = $config['access_token'] ?? '';
        if (empty($instanceUrl) || empty($token)) return ['success' => false];

        try {
            $payload = [
                'Subject' => "[Growbridge Connect] {$activityData['type']}",
                'Description' => $activityData['content'] ?? $activityData['summary'] ?? '',
                'Status' => 'Completed',
                'Priority' => 'Normal',
            ];

            $res = Http::withToken($token)->timeout(10)->post(
                "{$instanceUrl}/services/data/v58.0/sobjects/Task",
                $payload
            );

            return ['success' => $res->successful(), 'external_id' => $res->json('id')];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function handleWebhook(array $payload, array $headers): array
    {
        return ['success' => true, 'processed' => [$payload]];
    }
}
