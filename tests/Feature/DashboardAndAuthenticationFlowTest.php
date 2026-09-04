<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAndAuthenticationFlowTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private User $user;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $ctx = $this->createWorkspaceContext();
        $this->client = $ctx['client'];
        $this->user = $ctx['user'];
        $this->workspace = $ctx['workspace'];
    }

    public function test_unauthenticated_user_accessing_dashboard_redirects_to_login(): void
    {
        $response = $this->get('/app/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_client_can_load_dashboard_with_props(): void
    {
        $contact = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Demo',
            'phone_e164' => '+919988776655',
        ]);

        Conversation::create([
            'workspace_id' => $this->workspace->id,
            'contact_id' => $contact->id,
            'channel' => 'whatsapp',
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->user)->get('/app/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) =>
            $page->component('client/Dashboard')
                ->has('stats')
                ->has('charts')
                ->has('tables')
                ->where('hasWorkspace', true)
        );
    }

    public function test_sign_out_flow_invalidates_session_and_redirects_to_login(): void
    {
        $this->actingAs($this->user);

        $response = $this->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();

        // Ensure subsequent access to dashboard redirects back to login
        $followUp = $this->get('/app/dashboard');
        $followUp->assertRedirect('/login');
    }

    public function test_authenticated_user_visiting_login_redirects_to_dashboard(): void
    {
        $response = $this->actingAs($this->user)->get('/login');

        $response->assertRedirect('/app/dashboard');
    }

    public function test_public_marketing_pages_load_successfully(): void
    {
        $this->get('/')->assertOk();
        $this->get('/pricing')->assertOk();
        $this->get('/faq')->assertOk();
        $this->get('/use-cases')->assertOk();
        $this->get('/about')->assertOk();
        $this->get('/integrations')->assertOk();
        $this->get('/contact')->assertOk();
    }

    public function test_voice_and_telephony_pages_load_successfully(): void
    {
        $this->workspace->update(['service_type' => 'whatsapp_voice']);

        $this->actingAs($this->user)->get(route('client.voice.index'))->assertOk();
        $this->actingAs($this->user)->get(route('client.voice.numbers.index'))->assertOk();
        $this->actingAs($this->user)->get(route('client.voice.settings.index'))->assertOk();
    }
}
