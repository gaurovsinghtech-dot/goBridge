<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voice_agents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->string('name', 128);
            $table->text('description')->nullable();
            $table->string('status', 32)->default('draft');
            $table->string('language', 32)->default('en-US'); // en-US, hi-IN (Hindi), hinglish (Hinglish)
            $table->string('tone', 64)->default('professional'); // professional, friendly, empathetic, energetic
            $table->string('voice_id', 128)->nullable(); // e.g. alloy, shimmer, echo, aditi, etc.
            $table->string('provider', 32)->default('twilio');
            $table->string('phone_number', 32)->nullable(); // Virtual caller ID
            $table->text('system_prompt')->nullable();
            $table->text('greeting_message')->nullable();
            $table->unsignedBigInteger('ai_kb_id')->nullable(); // Knowledge Base linkage
            $table->json('tools_config')->nullable(); // appointment booking, lead qualification, crm update
            $table->json('call_flow_json')->nullable();
            $table->json('working_hours_json')->nullable();
            $table->string('human_transfer_number', 32)->nullable();
            $table->unsignedInteger('max_duration_sec')->default(300);
            $table->string('ai_model', 64)->default('gpt-4o-mini');
            $table->unsignedInteger('total_calls')->default(0);
            $table->unsignedInteger('successful_calls')->default(0);
            $table->timestamps();

            $table->index('workspace_id');
            $table->index(['workspace_id', 'status']);
        });

        Schema::create('voice_calls', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('voice_agent_id')->nullable();
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->enum('direction', ['inbound', 'outbound'])->default('outbound');
            $table->string('provider', 32)->default('twilio');
            $table->string('provider_call_id', 128)->nullable();
            $table->string('from_number', 32)->nullable();
            $table->string('to_number', 32)->nullable();
            $table->enum('status', ['queued', 'initiated', 'ringing', 'in-progress', 'completed', 'busy', 'failed', 'no-answer', 'cancelled'])->default('queued');
            $table->unsignedInteger('duration_sec')->default(0);
            $table->string('recording_url', 512)->nullable();
            $table->longText('transcript')->nullable();
            $table->text('summary')->nullable();
            $table->string('outcome', 64)->nullable(); // qualified, appointment_booked, support_resolved, transferred, follow_up_needed
            $table->json('extracted_data')->nullable(); // lead score, email, notes collected during call
            $table->json('error_json')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index('workspace_id');
            $table->index('voice_agent_id');
            $table->index('contact_id');
            $table->index('provider_call_id');
            $table->index(['workspace_id', 'status', 'created_at']);
        });

        Schema::create('voice_call_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('voice_call_id');
            $table->enum('speaker', ['caller', 'agent', 'system']);
            $table->text('text');
            $table->unsignedInteger('timestamp_sec')->default(0);
            $table->timestamps();

            $table->index('voice_call_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_call_logs');
        Schema::dropIfExists('voice_calls');
        Schema::dropIfExists('voice_agents');
    }
};
