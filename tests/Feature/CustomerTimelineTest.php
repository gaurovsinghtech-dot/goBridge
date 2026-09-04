<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Modules\Leads\Models\Lead;
use App\Modules\Leads\Models\LeadActivity;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\ContactTag;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use App\Modules\Voice\Models\VoiceAgent;
use App\Modules\Voice\Models\VoiceCall;
use App\Modules\Voice\Models\VoiceFollowUp;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTimelineTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;
    private Workspace $workspaceA;
    private User $userB;
    private Workspace $workspaceB;
    private Contact $contactA;
    private VoiceAgent $agentA;
    private VoiceCall $callA;
    private Conversation $convA;
    private VoiceFollowUp $followUpA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspaceA = Workspace::create([
            'name' => 'Workspace A Customer 360',
            'slug' => 'workspace-a-cust360',
            'status' => 'active',
        ]);

        $this->userA = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
            'role' => 'client',
        ]);

        $this->workspaceB = Workspace::create([
            'name' => 'Workspace B Customer 360',
            'slug' => 'workspace-b-cust360',
            'status' => 'active',
        ]);

        $this->userB = User::factory()->create([
            'workspace_id' => $this->workspaceB->id,
            'role' => 'client',
        ]);

        $this->contactA = Contact::create([
            'workspace_id' => $this->workspaceA->id,
            'first_name' => 'Rahul',
            'last_name' => 'Sharma',
            'phone_e164' => '+919876543211',
            'email' => 'rahul@example.com',
            'status' => 'lead',
        ]);

        $this->agentA = VoiceAgent::create([
            'workspace_id' => $this->workspaceA->id,
            'name' => 'Sales Intelligence Agent',
            'status' => 'active',
            'provider' => 'twilio',
            'voice_id' => 'Polly.Aditi',
            'language' => 'en-US',
            'tone' => 'sales',
        ]);

        // 1. Voice Call Event
        $this->callA = VoiceCall::create([
            'workspace_id' => $this->workspaceA->id,
            'voice_agent_id' => $this->agentA->id,
            'contact_id' => $this->contactA->id,
            'direction' => 'inbound',
            'provider' => 'twilio',
            'from_number' => '+919876543211',
            'to_number' => '+12125550199',
            'status' => 'completed',
            'outcome' => 'interested',
            'duration_sec' => 260,
            'summary' => 'Customer inquired about WhatsApp API pricing and asked for follow-up.',
            'started_at' => Carbon::now()->subHours(4),
            'ended_at' => Carbon::now()->subHours(4)->addMinutes(4),
        ]);

        // 2. WhatsApp Conversation Event
        $this->convA = Conversation::create([
            'workspace_id' => $this->workspaceA->id,
            'contact_id' => $this->contactA->id,
            'channel' => 'whatsapp',
            'status' => 'open',
            'ai_mode' => 'auto',
            'last_message_at' => Carbon::now()->subHours(3),
        ]);

        Message::create([
            'conversation_id' => $this->convA->id,
            'direction' => 'inbound',
            'sent_by' => 'contact',
            'channel' => 'whatsapp',
            'content' => 'Can you send the pricing PDF?',
            'status' => 'delivered',
            'created_at' => Carbon::now()->subHours(3),
        ]);

        // 3. Follow-up Event
        $this->followUpA = VoiceFollowUp::create([
            'workspace_id' => $this->workspaceA->id,
            'contact_id' => $this->contactA->id,
            'voice_call_id' => $this->callA->id,
            'type' => 'callback',
            'status' => 'scheduled',
            'priority' => 'high',
            'due_at' => Carbon::now()->addHours(2),
            'title' => 'Scheduled Callback with Rahul',
            'notes' => 'Customer requested pricing call.',
            'created_at' => Carbon::now()->subHours(2),
        ]);
    }

    public function test_customer_360_profile_renders(): void
    {
        $res = $this->actingAs($this->userA)->get(route('client.customers.show', $this->contactA->uuid));
        $res->assertOk();
        $res->assertInertia(fn ($page) =>
            $page->component('Contacts/Show')
                ->has('contact')
                ->has('timeline')
                ->has('journey')
                ->has('aiSummary')
        );
    }

    public function test_timeline_aggregates_multichannel_events(): void
    {
        $res = $this->actingAs($this->userA)->get(route('client.customers.show', $this->contactA->uuid));
        $res->assertOk();

        // 3 distinct events: Voice Call, WhatsApp Conversation, Callback Task
        $res->assertInertia(fn ($page) =>
            $page->has('timeline', 3)
        );
    }

    public function test_timeline_filtering_by_channel(): void
    {
        // Filter by voice only
        $res = $this->actingAs($this->userA)->get(route('client.customers.show', [
            'contact' => $this->contactA->uuid,
            'channel' => 'voice',
        ]));
        $res->assertOk();
        $res->assertInertia(fn ($page) =>
            $page->has('timeline', 1)
        );
    }

    public function test_timeline_json_feed_endpoint(): void
    {
        $res = $this->actingAs($this->userA)->getJson(route('client.customers.timeline', $this->contactA->uuid));
        $res->assertOk();

        $data = $res->json();
        $this->assertEquals($this->contactA->id, $data['contact_id']);
        $this->assertCount(3, $data['events']);
    }

    public function test_add_quick_note_to_timeline(): void
    {
        $res = $this->actingAs($this->userA)->post(route('client.customers.notes', $this->contactA->uuid), [
            'body' => 'Spoke to Rahul on LinkedIn, very receptive.',
        ]);
        $res->assertRedirect();

        $this->assertDatabaseHas('lead_activities', [
            'workspace_id' => $this->workspaceA->id,
            'type' => 'note',
            'body' => 'Spoke to Rahul on LinkedIn, very receptive.',
        ]);
    }

    public function test_safe_contact_merge(): void
    {
        $secondaryContact = Contact::create([
            'workspace_id' => $this->workspaceA->id,
            'first_name' => 'Rahul',
            'last_name' => 'Duplicate',
            'email' => 'rahul@example.com',
            'phone_e164' => '+919876543299',
            'status' => 'lead',
        ]);

        $secondaryConv = Conversation::create([
            'workspace_id' => $this->workspaceA->id,
            'contact_id' => $secondaryContact->id,
            'channel' => 'instagram',
            'status' => 'open',
        ]);

        $res = $this->actingAs($this->userA)->post(route('client.customers.merge', $this->contactA->uuid), [
            'secondary_contact_id' => $secondaryContact->id,
        ]);
        $res->assertRedirect();

        // Secondary contact is deleted
        $this->assertNull(Contact::find($secondaryContact->id));

        // Secondary conversation reassigned to primary
        $this->assertEquals($this->contactA->id, $secondaryConv->fresh()->contact_id);
    }

    public function test_workspace_isolation_protects_customer_360(): void
    {
        // User B cannot view User A's customer profile
        $res = $this->actingAs($this->userB)->get(route('client.customers.show', $this->contactA->uuid));
        $res->assertForbidden();

        // User B cannot view User A's timeline JSON
        $res2 = $this->actingAs($this->userB)->getJson(route('client.customers.timeline', $this->contactA->uuid));
        $res2->assertForbidden();
    }
}
