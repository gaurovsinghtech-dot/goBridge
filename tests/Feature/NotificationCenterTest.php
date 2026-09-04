<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Services\Notifications\NotificationCenterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private User $user;
    private NotificationCenterService $notificationService;

    protected function setUp(): void
    {
        parent::setUp();

        $ctx = $this->createWorkspaceContext();
        $this->user = $ctx['user'];
        $this->workspace = $ctx['workspace'];

        $this->notificationService = app(NotificationCenterService::class);
    }

    public function test_notification_center_service_dispatches_hot_lead_alert(): void
    {
        $this->notificationService->notifyHotLead($this->workspace, 'Priya Sharma', 95, 'con_test_uuid');

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->user->id,
        ]);

        $notification = DB::table('notifications')->where('notifiable_id', $this->user->id)->first();
        $data = json_decode($notification->data, true);

        $this->assertEquals('hot_lead', $data['type']);
        $this->assertEquals('🔥 Hot Lead Detected', $data['title']);
        $this->assertEquals(95, $data['score']);
    }

    public function test_notification_center_service_dispatches_human_handoff_alert(): void
    {
        $this->notificationService->notifyHumanHandoff($this->workspace, 'John Doe', 'Pricing inquiry requiring supervisor');

        $this->assertEquals(1, DB::table('notifications')->count());
        $notification = DB::table('notifications')->first();
        $data = json_decode($notification->data, true);

        $this->assertEquals('ai_human_handoff', $data['type']);
        $this->assertEquals('high', $data['priority']);
    }

    public function test_notification_unread_count_query(): void
    {
        $this->notificationService->notifyHotLead($this->workspace, 'Lead A', 90, 'uuid_a');
        $this->notificationService->notifyHotLead($this->workspace, 'Lead B', 85, 'uuid_b');

        $response = $this->actingAs($this->user)->get(route('client.notifications.unread-count'));

        $response->assertOk();
        $response->assertJson(['count' => 2]);
    }

    public function test_mark_notification_as_read(): void
    {
        $this->notificationService->notifyHotLead($this->workspace, 'Lead A', 90, 'uuid_a');
        $notification = DB::table('notifications')->where('notifiable_id', $this->user->id)->first();

        $response = $this->actingAs($this->user)->post(route('client.notifications.mark-read', $notification->id));

        $response->assertOk();
        $this->assertNotNull(DB::table('notifications')->where('id', $notification->id)->value('read_at'));
    }

    public function test_mark_all_notifications_as_read(): void
    {
        $this->notificationService->notifyHotLead($this->workspace, 'Lead A', 90, 'uuid_a');
        $this->notificationService->notifyHotLead($this->workspace, 'Lead B', 85, 'uuid_b');

        $response = $this->actingAs($this->user)->post(route('client.notifications.mark-all-read'));

        $response->assertRedirect();
        $this->assertEquals(0, $this->user->unreadNotifications()->count());
    }
}
