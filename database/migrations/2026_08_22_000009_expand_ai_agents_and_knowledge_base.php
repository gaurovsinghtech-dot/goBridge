<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_chatbots', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_chatbots', 'purpose')) {
                $table->string('purpose', 256)->nullable()->after('name');
            }
            if (! Schema::hasColumn('ai_chatbots', 'agent_type')) {
                $table->string('agent_type', 64)->default('general')->after('purpose');
            }
            if (! Schema::hasColumn('ai_chatbots', 'language')) {
                $table->string('language', 32)->default('auto')->after('agent_type');
            }
            if (! Schema::hasColumn('ai_chatbots', 'provider')) {
                $table->string('provider', 32)->default('openai')->after('language');
            }
            if (! Schema::hasColumn('ai_chatbots', 'model')) {
                $table->string('model', 64)->default('gpt-4o-mini')->after('provider');
            }
            if (! Schema::hasColumn('ai_chatbots', 'temperature')) {
                $table->decimal('temperature', 3, 2)->default(0.70)->after('model');
            }
            if (! Schema::hasColumn('ai_chatbots', 'max_tokens')) {
                $table->unsignedInteger('max_tokens')->default(512)->after('temperature');
            }
            if (! Schema::hasColumn('ai_chatbots', 'status')) {
                $table->string('status', 32)->default('active')->after('max_tokens');
            }
            if (! Schema::hasColumn('ai_chatbots', 'response_mode')) {
                $table->string('response_mode', 32)->default('auto_reply')->after('status');
            }
            if (! Schema::hasColumn('ai_chatbots', 'confidence_threshold')) {
                $table->unsignedTinyInteger('confidence_threshold')->default(70)->after('response_mode');
            }
            if (! Schema::hasColumn('ai_chatbots', 'strict_knowledge_mode')) {
                $table->boolean('strict_knowledge_mode')->default(false)->after('confidence_threshold');
            }
            if (! Schema::hasColumn('ai_chatbots', 'memory_mode')) {
                $table->string('memory_mode', 64)->default('conversation_only')->after('strict_knowledge_mode');
            }
            if (! Schema::hasColumn('ai_chatbots', 'human_handoff_enabled')) {
                $table->boolean('human_handoff_enabled')->default(true)->after('memory_mode');
            }
            if (! Schema::hasColumn('ai_chatbots', 'human_handoff_user_id')) {
                $table->unsignedBigInteger('human_handoff_user_id')->nullable()->after('human_handoff_enabled');
            }
            if (! Schema::hasColumn('ai_chatbots', 'human_handoff_message')) {
                $table->string('human_handoff_message', 256)->nullable()->after('human_handoff_user_id');
            }
            if (! Schema::hasColumn('ai_chatbots', 'qualification_rules')) {
                $table->json('qualification_rules')->nullable()->after('human_handoff_message');
            }
            if (! Schema::hasColumn('ai_chatbots', 'tools_enabled')) {
                $table->json('tools_enabled')->nullable()->after('qualification_rules');
            }
            if (! Schema::hasColumn('ai_chatbots', 'total_conversations')) {
                $table->unsignedInteger('total_conversations')->default(0)->after('tools_enabled');
            }
            if (! Schema::hasColumn('ai_chatbots', 'total_resolutions')) {
                $table->unsignedInteger('total_resolutions')->default(0)->after('total_conversations');
            }
            if (! Schema::hasColumn('ai_chatbots', 'total_handoffs')) {
                $table->unsignedInteger('total_handoffs')->default(0)->after('total_resolutions');
            }
            if (! Schema::hasColumn('ai_chatbots', 'last_active_at')) {
                $table->timestamp('last_active_at')->nullable()->after('total_handoffs');
            }
        });

        Schema::table('ai_knowledge_bases', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_knowledge_bases', 'category')) {
                $table->string('category', 64)->default('company')->after('name');
            }
            if (! Schema::hasColumn('ai_knowledge_bases', 'description')) {
                $table->text('description')->nullable()->after('category');
            }
            if (! Schema::hasColumn('ai_knowledge_bases', 'version')) {
                $table->unsignedInteger('version')->default(1)->after('description');
            }
            if (! Schema::hasColumn('ai_knowledge_bases', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('version');
            }
        });

        Schema::table('ai_kb_documents', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_kb_documents', 'category')) {
                $table->string('category', 64)->default('general')->after('title');
            }
            if (! Schema::hasColumn('ai_kb_documents', 'priority')) {
                $table->unsignedTinyInteger('priority')->default(5)->after('category');
            }
            if (! Schema::hasColumn('ai_kb_documents', 'meta')) {
                $table->json('meta')->nullable()->after('priority');
            }
            if (! Schema::hasColumn('ai_kb_documents', 'version')) {
                $table->unsignedInteger('version')->default(1)->after('meta');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_chatbots', function (Blueprint $table) {
            $table->dropColumn([
                'purpose',
                'agent_type',
                'language',
                'provider',
                'model',
                'temperature',
                'max_tokens',
                'status',
                'response_mode',
                'confidence_threshold',
                'strict_knowledge_mode',
                'memory_mode',
                'human_handoff_enabled',
                'human_handoff_user_id',
                'human_handoff_message',
                'qualification_rules',
                'tools_enabled',
                'total_conversations',
                'total_resolutions',
                'total_handoffs',
                'last_active_at',
            ]);
        });

        Schema::table('ai_knowledge_bases', function (Blueprint $table) {
            $table->dropColumn(['category', 'description', 'version', 'published_at']);
        });

        Schema::table('ai_kb_documents', function (Blueprint $table) {
            $table->dropColumn(['category', 'priority', 'meta', 'version']);
        });
    }
};
