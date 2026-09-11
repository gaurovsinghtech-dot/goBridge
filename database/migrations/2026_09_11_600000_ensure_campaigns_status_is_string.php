<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('campaigns')) {
            try {
                DB::statement("ALTER TABLE campaigns MODIFY COLUMN status VARCHAR(64) NOT NULL DEFAULT 'draft'");
            } catch (\Throwable $e) {
                // Ignore if already string or insufficient permissions
            }
        }

        if (Schema::hasTable('campaign_recipients')) {
            try {
                DB::statement("ALTER TABLE campaign_recipients MODIFY COLUMN status VARCHAR(64) NOT NULL DEFAULT 'queued'");
            } catch (\Throwable $e) {
                // Ignore
            }
        }

        if (Schema::hasTable('voice_campaigns')) {
            try {
                DB::statement("ALTER TABLE voice_campaigns MODIFY COLUMN status VARCHAR(64) NOT NULL DEFAULT 'draft'");
            } catch (\Throwable $e) {
                // Ignore
            }
        }

        if (Schema::hasTable('voice_campaign_recipients')) {
            try {
                DB::statement("ALTER TABLE voice_campaign_recipients MODIFY COLUMN status VARCHAR(64) NOT NULL DEFAULT 'pending'");
            } catch (\Throwable $e) {
                // Ignore
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
