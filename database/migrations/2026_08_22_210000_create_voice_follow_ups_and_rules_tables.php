<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('voice_follow_ups')) {
            Schema::create('voice_follow_ups', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('workspace_id');
                $table->unsignedBigInteger('voice_call_id')->nullable();
                $table->unsignedBigInteger('voice_campaign_id')->nullable();
                $table->unsignedBigInteger('voice_agent_id')->nullable();
                $table->unsignedBigInteger('contact_id')->nullable();
                $table->unsignedBigInteger('assigned_user_id')->nullable();
                $table->string('type', 32)->default('callback'); // callback, whatsapp, email, crm_task, team_notify
                $table->string('status', 32)->default('pending'); // pending, scheduled, in_progress, completed, cancelled, failed, overdue
                $table->string('priority', 32)->default('medium'); // high, medium, low
                $table->dateTime('due_at')->nullable();
                $table->string('timezone', 64)->default('UTC');
                $table->string('title', 255);
                $table->text('notes')->nullable();
                $table->string('outcome_trigger', 64)->nullable(); // interested, qualified, callback_requested, no_answer
                $table->json('execution_payload')->nullable();
                $table->json('result_json')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->timestamps();

                $table->index(['workspace_id', 'status', 'due_at']);
                $table->index('voice_call_id');
                $table->index('contact_id');
                $table->index('assigned_user_id');
            });
        }

        if (!Schema::hasTable('voice_follow_up_rules')) {
            Schema::create('voice_follow_up_rules', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('workspace_id');
                $table->string('name', 255);
                $table->unsignedBigInteger('voice_agent_id')->nullable();
                $table->unsignedBigInteger('voice_campaign_id')->nullable();
                $table->string('trigger_event', 64)->default('interested'); // call_completed, interested, qualified, callback_requested, not_interested, no_answer, human_handoff, failed
                $table->json('conditions')->nullable();
                $table->json('actions'); // [{"type": "schedule_callback"}, {"type": "send_whatsapp"}, ...]
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['workspace_id', 'is_active', 'trigger_event']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_follow_up_rules');
        Schema::dropIfExists('voice_follow_ups');
    }
};
