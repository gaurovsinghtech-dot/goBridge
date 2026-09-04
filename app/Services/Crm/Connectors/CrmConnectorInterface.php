<?php

namespace App\Services\Crm\Connectors;

interface CrmConnectorInterface
{
    /**
     * Get unique provider identifier slug (e.g. 'hubspot', 'salesforce')
     */
    public function getProvider(): string;

    /**
     * Human-friendly label
     */
    public function getLabel(): string;

    /**
     * Run 5-point diagnostics testing:
     * 1. Authentication / Token validity
     * 2. API Access
     * 3. Contact Read permission
     * 4. Contact Write permission
     * 5. Webhook availability
     */
    public function testConnection(array $config): array;

    /**
     * Pull updated contacts from CRM
     */
    public function pullContacts(array $config, ?string $since = null): array;

    /**
     * Push or update a contact in CRM
     */
    public function pushContact(array $config, array $contactData): array;

    /**
     * Push or update a lead in CRM
     */
    public function pushLead(array $config, array $leadData): array;

    /**
     * Push an activity (WhatsApp conversation, SMS, voice call, AI summary) to CRM
     */
    public function pushActivity(array $config, array $activityData): array;

    /**
     * Parse and process an incoming webhook payload from CRM
     */
    public function handleWebhook(array $payload, array $headers): array;
}
