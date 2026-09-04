<?php

namespace App\Services\Crm\Connectors;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CustomRestConnector extends BaseCrmConnector
{
    public function getProvider(): string
    {
        return 'custom';
    }

    public function getLabel(): string
    {
        return 'Custom CRM via REST API';
    }

    private function buildClient(array $config): \Illuminate\Http\Client\PendingRequest
    {
        $client = Http::timeout(15);
        $authType = $config['auth_type'] ?? 'bearer';
        $token = $config['auth_token'] ?? $config['api_key'] ?? '';

        if ($authType === 'bearer' && $token) {
            $client->withToken($token);
        } elseif ($authType === 'api_key_header' && $token) {
            $headerName = $config['auth_header_name'] ?? 'X-API-Key';
            $client->withHeaders([$headerName => $token]);
        } elseif ($authType === 'basic') {
            $username = $config['basic_username'] ?? '';
            $password = $config['basic_password'] ?? '';
            $client->withBasicAuth($username, $password);
        }

        return $client;
    }

    public function testConnection(array $config): array
    {
        $baseUrl = rtrim($config['base_url'] ?? '', '/');
        if (empty($baseUrl)) {
            return $this->formatTestResult(false, false, false, false, false, 'Custom CRM Base URL is required.');
        }

        $endpoint = $config['contacts_endpoint'] ?? '/contacts';
        $endpoint = str_starts_with($endpoint, '/') ? $endpoint : "/{$endpoint}";
        $testUrl = "{$baseUrl}{$endpoint}";

        try {
            $res = $this->buildClient($config)->get($testUrl, ['limit' => 1]);

            if ($res->successful() || in_array($res->status(), [200, 201, 204])) {
                return $this->formatTestResult(
                    true, true, true, true, true,
                    "Custom CRM endpoint {$endpoint} reachable and verified (HTTP {$res->status()}).",
                    ['base_url' => $baseUrl]
                );
            }

            return $this->formatTestResult(
                true, false, false, false, false,
                "Custom CRM returned HTTP {$res->status()}: ".substr($res->body(), 0, 200)
            );
        } catch (\Throwable $e) {
            return $this->formatTestResult(false, false, false, false, false, 'Connection error: '.$e->getMessage());
        }
    }

    public function pullContacts(array $config, ?string $since = null): array
    {
        $baseUrl = rtrim($config['base_url'] ?? '', '/');
        if (empty($baseUrl)) return [];

        $endpoint = $config['contacts_endpoint'] ?? '/contacts';
        $endpoint = str_starts_with($endpoint, '/') ? $endpoint : "/{$endpoint}";

        try {
            $res = $this->buildClient($config)->get("{$baseUrl}{$endpoint}");
            if (! $res->successful()) return [];

            $data = $res->json('data') ?? $res->json('contacts') ?? $res->json('results') ?? $res->json();
            if (! is_array($data)) return [];

            $results = [];
            foreach ($data as $item) {
                if (! is_array($item)) continue;
                $results[] = [
                    'external_id' => (string) ($item['id'] ?? $item['uuid'] ?? $item['contact_id'] ?? ''),
                    'first_name' => $item['first_name'] ?? $item['firstname'] ?? $item['name'] ?? '',
                    'last_name' => $item['last_name'] ?? $item['lastname'] ?? '',
                    'email' => $item['email'] ?? null,
                    'phone' => $item['phone'] ?? $item['mobile'] ?? null,
                    'company' => $item['company'] ?? $item['company_name'] ?? null,
                    'raw' => $item,
                ];
            }
            return $results;
        } catch (\Throwable) {
            return [];
        }
    }

    public function pushContact(array $config, array $contactData): array
    {
        $baseUrl = rtrim($config['base_url'] ?? '', '/');
        if (empty($baseUrl)) return ['success' => false, 'message' => 'Base URL missing'];

        $endpoint = $config['contacts_endpoint'] ?? '/contacts';
        $endpoint = str_starts_with($endpoint, '/') ? $endpoint : "/{$endpoint}";

        try {
            $res = $this->buildClient($config)->post("{$baseUrl}{$endpoint}", [
                'first_name' => $contactData['first_name'] ?? '',
                'last_name' => $contactData['last_name'] ?? '',
                'email' => $contactData['email'] ?? null,
                'phone' => $contactData['phone'] ?? null,
                'company' => $contactData['company'] ?? null,
                'source' => 'Growbridge Connect',
            ]);

            return [
                'success' => $res->successful(),
                'external_id' => (string) ($res->json('id') ?? $res->json('data.id') ?? ''),
                'message' => $res->successful() ? 'Custom CRM contact pushed' : $res->body(),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function pushLead(array $config, array $leadData): array
    {
        $baseUrl = rtrim($config['base_url'] ?? '', '/');
        $endpoint = $config['leads_endpoint'] ?? $config['contacts_endpoint'] ?? '/leads';
        $endpoint = str_starts_with($endpoint, '/') ? $endpoint : "/{$endpoint}";

        try {
            $res = $this->buildClient($config)->post("{$baseUrl}{$endpoint}", $leadData);
            return ['success' => $res->successful(), 'external_id' => (string) ($res->json('id') ?? '')];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function pushActivity(array $config, array $activityData): array
    {
        $baseUrl = rtrim($config['base_url'] ?? '', '/');
        $endpoint = $config['activities_endpoint'] ?? '/activities';
        $endpoint = str_starts_with($endpoint, '/') ? $endpoint : "/{$endpoint}";

        try {
            $res = $this->buildClient($config)->post("{$baseUrl}{$endpoint}", $activityData);
            return ['success' => $res->successful(), 'external_id' => (string) ($res->json('id') ?? '')];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function handleWebhook(array $payload, array $headers): array
    {
        return ['success' => true, 'processed' => [$payload]];
    }
}
