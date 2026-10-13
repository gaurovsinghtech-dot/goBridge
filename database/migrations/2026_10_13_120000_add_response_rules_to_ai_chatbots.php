<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_chatbots') && ! Schema::hasColumn('ai_chatbots', 'response_rules')) {
            Schema::table('ai_chatbots', function (Blueprint $table) {
                $table->text('response_rules')->nullable()->after('system_prompt');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ai_chatbots', 'response_rules')) {
            Schema::table('ai_chatbots', function (Blueprint $table) {
                $table->dropColumn('response_rules');
            });
        }
    }
};
