<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\PhoneNumber;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\Voice\Models\VoiceAgent;
use App\Modules\Whatsapp\Models\WhatsappBusinessAccount;
use App\Services\Channels\ChannelStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

use App\Models\Plan;

class ChannelStatusAndEmbeddedSignupTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Workspace $workspace;
    private Client $client;
    private ChannelStatusService $statusService;

    protected function setUp(): void
    {
        parent::setUp();

        $ctx = $this->createWorkspaceContext();
        $this->user = $ctx['user'];
        $this->workspace = $ctx['workspace'];
        $this->workspace->update(['service_type' => 'whatsapp_voice']);
        $this->client = $ctx['client'];
        $this->statusService = app(ChannelStatusService::class);

        $plan = Plan::factory()->create([
            'name' => 'All-in-One Voice & WhatsApp',
            'features' => ['voice_calling' => true, 'whatsapp' => true, 'ai_agents' => true],
            'enabled' => true,
        ]);
        $this->attachPlanToClient($this->client, $plan);
    }

    public function test_channel_status_service_computes_standard_channel_statuses(): void
    {
        // 1. Initial workspace state without connections
        $statuses = $this->statusService->getWorkspaceChannelStatuses($this->workspace);

        $this->assertArrayHasKey('whatsapp', $statuses);
        $this->assertArrayHasKey('instagram', $statuses);
        $this->assertArrayHasKey('messenger', $statuses);
        $this->assertArrayHasKey('email', $statuses);
        $this->assertArrayHasKey('twilio', $statuses);
        $this->assertArrayHasKey('ai', $statuses);

        $this->assertEquals(ChannelStatusService::STATUS_NOT_CONNECTED, $statuses['whatsapp']['status']);
        $this->assertEquals(ChannelStatusService::STATUS_NOT_CONNECTED, $statuses['twilio']['status']);

        // 2. Add phone number with Voice active and WhatsApp connected
        PhoneNumber::create([
            'workspace_id' => $this->workspace->id,
            'phone_number' => '+912245819200',
            'voice_enabled' => true,
            'whatsapp_status' => 'connected',
            'whatsapp_display_name' => 'Acme Test Corp',
            'status' => 'active',
        ]);

        $updatedStatuses = $this->statusService->getWorkspaceChannelStatuses($this->workspace);
        $this->assertEquals(ChannelStatusService::STATUS_CONNECTED, $updatedStatuses['whatsapp']['status']);
        $this->assertEquals(ChannelStatusService::STATUS_CONNECTED, $updatedStatuses['twilio']['status']);
    }

    public function test_channel_status_service_returns_setup_required_when_number_exists_without_whatsapp(): void
    {
        PhoneNumber::create([
            'workspace_id' => $this->workspace->id,
            'phone_number' => '+12125550199',
            'voice_enabled' => true,
            'whatsapp_status' => 'not_connected',
            'status' => 'active',
        ]);

        $statuses = $this->statusService->getWorkspaceChannelStatuses($this->workspace);
        $this->assertEquals(ChannelStatusService::STATUS_SETUP_REQUIRED, $statuses['whatsapp']['status']);
        $this->assertEquals(ChannelStatusService::STATUS_CONNECTED, $statuses['twilio']['status']);
    }

    public function test_channel_status_service_detects_active_ai_voice_agents(): void
    {
        VoiceAgent::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Sales Assistant Aditi',
            'language' => 'hi-IN',
            'tone' => 'professional',
            'provider' => 'twilio',
            'status' => 'active',
        ]);

        $statuses = $this->statusService->getWorkspaceChannelStatuses($this->workspace);
        $this->assertEquals(ChannelStatusService::STATUS_CONNECTED, $statuses['ai']['status']);
        $this->assertStringContainsString('Active', $statuses['ai']['summary']);
    }

    public function test_meta_embedded_signup_endpoint_connects_waba_and_updates_number(): void
    {
        $phone = PhoneNumber::create([
            'workspace_id' => $this->workspace->id,
            'phone_number' => '+912245819200',
            'voice_enabled' => true,
            'whatsapp_status' => 'not_connected',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->postJson(
            route('client.voice.numbers.whatsapp.embedded-signup', $phone->id),
            [
                'code' => 'TEST_OAUTH_CODE_FROM_META_SDK_9988',
                'waba_id' => '109823948572910',
                'phone_number_id' => '104928374829104',
                'display_name' => 'Growbridge Enterprise Line',
            ]
        );

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $phone->refresh();
        $this->assertEquals('connected', $phone->whatsapp_status);
        $this->assertEquals('Growbridge Enterprise Line', $phone->whatsapp_display_name);
        $this->assertTrue($phone->isUnified());

        $this->assertDatabaseHas('whatsapp_business_accounts', [
            'workspace_id' => $this->workspace->id,
            'waba_id' => '109823948572910',
            'status' => 'active',
        ]);
    }

    public function test_embedded_signup_rejects_cross_workspace_unauthorized_number(): void
    {
        $otherCtx = $this->createWorkspaceContext();
        $otherPhone = PhoneNumber::create([
            'workspace_id' => $otherCtx['workspace']->id,
            'phone_number' => '+14155550100',
            'voice_enabled' => true,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->postJson(
            route('client.voice.numbers.whatsapp.embedded-signup', $otherPhone->id),
            [
                'code' => 'TEST_ATTEMPT_CROSS_TENANT',
                'waba_id' => '999888777',
            ]
        );

        $response->assertStatus(403);
    }

    public function test_phone_and_whatsapp_index_props_contain_channel_statuses_and_meta_app(): void
    {
        $response = $this->actingAs($this->user)->get(route('client.voice.numbers.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Voice/PhoneNumbers/Index')
            ->has('channelStatuses')
            ->has('metaAppId')
            ->has('numbers')
            ->has('stats')
        );
    }
}
