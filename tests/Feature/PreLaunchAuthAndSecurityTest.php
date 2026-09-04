<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Modules\AI\Models\AiChatbot;
use App\Modules\AI\Models\AiKnowledgeBase;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreLaunchAuthAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $clientA;
    private Workspace $workspaceA;
    private User $clientB;
    private Workspace $workspaceB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspaceA = Workspace::create([
            'name' => 'Security Test Workspace A',
            'slug' => 'sec-workspace-a',
            'status' => 'active',
        ]);

        $this->clientA = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
            'role' => 'client',
        ]);

        $this->workspaceB = Workspace::create([
            'name' => 'Security Test Workspace B',
            'slug' => 'sec-workspace-b',
            'status' => 'active',
        ]);

        $this->clientB = User::factory()->create([
            'workspace_id' => $this->workspaceB->id,
            'role' => 'client',
        ]);
    }

    public function test_login_and_authentication_redirect(): void
    {
        $user = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
            'email' => 'auth_test@example.com',
            'password' => bcrypt('SecurePassword123!'),
            'role' => 'client',
        ]);

        $response = $this->post('/login', [
            'email' => 'auth_test@example.com',
            'password' => 'SecurePassword123!',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('client.dashboard', absolute: false));
    }

    public function test_logout_invalidates_session_and_clears_auth(): void
    {
        $this->actingAs($this->clientA);
        $this->assertAuthenticated();

        $response = $this->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/login');

        // Verify protected route access fails after logout
        $followUp = $this->get('/app/dashboard');
        $followUp->assertRedirect('/login');
    }

    public function test_unauthenticated_user_cannot_access_app_routes(): void
    {
        $protectedRoutes = [
            '/app/dashboard',
            '/app/inbox',
            '/app/customers',
            '/app/knowledge',
            '/app/ai-agents',
            '/app/voice/calls',
            '/app/voice/follow-ups',
        ];

        foreach ($protectedRoutes as $route) {
            $res = $this->get($route);
            $res->assertRedirect('/login');
        }
    }

    public function test_unauthenticated_api_requests_return_401_or_redirect(): void
    {
        $res = $this->getJson('/api/contacts');
        $this->assertContains($res->status(), [401, 404]);
    }

    public function test_workspace_isolation_across_all_entities(): void
    {
        // 1. Create resources in Workspace A
        $contactA = Contact::create([
            'workspace_id' => $this->workspaceA->id,
            'first_name' => 'Alice',
            'last_name' => 'Secret',
            'phone' => '+15550001111',
            'phone_e164' => '+15550001111',
            'email' => 'alice@company-a.com',
        ]);

        $convA = Conversation::create([
            'workspace_id' => $this->workspaceA->id,
            'contact_id' => $contactA->id,
            'channel' => 'whatsapp',
            'status' => 'open',
        ]);

        $agentA = AiChatbot::create([
            'workspace_id' => $this->workspaceA->id,
            'name' => 'Workspace A Exclusive Agent',
            'channels' => ['whatsapp'],
            'status' => 'published',
        ]);

        $kbA = AiKnowledgeBase::create([
            'workspace_id' => $this->workspaceA->id,
            'name' => 'Workspace A Proprietary Data',
            'category' => 'company',
            'status' => 'active',
        ]);

        // 2. Test Client B (Workspace B) cannot access Workspace A's Contact
        $resContact = $this->actingAs($this->clientB)->get(route('client.customers.show', $contactA->id));
        $this->assertContains($resContact->status(), [403, 404]);

        // 3. Test Client B cannot view or reply to Workspace A's Conversation
        $resConv = $this->actingAs($this->clientB)->get(route('client.inbox.show', $convA->uuid));
        $this->assertContains($resConv->status(), [403, 404]);

        // 4. Test Client B cannot access or modify Workspace A's AI Agent
        $resAgent = $this->actingAs($this->clientB)->get(route('client.ai-agents.show', $agentA->uuid));
        $this->assertEquals(403, $resAgent->status());

        $resDeleteAgent = $this->actingAs($this->clientB)->delete(route('client.ai-agents.destroy', $agentA->uuid));
        $this->assertEquals(403, $resDeleteAgent->status());

        // 5. Test Client B cannot view Workspace A's Knowledge Base
        $resKb = $this->actingAs($this->clientB)->get(route('client.knowledge.index'));
        $resKb->assertInertia(fn ($page) =>
            $page->has('kb', fn ($pageKb) =>
                $pageKb->where('workspace_id', $this->workspaceB->id)
                    ->whereNot('id', $kbA->id)
                    ->etc()
            )
        );
    }

    public function test_admin_routes_are_strictly_forbidden_for_regular_clients(): void
    {
        $adminRoutes = [
            '/admin/dashboard',
            '/admin/users',
            '/admin/workspaces',
            '/admin/plans',
            '/admin/settings',
        ];

        foreach ($adminRoutes as $route) {
            $res = $this->actingAs($this->clientA)->get($route);
            $this->assertContains($res->status(), [302, 403, 404]);
        }
    }

    public function test_secure_headers_are_present_on_all_responses(): void
    {
        $res = $this->actingAs($this->clientA)->get('/app/dashboard');
        $res->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $res->assertHeader('X-Content-Type-Options', 'nosniff');
        $res->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $cacheControl = (string) $res->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
    }
}
