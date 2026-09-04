<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('onboarding_steps')) {
            Schema::table('onboarding_steps', function (Blueprint $table) {
                if (! Schema::hasColumn('onboarding_steps', 'workspace_id')) {
                    $table->unsignedBigInteger('workspace_id')->nullable()->after('user_id')->index();
                }
                if (! Schema::hasColumn('onboarding_steps', 'status')) {
                    $table->string('status', 32)->default('pending')->after('step');
                }
                if (! Schema::hasColumn('onboarding_steps', 'payload_json')) {
                    $table->json('payload_json')->nullable()->after('completed_at');
                }
                if (! Schema::hasColumn('onboarding_steps', 'last_error')) {
                    $table->text('last_error')->nullable()->after('payload_json');
                }
            });
        }

        if (Schema::hasTable('workspaces')) {
            Schema::table('workspaces', function (Blueprint $table) {
                if (! Schema::hasColumn('workspaces', 'onboarding_completed')) {
                    $table->boolean('onboarding_completed')->default(false)->after('currency_code');
                }
                if (! Schema::hasColumn('workspaces', 'industry')) {
                    $table->string('industry', 100)->nullable()->after('name');
                }
                if (! Schema::hasColumn('workspaces', 'website')) {
                    $table->string('website', 255)->nullable()->after('industry');
                }
                if (! Schema::hasColumn('workspaces', 'country')) {
                    $table->string('country', 100)->nullable()->after('website');
                }
                if (! Schema::hasColumn('workspaces', 'timezone')) {
                    $table->string('timezone', 64)->nullable()->after('country');
                }
                if (! Schema::hasColumn('workspaces', 'business_hours')) {
                    $table->json('business_hours')->nullable()->after('timezone');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('onboarding_steps')) {
            Schema::table('onboarding_steps', function (Blueprint $table) {
                $cols = array_filter(['workspace_id', 'status', 'payload_json', 'last_error'], fn ($col) => Schema::hasColumn('onboarding_steps', $col));
                if (! empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }

        if (Schema::hasTable('workspaces')) {
            Schema::table('workspaces', function (Blueprint $table) {
                $cols = array_filter(['onboarding_completed', 'industry', 'website', 'country', 'timezone', 'business_hours'], fn ($col) => Schema::hasColumn('workspaces', $col));
                if (! empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
