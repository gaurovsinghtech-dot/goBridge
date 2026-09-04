<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            if (Schema::hasTable('conversations') && ! $this->hasIndex('conversations', 'idx_conv_ws_status_last_msg')) {
                $table->index(['workspace_id', 'status', 'last_message_at'], 'idx_conv_ws_status_last_msg');
            }
        });

        Schema::table('contacts', function (Blueprint $table) {
            if (Schema::hasTable('contacts')) {
                if (! $this->hasIndex('contacts', 'idx_contacts_ws_email')) {
                    $table->index(['workspace_id', 'email'], 'idx_contacts_ws_email');
                }
                if (! $this->hasIndex('contacts', 'idx_contacts_ws_created')) {
                    $table->index(['workspace_id', 'created_at'], 'idx_contacts_ws_created');
                }
            }
        });

        if (Schema::hasTable('campaign_recipients') && ! $this->hasIndex('campaign_recipients', 'idx_campaign_recipients_camp_status')) {
            Schema::table('campaign_recipients', function (Blueprint $table) {
                $table->index(['campaign_id', 'status'], 'idx_campaign_recipients_camp_status');
            });
        }
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            if (Schema::hasTable('conversations') && $this->hasIndex('conversations', 'idx_conv_ws_status_last_msg')) {
                $table->dropIndex('idx_conv_ws_status_last_msg');
            }
        });

        Schema::table('contacts', function (Blueprint $table) {
            if (Schema::hasTable('contacts')) {
                if ($this->hasIndex('contacts', 'idx_contacts_ws_email')) {
                    $table->dropIndex('idx_contacts_ws_email');
                }
                if ($this->hasIndex('contacts', 'idx_contacts_ws_created')) {
                    $table->dropIndex('idx_contacts_ws_created');
                }
            }
        });

        if (Schema::hasTable('campaign_recipients') && $this->hasIndex('campaign_recipients', 'idx_campaign_recipients_camp_status')) {
            Schema::table('campaign_recipients', function (Blueprint $table) {
                $table->dropIndex('idx_campaign_recipients_camp_status');
            });
        }
    }

    private function hasIndex(string $table, string $name): bool
    {
        try {
            $conn = Schema::getConnection();
            $dbDriver = $conn->getDriverName();
            if ($dbDriver === 'sqlite') {
                $indexes = $conn->select("PRAGMA index_list('{$table}')");
                return collect($indexes)->contains('name', $name);
            }
            $indexes = $conn->select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$name]);
            return ! empty($indexes);
        } catch (\Throwable) {
            return false;
        }
    }
};
