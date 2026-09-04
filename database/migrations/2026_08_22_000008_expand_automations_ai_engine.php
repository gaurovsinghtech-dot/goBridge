<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automations', function (Blueprint $table) {
            if (! Schema::hasColumn('automations', 'webhook_public_key')) {
                $table->string('webhook_public_key', 64)->nullable()->unique()->after('trigger_token');
            }
            if (! Schema::hasColumn('automations', 'version')) {
                $table->unsignedInteger('version')->default(1)->after('webhook_public_key');
            }
            if (! Schema::hasColumn('automations', 'category')) {
                $table->string('category', 64)->nullable()->after('version');
            }
            if (! Schema::hasColumn('automations', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
            if (! Schema::hasColumn('automations', 'successful_runs')) {
                $table->unsignedInteger('successful_runs')->default(0)->after('run_count');
            }
            if (! Schema::hasColumn('automations', 'failed_runs')) {
                $table->unsignedInteger('failed_runs')->default(0)->after('successful_runs');
            }
            if (! Schema::hasColumn('automations', 'last_run_at')) {
                $table->timestamp('last_run_at')->nullable()->after('failed_runs');
            }
            if (! Schema::hasColumn('automations', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('last_run_at');
            }
        });

        Schema::table('automation_runs', function (Blueprint $table) {
            if (! Schema::hasColumn('automation_runs', 'duration_ms')) {
                $table->unsignedInteger('duration_ms')->nullable()->after('completed_at');
            }
            if (! Schema::hasColumn('automation_runs', 'trigger_event')) {
                $table->string('trigger_event', 64)->nullable()->after('contact_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('automations', function (Blueprint $table) {
            $table->dropColumn([
                'webhook_public_key',
                'version',
                'category',
                'description',
                'successful_runs',
                'failed_runs',
                'last_run_at',
                'archived_at',
            ]);
        });

        Schema::table('automation_runs', function (Blueprint $table) {
            $table->dropColumn(['duration_ms', 'trigger_event']);
        });
    }
};
