<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telephony_phone_numbers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->string('phone_number', 32);
            $table->enum('provider', ['heyo', 'exotel', 'twilio', 'plivo', 'custom'])->default('heyo');
            $table->enum('status', ['connected', 'disconnected', 'pending', 'error'])->default('connected');
            $table->unsignedBigInteger('assigned_voice_agent_id')->nullable();
            $table->enum('direction', ['inbound', 'outbound', 'both'])->default('both');
            $table->boolean('is_default')->default(false);
            $table->json('config_json')->nullable();
            $table->timestamps();

            $table->index('workspace_id');
            $table->index(['workspace_id', 'phone_number']);
            $table->index('assigned_voice_agent_id');
        });

        Schema::create('telephony_api_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->string('provider', 32)->default('heyo');
            $table->string('endpoint', 255);
            $table->string('http_method', 16)->default('POST');
            $table->unsignedSmallInteger('status_code')->default(200);
            $table->unsignedInteger('response_time_ms')->default(0);
            $table->boolean('success')->default(true);
            $table->json('request_payload')->nullable();
            $table->json('response_body')->nullable();
            $table->timestamps();

            $table->index('workspace_id');
            $table->index(['workspace_id', 'provider', 'created_at']);
        });

        Schema::create('telephony_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->string('provider', 32)->default('heyo');
            $table->string('event_name', 64);
            $table->string('call_id', 128)->nullable();
            $table->json('payload_json')->nullable();
            $table->enum('status', ['received', 'processed', 'failed'])->default('received');
            $table->text('error_message')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('workspace_id');
            $table->index(['workspace_id', 'provider', 'created_at']);
            $table->index('call_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telephony_webhook_logs');
        Schema::dropIfExists('telephony_api_logs');
        Schema::dropIfExists('telephony_phone_numbers');
    }
};
