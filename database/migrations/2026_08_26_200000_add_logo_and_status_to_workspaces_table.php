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
        Schema::table('workspaces', function (Blueprint $table) {
            if (! Schema::hasColumn('workspaces', 'logo_path')) {
                $table->string('logo_path', 512)->nullable()->after('name');
            }
            if (! Schema::hasColumn('workspaces', 'status')) {
                $table->string('status', 32)->default('active')->after('onboarding_completed');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $cols = array_filter(['logo_path', 'status'], fn ($c) => Schema::hasColumn('workspaces', $c));
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
