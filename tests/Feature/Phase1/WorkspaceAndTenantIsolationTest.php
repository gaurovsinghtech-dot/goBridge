<?php

namespace Tests\Feature\Phase1;

use App\Models\Client;
use App\Models\StoredFile;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\Shared\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkspaceAndTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function createTenant(string $name = 'Acme Inc', string $email = 'admin@acme.com'): array
    {
        $client = Client::create([
            'name' => $name,
            'email' => $email,
            'status' => 'active',
        ]);

        $user = User::create([
            'name' => $name.' Admin',
            'email' => $email,
            'password' => Hash::make('Password123!'),
            'role' => User::ROLE_CLIENT,
            'status' => User::STATUS_ACTIVE,
            'client_id' => $client->id,
            'client_role' => User::CLIENT_ROLE_ADMINISTRATOR,
            'email_verified_at' => now(),
        ]);

        $workspace = Workspace::create([
            'client_id' => $client->id,
            'owner_id' => $user->id,
            'name' => $name.' Primary',
            'industry' => 'Retail & E-commerce',
            'website' => 'https://'.strtolower(str_replace(' ', '', $name)).'.com',
            'country' => 'India',
            'timezone' => 'Asia/Kolkata',
            'default_locale' => 'en',
            'currency_code' => 'INR',
        ]);

        $workspace->members()->attach($user->id, ['role' => 'owner']);
        $user->update(['workspace_id' => $workspace->id]);

        return compact('client', 'user', 'workspace');
    }

    public function test_workspace_settings_can_be_viewed_and_updated(): void
    {
        $tenant = $this->createTenant('Apex Retail', 'apex@example.com');

        $response = $this->actingAs($tenant['user'])
            ->withSession(['current_workspace_id' => $tenant['workspace']->id])
            ->get(route('client.settings.workspace'));

        $response->assertOk();

        // Update profile
        $updateResponse = $this->actingAs($tenant['user'])
            ->withSession(['current_workspace_id' => $tenant['workspace']->id])
            ->put(route('client.settings.workspace.update'), [
                'name' => 'Apex Retail Global',
                'industry' => 'Marketing & Digital Agency',
                'website' => 'https://apexglobal.example.com',
                'country' => 'United States',
                'timezone' => 'America/New_York',
                'business_hours' => [
                    'monday' => ['open' => '08:00', 'close' => '17:00', 'closed' => false],
                    'tuesday' => ['open' => '08:00', 'close' => '17:00', 'closed' => false],
                    'wednesday' => ['open' => '08:00', 'close' => '17:00', 'closed' => false],
                    'thursday' => ['open' => '08:00', 'close' => '17:00', 'closed' => false],
                    'friday' => ['open' => '08:00', 'close' => '17:00', 'closed' => false],
                    'saturday' => ['open' => '00:00', 'close' => '00:00', 'closed' => true],
                    'sunday' => ['open' => '00:00', 'close' => '00:00', 'closed' => true],
                ],
            ]);

        $updateResponse->assertRedirect();

        $freshWorkspace = $tenant['workspace']->fresh();
        $this->assertEquals('Apex Retail Global', $freshWorkspace->name);
        $this->assertEquals('Marketing & Digital Agency', $freshWorkspace->industry);
        $this->assertEquals('https://apexglobal.example.com', $freshWorkspace->website);
        $this->assertEquals('United States', $freshWorkspace->country);
        $this->assertEquals('America/New_York', $freshWorkspace->timezone);
        $this->assertTrue($freshWorkspace->business_hours['sunday']['closed']);
    }

    public function test_workspace_logo_can_be_uploaded_to_s3_and_removed(): void
    {
        Storage::fake('s3');
        config(['filesystems.default' => 's3']);

        $tenant = $this->createTenant('Zeta Logistics', 'zeta@example.com');
        $file = UploadedFile::fake()->image('zeta_logo.png', 400, 400);

        $uploadResponse = $this->actingAs($tenant['user'])
            ->withSession(['current_workspace_id' => $tenant['workspace']->id])
            ->post(route('client.settings.workspace.logo.upload'), [
                'logo' => $file,
            ]);

        $uploadResponse->assertRedirect();

        $freshWorkspace = $tenant['workspace']->fresh();
        $this->assertNotNull($freshWorkspace->logo_path);
        $this->assertStringStartsWith('workspaces/'.$tenant['workspace']->id.'/logos/', $freshWorkspace->logo_path);

        // Verify StoredFile record
        $storedFile = StoredFile::where('key', $freshWorkspace->logo_path)->first();
        $this->assertNotNull($storedFile);
        $this->assertEquals($tenant['workspace']->id, $storedFile->workspace_id);
        $this->assertEquals('logos', $storedFile->category);

        // Remove logo
        $deleteResponse = $this->actingAs($tenant['user'])
            ->withSession(['current_workspace_id' => $tenant['workspace']->id])
            ->delete(route('client.settings.workspace.logo.delete'));

        $deleteResponse->assertRedirect();
        $this->assertNull($tenant['workspace']->fresh()->logo_path);
    }

    public function test_workspace_switching_and_unauthorized_isolation(): void
    {
        $tenantA = $this->createTenant('Workspace Alpha', 'alpha@example.com');
        $tenantB = $this->createTenant('Workspace Beta', 'beta@example.com');

        // Create a secondary workspace for Tenant A
        $workspaceA2 = Workspace::create([
            'client_id' => $tenantA['client']->id,
            'owner_id' => $tenantA['user']->id,
            'name' => 'Workspace Alpha Branch 2',
            'default_locale' => 'en',
            'currency_code' => 'INR',
        ]);
        $workspaceA2->members()->attach($tenantA['user']->id, ['role' => 'owner']);

        // User A switches to Workspace A2 - Allowed
        $switchResponse = $this->actingAs($tenantA['user'])
            ->post(route('client.workspaces.switch'), [
                'workspace_id' => $workspaceA2->id,
            ]);

        $switchResponse->assertRedirect(route('client.dashboard'));
        $this->assertEquals($workspaceA2->id, session('current_workspace_id'));

        // User A tries to switch to Tenant B's workspace - Forbidden (403)
        $forbiddenResponse = $this->actingAs($tenantA['user'])
            ->post(route('client.workspaces.switch'), [
                'workspace_id' => $tenantB['workspace']->id,
            ]);

        $forbiddenResponse->assertForbidden();
    }

    public function test_team_member_management_syncs_workspace_roles(): void
    {
        $tenant = $this->createTenant('Omega Enterprises', 'omega@example.com');

        // Add team member via TeamController
        $storeResponse = $this->actingAs($tenant['user'])
            ->withSession(['current_workspace_id' => $tenant['workspace']->id])
            ->post(route('client.team.store'), [
                'name' => 'Agent Smith',
                'email' => 'smith@omega.com',
                'password' => 'AgentPass123!',
                'password_confirmation' => 'AgentPass123!',
                'client_role' => 'staff',
                'status' => 'active',
            ]);

        $storeResponse->assertRedirect(route('client.team.index'));

        $member = User::where('email', 'smith@omega.com')->first();
        $this->assertNotNull($member);
        $this->assertEquals($tenant['client']->id, $member->client_id);
        $this->assertEquals($tenant['workspace']->id, $member->workspace_id);

        // Verify workspace_user pivot attachment
        $this->assertTrue($tenant['workspace']->members()->where('user_id', $member->id)->exists());
        $pivot = $tenant['workspace']->members()->where('user_id', $member->id)->first()->pivot;
        $this->assertEquals('member', $pivot->role);

        // Update member role to administrator
        $updateResponse = $this->actingAs($tenant['user'])
            ->withSession(['current_workspace_id' => $tenant['workspace']->id])
            ->put(route('client.team.update', ['member' => $member->id]), [
                'name' => 'Agent Smith Promoted',
                'email' => 'smith@omega.com',
                'client_role' => 'administrator',
                'status' => 'active',
            ]);

        $updateResponse->assertRedirect(route('client.team.index'));
        $pivotAfter = $tenant['workspace']->members()->where('user_id', $member->id)->first()->pivot;
        $this->assertEquals('admin', $pivotAfter->role);

        // Delete member
        $deleteResponse = $this->actingAs($tenant['user'])
            ->withSession(['current_workspace_id' => $tenant['workspace']->id])
            ->delete(route('client.team.destroy', ['member' => $member->id]));

        $deleteResponse->assertRedirect(route('client.team.index'));
        $this->assertFalse($tenant['workspace']->members()->where('user_id', $member->id)->exists());
    }

    public function test_crm_contact_and_pipeline_cross_tenant_isolation(): void
    {
        $tenantA = $this->createTenant('Tenant One', 'one@example.com');
        $tenantB = $this->createTenant('Tenant Two', 'two@example.com');

        // Create a contact belonging to Tenant A
        $contactA = Contact::create([
            'workspace_id' => $tenantA['workspace']->id,
            'first_name' => 'Alice',
            'last_name' => 'Wonderland',
            'email' => 'alice@wonderland.com',
            'phone' => '+919876543210',
            'lifecycle_stage' => 'lead',
        ]);

        // Create a contact belonging to Tenant B
        $contactB = Contact::create([
            'workspace_id' => $tenantB['workspace']->id,
            'first_name' => 'Bob',
            'last_name' => 'Builder',
            'email' => 'bob@builder.com',
            'phone' => '+919876543211',
            'lifecycle_stage' => 'lead',
        ]);

        // Tenant A can see their own contact
        $this->assertEquals(1, Contact::where('workspace_id', $tenantA['workspace']->id)->count());
        $this->assertTrue(Contact::where('workspace_id', $tenantA['workspace']->id)->where('id', $contactA->id)->exists());

        // Tenant A query strictly excludes Tenant B's contacts
        $this->assertFalse(Contact::where('workspace_id', $tenantA['workspace']->id)->where('id', $contactB->id)->exists());
    }
}
