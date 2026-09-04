<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('phone_numbers')) {
            Schema::table('phone_numbers', function (Blueprint $table) {
                if (!Schema::hasColumn('phone_numbers', 'whatsapp_status')) {
                    $table->enum('whatsapp_status', ['not_connected', 'pending_verification', 'connected'])
                        ->default('not_connected')
                        ->after('call_recording_enabled');
                }
                if (!Schema::hasColumn('phone_numbers', 'whatsapp_account_id')) {
                    $table->unsignedBigInteger('whatsapp_account_id')->nullable()->after('whatsapp_status');
                }
                if (!Schema::hasColumn('phone_numbers', 'whatsapp_phone_number_id')) {
                    $table->string('whatsapp_phone_number_id', 64)->nullable()->after('whatsapp_account_id');
                }
                if (!Schema::hasColumn('phone_numbers', 'whatsapp_display_name')) {
                    $table->string('whatsapp_display_name', 128)->nullable()->after('whatsapp_phone_number_id');
                }
                if (!Schema::hasColumn('phone_numbers', 'assigned_chat_ai_agent_id')) {
                    $table->unsignedBigInteger('assigned_chat_ai_agent_id')->nullable()->after('assigned_ai_agent_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('phone_numbers')) {
            Schema::table('phone_numbers', function (Blueprint $table) {
                $columns = [
                    'whatsapp_status',
                    'whatsapp_account_id',
                    'whatsapp_phone_number_id',
                    'whatsapp_display_name',
                    'assigned_chat_ai_agent_id',
                ];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('phone_numbers', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
