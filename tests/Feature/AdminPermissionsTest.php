<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_admin_dashboard(): void
    {
        $response = $this->get(route('admin.dashboard'));
        $response->assertRedirect(route('admin.login', [], false));
    }

    public function test_admin_login_page_renders_successfully(): void
    {
        $response = $this->get(route('admin.login'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Admin/Auth/Login'));
    }

    public function test_admin_login_with_valid_credentials_redirects_to_dashboard(): void
    {
        $admin = AdminUser::factory()->create([
            'email' => 'admin@growbridge.io',
            'password' => Hash::make('Secret123!'),
            'status' => AdminUser::STATUS_ACTIVE,
        ]);

        $response = $this->post(route('admin.login'), [
            'email' => 'admin@growbridge.io',
            'password' => 'Secret123!',
        ]);

        $response->assertRedirect(route('admin.dashboard', [], false));
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_admin_login_with_invalid_credentials_fails(): void
    {
        AdminUser::factory()->create([
            'email' => 'admin@growbridge.io',
            'password' => Hash::make('Secret123!'),
            'status' => AdminUser::STATUS_ACTIVE,
        ]);

        $response = $this->post(route('admin.login'), [
            'email' => 'admin@growbridge.io',
            'password' => 'WrongPassword',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('admin');
    }

    public function test_authenticated_admin_visiting_login_redirects_to_dashboard(): void
    {
        $admin = AdminUser::factory()->create(['status' => AdminUser::STATUS_ACTIVE]);
        $response = $this->actingAs($admin, 'admin')->get(route('admin.login'));

        $response->assertRedirect(route('admin.dashboard', [], false));
    }

    public function test_admin_logout_destroys_session_and_redirects_to_admin_login(): void
    {
        $admin = AdminUser::factory()->create(['status' => AdminUser::STATUS_ACTIVE]);

        $response = $this->actingAs($admin, 'admin')->post(route('admin.logout'));

        $response->assertRedirect(route('admin.login', [], false));
        $this->assertGuest('admin');

        // Subsequent access to dashboard redirects to admin.login
        $followUp = $this->get(route('admin.dashboard'));
        $followUp->assertRedirect(route('admin.login', [], false));
    }

    public function test_regular_user_cannot_access_admin_dashboard(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get(route('admin.dashboard'));
        // Web-guard user accessing admin routes gets redirected to admin login
        $response->assertRedirect(route('admin.login', [], false));
    }

    public function test_admin_user_can_access_admin_dashboard(): void
    {
        $admin = $this->createSuperAdmin(['status' => AdminUser::STATUS_ACTIVE]);
        $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'));
        $response->assertOk();
    }
}
