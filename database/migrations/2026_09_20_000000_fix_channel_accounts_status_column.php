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
                // Safely convert to a string to prevent ENUM truncation errors on some live DB configurations
                $table->string('status', 32)->default('inactive')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No safe down migration for this since we don't know the original ENUM values
    }
};
