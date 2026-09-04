<?php

namespace App\Modules\Automation\Services;

class AutomationTemplateRegistry
{
    /**
     * Return all 10 pre-built omnichannel journey templates.
     */
    public static function all(): array
    {
        return [
            [
                'key' => 'unresponsive_lead_drip',
                'title' => 'Unresponsive Lead Multi-Channel Drip Nurture',
                'category' => 'Lead Gen & Sales',
                'description' => 'Automatically nurtures non-responsive leads: Wait 2 hours -> Follow-up WhatsApp -> Wait 1 day -> Email -> Create urgent salesperson task.',
                'trigger' => 'contact.created',
                'channels' => ['whatsapp', 'email', 'crm'],
                'nodes_count' => 7,
            ],
            [
                'key' => 'new_lead_followup',
                'title' => 'New Lead Omnichannel Follow-up',
                'category' => 'Sales & Growth',
                'description' => 'Instantly welcome new leads on WhatsApp, qualify intent via AI, and trigger an automated AI Voice call if score > 80.',
                'trigger' => 'contact.created',
                'channels' => ['whatsapp', 'heyo_phone', 'email'],
                'nodes_count' => 6,
            ],
            [
                'key' => 'whatsapp_qualification',
                'title' => 'WhatsApp Lead Qualification & CRM Scoring',
                'category' => 'Lead Gen',
                'description' => 'Autonomous multi-turn qualification on WhatsApp. Extracts budget, timeline, and updates CRM Lead score automatically.',
                'trigger' => 'message.received',
                'channels' => ['whatsapp'],
                'nodes_count' => 5,
            ],
            [
                'key' => 'missed_call_recovery',
                'title' => 'Missed Call Instant WhatsApp Recovery',
                'category' => 'Customer Care',
                'description' => 'When an inbound phone call goes unanswered, instantly send a friendly WhatsApp greeting with AI chatbot assistance.',
                'trigger' => 'call.missed',
                'channels' => ['heyo_phone', 'whatsapp'],
                'nodes_count' => 4,
            ],
            [
                'key' => 'ai_sales_agent',
                'title' => 'AI Autonomous Sales & Demo Booking',
                'category' => 'Sales',
                'description' => 'Engages interested prospects across Instagram/WhatsApp, answers product questions with RAG, and shares calendar link.',
                'trigger' => 'message.received',
                'channels' => ['whatsapp', 'instagram'],
                'nodes_count' => 5,
            ],
            [
                'key' => 'support_with_handoff',
                'title' => '24/7 Customer Support with Smart Human Escalation',
                'category' => 'Support',
                'description' => 'Resolves 80% of routine questions using Knowledge Base; instantly transfers to human agents on complaints or complex queries.',
                'trigger' => 'message.received',
                'channels' => ['whatsapp', 'messenger', 'email'],
                'nodes_count' => 6,
            ],
            [
                'key' => 'appointment_reminder',
                'title' => 'Multi-Channel Appointment Reminder',
                'category' => 'Operations',
                'description' => 'Sends 24-hour and 1-hour reminders via WhatsApp and SMS with interactive Confirm/Reschedule buttons.',
                'trigger' => 'appointment.scheduled',
                'channels' => ['whatsapp', 'sms'],
                'nodes_count' => 5,
            ],
            [
                'key' => 'high_value_quote',
                'title' => 'High-Value Quote Follow-up',
                'category' => 'Sales',
                'description' => 'Multi-day smart sequence: Email quotation -> 2-day wait -> Context-aware WhatsApp check-in -> AI Voice call.',
                'trigger' => 'quote.sent',
                'channels' => ['email', 'whatsapp', 'heyo_phone'],
                'nodes_count' => 7,
            ],
            [
                'key' => 'abandoned_lead_reengagement',
                'title' => 'Abandoned Lead Re-Engagement Flow',
                'category' => 'Retention',
                'description' => 'Re-engages inactive prospects after 7 days with a personalized discount or special consultation offer.',
                'trigger' => 'lead.inactive',
                'channels' => ['whatsapp', 'email'],
                'nodes_count' => 4,
            ],
            [
                'key' => 'customer_feedback_survey',
                'title' => 'Post-Purchase CSAT & Feedback Flow',
                'category' => 'Satisfaction',
                'description' => 'Automatically asks for feedback after order fulfillment or support resolution with interactive 1-5 rating buttons.',
                'trigger' => 'order.fulfilled',
                'channels' => ['whatsapp'],
                'nodes_count' => 4,
            ],
            [
                'key' => 'ai_voice_followup',
                'title' => 'AI Voice Agent Outbound Follow-up',
                'category' => 'Telephony',
                'description' => 'Dials prospects using Heyo Phone AI Voice Agent, generates AI summary, and delivers meeting recap on WhatsApp.',
                'trigger' => 'lead.stage_changed',
                'channels' => ['heyo_phone', 'whatsapp'],
                'nodes_count' => 5,
            ],
        ];
    }
}
