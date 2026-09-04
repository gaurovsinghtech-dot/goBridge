<?php

namespace Tests\Feature;

use App\Models\CrmConnection;
use App\Models\CrmFieldMapping;
use App\Models\CrmSyncLog;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\Integrations\Models\IntegrationConfig;
use App\Modules\Shared\Models\Contact;
use App\Services\Crm\Connectors\CrmManager;
use App\Services\Crm\CrmSyncService;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CrmIntegrationAndSyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $client;
    protected Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@growbridge.co.in',
        ]);

        $this->workspace = Workspace::factory()->create([
            'name' => 'Demo Enterprise',
            'industry' => 'Omnichannel Retail',
        ]);

        $this->client = User::factory()->create([
            'role' => 'client',
            'workspace_id' => $this->workspace->id,
        ]);
    }

    public function test_crm_manager_registers_all_required_providers(): void
    {
        $manager = app(CrmManager::class);
        $providers = $manager->getProviders();

        $expected = ['hubspot', 'salesforce', 'zoho', 'pipedrive', 'freshsales', 'dynamics', 'gohighlevel', 'custom', 'webhook'];
        foreach ($expected as $slug) {
            $this->assertArrayHasKey($slug, $providers, "Provider {$slug} is registered");
            $this->assertNotNull($manager->driver($slug), "Driver for {$slug} is resolvable");
        }
    }

    public function test_hubspot_connector_runs_diagnostics_and_syncs(): void
    {
        Http::fake([
            'https://api.hubapi.com/crm/v3/objects/contacts*' => Http::response([
                'results' => [
                    [
                        'id' => '101',
                        'properties' => [
                            'firstname' => 'John',
                            'lastname' => 'Doe',
                            'email' => 'john@example.com',
                            'mobilephone' => '+919876543210',
                            'company' => 'Acme Corp',
                        ],
                    ],
                ],
            ], 200),
            'https://api.hubapi.com/crm/v3/objects/notes' => Http::response([
                'id' => 'note_99',
            ], 201),
        ]);

        $manager = app(CrmManager::class);
        $testResult = $manager->test('hubspot', ['access_token' => 'pat-test-123']);

        $this->assertTrue($testResult['ok']);
        $this->assertTrue($testResult['checks']['authentication']['passed']);
        $this->assertTrue($testResult['checks']['contact_read']['passed']);

        // Test Contact Push
        $contact = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'phone_e164' => '+919876543211',
            'email' => 'alice@example.com',
        ]);

        $conn = CrmConnection::create([
            'workspace_id' => $this->workspace->id,
            'provider' => 'hubspot',
            'credentials' => ['access_token' => 'pat-test-123'],
            'status' => 'active',
            'sync_direction' => 'two_way',
        ]);

        $syncRes = $manager->syncContactToCrm($contact, $this->workspace);
        $this->assertTrue($syncRes['hubspot']['success']);

        // Verify Sync Log
        $this->assertDatabaseHas('crm_sync_logs', [
            'workspace_id' => $this->workspace->id,
            'provider' => 'hubspot',
            'object_type' => 'contact',
            'status' => 'success',
        ]);
    }

    public function test_crm_sync_service_handles_whatsapp_voice_and_ai_events(): void
    {
        Http::fake([
            'https://api.hubapi.com/crm/v3/objects/contacts' => Http::response(['id' => '202'], 201),
            'https://api.hubapi.com/crm/v3/objects/notes' => Http::response(['id' => '303'], 201),
        ]);

        CrmConnection::create([
            'workspace_id' => $this->workspace->id,
            'provider' => 'hubspot',
            'credentials' => ['access_token' => 'pat-test-123'],
            'status' => 'active',
            'sync_direction' => 'two_way',
        ]);

        $syncService = app(CrmSyncService::class);

        // 1. WhatsApp Inbound message
        $syncService->onMessageReceivedOrSent($this->workspace, '+919876543210', 'whatsapp', 'inbound', 'Hi, I need pricing information.', 'Jane Doe');

        $this->assertDatabaseHas('contacts', [
            'workspace_id' => $this->workspace->id,
            'phone_e164' => '+919876543210',
        ]);

        $this->assertDatabaseHas('crm_sync_logs', [
            'workspace_id' => $this->workspace->id,
            'provider' => 'hubspot',
            'object_type' => 'activity',
            'status' => 'success',
        ]);

        // 2. Voice Call Completed
        $syncService->onCallCompleted($this->workspace, '+919876543210', 145, 'completed', 'https://storage.growbridge.co.in/recordings/call_1.mp3');

        // 3. AI Interaction Summary
        $syncService->onAiInteractionSummary($this->workspace, '+919876543210', 'Customer requested enterprise plan quote for 10 agents.', 'positive');

        $this->assertEquals(4, CrmSyncLog::where('workspace_id', $this->workspace->id)->count());
    }

    public function test_client_crm_controller_connect_and_sync_endpoints(): void
    {
        Http::fake([
            'https://api.hubapi.com/crm/v3/objects/contacts*' => Http::response(['results' => []], 200),
        ]);

        $this->actingAs($this->client);

        // Connect HubSpot
        $resp = $this->postJson(route('client.crm.integrations.connect', 'hubspot'), [
            'credentials' => ['access_token' => 'pat-na1-xyz789'],
            'sync_direction' => 'two_way',
            'sync_mode' => 'realtime',
        ]);

        $resp->assertOk();
        $resp->assertJson(['success' => true]);

        $this->assertDatabaseHas('crm_connections', [
            'workspace_id' => $this->workspace->id,
            'provider' => 'hubspot',
            'status' => 'active',
        ]);

        // Disconnect
        $respDisc = $this->postJson(route('client.crm.integrations.disconnect', 'hubspot'));
        $respDisc->assertOk();

        $this->assertDatabaseHas('crm_connections', [
            'workspace_id' => $this->workspace->id,
            'provider' => 'hubspot',
            'status' => 'paused',
        ]);
    }

    public function test_onboarding_wizard_crm_step_save_and_skip(): void
    {
        $this->actingAs($this->client);

        // Save CRM during onboarding
        $resp = $this->postJson(route('client.onboarding.crm.save'), [
            'provider' => 'hubspot',
            'credentials' => ['access_token' => 'pat-na1-test-999'],
            'sync_direction' => 'two_way',
        ]);

        $resp->assertOk();
        $resp->assertJson(['success' => true]);

        $this->assertDatabaseHas('crm_connections', [
            'workspace_id' => $this->workspace->id,
            'provider' => 'hubspot',
            'status' => 'active',
        ]);

        // Skip CRM step
        $respSkip = $this->postJson(route('client.onboarding.crm.skip'));
        $respSkip->assertOk();
        $respSkip->assertJson(['success' => true]);
    }

    public function test_crm_webhook_ingress_endpoint(): void
    {
        $conn = CrmConnection::create([
            'workspace_id' => $this->workspace->id,
            'provider' => 'hubspot',
            'credentials' => ['access_token' => 'pat-test'],
            'status' => 'active',
        ]);

        $resp = $this->postJson('/api/v1/webhooks/crm/hubspot', [
            [
                'objectId' => 12345,
                'subscriptionType' => 'contact.creation',
                'changeSource' => 'CRM',
            ],
        ]);

        $resp->assertOk();
        $resp->assertJson(['success' => true]);

        $this->assertDatabaseHas('crm_sync_logs', [
            'workspace_id' => $this->workspace->id,
            'provider' => 'hubspot',
            'object_type' => 'webhook',
            'direction' => 'inbound',
            'status' => 'success',
        ]);
    }
}
