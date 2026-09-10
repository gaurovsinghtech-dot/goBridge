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
        if (Schema::hasTable('ai_chatbots')) {
            Schema::table('ai_chatbots', function (Blueprint $table) {
                if (! Schema::hasColumn('ai_chatbots', 'role')) {
                    $table->string('role', 128)->nullable()->after('name');
                }
                if (! Schema::hasColumn('ai_chatbots', 'purpose')) {
                    $table->string('purpose', 255)->nullable()->after('role');
                }
                if (! Schema::hasColumn('ai_chatbots', 'agent_type')) {
                    $table->string('agent_type', 64)->default('chat')->after('purpose');
                }
                if (! Schema::hasColumn('ai_chatbots', 'language')) {
                    $table->string('language', 32)->default('en')->after('agent_type');
                }
                if (! Schema::hasColumn('ai_chatbots', 'status')) {
                    $table->string('status', 32)->default('active')->after('language');
                }
                if (! Schema::hasColumn('ai_chatbots', 'enabled')) {
                    $table->boolean('enabled')->default(true)->after('status');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('ai_chatbots')) {
            Schema::table('ai_chatbots', function (Blueprint $table) {
                if (Schema::hasColumn('ai_chatbots', 'role')) {
                    $table->dropColumn('role');
                }
            });
        }
    }
};
