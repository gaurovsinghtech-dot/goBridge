<?php

namespace App\Services\Crm\Connectors;

use Illuminate\Support\Facades\Http;

class GenericWebhookConnector extends BaseCrmConnector
{
    public function getProvider(): string
    {
        return 'webhook';
    }

    public function getLabel(): string
    {
        return 'Generic CRM Webhook';
    }

    public function testConnection(array $config): array
    {
        $outboundUrl = $config['outbound_webhook_url'] ?? '';
        if (empty($outboundUrl)) {
            return $this->formatTestResult(
                true, true, true, true, true,
                'Inbound webhook endpoint active. Outbound URL not configured (inbound-only mode).'
            );
        }

        try {
            $res = Http::timeout(10)->post($outboundUrl, [
                'event' => 'ping',
                'timestamp' => now()->toIso8601String(),
                'source' => 'Growbridge Connect Test Ping',
            ]);

            return $this->formatTestResult(
                true, true, true, true, true,
                "Webhook endpoint reachable (HTTP {$res->status()}).",
                ['url' => $outboundUrl]
            );
        } catch (\Throwable $e) {
            return $this->formatTestResult(false, false, false, false, false, 'Webhook test failed: '.$e->getMessage());
        }
    }

    public function pullContacts(array $config, ?string $since = null): array
    {
        return [];
    }

    public function pushContact(array $config, array $contactData): array
    {
        $url = $config['outbound_webhook_url'] ?? '';
        if (empty($url)) return ['success' => false, 'message' => 'No outbound webhook URL configured'];

        try {
            $res = Http::timeout(10)->post($url, [
                'event' => 'contact.upsert',
                'data' => $contactData,
                'timestamp' => now()->toIso8601String(),
            ]);

            return ['success' => $res->successful(), 'external_id' => null];
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
        $url = $config['outbound_webhook_url'] ?? '';
        if (empty($url)) return ['success' => false];

        try {
            $res = Http::timeout(10)->post($url, [
                'event' => 'activity.created',
                'data' => $activityData,
                'timestamp' => now()->toIso8601String(),
            ]);

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
