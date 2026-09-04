<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_connections')) {
            Schema::create('crm_connections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('provider', 64); // hubspot, salesforce, zoho, pipedrive, freshsales, dynamics, gohighlevel, custom, webhook
                $table->string('name')->default('Primary CRM');
                $table->string('auth_type', 32)->default('api_key'); // api_key, oauth, bearer, basic
                $table->text('credentials')->nullable(); // Encrypted JSON credentials
                $table->string('status', 32)->default('active'); // active, paused, error, not_configured
                $table->string('sync_direction', 32)->default('two_way'); // two_way, outbound_only, inbound_only
                $table->string('sync_mode', 32)->default('realtime'); // realtime, hourly, daily, manual
                $table->string('conflict_resolution', 32)->default('most_recent'); // most_recent, crm_wins, growbridge_wins
                $table->timestamp('last_sync_at')->nullable();
                $table->string('last_sync_status', 32)->nullable(); // success, failed
                $table->text('last_sync_message')->nullable();
                $table->json('settings_json')->nullable();
                $table->timestamps();

                $table->unique(['workspace_id', 'provider']);
                $table->index(['workspace_id', 'status']);
            });
        }

        if (! Schema::hasTable('crm_field_mappings')) {
            Schema::create('crm_field_mappings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('provider', 64)->default('all');
                $table->string('growbridge_field', 64); // phone, email, first_name, last_name, company, lead_source, lead_status, tags, notes, custom_fields
                $table->string('crm_field', 128); // e.g. mobilephone, firstname, company_name
                $table->string('direction', 32)->default('bidirectional'); // bidirectional, to_crm, from_crm
                $table->boolean('is_custom')->default(false);
                $table->timestamps();

                $table->index(['workspace_id', 'provider']);
            });
        }

        if (! Schema::hasTable('crm_sync_logs')) {
            Schema::create('crm_sync_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('provider', 64);
                $table->string('object_type', 64); // contact, lead, conversation, call, activity, note
                $table->string('action', 32); // create, update, pull, push, webhook
                $table->string('direction', 16)->default('outbound'); // inbound, outbound
                $table->string('status', 16)->default('success'); // success, failed, skipped
                $table->string('external_record_id', 255)->nullable();
                $table->string('internal_record_id', 255)->nullable();
                $table->text('error_message')->nullable();
                $table->json('payload_json')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['workspace_id', 'created_at']);
                $table->index(['workspace_id', 'provider', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_sync_logs');
        Schema::dropIfExists('crm_field_mappings');
        Schema::dropIfExists('crm_connections');
    }
};
