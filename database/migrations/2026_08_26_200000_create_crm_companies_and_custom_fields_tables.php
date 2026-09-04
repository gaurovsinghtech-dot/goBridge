<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_companies')) {
            Schema::create('crm_companies', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workspace_id')->index();
                $table->string('name', 191);
                $table->unsignedBigInteger('owner_user_id')->nullable()->index();
                $table->string('industry', 128)->nullable();
                $table->string('website', 255)->nullable();
                $table->string('phone', 64)->nullable();
                $table->string('email', 191)->nullable();
                $table->string('address', 255)->nullable();
                $table->string('city', 128)->nullable();
                $table->string('country', 64)->nullable();
                $table->json('custom_fields')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['workspace_id', 'name']);
            });
        }

        if (! Schema::hasTable('crm_custom_fields')) {
            Schema::create('crm_custom_fields', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workspace_id')->index();
                $table->string('entity_type', 32); // lead, contact, company, deal
                $table->string('name', 128);
                $table->string('key', 64);
                $table->string('type', 32); // text, number, date, dropdown, multi-select, boolean, currency
                $table->json('options')->nullable();
                $table->boolean('is_required')->default(false);
                $table->string('default_value', 255)->nullable();
                $table->unsignedInteger('order_position')->default(0);
                $table->timestamps();

                $table->unique(['workspace_id', 'entity_type', 'key'], 'crm_cf_ws_entity_key_uniq');
            });
        }

        Schema::table('contacts', function (Blueprint $table) {
            if (! Schema::hasColumn('contacts', 'company_id')) {
                $table->unsignedBigInteger('company_id')->nullable()->after('company')->index();
            }
        });

        Schema::table('crm_deals', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_deals', 'company_id')) {
                $table->unsignedBigInteger('company_id')->nullable()->after('contact_id')->index();
            }
            if (! Schema::hasColumn('crm_deals', 'notes')) {
                $table->text('notes')->nullable()->after('loss_reason');
            }
            if (! Schema::hasColumn('crm_deals', 'custom_fields')) {
                $table->json('custom_fields')->nullable()->after('notes');
            }
        });

        Schema::table('crm_tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_tasks', 'deal_id')) {
                $table->unsignedBigInteger('deal_id')->nullable()->after('lead_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_custom_fields');
        Schema::dropIfExists('crm_companies');
    }
};
