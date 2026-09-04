<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\Shared\Models\Contact;
use App\Modules\Voice\Models\VoiceCall;
use App\Services\Billing\Gateways\RazorpayGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionSecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspaceA;
    private Workspace $workspaceB;
    private User $userA;
    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $ctxA = $this->createWorkspaceContext();
        $this->userA = $ctxA['user'];
        $this->workspaceA = $ctxA['workspace'];

        $ctxB = $this->createWorkspaceContext();
        $this->userB = $ctxB['user'];
        $this->workspaceB = $ctxB['workspace'];
    }

    public function test_cross_tenant_data_access_blocked_for_contacts(): void
    {
        $contactB = Contact::create([
            'workspace_id' => $this->workspaceB->id,
            'first_name' => 'Secret Customer B',
            'phone_e164' => '+919876500000',
        ]);

        // User A attempts to view Contact B
        $response = $this->actingAs($this->userA)->get(route('client.contacts.show', $contactB->uuid));

        $response->assertStatus(403);
    }

    public function test_cross_tenant_timeline_access_blocked(): void
    {
        $contactB = Contact::create([
            'workspace_id' => $this->workspaceB->id,
            'first_name' => 'Secret Customer B',
        ]);

        $response = $this->actingAs($this->userA)->get(route('client.contacts.timeline', $contactB->uuid));

        $response->assertStatus(403);
    }

    public function test_webhook_with_invalid_signature_rejected(): void
    {
        $gateway = new RazorpayGateway('test_key', 'test_secret', 'test_webhook_secret');

        $payload = ['event' => 'payment.captured', 'payload' => []];
        $invalidHeaders = ['x-razorpay-signature' => ['invalid_fake_signature']];

        $result = $gateway->handleWebhook($payload, $invalidHeaders);

        $this->assertFalse($result['success']);
        $this->assertEquals(401, $result['status']);
    }

    public function test_impersonation_requires_admin_authorization(): void
    {
        // Normal client user attempting admin impersonation endpoint
        $response = $this->actingAs($this->userA)->post(route('admin.clients.impersonate', $this->workspaceB->id));

        $response->assertRedirect(); // Redirects to admin login because web guard is not admin
    }

    public function test_safe_super_admin_impersonation_lifecycle(): void
    {
        $admin = $this->createSuperAdmin();
        $client = \App\Models\Client::create(['name' => 'Acme Corp']);
        $clientUser = User::factory()->create([
            'client_id' => $client->id,
            'client_role' => 'administrator',
            'status' => 'active',
        ]);

        // Admin initiates impersonation
        $response = $this->actingAs($admin, 'admin')->post(route('admin.clients.impersonate', $client->id));

        $response->assertRedirect(route('client.dashboard'));
        $this->assertEquals($admin->id, session('impersonator_admin_id'));

        // Admin stops impersonation
        $stopResponse = $this->actingAs($clientUser, 'web')->post(route('admin.impersonate.stop'));
        $stopResponse->assertRedirect(route('admin.clients.index'));
        $this->assertNull(session('impersonator_admin_id'));
    }
}
