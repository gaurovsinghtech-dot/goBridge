<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_pipelines')) {
            Schema::create('crm_pipelines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workspace_id')->index();
                $table->string('name', 191);
                $table->boolean('is_default')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('crm_pipeline_stages')) {
            Schema::create('crm_pipeline_stages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workspace_id')->index();
                $table->unsignedBigInteger('pipeline_id')->index();
                $table->string('name', 128);
                $table->string('color', 32)->default('blue');
                $table->unsignedTinyInteger('probability')->default(0);
                $table->unsignedInteger('position')->default(0);
                $table->boolean('is_won')->default(false);
                $table->boolean('is_lost')->default(false);
                $table->timestamps();

                $table->index(['workspace_id', 'pipeline_id', 'position'], 'crm_stage_pos_idx');
            });
        }

        if (! Schema::hasTable('crm_deals')) {
            Schema::create('crm_deals', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workspace_id')->index();
                $table->unsignedBigInteger('contact_id')->nullable()->index();
                $table->unsignedBigInteger('lead_id')->nullable()->index();
                $table->unsignedBigInteger('pipeline_id')->nullable()->index();
                $table->unsignedBigInteger('stage_id')->nullable()->index();
                $table->unsignedBigInteger('assigned_user_id')->nullable()->index();
                $table->string('name', 191);
                $table->decimal('value', 14, 2)->default(0);
                $table->string('currency', 10)->default('INR');
                $table->unsignedTinyInteger('probability')->default(0);
                $table->date('expected_close_date')->nullable();
                $table->string('status', 32)->default('open'); // open, won, lost
                $table->string('loss_reason', 255)->nullable();
                $table->timestamps();

                $table->index(['workspace_id', 'status']);
            });
        }

        if (! Schema::hasTable('crm_tasks')) {
            Schema::create('crm_tasks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workspace_id')->index();
                $table->unsignedBigInteger('contact_id')->nullable()->index();
                $table->unsignedBigInteger('lead_id')->nullable()->index();
                $table->unsignedBigInteger('assigned_user_id')->nullable()->index();
                $table->unsignedBigInteger('created_by_id')->nullable()->index();
                $table->string('title', 255);
                $table->text('description')->nullable();
                $table->dateTime('due_at')->nullable()->index();
                $table->string('priority', 32)->default('medium'); // low, medium, high, urgent
                $table->string('status', 32)->default('pending'); // pending, in_progress, completed, cancelled
                $table->timestamps();

                $table->index(['workspace_id', 'status', 'due_at']);
            });
        }

        if (! Schema::hasTable('crm_notes')) {
            Schema::create('crm_notes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workspace_id')->index();
                $table->unsignedBigInteger('contact_id')->nullable()->index();
                $table->unsignedBigInteger('lead_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->text('content');
                $table->boolean('is_private')->default(true);
                $table->json('mentions')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('crm_teams')) {
            Schema::create('crm_teams', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workspace_id')->index();
                $table->string('name', 191);
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('crm_team_user')) {
            Schema::create('crm_team_user', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('team_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('role', 32)->default('member'); // member, lead
                $table->timestamps();

                $table->unique(['team_id', 'user_id']);
            });
        }

        Schema::table('contacts', function (Blueprint $table) {
            if (! Schema::hasColumn('contacts', 'company')) {
                $table->string('company', 191)->nullable()->after('last_name');
            }
            if (! Schema::hasColumn('contacts', 'deal_value')) {
                $table->decimal('deal_value', 14, 2)->default(0)->after('company');
            }
            if (! Schema::hasColumn('contacts', 'pipeline_id')) {
                $table->unsignedBigInteger('pipeline_id')->nullable()->after('deal_value');
            }
            if (! Schema::hasColumn('contacts', 'stage_id')) {
                $table->unsignedBigInteger('stage_id')->nullable()->after('pipeline_id');
            }
            if (! Schema::hasColumn('contacts', 'assigned_user_id')) {
                $table->unsignedBigInteger('assigned_user_id')->nullable()->after('stage_id');
            }
            if (! Schema::hasColumn('contacts', 'assigned_team_id')) {
                $table->unsignedBigInteger('assigned_team_id')->nullable()->after('assigned_user_id');
            }
            if (! Schema::hasColumn('contacts', 'loss_reason')) {
                $table->string('loss_reason', 255)->nullable()->after('assigned_team_id');
            }
            if (! Schema::hasColumn('contacts', 'next_follow_up_at')) {
                $table->dateTime('next_follow_up_at')->nullable()->after('loss_reason');
            }
            if (! Schema::hasColumn('contacts', 'priority')) {
                $table->string('priority', 32)->default('medium')->after('next_follow_up_at');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_team_user');
        Schema::dropIfExists('crm_teams');
        Schema::dropIfExists('crm_notes');
        Schema::dropIfExists('crm_tasks');
        Schema::dropIfExists('crm_deals');
        Schema::dropIfExists('crm_pipeline_stages');
        Schema::dropIfExists('crm_pipelines');
    }
};
