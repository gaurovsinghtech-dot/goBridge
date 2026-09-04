<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add workspace and period columns to subscriptions if missing
        Schema::table('subscriptions', function (Blueprint $table) {
            if (Schema::hasTable('subscriptions')) {
                if (! Schema::hasColumn('subscriptions', 'workspace_id')) {
                    $table->unsignedBigInteger('workspace_id')->nullable()->after('user_id')->index();
                }
                if (! Schema::hasColumn('subscriptions', 'current_period_start')) {
                    $table->timestamp('current_period_start')->nullable()->after('ends_at');
                }
                if (! Schema::hasColumn('subscriptions', 'current_period_end')) {
                    $table->timestamp('current_period_end')->nullable()->after('current_period_start');
                }
                if (! Schema::hasColumn('subscriptions', 'cancelled_at')) {
                    $table->timestamp('cancelled_at')->nullable()->after('current_period_end');
                }
            }
        });

        // 2. Create monthly workspace usage table
        if (! Schema::hasTable('workspace_usages')) {
            Schema::create('workspace_usages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workspace_id');
                $table->date('period_month'); // e.g. 2026-08-01
                $table->unsignedBigInteger('contacts_count')->default(0);
                $table->unsignedBigInteger('messages_count')->default(0);
                $table->unsignedBigInteger('ai_requests_count')->default(0);
                $table->unsignedBigInteger('ai_tokens_count')->default(0);
                $table->unsignedBigInteger('voice_calls_count')->default(0);
                $table->unsignedBigInteger('voice_minutes_count')->default(0);
                $table->unsignedBigInteger('automation_executions_count')->default(0);
                $table->unsignedBigInteger('campaigns_count')->default(0);
                $table->unsignedBigInteger('api_requests_count')->default(0);
                $table->timestamps();

                $table->unique(['workspace_id', 'period_month'], 'idx_ws_usage_period');
            });
        }

        // 3. Create invoices table
        if (! Schema::hasTable('invoices')) {
            Schema::create('invoices', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('workspace_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('subscription_id')->nullable();
                $table->unsignedBigInteger('plan_id')->nullable();
                $table->string('invoice_number', 64)->unique();
                $table->unsignedBigInteger('amount_cents');
                $table->unsignedBigInteger('tax_cents')->default(0);
                $table->unsignedBigInteger('total_cents');
                $table->string('currency_code', 10)->default('INR');
                $table->string('status', 32)->default('paid'); // paid, pending, failed, refunded
                $table->string('payment_method', 32)->default('razorpay');
                $table->string('gateway_payment_id', 128)->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();

                $table->index(['workspace_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('workspace_usages');

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'workspace_id',
                'current_period_start',
                'current_period_end',
                'cancelled_at',
            ]);
        });
    }
};
