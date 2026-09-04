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
        // 1. Wallets table (Customer Balance)
        if (! Schema::hasTable('wallets')) {
            Schema::create('wallets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->unique()->constrained('workspaces')->cascadeOnDelete();
                $table->bigInteger('balance_cents')->default(0); // in smallest unit (paise/cents)
                $table->string('currency', 10)->default('INR');
                $table->unsignedBigInteger('low_balance_threshold_cents')->default(50000); // default ₹500
                $table->boolean('low_balance_alert_enabled')->default(true);
                $table->boolean('auto_recharge_enabled')->default(false);
                $table->unsignedBigInteger('auto_recharge_amount_cents')->default(200000); // default ₹2,000
                $table->string('status', 32)->default('active'); // active, frozen, suspended
                $table->timestamps();

                $table->index(['workspace_id', 'status']);
            });
        }

        // 2. Wallet Transactions (Ledger for Deposits, Deductions, Refunds)
        if (! Schema::hasTable('wallet_transactions')) {
            Schema::create('wallet_transactions', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();
                $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $table->string('type', 32); // credit, debit, refund, adjustment
                $table->string('category', 64); // topup, whatsapp_usage, ai_usage, voice_usage, sms_usage, phone_number, refund, adjustment
                $table->unsignedBigInteger('amount_cents');
                $table->bigInteger('balance_after_cents');
                $table->string('currency', 10)->default('INR');
                $table->string('description');
                $table->string('reference_type', 64)->nullable(); // invoice, usage_record, campaign, payment
                $table->string('reference_id', 128)->nullable();
                $table->json('metadata')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['workspace_id', 'created_at']);
                $table->index(['wallet_id', 'type']);
            });
        }

        // 3. Service Prices & Markup Rules
        if (! Schema::hasTable('service_prices')) {
            Schema::create('service_prices', function (Blueprint $table) {
                $table->id();
                $table->string('service', 64); // whatsapp_marketing, whatsapp_utility, ai_token, voice_minute, sms_message, phone_number_monthly
                $table->string('provider', 64); // meta, openai, gemini, claude, twilio, smtp
                $table->string('unit', 32); // message, token, 1k_tokens, minute, month
                $table->unsignedBigInteger('provider_cost_cents')->default(0); // Cost Growbridge pays to provider (in paise/cents)
                $table->unsignedBigInteger('customer_price_cents')->default(0); // Price customer pays Growbridge
                $table->string('currency', 10)->default('INR');
                $table->boolean('is_active')->default(true);
                $table->json('tier_rules')->nullable();
                $table->timestamps();

                $table->unique(['service', 'provider', 'currency'], 'idx_service_provider_curr');
            });
        }

        // 4. Detailed Metered Usage Records (Provider Cost vs Customer Charge Margin Ledger)
        if (! Schema::hasTable('usage_records')) {
            Schema::create('usage_records', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $table->string('service', 64); // whatsapp, ai, voice, sms, phone_number, email
                $table->string('provider', 64); // meta, openai, gemini, claude, twilio, smtp
                $table->string('connection_model', 32)->default('growbridge_managed'); // growbridge_managed, customer_connected
                $table->decimal('quantity', 14, 4)->default(1.0);
                $table->string('unit', 32)->default('messages'); // messages, tokens, minutes, numbers
                $table->unsignedBigInteger('provider_cost_cents')->default(0);
                $table->unsignedBigInteger('customer_charge_cents')->default(0);
                $table->bigInteger('gross_margin_cents')->default(0); // customer_charge - provider_cost
                $table->string('currency', 10)->default('INR');
                $table->boolean('is_billed')->default(true);
                $table->foreignId('wallet_transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();
                $table->json('metadata')->nullable();
                $table->timestamp('recorded_at');
                $table->timestamps();

                $table->index(['workspace_id', 'service', 'recorded_at']);
                $table->index(['provider', 'recorded_at']);
            });
        }

        // 5. Admin Provider Accounts (Health & Balance Tracking)
        if (! Schema::hasTable('provider_accounts')) {
            Schema::create('provider_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('provider', 64)->unique(); // meta, twilio, openai, gemini, claude, smtp
                $table->string('name', 128);
                $table->bigInteger('balance_cents')->nullable(); // provider balance if applicable
                $table->string('currency', 10)->default('INR');
                $table->string('status', 32)->default('healthy'); // healthy, low_balance, critical, error
                $table->bigInteger('threshold_alert_cents')->nullable();
                $table->unsignedBigInteger('monthly_spend_cents')->default(0);
                $table->timestamp('last_sync_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_accounts');
        Schema::dropIfExists('usage_records');
        Schema::dropIfExists('service_prices');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallets');
    }
};
