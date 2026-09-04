<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('campaigns')) {
            Schema::table('campaigns', function (Blueprint $table) {
                if (! Schema::hasColumn('campaigns', 'estimated_cost')) {
                    $table->decimal('estimated_cost', 10, 4)->nullable()->after('totals_json');
                }
                if (! Schema::hasColumn('campaigns', 'confirmation_required')) {
                    $table->boolean('confirmation_required')->default(false)->after('estimated_cost');
                }
                if (! Schema::hasColumn('campaigns', 'confirmed_at')) {
                    $table->dateTime('confirmed_at')->nullable()->after('confirmation_required');
                }
            });
        }

        if (Schema::hasTable('automation_runs')) {
            Schema::table('automation_runs', function (Blueprint $table) {
                if (! Schema::hasColumn('automation_runs', 'execution_id')) {
                    $table->uuid('execution_id')->nullable()->unique()->after('id');
                }
                if (! Schema::hasColumn('automation_runs', 'idempotency_key')) {
                    $table->string('idempotency_key', 128)->nullable()->index()->after('trigger_event');
                }
                if (! Schema::hasColumn('automation_runs', 'step_count')) {
                    $table->unsignedInteger('step_count')->default(0)->after('status');
                }
                if (! Schema::hasColumn('automation_runs', 'retry_count')) {
                    $table->unsignedInteger('retry_count')->default(0)->after('step_count');
                }
                if (! Schema::hasColumn('automation_runs', 'max_steps')) {
                    $table->unsignedInteger('max_steps')->default(100)->after('retry_count');
                }
                if (! Schema::hasColumn('automation_runs', 'max_duration_seconds')) {
                    $table->unsignedInteger('max_duration_seconds')->default(300)->after('max_steps');
                }
            });
        }

        if (Schema::hasTable('automation_run_logs')) {
            Schema::table('automation_run_logs', function (Blueprint $table) {
                if (! Schema::hasColumn('automation_run_logs', 'step_id')) {
                    $table->string('step_id', 64)->nullable()->after('run_id');
                }
                if (! Schema::hasColumn('automation_run_logs', 'step_index')) {
                    $table->unsignedInteger('step_index')->default(0)->after('step_id');
                }
                if (! Schema::hasColumn('automation_run_logs', 'category')) {
                    $table->string('category', 32)->nullable()->after('node_type');
                }
                if (! Schema::hasColumn('automation_run_logs', 'provider_payload')) {
                    $table->json('provider_payload')->nullable()->after('output');
                }
                if (! Schema::hasColumn('automation_run_logs', 'provider_response')) {
                    $table->json('provider_response')->nullable()->after('provider_payload');
                }
                if (! Schema::hasColumn('automation_run_logs', 'duration_ms')) {
                    $table->unsignedInteger('duration_ms')->nullable()->after('provider_response');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('automation_run_logs')) {
            Schema::table('automation_run_logs', function (Blueprint $table) {
                $cols = ['step_id', 'step_index', 'category', 'provider_payload', 'provider_response', 'duration_ms'];
                foreach ($cols as $c) {
                    if (Schema::hasColumn('automation_run_logs', $c)) {
                        $table->dropColumn($c);
                    }
                }
            });
        }

        if (Schema::hasTable('automation_runs')) {
            Schema::table('automation_runs', function (Blueprint $table) {
                $cols = ['execution_id', 'idempotency_key', 'step_count', 'retry_count', 'max_steps', 'max_duration_seconds'];
                foreach ($cols as $c) {
                    if (Schema::hasColumn('automation_runs', $c)) {
                        $table->dropColumn($c);
                    }
                }
            });
        }

        if (Schema::hasTable('campaigns')) {
            Schema::table('campaigns', function (Blueprint $table) {
                $cols = ['estimated_cost', 'confirmation_required', 'confirmed_at'];
                foreach ($cols as $c) {
                    if (Schema::hasColumn('campaigns', $c)) {
                        $table->dropColumn($c);
                    }
                }
            });
        }
    }
};
