<?php

namespace App\Modules\Broadcasting\Services;

class CampaignFailureFormatter
{
    /**
     * Parse raw provider errors and return a human-readable, actionable failure explanation.
     */
    public static function format(string $errorMessage): string
    {
        $err = strtolower($errorMessage);

        // 1. Meta Prepaid Balance & Rate Limit Issues
        if (
            str_contains($err, '130429') ||
            str_contains($err, '130428') ||
            str_contains($err, '131048') ||
            str_contains($err, '131056') ||
            str_contains($err, 'payment') ||
            str_contains($err, 'balance') ||
            str_contains($err, 'fund') ||
            str_contains($err, 'rate limit')
        ) {
            return 'Insufficient Meta WABA Balance / Rate Limit (Add funds in Meta Billing & Payments)';
        }

        // 2. Undeliverable / Number Not on WhatsApp
        if (
            str_contains($err, '131026') ||
            str_contains($err, '131042') ||
            str_contains($err, '131009') ||
            str_contains($err, 'not registered') ||
            str_contains($err, 'undeliverable') ||
            str_contains($err, 'invalid phone')
        ) {
            return 'Message Undeliverable (Number is invalid or not registered on WhatsApp)';
        }

        // 3. Ecosystem Health Protection
        if (str_contains($err, '131049') || str_contains($err, 'ecosystem')) {
            return 'Meta Ecosystem Health Protection (User reached daily marketing quota, retry after 24h)';
        }

        // 4. Session / Window Expiry
        if (str_contains($err, '131047') || str_contains($err, 're-engagement') || str_contains($err, '24 hour')) {
            return '24-Hour Session Expired (Outbound chat requires an approved WhatsApp template)';
        }

        // 5. Template Errors
        if (str_contains($err, '132001') || str_contains($err, 'template does not exist')) {
            return 'Template Not Found (Template is missing or not approved in WhatsApp Manager)';
        }

        if (str_contains($err, '132000') || str_contains($err, 'parameter count')) {
            return 'Template Variable Mismatch (Missing recipient contact variable)';
        }

        // 6. Media / Header Upload Errors
        if (str_contains($err, '131053') || str_contains($err, 'media upload')) {
            return 'Header Media Failed (Meta could not download header image/video URL)';
        }

        if (str_contains($err, '131051')) {
            return 'Unsupported Media Format for WhatsApp';
        }

        // 7. Opt-Out
        if (str_contains($err, 'opted_out') || str_contains($err, 'opted out') || str_contains($err, 'unsubscribed')) {
            return 'Contact Opted Out of WhatsApp Broadcasts';
        }

        // Fallback: Clean up raw message
        $cleaned = trim(preg_replace('/^(WhatsApp send failed|SMS send failed|Email send failed):\s*/i', '', $errorMessage));

        return substr($cleaned, 0, 255);
    }
}
