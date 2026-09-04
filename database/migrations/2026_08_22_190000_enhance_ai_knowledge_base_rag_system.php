<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_kb_documents', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_kb_documents', 'assigned_agents')) {
                $table->json('assigned_agents')->nullable()->after('priority');
            }
            if (! Schema::hasColumn('ai_kb_documents', 'visibility')) {
                $table->string('visibility', 32)->default('public')->after('status');
            }
            if (! Schema::hasColumn('ai_kb_documents', 'error_message')) {
                $table->text('error_message')->nullable()->after('visibility');
            }
            if (! Schema::hasColumn('ai_kb_documents', 'file_size')) {
                $table->unsignedBigInteger('file_size')->nullable()->after('tokens');
            }
        });

        Schema::table('ai_knowledge_bases', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_knowledge_bases', 'answer_policy')) {
                $table->string('answer_policy', 32)->default('strict_kb_only')->after('status');
            }
            if (! Schema::hasColumn('ai_knowledge_bases', 'allow_citations')) {
                $table->boolean('allow_citations')->default(true)->after('answer_policy');
            }
            if (! Schema::hasColumn('ai_knowledge_bases', 'fallback_message')) {
                $table->text('fallback_message')->nullable()->after('allow_citations');
            }
            if (! Schema::hasColumn('ai_knowledge_bases', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
            if (! Schema::hasColumn('ai_knowledge_bases', 'category')) {
                $table->string('category', 64)->default('company')->after('name');
            }
            if (! Schema::hasColumn('ai_knowledge_bases', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('status');
            }
            if (! Schema::hasColumn('ai_knowledge_bases', 'version')) {
                $table->unsignedInteger('version')->default(1)->after('published_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_kb_documents', function (Blueprint $table) {
            $cols = array_filter([
                Schema::hasColumn('ai_kb_documents', 'assigned_agents') ? 'assigned_agents' : null,
                Schema::hasColumn('ai_kb_documents', 'visibility') ? 'visibility' : null,
                Schema::hasColumn('ai_kb_documents', 'error_message') ? 'error_message' : null,
                Schema::hasColumn('ai_kb_documents', 'file_size') ? 'file_size' : null,
            ]);
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('ai_knowledge_bases', function (Blueprint $table) {
            $cols = array_filter([
                Schema::hasColumn('ai_knowledge_bases', 'answer_policy') ? 'answer_policy' : null,
                Schema::hasColumn('ai_knowledge_bases', 'allow_citations') ? 'allow_citations' : null,
                Schema::hasColumn('ai_knowledge_bases', 'fallback_message') ? 'fallback_message' : null,
            ]);
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
