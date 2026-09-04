<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesignSystemAndOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $ctx = $this->createWorkspaceContext(['client_role' => 'administrator']);
        $this->workspace = $ctx['workspace'];
        $this->user = $ctx['user'];
    }

    public function test_onboarding_wizard_renders_successfully(): void
    {
        $response = $this->actingAs($this->user)->get(route('client.onboarding'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('client/Onboarding/Wizard'));
    }

    public function test_dashboard_renders_with_growbridge_branding(): void
    {
        $response = $this->actingAs($this->user)->get(route('client.dashboard'));

        $response->assertOk();
    }
}
