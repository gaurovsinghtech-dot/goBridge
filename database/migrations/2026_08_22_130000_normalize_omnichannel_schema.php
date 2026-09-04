<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            if (! Schema::hasColumn('conversations', 'channel')) {
                $table->string('channel', 32)->nullable()->after('channel_account_id');
            }
            if (! Schema::hasColumn('conversations', 'assigned_ai_agent_id')) {
                $table->unsignedBigInteger('assigned_ai_agent_id')->nullable()->after('assigned_user_id');
            }
        });

        Schema::table('messages', function (Blueprint $table) {
            if (! Schema::hasColumn('messages', 'contact_id')) {
                $table->unsignedBigInteger('contact_id')->nullable()->after('conversation_id');
            }
            if (! Schema::hasColumn('messages', 'media_url')) {
                $table->string('media_url', 512)->nullable()->after('media_id');
            }
            if (! Schema::hasColumn('messages', 'external_message_id')) {
                $table->string('external_message_id', 255)->nullable()->after('provider_message_id');
            }
            if (! Schema::hasColumn('messages', 'sender_type')) {
                $table->string('sender_type', 32)->nullable()->after('sent_by');
            }
            if (! Schema::hasColumn('messages', 'message_type')) {
                $table->string('message_type', 64)->nullable()->after('type');
            }
            if (! Schema::hasColumn('messages', 'metadata')) {
                $table->json('metadata')->nullable()->after('payload');
            }
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            if (Schema::hasColumn('conversations', 'assigned_ai_agent_id')) {
                $table->dropColumn('assigned_ai_agent_id');
            }
            if (Schema::hasColumn('conversations', 'channel')) {
                $table->dropColumn('channel');
            }
        });

        Schema::table('messages', function (Blueprint $table) {
            $cols = array_filter(['contact_id', 'media_url', 'external_message_id', 'sender_type', 'message_type', 'metadata'], fn ($c) => Schema::hasColumn('messages', $c));
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
