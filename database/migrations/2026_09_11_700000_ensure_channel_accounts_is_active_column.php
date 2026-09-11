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
        if (Schema::hasTable('channel_accounts')) {
            Schema::table('channel_accounts', function (Blueprint $table) {
                if (! Schema::hasColumn('channel_accounts', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('status');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('channel_accounts')) {
            Schema::table('channel_accounts', function (Blueprint $table) {
                if (Schema::hasColumn('channel_accounts', 'is_active')) {
                    $table->dropColumn('is_active');
                }
            });
        }
    }
};
