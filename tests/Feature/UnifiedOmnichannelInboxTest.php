<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnifiedOmnichannelInboxTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;
    private Workspace $workspaceA;
    private User $userB;
    private Workspace $workspaceB;
    private Contact $contactA;
    private ChannelAccount $channelAccountA;
    private Conversation $convA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspaceA = Workspace::create([
            'name' => 'Workspace A Omnichannel Inbox',
            'slug' => 'workspace-a-inbox',
            'status' => 'active',
        ]);

        $this->userA = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
            'role' => 'client',
        ]);

        $this->workspaceB = Workspace::create([
            'name' => 'Workspace B Omnichannel Inbox',
            'slug' => 'workspace-b-inbox',
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

        $this->channelAccountA = ChannelAccount::create([
            'workspace_id' => $this->workspaceA->id,
            'channel' => 'whatsapp',
            'display_name' => 'Official WhatsApp Support',
            'phone_number_id' => '100234567890',
            'status' => 'active',
        ]);

        $this->convA = Conversation::create([
            'workspace_id' => $this->workspaceA->id,
            'contact_id' => $this->contactA->id,
            'channel_account_id' => $this->channelAccountA->id,
            'channel' => 'whatsapp',
            'status' => 'open',
            'ai_mode' => 'auto',
            'assigned_to' => 'bot',
            'unread_count' => 1,
            'last_message_at' => Carbon::now()->subMinutes(5),
        ]);

        Message::create([
            'conversation_id' => $this->convA->id,
            'direction' => 'in',
            'sent_by' => 'contact',
            'channel' => 'whatsapp',
            'type' => 'text',
            'body' => 'Hi, can you send me pricing details for enterprise?',
            'status' => 'delivered',
            'sent_at' => Carbon::now()->subMinutes(5),
        ]);
    }

    public function test_inbox_index_renders_with_multichannel_counts(): void
    {
        $res = $this->actingAs($this->userA)->get(route('client.inbox.index'));
        $res->assertOk();
        $res->assertInertia(fn ($page) =>
            $page->component('Inbox/Index')
                ->has('conversations')
                ->has('counts')
                ->has('channelAccounts')
        );
    }

    public function test_inbox_show_renders_with_customer_360_data(): void
    {
        $res = $this->actingAs($this->userA)->get(route('client.inbox.show', $this->convA->uuid));
        $res->assertOk();
        $res->assertInertia(fn ($page) =>
            $page->component('Inbox/Show')
                ->has('conversation')
                ->has('messages')
                ->has('journey')
                ->has('aiCustomerSummary')
        );

        // Verify unread count was reset to 0 upon opening
        $this->assertEquals(0, $this->convA->fresh()->unread_count);
    }

    public function test_outbound_reply_creation(): void
    {
        $res = $this->actingAs($this->userA)->postJson(route('client.inbox.reply', $this->convA->uuid), [
            'body' => 'Hello Rahul! Our enterprise pricing starts with volume tiering. Sending brochure now.',
            'type' => 'text',
        ]);

        $res->assertOk();

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $this->convA->id,
            'direction' => 'out',
            'body' => 'Hello Rahul! Our enterprise pricing starts with volume tiering. Sending brochure now.',
        ]);
    }

    public function test_ai_reply_assistant_with_tone_options(): void
    {
        // 1. Generate normal draft
        $res1 = $this->actingAs($this->userA)->postJson(route('client.inbox.ai-generate-reply', $this->convA->uuid), [
            'action' => 'generate',
        ]);
        $res1->assertOk();
        $this->assertTrue($res1->json('success'));
        $this->assertNotEmpty($res1->json('reply'));

        // 2. Professional tone refinement
        $res2 = $this->actingAs($this->userA)->postJson(route('client.inbox.ai-generate-reply', $this->convA->uuid), [
            'action' => 'professional',
            'draft' => 'yeah pricing is 50 bucks',
        ]);
        $res2->assertOk();
        $this->assertEquals('professional', $res2->json('action'));
    }

    public function test_human_takeover_switches_ai_mode(): void
    {
        $res = $this->actingAs($this->userA)->postJson(route('client.inbox.ai-mode', $this->convA->uuid), [
            'mode' => 'human',
        ]);

        $res->assertOk();
        $this->assertEquals('human', $this->convA->fresh()->ai_mode);
        $this->assertEquals('human', $this->convA->fresh()->assigned_to);
    }

    public function test_conversation_assignment_and_status_update(): void
    {
        // 1. Assign to User A
        $resAssign = $this->actingAs($this->userA)->post(route('client.inbox.assign', $this->convA->uuid), [
            'user_id' => $this->userA->id,
        ]);
        $resAssign->assertRedirect();
        $this->assertEquals($this->userA->id, $this->convA->fresh()->assigned_user_id);

        // 2. Update Status to resolved
        $resStatus = $this->actingAs($this->userA)->post(route('client.inbox.status', $this->convA->uuid), [
            'status' => 'resolved',
        ]);
        $resStatus->assertRedirect();
        $this->assertEquals('resolved', $this->convA->fresh()->status);
    }

    public function test_workspace_isolation_protects_inbox_conversations(): void
    {
        // User B cannot view User A's conversation
        $res = $this->actingAs($this->userB)->get(route('client.inbox.show', $this->convA->uuid));
        $res->assertForbidden();

        // User B cannot reply to User A's conversation
        $resReply = $this->actingAs($this->userB)->postJson(route('client.inbox.reply', $this->convA->uuid), [
            'body' => 'Intruder message',
        ]);
        $resReply->assertForbidden();
    }
}
