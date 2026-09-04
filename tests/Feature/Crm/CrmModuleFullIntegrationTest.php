<?php

namespace Tests\Feature\Crm;

use App\Models\Client;
use App\Models\Crm\CrmDeal;
use App\Models\Crm\CrmNote;
use App\Models\Crm\CrmPipeline;
use App\Models\Crm\CrmPipelineStage;
use App\Models\Crm\CrmTask;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\ContactTimelineEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CrmModuleFullIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setupWorkspace(): array
    {
        $client = Client::create([
            'name' => 'Growth Enterprise',
            'email' => 'admin@growthenterprise.com',
            'status' => 'active',
        ]);

        $workspace = Workspace::create([
            'client_id' => $client->id,
            'name' => 'Growth Enterprise Main',
            'industry' => 'Real Estate & Property',
            'currency_code' => 'INR',
            'default_locale' => 'en',
        ]);

        $admin = User::create([
            'name' => 'Sarah Admin',
            'email' => 'sarah@growthenterprise.com',
            'password' => Hash::make('Password123!'),
            'role' => User::ROLE_CLIENT,
            'client_id' => $client->id,
            'client_role' => User::CLIENT_ROLE_ADMINISTRATOR,
            'workspace_id' => $workspace->id,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        $agent = User::create([
            'name' => 'Alex Agent',
            'email' => 'alex@growthenterprise.com',
            'password' => Hash::make('Password123!'),
            'role' => User::ROLE_CLIENT,
            'client_id' => $client->id,
            'client_role' => User::CLIENT_ROLE_STAFF,
            'workspace_id' => $workspace->id,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        $workspace->forceFill(['owner_id' => $admin->id])->saveQuietly();
        $workspace->members()->syncWithoutDetaching([
            $admin->id => ['role' => 'owner'],
            $agent->id => ['role' => 'member'],
        ]);

        return compact('client', 'workspace', 'admin', 'agent');
    }

    public function test_crm_lead_creation_populates_pipeline_and_timeline(): void
    {
        ['workspace' => $workspace, 'admin' => $admin, 'agent' => $agent] = $this->setupWorkspace();

        // 1. Create a new lead via CRM Lead store endpoint
        $response = $this->actingAs($admin)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('client.crm.leads.store'), [
                'first_name' => 'Rahul',
                'last_name' => 'Sharma',
                'company' => 'Skyline Properties',
                'phone_e164' => '+919876500112',
                'email' => 'rahul.sharma@skyline.com',
                'deal_value' => 750000,
                'source' => 'whatsapp_inbound',
                'priority' => 'high',
                'assigned_user_id' => $agent->id,
                'next_follow_up_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            ]);

        $response->assertRedirect();

        $contact = Contact::where('workspace_id', $workspace->id)->where('email', 'rahul.sharma@skyline.com')->first();
        $this->assertNotNull($contact);
        $this->assertEquals('Rahul Sharma', $contact->full_name);
        $this->assertEquals(750000, $contact->deal_value);
        $this->assertEquals($agent->id, $contact->assigned_user_id);
        $this->assertNotNull($contact->pipeline_id);
        $this->assertNotNull($contact->stage_id);

        // Verify Timeline event logged
        $timelineEvent = ContactTimelineEvent::where('contact_id', $contact->id)
            ->where('event_type', 'crm_lead_created')
            ->first();
        $this->assertNotNull($timelineEvent);
        $this->assertEquals('crm', $timelineEvent->channel);
    }

    public function test_pipeline_stage_movement_and_deal_syncing(): void
    {
        ['workspace' => $workspace, 'admin' => $admin] = $this->setupWorkspace();

        $pipelineService = app(\App\Services\Crm\CrmPipelineService::class);
        $pipeline = $pipelineService->ensureDefaultPipeline($workspace->id);
        $stages = $pipeline->stages()->orderBy('position')->get();
        $firstStage = $stages[0];
        $qualifiedStage = $stages[1];
        $wonStage = $stages->firstWhere('is_won', true) ?? $stages->last();

        $contact = Contact::create([
            'workspace_id' => $workspace->id,
            'first_name' => 'Priya',
            'last_name' => 'Patel',
            'phone_e164' => '+919876500223',
            'email' => 'priya@example.com',
            'pipeline_id' => $pipeline->id,
            'stage_id' => $firstStage->id,
            'deal_value' => 500000,
        ]);

        // Move stage from initial to Qualified
        $moveResponse = $this->actingAs($admin)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('client.crm.leads.stage', ['uuid' => $contact->uuid]), [
                'stage_id' => $qualifiedStage->id,
            ]);

        $moveResponse->assertRedirect();
        $this->assertEquals($qualifiedStage->id, $contact->fresh()->stage_id);

        // Verify timeline event for stage move
        $stageMoveEvent = ContactTimelineEvent::where('contact_id', $contact->id)
            ->where('event_type', 'crm_stage_change')
            ->first();
        $this->assertNotNull($stageMoveEvent);
        $this->assertEquals("Lead moved to {$qualifiedStage->name}", $stageMoveEvent->title);

        // Add a secondary deal for this contact
        $dealResponse = $this->actingAs($admin)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('client.crm.deals.store'), [
                'contact_id' => $contact->id,
                'name' => 'Commercial Lease Package',
                'value' => 250000,
                'probability' => 80,
                'expected_close_date' => now()->addMonth()->format('Y-m-d'),
            ]);

        $dealResponse->assertRedirect();

        $deal = CrmDeal::where('contact_id', $contact->id)->first();
        $this->assertNotNull($deal);
        $this->assertEquals('Commercial Lease Package', $deal->name);
        $this->assertEquals(250000, $deal->value);

        // Contact total deal value synced
        $this->assertEquals(250000, $contact->fresh()->deal_value);

        // Mark deal won
        $wonResponse = $this->actingAs($admin)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->put(route('client.crm.deals.status', ['deal' => $deal->id]), [
                'status' => 'won',
            ]);

        $wonResponse->assertRedirect();
        $this->assertEquals('won', $deal->fresh()->status);
    }

    public function test_crm_task_creation_follow_up_sync_and_completion(): void
    {
        ['workspace' => $workspace, 'admin' => $admin, 'agent' => $agent] = $this->setupWorkspace();

        $contact = Contact::create([
            'workspace_id' => $workspace->id,
            'first_name' => 'Vikram',
            'last_name' => 'Mehta',
            'phone_e164' => '+919876500334',
            'email' => 'vikram@example.com',
            'deal_value' => 120000,
        ]);

        $dueAt = now()->addDays(3);

        // Create Task
        $taskResponse = $this->actingAs($admin)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('client.crm.tasks.store'), [
                'contact_id' => $contact->id,
                'title' => 'Follow up on proposal review',
                'description' => 'Call lead to discuss discount terms.',
                'due_at' => $dueAt->format('Y-m-d H:i:s'),
                'priority' => 'urgent',
                'assigned_user_id' => $agent->id,
            ]);

        $taskResponse->assertRedirect();

        $task = CrmTask::where('contact_id', $contact->id)->first();
        $this->assertNotNull($task);
        $this->assertEquals('Follow up on proposal review', $task->title);
        $this->assertEquals('pending', $task->status);
        $this->assertEquals('urgent', $task->priority);
        $this->assertEquals($agent->id, $task->assigned_user_id);

        // Verify contact's next follow up date was automatically synced
        $freshContact = $contact->fresh();
        $this->assertNotNull($freshContact->next_follow_up_at);

        // Complete Task
        $completeResponse = $this->actingAs($agent)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->put(route('client.crm.tasks.status', ['task' => $task->id]), [
                'status' => 'completed',
            ]);

        $completeResponse->assertRedirect();
        $this->assertEquals('completed', $task->fresh()->status);

        // Verify timeline event for task completion
        $taskCompletedEvent = ContactTimelineEvent::where('contact_id', $contact->id)
            ->where('event_type', 'crm_task_completed')
            ->first();
        $this->assertNotNull($taskCompletedEvent);
        $this->assertStringContainsString('Task Completed', $taskCompletedEvent->title);
    }

    public function test_crm_internal_note_with_user_mentions_and_timeline(): void
    {
        ['workspace' => $workspace, 'admin' => $admin, 'agent' => $agent] = $this->setupWorkspace();

        $contact = Contact::create([
            'workspace_id' => $workspace->id,
            'first_name' => 'Ananya',
            'last_name' => 'Deshmukh',
            'phone_e164' => '+919876500445',
            'email' => 'ananya@example.com',
        ]);

        $noteResponse = $this->actingAs($admin)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('client.crm.notes.store'), [
                'contact_id' => $contact->id,
                'content' => "Client requested custom pricing. @{$agent->name} please prepare the draft.",
                'is_private' => true,
            ]);

        $noteResponse->assertRedirect();

        $note = CrmNote::where('contact_id', $contact->id)->first();
        $this->assertNotNull($note);
        $this->assertStringContainsString('Client requested custom pricing', $note->content);
        $this->assertEquals($admin->id, $note->user_id);

        // Verify timeline event for note
        $noteEvent = ContactTimelineEvent::where('contact_id', $contact->id)
            ->where('event_type', 'crm_internal_note')
            ->first();
        $this->assertNotNull($noteEvent);
        $this->assertEquals("Internal Note Added by {$admin->name}", $noteEvent->title);
    }

    public function test_crm_kanban_dashboard_aggregates_columns_and_metrics(): void
    {
        ['workspace' => $workspace, 'admin' => $admin] = $this->setupWorkspace();

        $pipelineService = app(\App\Services\Crm\CrmPipelineService::class);
        $pipeline = $pipelineService->ensureDefaultPipeline($workspace->id);
        $stages = $pipeline->stages()->orderBy('position')->get();

        Contact::create([
            'workspace_id' => $workspace->id,
            'first_name' => 'Lead A',
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stages[0]->id,
            'deal_value' => 100000,
        ]);

        Contact::create([
            'workspace_id' => $workspace->id,
            'first_name' => 'Lead B',
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stages[1]->id,
            'deal_value' => 200000,
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('client.crm.dashboard'));

        $response->assertOk();
    }
}
