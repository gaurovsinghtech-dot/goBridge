<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            if (! Schema::hasColumn('conversations', 'ai_mode')) {
                $table->string('ai_mode', 32)->default('auto')->after('assigned_to');
            }
            if (! Schema::hasColumn('conversations', 'ai_agent_id')) {
                $table->unsignedBigInteger('ai_agent_id')->nullable()->after('assigned_ai_agent_id');
            }
            if (! Schema::hasColumn('conversations', 'handoff_reason')) {
                $table->string('handoff_reason', 255)->nullable()->after('ai_mode');
            }
            if (! Schema::hasColumn('conversations', 'human_takeover_at')) {
                $table->timestamp('human_takeover_at')->nullable()->after('handoff_reason');
            }
            if (! Schema::hasColumn('conversations', 'ai_last_response_at')) {
                $table->timestamp('ai_last_response_at')->nullable()->after('human_takeover_at');
            }
            if (! Schema::hasColumn('conversations', 'confidence_score')) {
                $table->unsignedTinyInteger('confidence_score')->nullable()->after('ai_last_response_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $cols = array_filter(
                ['ai_mode', 'ai_agent_id', 'handoff_reason', 'human_takeover_at', 'ai_last_response_at', 'confidence_score'],
                fn ($c) => Schema::hasColumn('conversations', $c)
            );
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
