<?php

namespace App\Services\Campaigns;

use Illuminate\Support\Str;

class CampaignAiAssistantService
{
    /**
     * Generate structured campaign suggestions based on a user prompt and channel.
     *
     * @return array{
     *     objective: string,
     *     suggested_name: string,
     *     subject: string,
     *     message_body: string,
     *     call_to_action: string,
     *     audience_suggestion: string,
     *     recommended_timing: string
     * }
     */
    public function generateCampaignCopy(
        string $prompt,
        string $channel = 'whatsapp',
        string $language = 'en',
        ?string $objective = null
    ): array {
        $promptLower = strtolower($prompt);

        $isDiscount = str_contains($promptLower, 'discount') || str_contains($promptLower, 'offer') || str_contains($promptLower, 'sale') || str_contains($promptLower, 'promo');
        $isReEngagement = str_contains($promptLower, 'inactive') || str_contains($promptLower, '30 days') || str_contains($promptLower, 'haven\'t purchased') || str_contains($promptLower, 're-engage');
        $isEvent = str_contains($promptLower, 'event') || str_contains($promptLower, 'webinar') || str_contains($promptLower, 'launch');

        $obj = $objective ?: ($isDiscount ? 'Drive Conversions & Immediate Sales' : ($isReEngagement ? 'Customer Win-back & Re-engagement' : 'Customer Engagement & Brand Awareness'));
        $name = $isDiscount ? 'Special Flash Promotion' : ($isReEngagement ? 'Customer Win-back Campaign' : ($isEvent ? 'Product Launch Announcement' : 'Omnichannel Campaign Update'));

        $subject = match ($channel) {
            'email' => $isDiscount
                ? 'Exclusive {{discount_percent|20%}} Discount Inside for You, {{first_name|there}}!'
                : ($isReEngagement ? 'We miss you, {{first_name|there}} - Here is something special' : 'Exciting news from our team, {{first_name|there}}'),
            default => '',
        };

        $body = match ($channel) {
            'whatsapp' => $isDiscount
                ? "Hello {{first_name|there}}! 🌟\n\nWe have a special limited-time offer just for you. Get 20% off on your favorite products today.\n\nClick below to claim your offer before it expires:"
                : ($isReEngagement
                    ? "Hi {{first_name|there}}! 👋 We noticed it's been a while since your last visit. We've added exciting new updates and want to welcome you back with a special gift."
                    : "Hello {{first_name|there}}! 🚀 Exciting updates are now live. Discover what's new and let us know how we can assist you today."),
            'instagram', 'messenger' => $isDiscount
                ? "Hey {{first_name|there}}! ✨ Flash Sale is on! Use code GROW20 for 20% off your next purchase. Tap below to shop now!"
                : "Hey {{first_name|there}}! 👋 Check out our latest collection and special offers curated just for you.",
            'email' => $isDiscount
                ? "<p>Hello <strong>{{first_name|there}}</strong>,</p><p>We are delighted to offer you an exclusive 20% discount on all our services today.</p><p>Don't miss out on this limited-time opportunity.</p>"
                : "<p>Hello <strong>{{first_name|there}}</strong>,</p><p>We wanted to share some exciting news with you and ensure you have everything you need to grow your business.</p>",
            default => "Hello {{first_name|there}}! Check out our special update today.",
        };

        $cta = $isDiscount ? 'Claim Offer Now' : ($isReEngagement ? 'Explore Offers' : 'Learn More');
        $audienceTip = $isReEngagement ? 'Target: Segment contacts inactive for >30 days with Lead Score >= 40' : 'Target: High-intent leads & active subscribers';
        $timing = 'Recommended Send Time: Tuesday or Thursday between 10:00 AM and 2:00 PM (Local Time)';

        return [
            'objective' => $obj,
            'suggested_name' => $name,
            'subject' => $subject,
            'message_body' => $body,
            'call_to_action' => $cta,
            'audience_suggestion' => $audienceTip,
            'recommended_timing' => $timing,
        ];
    }

    /**
     * Adjust or rewrite copy tone.
     *
     * @return array{text: string, action: string}
     */
    public function adjustMessageTone(string $message, string $action, string $language = 'en'): array
    {
        $adjusted = match ($action) {
            'shorten' => trim(preg_replace('/\s+/', ' ', Str::words($message, 25, '...'))),
            'professional' => "Dear {{first_name|Valued Client}},\n\n".trim(strip_tags($message))."\n\nPlease feel free to contact us should you have any questions.\n\nWarm regards,\nGrowbridge Team",
            'friendly' => "Hey {{first_name|friend}}! 🎉\n\n".trim(strip_tags($message))."\n\nHave an amazing day! ✨",
            'translate_es' => "¡Hola {{first_name|amigo}}! Gracias por contactarnos. ¿En qué podemos ayudarte hoy?",
            'translate_fr' => "Bonjour {{first_name|cher client}}! Merci de nous avoir contactés. Comment pouvons-nous vous aider aujourd'hui?",
            'translate_hi' => "नमस्ते {{first_name|जी}}, ग्रोब्रिज कनेक्ट में आपका स्वागत है। हम आपकी क्या मदद कर सकते हैं?",
            'cta' => trim($message)."\n\n👉 [Click here to get started now]",
            default => trim($message),
        };

        return [
            'text' => $adjusted,
            'action' => $action,
            'language' => $language,
        ];
    }

    /**
     * AI Intent Classification for inbound campaign replies.
     *
     * @return array{
     *     intent: string,
     *     sentiment: string,
     *     lead_score_boost: int,
     *     suggested_action: string,
     *     requires_human_attention: bool
     * }
     */
    public function classifyReply(string $replyText): array
    {
        $text = strtolower(trim($replyText));

        // High buying intent / price request
        if (str_contains($text, 'price') || str_contains($text, 'cost') || str_contains($text, 'quote') || str_contains($text, 'how much') || str_contains($text, 'discount') || str_contains($text, 'rate')) {
            return [
                'intent' => 'price_request',
                'sentiment' => 'positive',
                'lead_score_boost' => 25,
                'suggested_action' => 'Send pricing breakdown & schedule demo call',
                'requires_human_attention' => true,
            ];
        }

        // Interested / Positive
        if (str_contains($text, 'yes') || str_contains($text, 'interested') || str_contains($text, 'tell me more') || str_contains($text, 'sure') || str_contains($text, 'details') || str_contains($text, 'sign me up')) {
            return [
                'intent' => 'interested',
                'sentiment' => 'positive',
                'lead_score_boost' => 20,
                'suggested_action' => 'Qualify lead & dispatch sales representative',
                'requires_human_attention' => true,
            ];
        }

        // Human / Agent handoff request
        if (str_contains($text, 'agent') || str_contains($text, 'human') || str_contains($text, 'representative') || str_contains($text, 'call me') || str_contains($text, 'speak to someone')) {
            return [
                'intent' => 'human_request',
                'sentiment' => 'neutral',
                'lead_score_boost' => 15,
                'suggested_action' => 'Instant human takeover alert dispatched',
                'requires_human_attention' => true,
            ];
        }

        // Complaint / Negative
        if (str_contains($text, 'stop') || str_contains($text, 'unsubscribe') || str_contains($text, 'remove') || str_contains($text, 'spam') || str_contains($text, 'not interested') || str_contains($text, 'bad')) {
            return [
                'intent' => 'not_interested',
                'sentiment' => 'negative',
                'lead_score_boost' => -10,
                'suggested_action' => 'Honor opt-out & suppress from future promotional broadcasts',
                'requires_human_attention' => false,
            ];
        }

        // General inquiry / Question
        return [
            'intent' => 'question',
            'sentiment' => 'neutral',
            'lead_score_boost' => 10,
            'suggested_action' => 'AI Chatbot answer or agent reply',
            'requires_human_attention' => false,
        ];
    }
}
