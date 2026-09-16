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
        if (Schema::hasTable('clients') && !Schema::hasColumn('clients', 'custom_ai_token_limit')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->unsignedBigInteger('custom_ai_token_limit')->nullable()->after('status')->comment('Custom monthly AI Token limit override. Null inherits plan limit.');
            });
        }

        if (Schema::hasTable('workspaces') && !Schema::hasColumn('workspaces', 'custom_ai_token_limit')) {
            Schema::table('workspaces', function (Blueprint $table) {
                $table->unsignedBigInteger('custom_ai_token_limit')->nullable()->after('status')->comment('Custom monthly AI Token limit override for workspace.');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('clients') && Schema::hasColumn('clients', 'custom_ai_token_limit')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropColumn('custom_ai_token_limit');
            });
        }

        if (Schema::hasTable('workspaces') && Schema::hasColumn('workspaces', 'custom_ai_token_limit')) {
            Schema::table('workspaces', function (Blueprint $table) {
                $table->dropColumn('custom_ai_token_limit');
            });
        }
    }
};
