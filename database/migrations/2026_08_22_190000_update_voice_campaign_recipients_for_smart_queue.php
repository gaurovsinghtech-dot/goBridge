<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voice_campaign_recipients', function (Blueprint $table) {
            $table->string('priority_level', 16)->default('medium')->index(); // high, medium, low
            $table->unsignedInteger('priority_score')->default(50)->index(); // 100=high, 50=medium, 10=low
            $table->string('queue_reason', 64)->default('routine_followup'); // callback_requested, hot_lead, appointment_reminder, new_lead, routine_followup, etc.
            $table->string('exclusion_reason', 64)->nullable()->index(); // opted_out, invalid_phone, no_consent, outside_calling_hours, max_attempts_reached, blocked
            $table->string('preferred_calling_window', 32)->nullable(); // e.g. "17:00-19:00"
            $table->string('timezone', 64)->default('Asia/Kolkata');
            $table->boolean('is_callback')->default(false)->index();
            $table->dateTime('callback_scheduled_at')->nullable()->index();
            $table->dateTime('locked_at')->nullable();
            $table->string('locked_by', 64)->nullable();

            $table->index(['workspace_id', 'status', 'priority_score', 'next_attempt_at'], 'vcr_smart_queue_idx');
        });
    }

    public function down(): void
    {
        Schema::table('voice_campaign_recipients', function (Blueprint $table) {
            $table->dropIndex('vcr_smart_queue_idx');
            $table->dropColumn([
                'priority_level',
                'priority_score',
                'queue_reason',
                'exclusion_reason',
                'preferred_calling_window',
                'timezone',
                'is_callback',
                'callback_scheduled_at',
                'locked_at',
                'locked_by',
            ]);
        });
    }
};
