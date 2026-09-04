<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\PhoneNumber;
use App\Models\TwilioAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\Voice\Models\VoiceAgent;
use App\Modules\Voice\Models\VoiceCall;
use App\Services\TwilioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwilioProvisioningAndWebhooksTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Workspace $workspace;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $ctx = $this->createWorkspaceContext();
        $this->user = $ctx['user'];
        $this->workspace = $ctx['workspace'];
        $this->workspace->update(['service_type' => 'whatsapp_voice']);
        $this->client = $ctx['client'];
    }

    public function test_customer_can_search_available_numbers()
    {
        $response = $this->actingAs($this->user)->getJson(route('client.voice.numbers.search', [
            'country' => 'IN',
            'voice' => 1,
            'sms' => 1,
        ]));

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'country',
            'numbers',
        ]);
        $this->assertNotEmpty($response->json('numbers'));
    }

    public function test_customer_can_purchase_and_configure_virtual_number()
    {
        $testNumber = '+91 22 ' . rand(1000, 9999) . ' ' . rand(1000, 9999);

        $response = $this->actingAs($this->user)->post(route('client.voice.numbers.purchase'), [
            'phone_number' => $testNumber,
            'country' => 'IN',
            'friendly_name' => 'Automated Test Line',
            'voice' => true,
            'sms' => true,
            'mms' => false,
            'call_recording' => true,
        ]);

        $response->assertRedirect(route('client.voice.numbers.index'));

        $this->assertDatabaseHas('phone_numbers', [
            'workspace_id' => $this->workspace->id,
            'phone_number' => $testNumber,
            'status' => 'active',
            'call_recording_enabled' => true,
        ]);
    }

    public function test_twilio_inbound_voice_webhook_produces_twiml_and_logs_call()
    {
        $phone = PhoneNumber::create([
            'workspace_id' => $this->workspace->id,
            'phone_number' => '+912245819200',
            'status' => 'active',
        ]);

        $callSid = 'CA_TEST_' . bin2hex(random_bytes(8));

        $response = $this->post('/api/v1/webhooks/twilio/voice', [
            'To' => $phone->phone_number,
            'From' => '+919988776655',
            'CallSid' => $callSid,
        ]);

        $response->assertOk();
        $this->assertStringContainsString('<Response', $response->getContent());
        $this->assertStringContainsString('Say', $response->getContent());

        $this->assertDatabaseHas('voice_calls', [
            'workspace_id' => $this->workspace->id,
            'provider_call_id' => $callSid,
            'direction' => 'inbound',
            'status' => 'in-progress',
        ]);
    }

    public function test_twilio_call_status_webhook_computes_duration_and_summary()
    {
        $callSid = 'CA_STATUS_' . bin2hex(random_bytes(8));

        $call = VoiceCall::create([
            'workspace_id' => $this->workspace->id,
            'provider_call_id' => $callSid,
            'from_number' => '+919876543210',
            'to_number' => '+912245819200',
            'status' => 'in-progress',
        ]);

        $response = $this->post('/api/v1/webhooks/twilio/status', [
            'CallSid' => $callSid,
            'CallStatus' => 'completed',
            'CallDuration' => '180',
        ]);

        $response->assertOk();

        $call->refresh();
        $this->assertEquals('completed', $call->status);
        $this->assertEquals(180, $call->duration_sec);
        $this->assertNotNull($call->summary);
        $this->assertNotNull($call->lead_score);
    }
}
