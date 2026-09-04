<?php

namespace App\Services\Crm\Connectors;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PipedriveConnector extends BaseCrmConnector
{
    public function getProvider(): string
    {
        return 'pipedrive';
    }

    public function getLabel(): string
    {
        return 'Pipedrive CRM';
    }

    private function getBaseUrl(array $config): string
    {
        $domain = $config['company_domain'] ?? '';
        return $domain ? "https://{$domain}.pipedrive.com/api/v1" : 'https://api.pipedrive.com/v1';
    }

    public function testConnection(array $config): array
    {
        $token = $config['api_token'] ?? $config['access_token'] ?? '';
        if (empty($token)) {
            return $this->formatTestResult(false, false, false, false, false, 'Pipedrive API Token is missing.');
        }

        try {
            $base = $this->getBaseUrl($config);
            $res = Http::timeout(10)->get("{$base}/users/me", ['api_token' => $token]);

            if ($res->successful()) {
                return $this->formatTestResult(
                    true, true, true, true, true,
                    'Pipedrive connection successful. User: '.($res->json('data.name') ?? 'Admin'),
                    ['company' => $res->json('data.company_name')]
                );
            }

            return $this->formatTestResult(false, false, false, false, false, 'Pipedrive API Error: '.$res->body());
        } catch (\Throwable $e) {
            return $this->formatTestResult(false, false, false, false, false, 'Connection error: '.$e->getMessage());
        }
    }

    public function pullContacts(array $config, ?string $since = null): array
    {
        $token = $config['api_token'] ?? $config['access_token'] ?? '';
        if (empty($token)) return [];

        try {
            $base = $this->getBaseUrl($config);
            $res = Http::timeout(15)->get("{$base}/persons", ['api_token' => $token, 'limit' => 50]);
            if (! $res->successful()) return [];

            $results = [];
            foreach ($res->json('data', []) as $rec) {
                $emails = $rec['email'] ?? [];
                $phones = $rec['phone'] ?? [];
                $results[] = [
                    'external_id' => (string) ($rec['id'] ?? ''),
                    'first_name' => $rec['first_name'] ?? $rec['name'] ?? '',
                    'last_name' => $rec['last_name'] ?? '',
                    'email' => is_array($emails) && isset($emails[0]['value']) ? $emails[0]['value'] : null,
                    'phone' => is_array($phones) && isset($phones[0]['value']) ? $phones[0]['value'] : null,
                    'company' => $rec['org_name'] ?? null,
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
        $token = $config['api_token'] ?? $config['access_token'] ?? '';
        if (empty($token)) return ['success' => false, 'message' => 'Missing token'];

        try {
            $base = $this->getBaseUrl($config);
            $payload = [
                'name' => trim(($contactData['first_name'] ?? '').' '.($contactData['last_name'] ?? '')) ?: 'Growbridge Contact',
                'email' => array_filter([$contactData['email'] ?? null]),
                'phone' => array_filter([$contactData['phone'] ?? null]),
            ];

            $res = Http::timeout(15)->post("{$base}/persons?api_token={$token}", $payload);
            return [
                'success' => $res->successful(),
                'external_id' => (string) $res->json('data.id'),
                'message' => 'Pipedrive person created',
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function pushLead(array $config, array $leadData): array
    {
        $contactRes = $this->pushContact($config, $leadData);
        $personId = $contactRes['external_id'] ?? null;
        if (! $personId) return $contactRes;

        $token = $config['api_token'] ?? $config['access_token'] ?? '';
        try {
            $base = $this->getBaseUrl($config);
            $res = Http::timeout(15)->post("{$base}/leads?api_token={$token}", [
                'title' => $leadData['title'] ?? 'Growbridge Inbound Lead',
                'person_id' => (int) $personId,
            ]);

            return ['success' => $res->successful(), 'external_id' => (string) $res->json('data.id')];
        } catch (\Throwable) {
            return $contactRes;
        }
    }

    public function pushActivity(array $config, array $activityData): array
    {
        $token = $config['api_token'] ?? $config['access_token'] ?? '';
        if (empty($token)) return ['success' => false];

        try {
            $base = $this->getBaseUrl($config);
            $res = Http::timeout(10)->post("{$base}/activities?api_token={$token}", [
                'subject' => "[Growbridge Connect] {$activityData['type']}",
                'type' => match ($activityData['type'] ?? 'message') {
                    'call', 'voice' => 'call',
                    'appointment', 'meeting' => 'meeting',
                    default => 'task',
                },
                'note' => $activityData['content'] ?? $activityData['summary'] ?? '',
                'done' => 1,
            ]);

            return ['success' => $res->successful(), 'external_id' => (string) $res->json('data.id')];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function handleWebhook(array $payload, array $headers): array
    {
        return ['success' => true, 'processed' => [$payload]];
    }
}
