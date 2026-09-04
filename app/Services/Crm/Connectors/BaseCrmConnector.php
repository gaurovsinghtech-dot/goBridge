<?php

namespace App\Services\Crm\Connectors;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class BaseCrmConnector implements CrmConnectorInterface
{
    /**
     * Helper for standardized 5-point test structure
     */
    protected function formatTestResult(
        bool $auth,
        bool $apiAccess,
        bool $read,
        bool $write,
        bool $webhook,
        string $message,
        array $details = []
    ): array {
        $allPassed = $auth && $apiAccess && $read && $write;

        return [
            'ok' => $allPassed,
            'message' => $message,
            'checks' => [
                'authentication' => ['passed' => $auth, 'label' => 'Authentication & API Keys'],
                'api_access' => ['passed' => $apiAccess, 'label' => 'API Access & Rate Limits'],
                'contact_read' => ['passed' => $read, 'label' => 'Contact Read Permission'],
                'contact_write' => ['passed' => $write, 'label' => 'Contact Write Permission'],
                'webhook_ready' => ['passed' => $webhook, 'label' => 'Webhook Ingress Ready'],
            ],
            'details' => $details,
        ];
    }

    /**
     * Map Growbridge fields into target CRM format using mapping array
     */
    protected function transformFields(array $data, array $mappings): array
    {
        $payload = [];
        foreach ($mappings as $gbField => $crmField) {
            if (array_key_exists($gbField, $data) && $data[$gbField] !== null) {
                $payload[$crmField] = $data[$gbField];
            }
        }

        return $payload;
    }
}
