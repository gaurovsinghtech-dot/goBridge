<?php

namespace Database\Seeders;

use App\Models\ProviderAccount;
use App\Models\ServicePrice;
use Illuminate\Database\Seeder;

class CentralizedBillingSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Service Prices & Markup Rules
        $prices = [
            [
                'service' => 'whatsapp_marketing',
                'provider' => 'meta',
                'unit' => 'conversation',
                'provider_cost_cents' => 78, // ₹0.78
                'customer_price_cents' => 105, // ₹1.05
                'currency' => 'INR',
            ],
            [
                'service' => 'whatsapp_utility',
                'provider' => 'meta',
                'unit' => 'conversation',
                'provider_cost_cents' => 35, // ₹0.35
                'customer_price_cents' => 50, // ₹0.50
                'currency' => 'INR',
            ],
            [
                'service' => 'whatsapp_service',
                'provider' => 'meta',
                'unit' => 'conversation',
                'provider_cost_cents' => 0,
                'customer_price_cents' => 15, // ₹0.15
                'currency' => 'INR',
            ],
            [
                'service' => 'ai_message',
                'provider' => 'openai',
                'unit' => 'message',
                'provider_cost_cents' => 10, // ₹0.10
                'customer_price_cents' => 25, // ₹0.25
                'currency' => 'INR',
            ],
            [
                'service' => 'ai_token_1k',
                'provider' => 'openai',
                'unit' => '1k_tokens',
                'provider_cost_cents' => 15, // ₹0.15
                'customer_price_cents' => 40, // ₹0.40
                'currency' => 'INR',
            ],
            [
                'service' => 'voice_inbound_minute',
                'provider' => 'twilio',
                'unit' => 'minute',
                'provider_cost_cents' => 85, // ₹0.85
                'customer_price_cents' => 125, // ₹1.25
                'currency' => 'INR',
            ],
            [
                'service' => 'voice_outbound_minute',
                'provider' => 'twilio',
                'unit' => 'minute',
                'provider_cost_cents' => 110, // ₹1.10
                'customer_price_cents' => 175, // ₹1.75
                'currency' => 'INR',
            ],
            [
                'service' => 'sms_domestic',
                'provider' => 'twilio',
                'unit' => 'message',
                'provider_cost_cents' => 18, // ₹0.18
                'customer_price_cents' => 30, // ₹0.30
                'currency' => 'INR',
            ],
            [
                'service' => 'phone_number_monthly',
                'provider' => 'twilio',
                'unit' => 'number',
                'provider_cost_cents' => 9500, // ₹95.00
                'customer_price_cents' => 15000, // ₹150.00
                'currency' => 'INR',
            ],
        ];

        foreach ($prices as $p) {
            ServicePrice::updateOrCreate(
                [
                    'service' => $p['service'],
                    'provider' => $p['provider'],
                    'currency' => $p['currency'],
                ],
                [
                    'unit' => $p['unit'],
                    'provider_cost_cents' => $p['provider_cost_cents'],
                    'customer_price_cents' => $p['customer_price_cents'],
                    'is_active' => true,
                ]
            );
        }

        // 2. Seed Provider Accounts for Monitoring
        $accounts = [
            [
                'provider' => 'meta',
                'name' => 'Meta WhatsApp Business Cloud',
                'balance_cents' => null,
                'status' => 'healthy',
                'monthly_spend_cents' => 11000000, // ₹1,10,000
                'currency' => 'INR',
            ],
            [
                'provider' => 'twilio',
                'name' => 'Twilio Voice & SMS Gateway',
                'balance_cents' => 1245000, // ₹12,450.00
                'status' => 'healthy',
                'threshold_alert_cents' => 300000, // ₹3,000.00
                'monthly_spend_cents' => 8500000, // ₹85,000
                'currency' => 'INR',
            ],
            [
                'provider' => 'openai',
                'name' => 'OpenAI API Platform (GPT-4o / Mini)',
                'balance_cents' => null,
                'status' => 'healthy',
                'monthly_spend_cents' => 6200000, // ₹62,000
                'currency' => 'INR',
            ],
            [
                'provider' => 'gemini',
                'name' => 'Google Gemini 1.5 Pro Platform',
                'balance_cents' => null,
                'status' => 'healthy',
                'monthly_spend_cents' => 1400000, // ₹14,000
                'currency' => 'INR',
            ],
            [
                'provider' => 'smtp',
                'name' => 'Platform Transactional Email Relay',
                'balance_cents' => null,
                'status' => 'healthy',
                'monthly_spend_cents' => 1200000, // ₹12,000
                'currency' => 'INR',
            ],
        ];

        foreach ($accounts as $acc) {
            ProviderAccount::updateOrCreate(
                ['provider' => $acc['provider']],
                [
                    'name' => $acc['name'],
                    'balance_cents' => $acc['balance_cents'],
                    'status' => $acc['status'],
                    'threshold_alert_cents' => $acc['threshold_alert_cents'] ?? null,
                    'monthly_spend_cents' => $acc['monthly_spend_cents'],
                    'currency' => $acc['currency'],
                    'last_sync_at' => now(),
                ]
            );
        }
    }
}
