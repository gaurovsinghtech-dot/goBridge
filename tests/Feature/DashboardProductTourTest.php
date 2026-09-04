<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Models\UserProductTour;
use App\Models\Workspace;
use App\Services\Billing\EntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardProductTourTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Workspace $workspace;
    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Client::create([
            'name' => 'Acme Test Corp',
            'email' => 'acme@test.com',
            'status' => 'active',
        ]);

        $this->user = User::create([
            'name' => 'John Doe',
            'email' => 'john@acme.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_CLIENT,
            'client_id' => $this->client->id,
            'email_verified_at' => now(),
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->workspace = Workspace::create([
            'name' => 'Acme Workspace',
            'client_id' => $this->client->id,
            'owner_user_id' => $this->user->id,
            'service_type' => 'whatsapp_only',
        ]);

        $this->user->update(['workspace_id' => $this->workspace->id]);
    }

    public function test_new_user_tour_status_defaults_to_should_show(): void
    {
        $response = $this->actingAs($this->user)->getJson(route('client.tour.status', ['tour_key' => 'dashboard_tour']));

        $response->assertOk();
        $response->assertJson([
            'tour_key' => 'dashboard_tour',
            'current_step' => 0,
            'is_completed' => false,
            'is_skipped' => false,
            'should_show' => true,
        ]);
    }

    public function test_saving_tour_progress_updates_database(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('client.tour.progress'), [
            'tour_key' => 'dashboard_tour',
            'step' => 4,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'current_step' => 4,
        ]);

        $this->assertDatabaseHas('user_product_tours', [
            'user_id' => $this->user->id,
            'tour_key' => 'dashboard_tour',
            'current_step' => 4,
        ]);
    }

    public function test_skipping_tour_records_skipped_at_and_hides_tour(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('client.tour.skip'), [
            'tour_key' => 'dashboard_tour',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('user_product_tours', [
            'user_id' => $this->user->id,
            'tour_key' => 'dashboard_tour',
        ]);

        $tour = UserProductTour::where('user_id', $this->user->id)->where('tour_key', 'dashboard_tour')->first();
        $this->assertNotNull($tour->skipped_at);
        $this->assertTrue($tour->isSkipped());

        // Status endpoint now returns should_show = false
        $statusResponse = $this->actingAs($this->user)->getJson(route('client.tour.status', ['tour_key' => 'dashboard_tour']));
        $statusResponse->assertJson(['should_show' => false, 'is_skipped' => true]);
    }

    public function test_completing_tour_records_completed_at(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('client.tour.complete'), [
            'tour_key' => 'dashboard_tour',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $tour = UserProductTour::where('user_id', $this->user->id)->where('tour_key', 'dashboard_tour')->first();
        $this->assertNotNull($tour->completed_at);
        $this->assertTrue($tour->isCompleted());

        // Status endpoint now returns should_show = false
        $statusResponse = $this->actingAs($this->user)->getJson(route('client.tour.status', ['tour_key' => 'dashboard_tour']));
        $statusResponse->assertJson(['should_show' => false, 'is_completed' => true]);
    }

    public function test_resetting_tour_allows_restarting_from_settings(): void
    {
        // First complete it
        UserProductTour::create([
            'user_id' => $this->user->id,
            'tour_key' => 'dashboard_tour',
            'current_step' => 10,
            'completed_at' => now(),
        ]);

        $resetResponse = $this->actingAs($this->user)->postJson(route('client.tour.reset'), [
            'tour_key' => 'dashboard_tour',
        ]);

        $resetResponse->assertOk();
        $resetResponse->assertJson([
            'success' => true,
            'reset' => true,
            'current_step' => 0,
        ]);

        $tour = UserProductTour::where('user_id', $this->user->id)->where('tour_key', 'dashboard_tour')->first();
        $this->assertNull($tour->completed_at);
        $this->assertNull($tour->skipped_at);
        $this->assertEquals(0, $tour->current_step);

        $statusResponse = $this->actingAs($this->user)->getJson(route('client.tour.status', ['tour_key' => 'dashboard_tour']));
        $statusResponse->assertJson(['should_show' => true, 'is_completed' => false]);
    }

    public function test_guest_cannot_access_tour_endpoints(): void
    {
        $this->getJson(route('client.tour.status'))->assertUnauthorized();
        $this->postJson(route('client.tour.progress'), ['tour_key' => 'dashboard_tour', 'step' => 1])->assertUnauthorized();
        $this->postJson(route('client.tour.complete'), ['tour_key' => 'dashboard_tour'])->assertUnauthorized();
        $this->postJson(route('client.tour.skip'), ['tour_key' => 'dashboard_tour'])->assertUnauthorized();
        $this->postJson(route('client.tour.reset'), ['tour_key' => 'dashboard_tour'])->assertUnauthorized();
    }
}
