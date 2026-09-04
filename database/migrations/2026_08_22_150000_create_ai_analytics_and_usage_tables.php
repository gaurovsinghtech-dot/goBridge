<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->date('date')->index();
            $table->foreignId('ai_agent_id')->nullable()->constrained('ai_chatbots')->nullOnDelete();
            $table->string('channel', 32)->default('all')->index();
            $table->unsignedInteger('conversations')->default(0);
            $table->unsignedInteger('ai_messages')->default(0);
            $table->unsignedInteger('resolved')->default(0);
            $table->unsignedInteger('handoffs')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->unsignedInteger('avg_response_ms')->default(0);
            $table->unsignedInteger('positive_feedback')->default(0);
            $table->unsignedInteger('negative_feedback')->default(0);
            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->decimal('estimated_cost', 10, 4)->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'date', 'ai_agent_id', 'channel'], 'ai_daily_stats_unique');
        });

        Schema::create('ai_unknown_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_agent_id')->nullable()->constrained('ai_chatbots')->nullOnDelete();
            $table->string('question', 500);
            $table->unsignedInteger('occurrences')->default(1);
            $table->string('category_suggested', 64)->nullable();
            $table->string('status', 32)->default('pending'); // pending, resolved, dismissed
            $table->timestamp('last_asked_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'occurrences']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_unknown_questions');
        Schema::dropIfExists('ai_daily_stats');
    }
};
