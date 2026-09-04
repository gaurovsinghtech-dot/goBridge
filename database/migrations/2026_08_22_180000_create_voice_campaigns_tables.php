<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voice_campaigns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 128);
            $table->string('type', 64)->default('lead_followup');
            $table->text('description')->nullable();
            $table->foreignId('voice_agent_id')->nullable()->constrained('voice_agents')->nullOnDelete();
            $table->foreignId('phone_number_id')->nullable()->constrained('phone_numbers')->nullOnDelete();
            $table->string('caller_id_number', 32)->nullable();
            $table->string('status', 32)->default('draft')->index(); // draft, scheduled, running, paused, completed, cancelled, failed
            $table->dateTime('start_at')->nullable();
            $table->dateTime('end_at')->nullable();
            $table->string('timezone', 64)->default('Asia/Kolkata');
            $table->json('calling_days')->nullable(); // ["Monday", "Tuesday", ...]
            $table->string('calling_start_time', 8)->default('09:00');
            $table->string('calling_end_time', 8)->default('18:00');
            $table->unsignedInteger('max_attempts')->default(3);
            $table->unsignedInteger('retry_delay_hours')->default(24);
            $table->unsignedInteger('call_timeout_sec')->default(30);
            $table->unsignedInteger('max_duration_sec')->default(600);
            $table->unsignedInteger('concurrent_limit')->default(2);
            $table->unsignedInteger('daily_limit')->default(100);
            $table->unsignedInteger('max_campaign_calls')->default(500);
            $table->boolean('compliance_confirmed')->default(false);
            $table->boolean('ai_disclosure_enabled')->default(true);
            $table->boolean('whatsapp_followup_enabled')->default(true);
            $table->string('whatsapp_template_name')->nullable();
            $table->json('audience_filters')->nullable();
            $table->unsignedInteger('total_contacts')->default(0);
            $table->unsignedInteger('completed_calls')->default(0);
            $table->unsignedInteger('answered_calls')->default(0);
            $table->unsignedInteger('interested_calls')->default(0);
            $table->unsignedInteger('qualified_calls')->default(0);
            $table->unsignedInteger('callback_calls')->default(0);
            $table->unsignedInteger('not_interested_calls')->default(0);
            $table->unsignedInteger('no_answer_calls')->default(0);
            $table->unsignedInteger('failed_calls')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('voice_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voice_campaign_id')->constrained('voice_campaigns')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->string('phone_e164', 32)->index();
            $table->string('contact_name', 128)->nullable();
            $table->string('status', 32)->default('pending')->index(); // pending, queued, calling, completed, failed, skipped
            $table->unsignedInteger('attempts_count')->default(0);
            $table->unsignedInteger('max_attempts')->default(3);
            $table->dateTime('last_attempt_at')->nullable();
            $table->dateTime('next_attempt_at')->nullable()->index();
            $table->string('call_outcome', 64)->nullable()->index(); // interested, not_interested, callback_requested, qualified, unqualified, no_answer, busy, invalid_number, human_handoff, completed, failed
            $table->string('lead_score', 32)->nullable();
            $table->foreignId('voice_call_id')->nullable()->constrained('voice_calls')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['voice_campaign_id', 'phone_e164'], 'voice_campaign_recipient_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_campaign_recipients');
        Schema::dropIfExists('voice_campaigns');
    }
};
