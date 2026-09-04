<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\PhoneNumber;
use App\Models\Subscription;
use App\Models\Workspace;
use App\Modules\Voice\Models\TelephonyPhoneNumber;
use App\Modules\Voice\Models\VoiceAgent;
use Illuminate\Support\Facades\Schema;

class EntitlementService
{
    /**
     * Standard platform feature definitions.
     */
    public const FEATURE_CRM = 'crm';
    public const FEATURE_WHATSAPP = 'whatsapp';
    public const FEATURE_EMAIL_MARKETING = 'email_marketing';
    public const FEATURE_AI_AGENT = 'ai_agent';
    public const FEATURE_KNOWLEDGE_BASE = 'knowledge_base';
    public const FEATURE_ADVANCED_AUTOMATION = 'advanced_automation';
    public const FEATURE_CRM_INTEGRATIONS = 'crm_integrations';
    public const FEATURE_CALLING = 'calling';
    public const FEATURE_ADVANCED_ANALYTICS = 'advanced_analytics';

    // Legacy aliases
    public const FEATURE_WHATSAPP_API = 'whatsapp_api';
    public const FEATURE_INBOX = 'inbox';
    public const FEATURE_CONTACTS = 'contacts';
    public const FEATURE_CAMPAIGNS = 'campaigns';
    public const FEATURE_AUTOMATIONS = 'automations';
    public const FEATURE_AI_AGENTS = 'ai_agents';
    public const FEATURE_VOICE_CALLING = 'voice_calling';
    public const FEATURE_AI_VOICE_AGENTS = 'ai_voice_agents';
    public const FEATURE_SMS = 'sms';
    public const FEATURE_ADVANCED_INTEGRATIONS = 'advanced_integrations';

    /**
     * All recognizable feature keys.
     */
    public const ALL_FEATURES = [
        self::FEATURE_CRM,
        self::FEATURE_WHATSAPP,
        self::FEATURE_EMAIL_MARKETING,
        self::FEATURE_AI_AGENT,
        self::FEATURE_KNOWLEDGE_BASE,
        self::FEATURE_ADVANCED_AUTOMATION,
        self::FEATURE_CRM_INTEGRATIONS,
        self::FEATURE_CALLING,
        self::FEATURE_ADVANCED_ANALYTICS,
        self::FEATURE_WHATSAPP_API,
        self::FEATURE_INBOX,
        self::FEATURE_CONTACTS,
        self::FEATURE_CAMPAIGNS,
        self::FEATURE_AUTOMATIONS,
        self::FEATURE_AI_AGENTS,
        self::FEATURE_VOICE_CALLING,
        self::FEATURE_AI_VOICE_AGENTS,
        self::FEATURE_SMS,
        self::FEATURE_ADVANCED_INTEGRATIONS,
    ];

    /**
     * Default entitlements for standard tier plans.
     */
    public const DEFAULT_PLAN_ENTITLEMENTS = [
        'starter' => [
            self::FEATURE_CRM => true,
            self::FEATURE_WHATSAPP => true,
            self::FEATURE_EMAIL_MARKETING => false,
            self::FEATURE_AI_AGENT => false,
            self::FEATURE_KNOWLEDGE_BASE => false,
            self::FEATURE_ADVANCED_AUTOMATION => false,
            self::FEATURE_CRM_INTEGRATIONS => false,
            self::FEATURE_CALLING => false,
            self::FEATURE_ADVANCED_ANALYTICS => false,
            self::FEATURE_WHATSAPP_API => true,
            self::FEATURE_INBOX => true,
            self::FEATURE_CONTACTS => true,
            self::FEATURE_CAMPAIGNS => false,
            self::FEATURE_AUTOMATIONS => false,
            self::FEATURE_AI_AGENTS => false,
            self::FEATURE_VOICE_CALLING => false,
            self::FEATURE_AI_VOICE_AGENTS => false,
            self::FEATURE_SMS => false,
            self::FEATURE_ADVANCED_INTEGRATIONS => false,
        ],
        'growth' => [
            self::FEATURE_CRM => true,
            self::FEATURE_WHATSAPP => true,
            self::FEATURE_EMAIL_MARKETING => true,
            self::FEATURE_AI_AGENT => true,
            self::FEATURE_KNOWLEDGE_BASE => true,
            self::FEATURE_ADVANCED_AUTOMATION => true,
            self::FEATURE_CRM_INTEGRATIONS => false,
            self::FEATURE_CALLING => false,
            self::FEATURE_ADVANCED_ANALYTICS => true,
            self::FEATURE_WHATSAPP_API => true,
            self::FEATURE_INBOX => true,
            self::FEATURE_CONTACTS => true,
            self::FEATURE_CAMPAIGNS => true,
            self::FEATURE_AUTOMATIONS => true,
            self::FEATURE_AI_AGENTS => true,
            self::FEATURE_VOICE_CALLING => false,
            self::FEATURE_AI_VOICE_AGENTS => false,
            self::FEATURE_SMS => true,
            self::FEATURE_ADVANCED_INTEGRATIONS => false,
        ],
        'business' => [
            self::FEATURE_CRM => true,
            self::FEATURE_WHATSAPP => true,
            self::FEATURE_EMAIL_MARKETING => true,
            self::FEATURE_AI_AGENT => true,
            self::FEATURE_KNOWLEDGE_BASE => true,
            self::FEATURE_ADVANCED_AUTOMATION => true,
            self::FEATURE_CRM_INTEGRATIONS => true,
            self::FEATURE_CALLING => true,
            self::FEATURE_ADVANCED_ANALYTICS => true,
            self::FEATURE_WHATSAPP_API => true,
            self::FEATURE_INBOX => true,
            self::FEATURE_CONTACTS => true,
            self::FEATURE_CAMPAIGNS => true,
            self::FEATURE_AUTOMATIONS => true,
            self::FEATURE_AI_AGENTS => true,
            self::FEATURE_VOICE_CALLING => true,
            self::FEATURE_AI_VOICE_AGENTS => true,
            self::FEATURE_SMS => true,
            self::FEATURE_ADVANCED_INTEGRATIONS => true,
        ],
        'pro' => [
            self::FEATURE_CRM => true,
            self::FEATURE_WHATSAPP => true,
            self::FEATURE_EMAIL_MARKETING => true,
            self::FEATURE_AI_AGENT => true,
            self::FEATURE_KNOWLEDGE_BASE => true,
            self::FEATURE_ADVANCED_AUTOMATION => true,
            self::FEATURE_CRM_INTEGRATIONS => true,
            self::FEATURE_CALLING => true,
            self::FEATURE_ADVANCED_ANALYTICS => true,
            self::FEATURE_WHATSAPP_API => true,
            self::FEATURE_INBOX => true,
            self::FEATURE_CONTACTS => true,
            self::FEATURE_CAMPAIGNS => true,
            self::FEATURE_AUTOMATIONS => true,
            self::FEATURE_AI_AGENTS => true,
            self::FEATURE_VOICE_CALLING => true,
            self::FEATURE_AI_VOICE_AGENTS => true,
            self::FEATURE_SMS => true,
            self::FEATURE_ADVANCED_INTEGRATIONS => true,
        ],
        'enterprise' => [
            self::FEATURE_CRM => true,
            self::FEATURE_WHATSAPP => true,
            self::FEATURE_EMAIL_MARKETING => true,
            self::FEATURE_AI_AGENT => true,
            self::FEATURE_KNOWLEDGE_BASE => true,
            self::FEATURE_ADVANCED_AUTOMATION => true,
            self::FEATURE_CRM_INTEGRATIONS => true,
            self::FEATURE_CALLING => true,
            self::FEATURE_ADVANCED_ANALYTICS => true,
            self::FEATURE_WHATSAPP_API => true,
            self::FEATURE_INBOX => true,
            self::FEATURE_CONTACTS => true,
            self::FEATURE_CAMPAIGNS => true,
            self::FEATURE_AUTOMATIONS => true,
            self::FEATURE_AI_AGENTS => true,
            self::FEATURE_VOICE_CALLING => true,
            self::FEATURE_AI_VOICE_AGENTS => true,
            self::FEATURE_SMS => true,
            self::FEATURE_ADVANCED_INTEGRATIONS => true,
        ],
    ];

    /**
     * In-memory cache for resolved entitlements per workspace ID.
     */
    protected static array $entitlementsCache = [];

    /**
     * Clear cached entitlements.
     */
    public static function clearCache(): void
    {
        static::$entitlementsCache = [];
    }

    /**
     * Normalize feature names to handle synonyms across modules.
     */
    public static function normalizeFeature(string $feature): string
    {
        return match (strtolower($feature)) {
            'crm', 'contacts', 'leads' => self::FEATURE_CRM,
            'whatsapp', 'whatsapp_api', 'waba' => self::FEATURE_WHATSAPP,
            'email_marketing', 'campaigns', 'broadcast' => self::FEATURE_EMAIL_MARKETING,
            'ai_agent', 'ai_agents', 'ai', 'chatbot' => self::FEATURE_AI_AGENT,
            'knowledge_base', 'rag', 'kb' => self::FEATURE_KNOWLEDGE_BASE,
            'advanced_automation', 'automations', 'workflows' => self::FEATURE_ADVANCED_AUTOMATION,
            'crm_integrations', 'advanced_integrations', 'integrations' => self::FEATURE_CRM_INTEGRATIONS,
            'calling', 'voice_calling', 'voice', 'telephony' => self::FEATURE_CALLING,
            'advanced_analytics', 'analytics', 'reports' => self::FEATURE_ADVANCED_ANALYTICS,
            default => $feature,
        };
    }

    /**
     * Check if a workspace has an active entitlement for a specific feature.
     */
    public static function can(Workspace|int|null $workspace, string $feature): bool
    {
        if (! $workspace) {
            return false;
        }

        $normalized = static::normalizeFeature($feature);
        $entitlements = static::getEntitlements($workspace);

        if (! empty($entitlements[$normalized])) {
            return true;
        }

        return ! empty($entitlements[$feature]);
    }

    /**
     * Get complete dictionary of boolean feature entitlements for a workspace.
     */
    public static function getEntitlements(Workspace|int $workspace): array
    {
        $wsId = $workspace instanceof Workspace ? $workspace->id : (int) $workspace;

        if (isset(static::$entitlementsCache[$wsId])) {
            return static::$entitlementsCache[$wsId];
        }

        $workspaceModel = $workspace instanceof Workspace ? $workspace : Workspace::find($workspace);
        if (! $workspaceModel) {
            return static::DEFAULT_PLAN_ENTITLEMENTS['starter'];
        }

        // 1. Resolve active subscription and plan
        $subscription = Subscription::with('plan')
            ->where('workspace_id', $workspaceModel->id)
            ->latest('id')
            ->first();

        if (! $subscription && $workspaceModel->owner_id) {
            $subscription = Subscription::with('plan')
                ->where('user_id', $workspaceModel->owner_id)
                ->latest('id')
                ->first();
        }

        $plan = ($subscription && $subscription->isActive()) ? $subscription->plan : null;

        // If no active paid plan found, fall back to free/starter or existing service capability
        if (! $plan) {
            $starter = static::DEFAULT_PLAN_ENTITLEMENTS['starter'];

            $hasVoiceCapability = ($workspaceModel->service_type === 'whatsapp_voice')
                || (Schema::hasTable('voice_agents') && VoiceAgent::where('workspace_id', $workspaceModel->id)->exists())
                || (Schema::hasTable('telephony_phone_numbers') && TelephonyPhoneNumber::where('workspace_id', $workspaceModel->id)->exists())
                || (Schema::hasTable('phone_numbers') && PhoneNumber::where('workspace_id', $workspaceModel->id)->where('voice_enabled', true)->exists());

            if ($hasVoiceCapability) {
                $starter[self::FEATURE_CALLING] = true;
                $starter[self::FEATURE_VOICE_CALLING] = true;
                $starter[self::FEATURE_AI_VOICE_AGENTS] = true;
            }

            return static::$entitlementsCache[$wsId] = $starter;
        }

        return static::$entitlementsCache[$wsId] = static::resolvePlanEntitlements($plan, $workspaceModel->service_type);
    }

    /**
     * Resolve feature dictionary for a given plan and optional workspace service type.
     */
    public static function resolvePlanEntitlements(Plan $plan, ?string $serviceType = null): array
    {
        $slug = strtolower($plan->slug ?: 'starter');
        $base = static::DEFAULT_PLAN_ENTITLEMENTS[$slug] ?? static::DEFAULT_PLAN_ENTITLEMENTS['starter'];

        $features = $plan->features ?? [];

        // If plan has wildcard access
        if (isset($features['*']) && $features['*'] === true) {
            $all = [];
            foreach (static::ALL_FEATURES as $f) {
                $all[$f] = true;
            }
            return $all;
        }

        // If features is an associative array with booleans or list of strings
        if (is_array($features) && ! empty($features)) {
            foreach ($features as $key => $val) {
                if (is_string($key) && is_bool($val)) {
                    $norm = static::normalizeFeature($key);
                    $base[$key] = $val;
                    $base[$norm] = $val;
                } elseif (is_string($val)) {
                    $norm = static::normalizeFeature($val);
                    $base[$val] = true;
                    $base[$norm] = true;
                }
            }
        }

        // If service type was explicitly set to whatsapp_voice and plan allows it
        if ($serviceType === 'whatsapp_voice' && in_array($slug, ['growth', 'business', 'pro', 'enterprise'], true)) {
            $base[self::FEATURE_CALLING] = true;
            $base[self::FEATURE_VOICE_CALLING] = true;
            $base[self::FEATURE_AI_VOICE_AGENTS] = true;
        }

        return $base;
    }

    /**
     * Enforce feature access; throws HTTP 403 or aborts if disallowed.
     */
    public static function enforce(Workspace|int|null $workspace, string $feature, ?string $customMessage = null): void
    {
        if (! static::can($workspace, $feature)) {
            $readableFeature = ucwords(str_replace('_', ' ', $feature));
            $msg = $customMessage ?: "{$readableFeature} is not available on your current plan. Upgrade your plan to activate it.";
            abort(403, $msg);
        }
    }
}
