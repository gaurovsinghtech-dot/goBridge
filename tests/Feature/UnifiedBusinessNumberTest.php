<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\PhoneNumber;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\Voice\Models\VoiceAgent;
use App\Modules\Whatsapp\Models\WhatsappBusinessAccount;
use App\Services\UnifiedNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnifiedBusinessNumberTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Workspace $workspace;
    private Client $client;
    private UnifiedNumberService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $ctx = $this->createWorkspaceContext();
        $this->user = $ctx['user'];
        $this->workspace = $ctx['workspace'];
        $this->workspace->update(['service_type' => 'whatsapp_voice']);
        $this->client = $ctx['client'];
        $this->service = app(UnifiedNumberService::class);
    }

    public function test_purchased_number_defaults_to_whatsapp_not_connected(): void
    {
        $phone = PhoneNumber::create([
            'workspace_id' => $this->workspace->id,
            'phone_number' => '+912245819200',
            'country' => 'IN',
            'voice_enabled' => true,
            'sms_enabled' => true,
            'status' => 'active',
        ]);

        $this->assertEquals('not_connected', $phone->whatsapp_status);
        $this->assertFalse($phone->isWhatsappConnected());
        $this->assertTrue($phone->isVoiceConnected());
        $this->assertFalse($phone->isUnified());
    }

    public function test_customer_can_connect_whatsapp_to_virtual_number(): void
    {
        $phone = PhoneNumber::create([
            'workspace_id' => $this->workspace->id,
            'phone_number' => '+912245819200',
            'voice_enabled' => true,
            'status' => 'active',
        ]);

        $waba = WhatsappBusinessAccount::create([
            'workspace_id' => $this->workspace->id,
            'waba_id' => 'waba_test_123',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->post(
            route('client.voice.numbers.whatsapp.connect', $phone->id),
            [
                'display_name' => 'ABC Technologies India',
                'waba_id' => $waba->id,
                'whatsapp_phone_number_id' => 'meta_phone_12345',
            ]
        );

        $response->assertRedirect();

        $phone->refresh();
        $this->assertEquals('connected', $phone->whatsapp_status);
        $this->assertEquals('ABC Technologies India', $phone->whatsapp_display_name);
        $this->assertEquals('meta_phone_12345', $phone->whatsapp_phone_number_id);
        $this->assertTrue($phone->isWhatsappConnected());
        $this->assertTrue($phone->isUnified());
    }

    public function test_customer_can_assign_voice_and_chat_ai_agents(): void
    {
        $phone = PhoneNumber::create([
            'workspace_id' => $this->workspace->id,
            'phone_number' => '+912245819200',
            'voice_enabled' => true,
            'status' => 'active',
        ]);

        $voiceAgent = VoiceAgent::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Aditi (Sales Voice Assistant)',
            'language' => 'hi-IN',
            'status' => 'active',
        ]);

        $chatAgent = VoiceAgent::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Support Omnichannel Bot',
            'language' => 'en-US',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->post(
            route('client.voice.numbers.ai-agents', $phone->id),
            [
                'assigned_ai_agent_id' => $voiceAgent->id,
                'assigned_chat_ai_agent_id' => $chatAgent->id,
            ]
        );

        $response->assertRedirect();

        $phone->refresh();
        $this->assertEquals($voiceAgent->id, $phone->assigned_ai_agent_id);
        $this->assertEquals($chatAgent->id, $phone->assigned_chat_ai_agent_id);
        $this->assertEquals('Aditi (Sales Voice Assistant)', $phone->assignedVoiceAgent->name);
        $this->assertEquals('Support Omnichannel Bot', $phone->assignedChatAgent->name);
    }

    public function test_customer_can_disconnect_whatsapp(): void
    {
        $phone = PhoneNumber::create([
            'workspace_id' => $this->workspace->id,
            'phone_number' => '+912245819200',
            'whatsapp_status' => 'connected',
            'whatsapp_display_name' => 'ABC Technologies',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->post(
            route('client.voice.numbers.whatsapp.disconnect', $phone->id)
        );

        $response->assertRedirect();

        $phone->refresh();
        $this->assertEquals('not_connected', $phone->whatsapp_status);
        $this->assertNull($phone->whatsapp_display_name);
        $this->assertFalse($phone->isUnified());
    }

    public function test_workspace_isolation_prevents_unauthorized_number_connection(): void
    {
        $otherCtx = $this->createWorkspaceContext(['email' => 'other_org@test.com'], ['email' => 'other_user@test.com']);
        $otherWorkspace = $otherCtx['workspace'];

        $phone = PhoneNumber::create([
            'workspace_id' => $otherWorkspace->id,
            'phone_number' => '+12125550199',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->post(
            route('client.voice.numbers.whatsapp.connect', $phone->id),
            [
                'display_name' => 'Intruder Business',
            ]
        );

        $response->assertSessionHasErrors('error');
        $phone->refresh();
        $this->assertEquals('not_connected', $phone->whatsapp_status);
    }
}
