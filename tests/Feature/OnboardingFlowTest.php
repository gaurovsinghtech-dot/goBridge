<?php

namespace Tests\Feature;

use App\Models\OnboardingStep;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\AI\Models\AiChatbot;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Voice\Models\TelephonyPhoneNumber;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $client = \App\Models\Client::create([
            'name' => 'Test Company',
            'status' => 'active',
        ]);

        $this->user = User::factory()->create([
            'email' => 'founder@testcompany.com',
            'name' => 'John Founder',
            'role' => 'client',
            'client_id' => $client->id,
        ]);

        $this->workspace = Workspace::create([
            'owner_id' => $this->user->id,
            'client_id' => $client->id,
            'name' => 'Test Company',
            'default_locale' => 'en',
            'currency_code' => 'INR',
            'service_type' => 'whatsapp_voice',
        ]);

        $this->user->workspace_id = $this->workspace->id;
        $this->user->save();
    }

    public function test_initial_onboarding_state_has_account_completed_and_phone_as_current(): void
    {
        $response = $this->actingAs($this->user)->get(route('client.onboarding'));
        $response->assertOk();

        $service = app(OnboardingService::class);
        $progress = $service->getProgress($this->user);

        $this->assertEquals(9, $progress['total']);
        $this->assertGreaterThanOrEqual(1, $progress['done']);
        $this->assertEquals('phone', $progress['current_step_key']);
    }

    public function test_step2_phone_number_provision_and_verification(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('client.onboarding.numbers.provision'), [
            'phone_number' => '+919876543210',
            'country' => 'IN',
            'provider' => 'twilio',
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('telephony_phone_numbers', [
            'workspace_id' => $this->workspace->id,
            'phone_number' => '+919876543210',
            'status' => 'connected',
        ]);

        $progress = app(OnboardingService::class)->getProgress($this->user);
        $phoneStep = collect($progress['steps'])->firstWhere('key', 'phone');
        $this->assertEquals('completed', $phoneStep['status']);
    }

    public function test_step3_whatsapp_connect_and_verification(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('client.onboarding.whatsapp.connect'), [
            'phone_number' => '+919876543210',
            'waba_id' => 'WABA-12345678',
            'phone_number_id' => 'PHONE-ID-9999',
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('channel_accounts', [
            'workspace_id' => $this->workspace->id,
            'channel' => 'whatsapp',
            'status' => 'active',
        ]);

        $progress = app(OnboardingService::class)->getProgress($this->user);
        $waStep = collect($progress['steps'])->firstWhere('key', 'whatsapp');
        $this->assertEquals('completed', $waStep['status']);
    }

    public function test_step4_calling_configure_and_verification(): void
    {
        TelephonyPhoneNumber::create([
            'workspace_id' => $this->workspace->id,
            'phone_number' => '+919876543210',
            'provider' => 'twilio',
            'status' => 'connected',
        ]);

        $response = $this->actingAs($this->user)->postJson(route('client.onboarding.calling.configure'), [
            'phone_number' => '+919876543210',
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $progress = app(OnboardingService::class)->getProgress($this->user);
        $callingStep = collect($progress['steps'])->firstWhere('key', 'calling');
        $this->assertEquals('completed', $callingStep['status']);
    }

    public function test_step5_ai_agent_creation(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('client.onboarding.ai-agent'), [
            'name' => 'Sarah - Sales Assistant',
            'purpose' => 'sales',
            'language' => 'en',
            'tone' => 'professional',
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('ai_chatbots', [
            'workspace_id' => $this->workspace->id,
            'name' => 'Sarah - Sales Assistant',
            'enabled' => true,
        ]);

        $progress = app(OnboardingService::class)->getProgress($this->user);
        $agentStep = collect($progress['steps'])->firstWhere('key', 'ai_agent');
        $this->assertEquals('completed', $agentStep['status']);
    }

    public function test_step6_business_setup_and_timezone(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('client.onboarding.business'), [
            'name' => 'Growbridge Retail Ltd',
            'industry' => 'E-Commerce / Retail',
            'website' => 'https://growbridge.co.in',
            'country' => 'India',
            'timezone' => 'Asia/Kolkata',
            'currency_code' => 'INR',
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->workspace->refresh();
        $this->assertEquals('Growbridge Retail Ltd', $this->workspace->name);
        $this->assertEquals('E-Commerce / Retail', $this->workspace->industry);
        $this->assertEquals('Asia/Kolkata', $this->workspace->timezone);

        $progress = app(OnboardingService::class)->getProgress($this->user);
        $businessStep = collect($progress['steps'])->firstWhere('key', 'business');
        $this->assertEquals('completed', $businessStep['status']);
    }

    public function test_step7_complete_launch_blocks_if_incomplete_and_succeeds_when_ready(): void
    {
        // 1. Attempt launch without preceding steps completed -> should fail with 422
        $response = $this->actingAs($this->user)->postJson(route('client.onboarding.launch'));
        $response->assertStatus(422);

        // 2. Complete all preceding steps
        TelephonyPhoneNumber::create([
            'workspace_id' => $this->workspace->id,
            'phone_number' => '+919876543210',
            'status' => 'connected',
            'voice_enabled' => true,
        ]);

        ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel' => 'whatsapp',
            'display_name' => 'WhatsApp',
            'status' => 'active',
        ]);

        AiChatbot::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Support Assistant',
            'enabled' => true,
        ]);

        $this->workspace->update([
            'industry' => 'SaaS',
            'timezone' => 'Asia/Kolkata',
        ]);

        // 3. Launch again -> should succeed
        $response = $this->actingAs($this->user)->postJson(route('client.onboarding.launch'));
        $response->assertOk()->assertJson(['success' => true]);

        $this->workspace->refresh();
        $this->assertTrue((bool) $this->workspace->onboarding_completed);
    }
}
