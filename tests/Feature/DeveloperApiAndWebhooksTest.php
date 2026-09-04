<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Models\Workspace;
use App\Modules\Shared\Models\Contact;
use App\Modules\Voice\Models\VoiceAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeveloperApiAndWebhooksTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $ctx = $this->createWorkspaceContext();
        $this->user = $ctx['user'];
        $this->workspace = $ctx['workspace'];
    }

    public function test_contact_api_list_returns_scoped_contacts_only(): void
    {
        Sanctum::actingAs($this->user, ['contacts:read']);

        Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Amit',
            'phone_e164' => '+919876543210',
        ]);

        $response = $this->getJson('/api/v1/contacts');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Amit', $response->json('data.0.first_name'));
    }

    public function test_api_permission_scopes_enforced(): void
    {
        // Token has only messages:write scope, lacks contacts:read
        Sanctum::actingAs($this->user, ['messages:write']);

        $response = $this->getJson('/api/v1/contacts');

        $response->assertStatus(403);
    }

    public function test_public_website_lead_capture_creates_contact(): void
    {
        $response = $this->postJson("/api/v1/public/leads/{$this->workspace->id}", [
            'name' => 'Kavita Singh',
            'phone' => '+919876599887',
            'email' => 'kavita@example.com',
            'message' => 'Need product demo.',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('contacts', [
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Kavita Singh',
            'email' => 'kavita@example.com',
            'source' => 'website_form',
        ]);
    }

    public function test_tenant_webhook_signature_calculation(): void
    {
        $endpoint = WebhookEndpoint::create([
            'user_id' => $this->user->id,
            'url' => 'https://example.com/webhook',
            'secret' => 'whsec_test_secret_123',
            'enabled' => true,
        ]);

        $payload = json_encode(['event' => 'lead.created', 'data' => ['id' => 1]]);
        $sig = $endpoint->signature($payload);

        $this->assertStringContainsString('t=', $sig);
        $this->assertStringContainsString('v1=', $sig);
    }
}
