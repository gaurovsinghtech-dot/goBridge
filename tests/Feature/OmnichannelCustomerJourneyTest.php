<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Modules\Automation\Models\Automation;
use App\Modules\Automation\Models\AutomationRun;
use App\Modules\Automation\Services\SmartFollowUpService;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\ContactTimelineEvent;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use App\Modules\Shared\Services\ContactIdentityService;
use App\Modules\Shared\Services\CustomerJourneyService;
use App\Modules\Shared\Services\OmnichannelLeadScoringService;
use App\Modules\Shared\Services\OptOutComplianceService;
use App\Modules\Voice\Models\VoiceCall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OmnichannelCustomerJourneyTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $ctx = $this->createWorkspaceContext();
        $this->user = $ctx['user'];
        $this->workspace = $ctx['workspace'];
    }

    public function test_unified_timeline_event_recording_and_retrieval(): void
    {
        $contact = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Rahul',
            'last_name' => 'Sharma',
            'phone_e164' => '+919876543210',
            'email' => 'rahul@example.com',
        ]);

        $journeyService = app(CustomerJourneyService::class);

        // Record WhatsApp event
        $journeyService->recordEvent(
            $contact,
            'whatsapp',
            'message_in',
            'Inbound WhatsApp Message',
            'Inquired about pricing package'
        );

        // Record AI Voice Call
        VoiceCall::create([
            'workspace_id' => $this->workspace->id,
            'contact_id' => $contact->id,
            'direction' => 'outbound',
            'provider' => 'twilio',
            'from_number' => '+919876543210',
            'to_number' => '+919876543210',
            'status' => 'completed',
            'duration_sec' => 95,
            'summary' => 'Customer confirmed demo booking for Monday.',
            'started_at' => now(),
        ]);

        $timeline = $journeyService->getUnifiedTimeline($contact);

        $this->assertGreaterThanOrEqual(2, $timeline->count());
        $this->assertTrue($timeline->contains('title', 'Inbound WhatsApp Message'));
        $this->assertTrue($timeline->contains('channel', 'phone'));
    }

    public function test_contact_identity_resolution_and_clean_merge(): void
    {
        $identityService = app(ContactIdentityService::class);

        // Create Master Contact
        $master = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Amit',
            'phone_e164' => '+919811122233',
        ]);

        // Create Duplicate Contact (with email)
        $dup = Contact::create([
            'workspace_id' => $this->workspace->id,
            'email' => 'amit.work@example.com',
            'duplicate_of_id' => $master->id,
        ]);

        // Assign voice call to duplicate
        $call = VoiceCall::create([
            'workspace_id' => $this->workspace->id,
            'contact_id' => $dup->id,
            'direction' => 'inbound',
            'status' => 'completed',
        ]);

        // Merge duplicate into master
        $merged = $identityService->mergeContacts($master, $dup);

        $this->assertTrue($merged);
        $master->refresh();

        $this->assertEquals('amit.work@example.com', $master->email);
        $this->assertEquals($master->id, $call->fresh()->contact_id);
        $this->assertSoftDeleted('contacts', ['id' => $dup->id]);
    }

    public function test_omnichannel_lead_scoring_and_tier_mapping(): void
    {
        $scoringService = app(OmnichannelLeadScoringService::class);

        $contact = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Sneha',
            'lead_score' => 20,
            'lead_score_band' => 'cold',
        ]);

        // High commercial intent & qualified voice call
        $scoringService->updateScore($contact, [
            'intent' => 'demo_request',
            'call_outcome' => 'qualified',
            'requested_demo' => true,
        ]);

        $contact->refresh();

        $this->assertGreaterThanOrEqual(81, $contact->lead_score);
        $this->assertEquals('very_hot', $contact->lead_score_band);
    }

    public function test_opt_out_detection_and_automatic_run_cancellation(): void
    {
        $optOutService = app(OptOutComplianceService::class);

        $this->assertTrue($optOutService->isOptOutRequest('STOP'));
        $this->assertTrue($optOutService->isOptOutRequest('Unsubscribe'));
        $this->assertTrue($optOutService->isOptOutRequest('DO NOT CALL'));
        $this->assertFalse($optOutService->isOptOutRequest('Hello I need help'));

        $contact = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Vikram',
            'phone_e164' => '+919877788899',
            'marketing_opt_out' => false,
        ]);

        $automation = Automation::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Follow-up Sequence',
            'trigger_type' => 'contact.created',
            'status' => 'active',
        ]);

        $run = AutomationRun::create([
            'automation_id' => $automation->id,
            'contact_id' => $contact->id,
            'status' => 'waiting',
        ]);

        $optOutService->processOptOut($contact, 'whatsapp');

        $contact->refresh();
        $run->refresh();

        $this->assertTrue($contact->marketing_opt_out);
        $this->assertEquals('cancelled', $run->status);
    }

    public function test_smart_follow_up_halts_when_customer_replied(): void
    {
        $contact = Contact::create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Ananya',
            'phone_e164' => '+919833344455',
        ]);

        $automation = Automation::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Nurture Campaign',
            'trigger_type' => 'contact.created',
            'status' => 'active',
        ]);

        $run = AutomationRun::create([
            'workspace_id' => $this->workspace->id,
            'automation_id' => $automation->id,
            'contact_id' => $contact->id,
            'status' => 'waiting',
        ]);
        $run->timestamps = false;
        $run->created_at = now()->subHours(2);
        $run->save();

        $conv = Conversation::create([
            'workspace_id' => $this->workspace->id,
            'contact_id' => $contact->id,
            'status' => 'open',
            'assigned_to' => 'bot',
        ]);

        // Customer replied after run started
        $msg = Message::create([
            'conversation_id' => $conv->id,
            'direction' => 'in',
            'channel' => 'whatsapp',
            'body' => 'I am interested, please send details.',
        ]);
        $msg->timestamps = false;
        $msg->created_at = now()->subHour();
        $msg->save();

        $smartFollowUp = app(SmartFollowUpService::class);
        $shouldHalt = $smartFollowUp->shouldHaltFollowup($run);

        $this->assertTrue($shouldHalt);
    }

    public function test_automation_template_installation(): void
    {
        $response = $this->actingAs($this->user)->post(route('client.automations.templates.install', 'new_lead_followup'));

        $response->assertRedirect();
        $this->assertDatabaseHas('automations', [
            'workspace_id' => $this->workspace->id,
            'name' => 'New Lead Omnichannel Follow-up',
        ]);
    }
}
