<?php

namespace Tests\Feature\Phase1;

use App\Models\Client;
use App\Models\Invitation;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthenticationAndRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_registration_creates_client_and_workspace_with_owner_role(): void
    {
        $response = $this->post('/register', [
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'business_name' => 'Acme Technologies',
            'industry' => 'Retail & E-commerce',
            'agree_terms' => true,
            'timezone' => 'Asia/Kolkata',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('client.dashboard', absolute: false));

        // Verify User was created
        $user = User::where('email', 'john.doe@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals(User::CLIENT_ROLE_ADMINISTRATOR, $user->client_role);

        // Verify Client was created
        $client = Client::where('email', 'john.doe@example.com')->first();
        $this->assertNotNull($client);
        $this->assertEquals('Acme Technologies', $client->name);

        // Verify Primary Workspace was created
        $workspace = Workspace::where('client_id', $client->id)->first();
        $this->assertNotNull($workspace);
        $this->assertEquals('Acme Technologies', $workspace->name);
        $this->assertEquals('Retail & E-commerce', $workspace->industry);
        $this->assertEquals($user->id, $workspace->owner_id);

        // Verify Pivot table assignment
        $this->assertTrue($workspace->members()->where('user_id', $user->id)->exists());
        $pivotRole = $workspace->members()->where('user_id', $user->id)->first()->pivot->role;
        $this->assertEquals('owner', $pivotRole);

        // Verify user workspace context
        $this->assertEquals($workspace->id, $user->workspace_id);
        $this->assertEquals($workspace->id, session('current_workspace_id'));
    }

    public function test_login_initializes_active_workspace_session(): void
    {
        $client = Client::create([
            'name' => 'Beta Corp',
            'email' => 'beta@example.com',
            'status' => 'active',
        ]);

        $user = User::create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
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
            'name' => 'Beta Main Workspace',
            'default_locale' => 'en',
            'currency_code' => 'INR',
        ]);

        $workspace->members()->attach($user->id, ['role' => 'owner']);
        $user->update(['workspace_id' => $workspace->id]);

        $response = $this->post('/login', [
            'email' => 'jane@example.com',
            'password' => 'Password123!',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('client.dashboard', absolute: false));
        $this->assertEquals($workspace->id, session('current_workspace_id'));
    }

    public function test_invited_team_member_acceptance_links_to_workspace(): void
    {
        $client = Client::create([
            'name' => 'Gamma Logistics',
            'email' => 'admin@gamma.com',
            'status' => 'active',
        ]);

        $workspace = Workspace::create([
            'client_id' => $client->id,
            'name' => 'Gamma Workspace',
            'default_locale' => 'en',
            'currency_code' => 'INR',
        ]);

        $admin = User::create([
            'name' => 'Gamma Admin',
            'email' => 'admin@gamma.com',
            'password' => Hash::make('Password123!'),
            'role' => User::ROLE_CLIENT,
            'status' => User::STATUS_ACTIVE,
            'client_id' => $client->id,
            'client_role' => User::CLIENT_ROLE_ADMINISTRATOR,
            'workspace_id' => $workspace->id,
            'email_verified_at' => now(),
        ]);

        $workspace->forceFill(['owner_id' => $admin->id])->saveQuietly();
        $workspace->members()->syncWithoutDetaching([$admin->id => ['role' => 'owner']]);

        $invitation = Invitation::create([
            'client_id' => $client->id,
            'email' => 'staff@gamma.com',
            'client_role' => 'staff',
            'token' => Str::random(64),
            'invited_by' => $admin->id,
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->post(route('auth.invitations.accept', ['token' => $invitation->token]), [
            'name' => 'Staff Member',
            'password' => 'NewStaffPass123!',
            'password_confirmation' => 'NewStaffPass123!',
        ]);

        $response->assertRedirect(route('client.dashboard'));

        $staffUser = User::where('email', 'staff@gamma.com')->first();
        $this->assertNotNull($staffUser);
        $this->assertEquals($client->id, $staffUser->client_id);
        $this->assertEquals($workspace->id, $staffUser->workspace_id);
        $this->assertTrue($workspace->members()->where('user_id', $staffUser->id)->exists());
    }
}
