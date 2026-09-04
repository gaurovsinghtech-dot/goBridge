<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add journey & scoring columns to contacts
        Schema::table('contacts', function (Blueprint $table) {
            if (Schema::hasTable('contacts')) {
                if (! Schema::hasColumn('contacts', 'lead_score')) {
                    $table->unsignedSmallInteger('lead_score')->default(10)->after('source');
                }
                if (! Schema::hasColumn('contacts', 'lead_score_band')) {
                    $table->string('lead_score_band', 16)->default('cold')->after('lead_score');
                }
                if (! Schema::hasColumn('contacts', 'lead_intent')) {
                    $table->string('lead_intent', 64)->nullable()->after('lead_score_band');
                }
                if (! Schema::hasColumn('contacts', 'marketing_opt_out')) {
                    $table->boolean('marketing_opt_out')->default(false)->after('opt_in_email');
                }
                if (! Schema::hasColumn('contacts', 'opt_out_channel')) {
                    $table->string('opt_out_channel', 32)->nullable()->after('marketing_opt_out');
                }
                if (! Schema::hasColumn('contacts', 'opt_out_at')) {
                    $table->timestamp('opt_out_at')->nullable()->after('opt_out_channel');
                }
                if (! Schema::hasColumn('contacts', 'duplicate_of_id')) {
                    $table->unsignedBigInteger('duplicate_of_id')->nullable()->after('lead_id');
                }
                if (! Schema::hasColumn('contacts', 'external_ids')) {
                    $table->json('external_ids')->nullable()->after('custom_fields');
                }
            }
        });

        // Create unified customer timeline table
        if (! Schema::hasTable('contact_timeline_events')) {
            Schema::create('contact_timeline_events', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('workspace_id');
                $table->unsignedBigInteger('contact_id');
                $table->string('channel', 32)->default('system'); // whatsapp, messenger, instagram, email, sms, phone, system
                $table->string('event_type', 64); // message_in, message_out, ai_reply, call_completed, lead_created, human_handoff, follow_up_sent, opt_out
                $table->string('title', 191);
                $table->text('description')->nullable();
                $table->json('metadata_json')->nullable();
                $table->timestamp('occurred_at')->useCurrent();
                $table->timestamps();

                $table->index('workspace_id');
                $table->index(['contact_id', 'occurred_at']);
                $table->index('event_type');
            });
        }

        // Create automation execution safety controls table
        if (! Schema::hasTable('automation_safety_controls')) {
            Schema::create('automation_safety_controls', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workspace_id');
                $table->unsignedBigInteger('contact_id');
                $table->string('action_type', 32); // automation_run, ai_reply, voice_call
                $table->unsignedInteger('execution_count')->default(1);
                $table->timestamp('window_start_at');
                $table->timestamps();

                $table->index(['workspace_id', 'contact_id', 'action_type', 'window_start_at'], 'idx_auto_safety_win');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_safety_controls');
        Schema::dropIfExists('contact_timeline_events');

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn([
                'lead_score',
                'lead_score_band',
                'lead_intent',
                'marketing_opt_out',
                'opt_out_channel',
                'opt_out_at',
                'duplicate_of_id',
                'external_ids',
            ]);
        });
    }
};
