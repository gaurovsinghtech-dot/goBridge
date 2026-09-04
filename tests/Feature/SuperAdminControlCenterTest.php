<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Plan;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\Shared\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminControlCenterTest extends TestCase
{
    use RefreshDatabase;

    private AdminUser $admin;
    private Workspace $workspace;
    private User $clientUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createSuperAdminUser();

        $ctx = $this->createWorkspaceContext();
        $this->clientUser = $ctx['user'];
        $this->workspace = $ctx['workspace'];
    }

    public function test_super_admin_dashboard_metrics_aggregation(): void
    {
        Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'John',
            'phone_e164' => '+919876543210',
        ]);

        $response = $this->actingAs($this->admin, 'admin')->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Dashboard')
            ->has('stats')
            ->where('stats.contacts_total', 1)
        );
    }

    public function test_ensure_workspace_not_suspended_middleware_blocks_access(): void
    {
        // Suspend client organization
        $this->clientUser->client->update(['status' => 'inactive']);

        $response = $this->actingAs($this->clientUser)->get(route('client.dashboard'));

        $response->assertStatus(403);
    }

    public function test_system_health_diagnostics_evaluation(): void
    {
        SystemSetting::set('system.last_cron_run_at', now()->toIso8601String());

        $response = $this->actingAs($this->admin, 'admin')->get(route('admin.system-health.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/SystemHealth')
            ->where('diagnostics.db_status', 'healthy')
            ->where('diagnostics.cron_status', 'healthy')
        );
    }

    public function test_system_announcements_creation_and_visibility(): void
    {
        $response = $this->actingAs($this->admin, 'admin')->post(route('admin.announcements.store'), [
            'title' => 'Scheduled Maintenance',
            'message' => 'Platform maintenance scheduled for Sunday 2 AM.',
            'type' => 'warning',
            'target' => 'all',
        ]);

        $response->assertRedirect();

        $saved = json_decode(SystemSetting::get('system.announcements', '[]'), true);
        $this->assertCount(1, $saved);
        $this->assertEquals('Scheduled Maintenance', $saved[0]['title']);
    }

    public function test_multi_tenant_isolation_prevents_cross_tenant_access(): void
    {
        $workspaceB = Workspace::factory()->create();
        $contactB = Contact::create([
            'workspace_id' => $workspaceB->id,
            'first_name' => 'Secret Customer',
        ]);

        // User from Workspace A attempts to view timeline of Contact in Workspace B
        $response = $this->actingAs($this->clientUser)->get(route('client.contacts.timeline', $contactB->uuid));

        $response->assertStatus(403);
    }

    public function test_super_admin_can_access_integrations_page(): void
    {
        $response = $this->actingAs($this->admin, 'admin')->get(route('admin.integrations.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Integrations/Index')
            ->has('grouped')
        );
    }
}
