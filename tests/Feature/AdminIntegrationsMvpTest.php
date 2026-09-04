<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Modules\Integrations\Models\IntegrationConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminIntegrationsMvpTest extends TestCase
{
    use RefreshDatabase;

    protected AdminUser $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createSuperAdminUser();
    }

    public function test_admin_integrations_index_renders_mvp_structure_and_launch_readiness(): void
    {
        $response = $this->actingAs($this->admin, 'admin')->get(route('admin.integrations.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Integrations/Index')
            ->has('grouped')
            ->has('grouped.Core Platform')
            ->has('launchReadiness')
            ->where('launchReadiness.total_required', 5)
        );
    }

    public function test_twilio_integration_configure_update_and_test(): void
    {
        // 1. Visit Edit
        $response = $this->actingAs($this->admin, 'admin')->get(route('admin.integrations.edit', 'twilio'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Integrations/Edit')
            ->where('provider', 'twilio')
            ->has('webhookUrls.voice_webhook')
        );

        // 2. Update Credentials
        $updateRes = $this->actingAs($this->admin, 'admin')->put(route('admin.integrations.update', 'twilio'), [
            'enabled' => true,
            'mode' => 'live',
            'credentials' => [
                'account_sid' => 'ACtestmockaccount00000000000000000',
                'auth_token' => 'secret_auth_token_999',
            ],
        ]);
        $updateRes->assertRedirect();

        $config = IntegrationConfig::forProvider('twilio');
        $this->assertNotNull($config);
        $this->assertTrue($config->enabled);
        $this->assertEquals('ACtestmockaccount00000000000000000', $config->credentials['account_sid']);

        // 3. Test Connection endpoint
        $testRes = $this->actingAs($this->admin, 'admin')->postJson(route('admin.integrations.test', 'twilio'));
        $testRes->assertOk();
    }

    public function test_meta_whatsapp_integration_configure_and_update(): void
    {
        $updateRes = $this->actingAs($this->admin, 'admin')->put(route('admin.integrations.update', 'meta_app'), [
            'enabled' => true,
            'mode' => 'live',
            'credentials' => [
                'app_id' => '109283746591823',
                'app_secret' => 'meta_app_secret_xyz',
                'config_id_whatsapp' => 'CONFIG-WA-12345',
            ],
        ]);
        $updateRes->assertRedirect();

        $config = IntegrationConfig::forProvider('meta_app');
        $this->assertNotNull($config);
        $this->assertTrue($config->enabled);
        $this->assertEquals('109283746591823', $config->credentials['app_id']);
    }

    public function test_ai_providers_composite_configuration_and_sync(): void
    {
        $updateRes = $this->actingAs($this->admin, 'admin')->put(route('admin.integrations.update', 'ai_providers'), [
            'enabled' => true,
            'mode' => 'live',
            'credentials' => [
                'default_provider' => 'openai',
                'openai_api_key' => 'sk-proj-test-openai-key',
                'openai_model' => 'gpt-4o',
                'gemini_api_key' => 'AIzaSy-test-gemini-key',
                'gemini_model' => 'gemini-1.5-flash',
                'anthropic_api_key' => 'sk-ant-test-claude-key',
                'anthropic_model' => 'claude-3-5-sonnet-20241022',
            ],
        ]);
        $updateRes->assertRedirect();

        $aiConfig = IntegrationConfig::forProvider('ai_providers');
        $this->assertNotNull($aiConfig);
        $this->assertTrue($aiConfig->enabled);
        $this->assertEquals('openai', $aiConfig->credentials['default_provider']);

        // Check synced legacy record for OpenAI
        $legacyOpenai = IntegrationConfig::forProvider('llm_openai_default');
        $this->assertNotNull($legacyOpenai);
        $this->assertEquals('sk-proj-test-openai-key', $legacyOpenai->credentials['api_key']);
    }

    public function test_launch_readiness_reflects_configuration_status(): void
    {
        // 1. Initially missing Twilio, Meta, AI -> launch readiness not ready
        $res = $this->actingAs($this->admin, 'admin')->get(route('admin.integrations.index'));
        $res->assertInertia(fn ($page) => $page
            ->where('launchReadiness.is_ready', false)
            ->where('launchReadiness.completed_count', 2) // Database + Local Storage
        );

        // 2. Configure remaining required providers
        IntegrationConfig::updateOrCreate(
            ['provider' => 'twilio', 'mode' => 'live'],
            [
                'label' => 'Twilio',
                'enabled' => true,
                'credentials' => ['account_sid' => 'AC1234', 'auth_token' => 'token1234'],
            ]
        );

        IntegrationConfig::updateOrCreate(
            ['provider' => 'meta_app', 'mode' => 'live'],
            [
                'label' => 'Meta WhatsApp Business API',
                'enabled' => true,
                'credentials' => ['app_id' => '123456', 'app_secret' => 'secret1234'],
            ]
        );

        IntegrationConfig::updateOrCreate(
            ['provider' => 'ai_providers', 'mode' => 'live'],
            [
                'label' => 'AI Providers',
                'enabled' => true,
                'credentials' => ['default_provider' => 'openai', 'openai_api_key' => 'sk-123456'],
            ]
        );

        // 3. Re-check readiness -> 5 / 5 complete and is_ready = true
        $res2 = $this->actingAs($this->admin, 'admin')->get(route('admin.integrations.index'));
        $res2->assertInertia(fn ($page) => $page
            ->where('launchReadiness.is_ready', true)
            ->where('launchReadiness.completed_count', 5)
        );
    }
}
