<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('telephony_phone_numbers')) {
            if (! Schema::hasColumn('telephony_phone_numbers', 'voice_enabled')) {
                Schema::table('telephony_phone_numbers', function (Blueprint $table) {
                    $table->boolean('voice_enabled')->default(true)->after('status');
                });
            }
            if (! Schema::hasColumn('telephony_phone_numbers', 'sms_enabled')) {
                Schema::table('telephony_phone_numbers', function (Blueprint $table) {
                    $table->boolean('sms_enabled')->default(true)->after('voice_enabled');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('telephony_phone_numbers')) {
            Schema::table('telephony_phone_numbers', function (Blueprint $table) {
                if (Schema::hasColumn('telephony_phone_numbers', 'voice_enabled')) {
                    $table->dropColumn('voice_enabled');
                }
                if (Schema::hasColumn('telephony_phone_numbers', 'sms_enabled')) {
                    $table->dropColumn('sms_enabled');
                }
            });
        }
    }
};
