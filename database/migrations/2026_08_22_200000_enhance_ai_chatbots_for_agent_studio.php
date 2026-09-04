<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_chatbots', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_chatbots', 'description')) {
                $table->text('description')->nullable()->after('purpose');
            }
            if (! Schema::hasColumn('ai_chatbots', 'response_style')) {
                $table->string('response_style', 32)->default('balanced')->after('response_mode');
            }
            if (! Schema::hasColumn('ai_chatbots', 'languages')) {
                $table->json('languages')->nullable()->after('language');
            }
            if (! Schema::hasColumn('ai_chatbots', 'emoji_style')) {
                $table->string('emoji_style', 32)->default('sometimes')->after('tone');
            }
            if (! Schema::hasColumn('ai_chatbots', 'response_delay_mode')) {
                $table->string('response_delay_mode', 32)->default('natural')->after('emoji_style');
            }
            if (! Schema::hasColumn('ai_chatbots', 'response_delay_seconds')) {
                $table->unsignedInteger('response_delay_seconds')->default(2)->after('response_delay_mode');
            }
            if (! Schema::hasColumn('ai_chatbots', 'objectives')) {
                $table->json('objectives')->nullable()->after('response_delay_seconds');
            }
            if (! Schema::hasColumn('ai_chatbots', 'guardrails')) {
                $table->json('guardrails')->nullable()->after('objectives');
            }
            if (! Schema::hasColumn('ai_chatbots', 'knowledge_source_ids')) {
                $table->json('knowledge_source_ids')->nullable()->after('ai_kb_id');
            }
            if (! Schema::hasColumn('ai_chatbots', 'business_hours_mode')) {
                $table->string('business_hours_mode', 32)->default('always')->after('guardrails');
            }
            if (! Schema::hasColumn('ai_chatbots', 'business_hours_schedule')) {
                $table->json('business_hours_schedule')->nullable()->after('business_hours_mode');
            }
            if (! Schema::hasColumn('ai_chatbots', 'outside_hours_action')) {
                $table->string('outside_hours_action', 32)->default('message_only')->after('business_hours_schedule');
            }
            if (! Schema::hasColumn('ai_chatbots', 'handoff_conditions')) {
                $table->json('handoff_conditions')->nullable()->after('human_handoff_message');
            }
            if (! Schema::hasColumn('ai_chatbots', 'handoff_target_type')) {
                $table->string('handoff_target_type', 32)->default('team')->after('handoff_conditions');
            }
            if (! Schema::hasColumn('ai_chatbots', 'handoff_target_team')) {
                $table->string('handoff_target_team', 64)->default('sales')->after('handoff_target_type');
            }
            if (! Schema::hasColumn('ai_chatbots', 'lead_qualification_fields')) {
                $table->json('lead_qualification_fields')->nullable()->after('qualification_rules');
            }
            if (! Schema::hasColumn('ai_chatbots', 'crm_actions')) {
                $table->json('crm_actions')->nullable()->after('lead_qualification_fields');
            }
            if (! Schema::hasColumn('ai_chatbots', 'crm_tag')) {
                $table->string('crm_tag', 128)->nullable()->after('crm_actions');
            }
            if (! Schema::hasColumn('ai_chatbots', 'crm_lead_score_boost')) {
                $table->integer('crm_lead_score_boost')->default(10)->after('crm_tag');
            }
            if (! Schema::hasColumn('ai_chatbots', 'voice_config')) {
                $table->json('voice_config')->nullable()->after('crm_lead_score_boost');
            }
            if (! Schema::hasColumn('ai_chatbots', 'version')) {
                $table->unsignedInteger('version')->default(1)->after('voice_config');
            }
            if (! Schema::hasColumn('ai_chatbots', 'published_version')) {
                $table->unsignedInteger('published_version')->default(1)->after('version');
            }
            if (! Schema::hasColumn('ai_chatbots', 'published_at')) {
                $table->dateTime('published_at')->nullable()->after('published_version');
            }
            if (! Schema::hasColumn('ai_chatbots', 'updated_by_user_id')) {
                $table->foreignId('updated_by_user_id')->nullable()->after('published_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_chatbots', function (Blueprint $table) {
            $table->dropColumn([
                'description',
                'response_style',
                'languages',
                'emoji_style',
                'response_delay_mode',
                'response_delay_seconds',
                'objectives',
                'guardrails',
                'knowledge_source_ids',
                'business_hours_mode',
                'business_hours_schedule',
                'outside_hours_action',
                'handoff_conditions',
                'handoff_target_type',
                'handoff_target_team',
                'lead_qualification_fields',
                'crm_actions',
                'crm_tag',
                'crm_lead_score_boost',
                'voice_config',
                'version',
                'published_version',
                'published_at',
                'updated_by_user_id',
            ]);
        });
    }
};
