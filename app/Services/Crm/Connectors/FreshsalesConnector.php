<?php

namespace App\Services\Crm\Connectors;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FreshsalesConnector extends BaseCrmConnector
{
    public function getProvider(): string
    {
        return 'freshsales';
    }

    public function getLabel(): string
    {
        return 'Freshsales CRM';
    }

    private function getBaseUrl(array $config): string
    {
        $domain = rtrim($config['domain'] ?? '', '/');
        if (! str_contains($domain, '.')) {
            $domain = "{$domain}.freshsales.io";
        }
        if (! str_starts_with($domain, 'http')) {
            $domain = "https://{$domain}";
        }
        return "{$domain}/api";
    }

    public function testConnection(array $config): array
    {
        $apiKey = $config['api_key'] ?? '';
        if (empty($apiKey)) {
            return $this->formatTestResult(false, false, false, false, false, 'Freshsales API Key is missing.');
        }

        try {
            $base = $this->getBaseUrl($config);
            $res = Http::withHeaders(['Authorization' => "Token token={$apiKey}"])
                ->timeout(10)
                ->get("{$base}/contacts/filters");

            if ($res->successful()) {
                return $this->formatTestResult(
                    true, true, true, true, true,
                    'Freshsales connection successful. Contact filters verified.',
                    ['domain' => $config['domain'] ?? '']
                );
            }

            return $this->formatTestResult(false, false, false, false, false, 'Freshsales API Error: '.$res->body());
        } catch (\Throwable $e) {
            return $this->formatTestResult(false, false, false, false, false, 'Connection error: '.$e->getMessage());
        }
    }

    public function pullContacts(array $config, ?string $since = null): array
    {
        $apiKey = $config['api_key'] ?? '';
        if (empty($apiKey)) return [];

        try {
            $base = $this->getBaseUrl($config);
            $res = Http::withHeaders(['Authorization' => "Token token={$apiKey}"])
                ->timeout(15)
                ->get("{$base}/contacts/view/1");

            if (! $res->successful()) return [];

            $results = [];
            foreach ($res->json('contacts', []) as $rec) {
                $results[] = [
                    'external_id' => (string) ($rec['id'] ?? ''),
                    'first_name' => $rec['first_name'] ?? '',
                    'last_name' => $rec['last_name'] ?? '',
                    'email' => $rec['email'] ?? null,
                    'phone' => $rec['mobile_number'] ?? $rec['work_number'] ?? null,
                    'company' => $rec['sales_accounts']['name'] ?? null,
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
        $apiKey = $config['api_key'] ?? '';
        if (empty($apiKey)) return ['success' => false, 'message' => 'Missing API key'];

        try {
            $base = $this->getBaseUrl($config);
            $payload = [
                'contact' => [
                    'first_name' => $contactData['first_name'] ?? '',
                    'last_name' => $contactData['last_name'] ?? 'Contact',
                    'email' => $contactData['email'] ?? null,
                    'mobile_number' => $contactData['phone'] ?? null,
                ],
            ];

            $res = Http::withHeaders(['Authorization' => "Token token={$apiKey}"])
                ->timeout(15)
                ->post("{$base}/contacts", $payload);

            return ['success' => $res->successful(), 'external_id' => (string) $res->json('contact.id')];
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
        $apiKey = $config['api_key'] ?? '';
        if (empty($apiKey)) return ['success' => false];

        try {
            $base = $this->getBaseUrl($config);
            $payload = [
                'note' => [
                    'description' => "[Growbridge Connect] {$activityData['type']}: ".($activityData['content'] ?? $activityData['summary'] ?? ''),
                ],
            ];

            $res = Http::withHeaders(['Authorization' => "Token token={$apiKey}"])
                ->timeout(10)
                ->post("{$base}/notes", $payload);

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
