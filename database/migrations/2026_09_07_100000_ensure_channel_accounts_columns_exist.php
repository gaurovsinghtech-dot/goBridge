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
        if (Schema::hasTable('channel_accounts')) {
            Schema::table('channel_accounts', function (Blueprint $table) {
                if (! Schema::hasColumn('channel_accounts', 'provider')) {
                    $table->string('provider', 64)->nullable()->after('channel');
                }
                if (! Schema::hasColumn('channel_accounts', 'credentials')) {
                    $table->text('credentials')->nullable()->after('provider');
                }
                if (! Schema::hasColumn('channel_accounts', 'display_name')) {
                    $table->string('display_name', 128)->default('Channel Account')->after('credentials');
                }
                if (! Schema::hasColumn('channel_accounts', 'phone_number_id')) {
                    $table->string('phone_number_id', 64)->nullable()->after('display_name');
                }
                if (! Schema::hasColumn('channel_accounts', 'business_account_id')) {
                    $table->string('business_account_id', 64)->nullable()->after('phone_number_id');
                }
                if (! Schema::hasColumn('channel_accounts', 'status')) {
                    $table->string('status', 32)->default('inactive')->after('business_account_id');
                }
                if (! Schema::hasColumn('channel_accounts', 'meta_json')) {
                    $table->json('meta_json')->nullable()->after('status');
                }
            });
        } else {
            Schema::create('channel_accounts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workspace_id');
                $table->string('channel', 32);
                $table->string('provider', 64)->nullable();
                $table->text('credentials')->nullable();
                $table->string('display_name', 128)->default('Channel Account');
                $table->string('phone_number_id', 64)->nullable();
                $table->string('business_account_id', 64)->nullable();
                $table->string('status', 32)->default('inactive');
                $table->json('meta_json')->nullable();
                $table->timestamps();

                $table->index('workspace_id');
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
