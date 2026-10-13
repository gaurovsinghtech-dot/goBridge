<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_chatbots') && ! Schema::hasColumn('ai_chatbots', 'specialist_phone')) {
            Schema::table('ai_chatbots', function (Blueprint $table) {
                $table->string('specialist_phone', 30)->nullable()->after('human_handoff_message');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ai_chatbots', 'specialist_phone')) {
            Schema::table('ai_chatbots', function (Blueprint $table) {
                $table->dropColumn('specialist_phone');
            });
        }
    }
};
