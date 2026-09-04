<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('twilio_accounts')) {
            Schema::create('twilio_accounts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workspace_id');
                $table->string('twilio_account_sid', 64)->nullable();
                $table->text('encrypted_auth_token')->nullable();
                $table->string('friendly_name', 128)->nullable();
                $table->enum('status', ['active', 'suspended', 'pending'])->default('active');
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index('workspace_id');
                $table->index('twilio_account_sid');
            });
        }

        if (!Schema::hasTable('phone_numbers')) {
            Schema::create('phone_numbers', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('workspace_id');
                $table->string('twilio_account_sid', 64)->nullable();
                $table->string('twilio_phone_number_sid', 64)->nullable();
                $table->string('phone_number', 32);
                $table->string('country', 8)->default('US');
                $table->string('friendly_name', 128)->nullable();
                $table->json('capabilities')->nullable();
                $table->boolean('voice_enabled')->default(true);
                $table->boolean('sms_enabled')->default(true);
                $table->boolean('mms_enabled')->default(false);
                $table->boolean('call_recording_enabled')->default(false);
                $table->enum('status', ['active', 'pending', 'released', 'suspended'])->default('active');
                $table->decimal('monthly_cost', 10, 2)->default(1.15);
                $table->unsignedBigInteger('assigned_ai_agent_id')->nullable();
                $table->string('voice_webhook_url', 512)->nullable();
                $table->string('sms_webhook_url', 512)->nullable();
                $table->timestamps();

                $table->index('workspace_id');
                $table->index(['workspace_id', 'status']);
                $table->index('phone_number');
                $table->index('twilio_phone_number_sid');
                $table->index('assigned_ai_agent_id');
            });
        }

        if (!Schema::hasTable('phone_number_assignments')) {
            Schema::create('phone_number_assignments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('phone_number_id');
                $table->unsignedBigInteger('workspace_id');
                $table->string('assigned_to_type', 128)->default('ai_agent');
                $table->unsignedBigInteger('assigned_to_id');
                $table->timestamps();

                $table->index('phone_number_id');
                $table->index('workspace_id');
            });
        }

        if (Schema::hasTable('voice_calls')) {
            Schema::table('voice_calls', function (Blueprint $table) {
                if (!Schema::hasColumn('voice_calls', 'phone_number_id')) {
                    $table->unsignedBigInteger('phone_number_id')->nullable()->after('workspace_id');
                    $table->index('phone_number_id');
                }
                if (!Schema::hasColumn('voice_calls', 'lead_score')) {
                    $table->unsignedSmallInteger('lead_score')->nullable()->after('summary');
                }
                if (!Schema::hasColumn('voice_calls', 'assigned_ai_agent_id')) {
                    $table->unsignedBigInteger('assigned_ai_agent_id')->nullable()->after('voice_agent_id');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_number_assignments');
        Schema::dropIfExists('phone_numbers');
        Schema::dropIfExists('twilio_accounts');
    }
};
