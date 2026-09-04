<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Modules\Shared\DTOs\NormalizedMessage;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\ContactTag;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use App\Services\Conversation\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiReplyAndHumanHandoffTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Workspace $workspace;
    private ChannelAccount $whatsappAccount;
    private ConversationService $conversationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create([
            'name' => 'Growbridge AI Workspace',
            'slug' => 'growbridge-ai-workspace',
            'status' => 'active',
        ]);

        $this->user = User::factory()->create([
            'workspace_id' => $this->workspace->id,
            'role' => 'client',
        ]);

        $this->whatsappAccount = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel' => 'whatsapp',
            'display_name' => 'Official WhatsApp',
            'phone_number' => '+919876543210',
            'status' => 'active',
        ]);

        $this->conversationService = app(ConversationService::class);
    }

    public function test_ai_suggested_reply_generation_without_auto_sending(): void
    {
        $contact = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Rahul',
            'last_name' => 'Sharma',
            'phone_e164' => '+919876543210',
        ]);

        $conv = Conversation::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'workspace_id' => $this->workspace->id,
            'contact_id' => $contact->id,
            'channel_account_id' => $this->whatsappAccount->id,
            'channel' => 'whatsapp',
            'status' => 'open',
            'ai_mode' => 'suggested',
            'assigned_to' => 'bot',
        ]);

        Message::create([
            'conversation_id' => $conv->id,
            'contact_id' => $contact->id,
            'channel' => 'whatsapp',
            'direction' => 'in',
            'type' => 'text',
            'body' => 'What is your Professional plan price?',
            'sent_by' => 'customer',
            'sent_at' => now(),
        ]);

        $suggestion = $this->conversationService->generateAiSuggestion($conv);

        $this->assertNotEmpty($suggestion['reply']);
        $this->assertGreaterThanOrEqual(80, $suggestion['confidence']);
        $this->assertEquals('suggested', $suggestion['ai_mode']);

        // Assert message was NOT auto-sent to database as an outbound message
        $outboundCount = Message::where('conversation_id', $conv->id)->where('direction', 'out')->count();
        $this->assertEquals(0, $outboundCount);
    }

    public function test_automatic_human_handoff_when_customer_asks_for_human(): void
    {
        $normalized = new NormalizedMessage(
            channel: 'whatsapp',
            direction: 'inbound',
            senderType: 'customer',
            messageType: 'text',
            body: 'I want to talk to a human agent please',
            senderIdentifier: '+919876543210',
            senderName: 'Rahul Sharma'
        );

        $message = $this->conversationService->processIncomingMessage($normalized, $this->workspace->id);
        $conv = $message->conversation->fresh();

        // 1. Assert AI is switched to Human mode
        $this->assertEquals('human', $conv->ai_mode);
        $this->assertEquals('human', $conv->assigned_to);
        $this->assertNotNull($conv->human_takeover_at);
        $this->assertEquals('Customer requested human', $conv->handoff_reason);

        // 2. Assert 'Human Required' tag was attached to contact
        $hasTag = $conv->contact->tags()->where('name', 'Human Required')->exists();
        $this->assertTrue($hasTag);

        // 3. Assert system timeline message was created
        $systemMsg = Message::where('conversation_id', $conv->id)
            ->where('type', 'system')
            ->first();

        $this->assertNotNull($systemMsg);
        $this->assertStringContainsString('AI → Human handoff', $systemMsg->body);
    }

    public function test_automatic_human_handoff_when_complaint_detected(): void
    {
        $normalized = new NormalizedMessage(
            channel: 'whatsapp',
            direction: 'inbound',
            senderType: 'customer',
            messageType: 'text',
            body: 'This is the worst service! Total scam, I want refund now!',
            senderIdentifier: '+919876543210',
            senderName: 'Angry Customer'
        );

        $message = $this->conversationService->processIncomingMessage($normalized, $this->workspace->id);
        $conv = $message->conversation->fresh();

        $this->assertEquals('human', $conv->ai_mode);
        $this->assertEquals('Complaint detected', $conv->handoff_reason);
    }

    public function test_manual_switch_between_ai_and_human_mode_via_web_api(): void
    {
        $contact = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Amit',
            'phone_e164' => '+919811122233',
        ]);

        $conv = Conversation::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'workspace_id' => $this->workspace->id,
            'contact_id' => $contact->id,
            'channel_account_id' => $this->whatsappAccount->id,
            'channel' => 'whatsapp',
            'status' => 'open',
            'ai_mode' => 'auto',
            'assigned_to' => 'bot',
        ]);

        // 1. Switch to Human
        $res = $this->actingAs($this->user)->postJson(route('client.inbox.ai-mode', $conv->uuid), [
            'mode' => 'human',
        ]);

        $res->assertOk();
        $res->assertJson(['success' => true, 'ai_mode' => 'human', 'mode' => 'human']);
        $this->assertTrue($conv->fresh()->isHumanActive());

        // 2. Enable AI Auto
        $res = $this->actingAs($this->user)->postJson(route('client.inbox.ai-mode', $conv->uuid), [
            'mode' => 'auto',
        ]);

        $res->assertOk();
        $res->assertJson(['success' => true, 'ai_mode' => 'auto', 'mode' => 'bot']);
        $this->assertTrue($conv->fresh()->isAiActive());
    }

    public function test_rest_api_v1_ai_reply_and_handoff_endpoints(): void
    {
        $contact = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Priya',
            'email' => 'priya@example.com',
        ]);

        $conv = Conversation::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'workspace_id' => $this->workspace->id,
            'contact_id' => $contact->id,
            'channel_account_id' => $this->whatsappAccount->id,
            'channel' => 'whatsapp',
            'status' => 'open',
            'ai_mode' => 'auto',
            'assigned_to' => 'bot',
        ]);

        $token = $this->user->createToken('test-token', ['conversations:read', 'conversations:write'])->plainTextToken;

        // 1. Test POST /api/v1/conversations/{id}/ai/reply
        $res = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/conversations/{$conv->id}/ai/reply", ['prompt' => 'How can I get started?']);

        $res->assertOk();
        $res->assertJsonStructure(['success', 'data' => ['reply', 'confidence']]);

        // 2. Test POST /api/v1/conversations/{id}/handoff
        $res = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/conversations/{$conv->id}/handoff", ['reason' => 'VIP client requested executive']);

        $res->assertOk();
        $res->assertJson(['success' => true, 'ai_mode' => 'human']);
        $this->assertEquals('VIP client requested executive', $conv->fresh()->handoff_reason);

        // 3. Test POST /api/v1/conversations/{id}/ai/enable
        $res = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/conversations/{$conv->id}/ai/enable", ['mode' => 'auto']);

        $res->assertOk();
        $res->assertJson(['success' => true, 'ai_mode' => 'auto']);
    }

    public function test_safety_rule_ai_does_not_auto_reply_when_human_mode_is_active(): void
    {
        $contact = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Sarah',
            'phone_e164' => '+919998887776',
        ]);

        $conv = Conversation::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'workspace_id' => $this->workspace->id,
            'contact_id' => $contact->id,
            'channel_account_id' => $this->whatsappAccount->id,
            'channel' => 'whatsapp',
            'status' => 'open',
            'ai_mode' => 'human',
            'assigned_to' => 'human',
            'human_takeover_at' => now(),
        ]);

        $normalized = new NormalizedMessage(
            channel: 'whatsapp',
            direction: 'inbound',
            senderType: 'customer',
            messageType: 'text',
            body: 'What are your store hours?',
            senderIdentifier: '+919998887776',
            senderName: 'Sarah'
        );

        $msg = $this->conversationService->processIncomingMessage($normalized, $this->workspace->id);

        // AI should NOT send automatic reply since human mode is active
        $aiMessagesCount = Message::where('conversation_id', $conv->id)
            ->where('direction', 'out')
            ->where('sent_by', 'bot')
            ->count();

        $this->assertEquals(0, $aiMessagesCount);
    }
}
