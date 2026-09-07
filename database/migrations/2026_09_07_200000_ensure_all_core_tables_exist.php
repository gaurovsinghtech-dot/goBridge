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
        // 1. contact_tags
        if (! Schema::hasTable('contact_tags')) {
            Schema::create('contact_tags', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workspace_id');
                $table->string('name', 64);
                $table->string('color', 16)->default('#6366f1');
                $table->timestamps();

                $table->unique(['workspace_id', 'name']);
            });
        }

        // 2. contact_tag_pivot
        if (! Schema::hasTable('contact_tag_pivot')) {
            Schema::create('contact_tag_pivot', function (Blueprint $table) {
                $table->unsignedBigInteger('contact_id');
                $table->unsignedBigInteger('tag_id');
                $table->primary(['contact_id', 'tag_id']);
            });
        }

        // 3. segments
        if (! Schema::hasTable('segments')) {
            Schema::create('segments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workspace_id');
                $table->string('name', 128);
                $table->enum('type', ['static', 'dynamic'])->default('static');
                $table->json('rules_json')->nullable();
                $table->unsignedInteger('contact_count')->default(0);
                $table->timestamps();

                $table->index('workspace_id');
            });
        }

        // 4. segment_contact
        if (! Schema::hasTable('segment_contact')) {
            Schema::create('segment_contact', function (Blueprint $table) {
                $table->unsignedBigInteger('segment_id');
                $table->unsignedBigInteger('contact_id');
                $table->primary(['segment_id', 'contact_id']);
            });
        }

        // 5. inbox_labels
        if (! Schema::hasTable('inbox_labels')) {
            Schema::create('inbox_labels', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workspace_id');
                $table->string('name', 64);
                $table->string('color', 16)->default('#6366f1');
                $table->timestamps();

                $table->unique(['workspace_id', 'name']);
            });
        }

        // 6. inbox_label_conversation
        if (! Schema::hasTable('inbox_label_conversation')) {
            Schema::create('inbox_label_conversation', function (Blueprint $table) {
                $table->unsignedBigInteger('label_id');
                $table->unsignedBigInteger('conversation_id');
                $table->primary(['label_id', 'conversation_id']);
            });
        }

        // 7. inbox_canned_replies
        if (! Schema::hasTable('inbox_canned_replies')) {
            Schema::create('inbox_canned_replies', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workspace_id');
                $table->string('shortcut', 64);
                $table->text('body');
                $table->timestamps();

                $table->unique(['workspace_id', 'shortcut']);
            });
        }

        // 8. inbox_notes
        if (! Schema::hasTable('inbox_notes')) {
            Schema::create('inbox_notes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('conversation_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->text('body');
                $table->timestamps();

                $table->index('conversation_id');
            });
        }

        // 9. inbox_assignments
        if (! Schema::hasTable('inbox_assignments')) {
            Schema::create('inbox_assignments', function (Blueprint $table) {
                $table->unsignedBigInteger('conversation_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamp('assigned_at');
                $table->unsignedBigInteger('assigned_by')->nullable();

                $table->primary(['conversation_id', 'user_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Safe no-op
    }
};
