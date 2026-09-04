<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('campaigns')) {
            Schema::table('campaigns', function (Blueprint $table) {
                if (! Schema::hasColumn('campaigns', 'channel_account_id')) {
                    $table->unsignedBigInteger('channel_account_id')->nullable()->after('whatsapp_phone_number_id');
                }
                if (! Schema::hasColumn('campaigns', 'quiet_hours_enabled')) {
                    $table->boolean('quiet_hours_enabled')->default(true)->after('timezone');
                }
                if (! Schema::hasColumn('campaigns', 'quiet_hours_start')) {
                    $table->string('quiet_hours_start', 8)->default('09:00')->after('quiet_hours_enabled');
                }
                if (! Schema::hasColumn('campaigns', 'quiet_hours_end')) {
                    $table->string('quiet_hours_end', 8)->default('20:00')->after('quiet_hours_start');
                }
                if (! Schema::hasColumn('campaigns', 'frequency_cap_days')) {
                    $table->unsignedSmallInteger('frequency_cap_days')->default(7)->after('quiet_hours_end');
                }
                if (! Schema::hasColumn('campaigns', 'frequency_cap_max')) {
                    $table->unsignedSmallInteger('frequency_cap_max')->default(3)->after('frequency_cap_days');
                }
                if (! Schema::hasColumn('campaigns', 'requires_approval')) {
                    $table->boolean('requires_approval')->default(false)->after('frequency_cap_max');
                }
                if (! Schema::hasColumn('campaigns', 'approved_by')) {
                    $table->unsignedBigInteger('approved_by')->nullable()->after('requires_approval');
                }
                if (! Schema::hasColumn('campaigns', 'approved_at')) {
                    $table->dateTime('approved_at')->nullable()->after('approved_by');
                }
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
            });
        }
    }

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
                $columns = [
                    'channel_account_id', 'quiet_hours_enabled', 'quiet_hours_start',
                    'quiet_hours_end', 'frequency_cap_days', 'frequency_cap_max',
                    'requires_approval', 'approved_by', 'approved_at', 'replied_count',
                ];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('campaigns', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
