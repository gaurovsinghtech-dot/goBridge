<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voice_calls', function (Blueprint $table) {
            if (!Schema::hasColumn('voice_calls', 'phone_number_id')) {
                $table->unsignedBigInteger('phone_number_id')->nullable()->after('workspace_id')->index();
            }
            if (!Schema::hasColumn('voice_calls', 'assigned_ai_agent_id')) {
                $table->unsignedBigInteger('assigned_ai_agent_id')->nullable()->after('voice_agent_id')->index();
            }
            if (!Schema::hasColumn('voice_calls', 'handoff_reason')) {
                $table->string('handoff_reason', 128)->nullable()->after('outcome');
            }
            if (!Schema::hasColumn('voice_calls', 'lead_score')) {
                $table->unsignedInteger('lead_score')->default(0)->after('handoff_reason');
            }
        });

        Schema::table('phone_numbers', function (Blueprint $table) {
            if (!Schema::hasColumn('phone_numbers', 'assigned_chat_ai_agent_id')) {
                $table->unsignedBigInteger('assigned_chat_ai_agent_id')->nullable()->after('assigned_ai_agent_id');
            }
            if (!Schema::hasColumn('phone_numbers', 'handoff_number')) {
                $table->string('handoff_number', 32)->nullable()->after('call_recording_enabled');
            }
            if (!Schema::hasColumn('phone_numbers', 'fallback_action')) {
                $table->string('fallback_action', 32)->default('whatsapp_callback')->after('handoff_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('voice_calls', function (Blueprint $table) {
            $table->dropColumn(['phone_number_id', 'assigned_ai_agent_id', 'handoff_reason', 'lead_score']);
        });
    }
};
