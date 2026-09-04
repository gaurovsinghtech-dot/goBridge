<?php

return [
    'gateways' => [
        // Razorpay — native Subscriptions API, webhook-driven renewals.
        'razorpay' => [
            'enabled' => env('BILLING_RAZORPAY_ENABLED', false),
            'key_id' => env('RAZORPAY_KEY_ID'),
            'key_secret' => env('RAZORPAY_KEY_SECRET'),
            'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
        ],
    ],
];
