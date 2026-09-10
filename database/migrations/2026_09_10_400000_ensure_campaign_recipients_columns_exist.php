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
        if (Schema::hasTable('campaigns')) {
            Schema::table('campaigns', function (Blueprint $table) {
                if (! Schema::hasColumn('campaigns', 'replied_count')) {
                    $table->unsignedInteger('replied_count')->default(0)->after('totals_json');
                }
            });
        }

        if (Schema::hasTable('campaign_recipients')) {
            Schema::table('campaign_recipients', function (Blueprint $table) {
                if (! Schema::hasColumn('campaign_recipients', 'replied_at')) {
                    $table->dateTime('replied_at')->nullable()->after('read_at');
                }
                if (! Schema::hasColumn('campaign_recipients', 'clicked_at')) {
                    $table->dateTime('clicked_at')->nullable()->after('replied_at');
                }
                if (! Schema::hasColumn('campaign_recipients', 'opted_out_at')) {
                    $table->dateTime('opted_out_at')->nullable()->after('clicked_at');
                }
                if (! Schema::hasColumn('campaign_recipients', 'failed_reason')) {
                    $table->string('failed_reason', 512)->nullable()->after('opted_out_at');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('campaign_recipients')) {
            Schema::table('campaign_recipients', function (Blueprint $table) {
                if (Schema::hasColumn('campaign_recipients', 'replied_at')) {
                    $table->dropColumn('replied_at');
                }
            });
        }

        if (Schema::hasTable('campaigns')) {
            Schema::table('campaigns', function (Blueprint $table) {
                if (Schema::hasColumn('campaigns', 'replied_count')) {
                    $table->dropColumn('replied_count');
                }
            });
        }
    }
};
