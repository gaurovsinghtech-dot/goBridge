<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('workspaces')) {
            Schema::table('workspaces', function (Blueprint $table) {
                if (! Schema::hasColumn('workspaces', 'service_type')) {
                    $table->string('service_type', 32)->default('whatsapp_only')->after('currency_code')->index();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('workspaces')) {
            Schema::table('workspaces', function (Blueprint $table) {
                if (Schema::hasColumn('workspaces', 'service_type')) {
                    $table->dropColumn('service_type');
                }
            });
        }
    }
};
