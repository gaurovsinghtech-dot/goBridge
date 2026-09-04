<?php

namespace App\Services;

use App\Models\PhoneNumber;
use App\Models\Workspace;
use App\Modules\Whatsapp\Models\WhatsappBusinessAccount;
use App\Modules\Whatsapp\Models\WhatsappPhoneNumber;
use Illuminate\Support\Facades\Log;

class UnifiedNumberService
{
    /**
     * Get Unified Status Summary for a Phone Number
     */
    public function getUnifiedSummary(PhoneNumber $number): array
    {
        return [
            'id' => $number->id,
            'uuid' => $number->uuid,
            'phone_number' => $number->phone_number,
            'country' => $number->country,
            'status' => $number->status,
            'voice' => [
                'enabled' => (bool) $number->voice_enabled,
                'status' => $number->voice_enabled ? 'enabled' : 'disabled',
                'assigned_agent' => $number->assignedVoiceAgent ? [
                    'id' => $number->assignedVoiceAgent->id,
                    'name' => $number->assignedVoiceAgent->name,
                    'language' => $number->assignedVoiceAgent->language,
                    'tone' => $number->assignedVoiceAgent->tone,
                ] : null,
            ],
            'whatsapp' => [
                'status' => $number->whatsapp_status ?? 'not_connected', // 'not_connected' | 'pending_verification' | 'connected'
                'is_connected' => $number->whatsapp_status === 'connected',
                'display_name' => $number->whatsapp_display_name,
                'phone_number_id' => $number->whatsapp_phone_number_id,
                'account_id' => $number->whatsapp_account_id,
            ],
            'sms' => [
                'enabled' => (bool) $number->sms_enabled,
            ],
            'chat_ai' => [
                'assigned_agent' => $number->assignedChatAgent ? [
                    'id' => $number->assignedChatAgent->id,
                    'name' => $number->assignedChatAgent->name,
                ] : null,
            ],
            'is_unified' => $number->isUnified(),
        ];
    }

    /**
     * Connect or Initiate WhatsApp Onboarding for a Virtual Phone Number
     */
    public function connectWhatsapp(Workspace $workspace, PhoneNumber $number, array $data): PhoneNumber
    {
        // Enforce workspace tenant isolation
        if ($number->workspace_id !== $workspace->id) {
            throw new \RuntimeException('Unauthorized workspace number access.');
        }

        $businessName = $data['display_name'] ?? $data['business_name'] ?? ($workspace->name ?? 'My Business');
        $wabaId = $data['waba_id'] ?? null;
        $metaPhoneId = $data['whatsapp_phone_number_id'] ?? ('meta_pn_' . bin2hex(random_bytes(6)));

        // If linking to an existing WABA
        $waba = null;
        if ($wabaId) {
            $waba = WhatsappBusinessAccount::where('workspace_id', $workspace->id)
                ->where('id', $wabaId)
                ->first();
        }

        if (!$waba) {
            // Find first active WABA or link
            $waba = WhatsappBusinessAccount::where('workspace_id', $workspace->id)->first();
        }

        $number->update([
            'whatsapp_status' => 'connected',
            'whatsapp_display_name' => $businessName,
            'whatsapp_phone_number_id' => $metaPhoneId,
            'whatsapp_account_id' => $waba?->id,
        ]);

        // Register or sync in whatsapp_phone_numbers if WABA exists
        if ($waba) {
            WhatsappPhoneNumber::firstOrCreate(
                [
                    'waba_id_fk' => $waba->id,
                    'phone_number_id' => $metaPhoneId,
                ],
                [
                    'display_phone' => $number->phone_number,
                    'verified_name' => $businessName,
                    'quality_rating' => 'GREEN',
                    'messaging_limit_tier' => 'TIER_1K',
                    'code_verification_status' => 'VERIFIED',
                    'name_status' => 'APPROVED',
                    'account_mode' => 'LIVE',
                ]
            );
        }

        Log::info("UnifiedNumberService: WhatsApp connected to {$number->phone_number} for workspace #{$workspace->id}");

        return $number->fresh(['assignedVoiceAgent', 'assignedChatAgent', 'whatsappAccount']);
    }

    /**
     * Disconnect WhatsApp from Number
     */
    public function disconnectWhatsapp(Workspace $workspace, PhoneNumber $number): PhoneNumber
    {
        if ($number->workspace_id !== $workspace->id) {
            throw new \RuntimeException('Unauthorized workspace number access.');
        }

        $number->update([
            'whatsapp_status' => 'not_connected',
            'whatsapp_display_name' => null,
            'whatsapp_phone_number_id' => null,
            'whatsapp_account_id' => null,
        ]);

        return $number->fresh();
    }

    /**
     * Assign AI Agents (Voice AI + Chat AI) to a Unified Number
     */
    public function assignAiAgents(Workspace $workspace, PhoneNumber $number, ?int $voiceAgentId, ?int $chatAgentId = null): PhoneNumber
    {
        if ($number->workspace_id !== $workspace->id) {
            throw new \RuntimeException('Unauthorized workspace number access.');
        }

        $number->update([
            'assigned_ai_agent_id' => $voiceAgentId,
            'assigned_chat_ai_agent_id' => $chatAgentId ?? $voiceAgentId,
        ]);

        return $number->fresh(['assignedVoiceAgent', 'assignedChatAgent']);
    }
}
