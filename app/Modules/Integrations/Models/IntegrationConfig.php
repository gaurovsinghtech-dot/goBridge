<?php

namespace App\Modules\Integrations\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationConfig extends Model
{
    // All valid provider slugs
    public const PROVIDERS = [
        'twilio',
        'meta_app',
        'ai_providers',
        'storage_local',
        'crm_hubspot',
        'crm_salesforce',
        'crm_zoho',
        'crm_pipedrive',
        'crm_freshsales',
        'crm_dynamics',
        'crm_gohighlevel',
        'crm_custom',
        'crm_webhook',
        'google_places',
        'storage_s3',
        'storage_do',
        'storage_wasabi',
        // Preserved for marketplace/extensibility
        'oauth_linkedin',
        'oauth_twitter',
        'oauth_youtube',
        'oauth_tiktok',
        'oauth_shopify',
        'oauth_bigcommerce',
        'llm_openai_default',
        'llm_anthropic_default',
        'llm_gemini_default',
        'google_workspace',
        'qdrant',
    ];

    /** Core MVP providers for Launch Readiness */
    public const CORE_PLATFORM_PROVIDERS = ['twilio', 'meta_app', 'ai_providers', 'storage_local'];
    public const CRM_PROVIDERS = [
        'crm_hubspot',
        'crm_salesforce',
        'crm_zoho',
        'crm_pipedrive',
        'crm_freshsales',
        'crm_dynamics',
        'crm_gohighlevel',
        'crm_custom',
        'crm_webhook',
    ];
    public const OPTIONAL_SERVICE_PROVIDERS = ['google_places'];
    public const ADVANCED_STORAGE_PROVIDERS = ['storage_s3', 'storage_do', 'storage_wasabi'];

    /** The single provider slug that is the active storage backend. */
    public const STORAGE_PROVIDERS = ['storage_local', 'storage_s3', 'storage_do', 'storage_wasabi'];

    /** Maps provider slug → Laravel disk name. */
    public const STORAGE_DISK_MAP = [
        'storage_local' => 'public',
        'storage_s3' => 's3',
        'storage_do' => 'do_spaces',
        'storage_wasabi' => 'wasabi',
    ];

    // Human-readable labels per provider
    public const LABELS = [
        'twilio' => 'Twilio',
        'meta_app' => 'Meta WhatsApp Business API',
        'ai_providers' => 'AI Providers',
        'storage_local' => 'Local Storage',
        'crm_hubspot' => 'HubSpot CRM',
        'crm_salesforce' => 'Salesforce CRM',
        'crm_zoho' => 'Zoho CRM',
        'crm_pipedrive' => 'Pipedrive CRM',
        'crm_freshsales' => 'Freshsales CRM',
        'crm_dynamics' => 'Microsoft Dynamics 365',
        'crm_gohighlevel' => 'GoHighLevel',
        'crm_custom' => 'Custom CRM / REST API',
        'crm_webhook' => 'Generic CRM Webhook',
        'google_places' => 'Google Places API',
        'storage_s3' => 'Amazon S3',
        'storage_do' => 'DigitalOcean Spaces',
        'storage_wasabi' => 'Wasabi Cloud Storage',
        'oauth_linkedin' => 'LinkedIn OAuth',
        'oauth_twitter' => 'Twitter / X OAuth',
        'oauth_youtube' => 'YouTube / Google OAuth',
        'oauth_tiktok' => 'TikTok OAuth',
        'oauth_shopify' => 'Shopify App (OAuth)',
        'oauth_bigcommerce' => 'BigCommerce App (OAuth)',
        'llm_openai_default' => 'OpenAI (Default)',
        'llm_anthropic_default' => 'Anthropic Claude (Default)',
        'llm_gemini_default' => 'Google Gemini (Default)',
        'google_workspace' => 'Google Workspace',
        'qdrant' => 'Qdrant Vector Store',
    ];

    // Which category each provider belongs to (for UI grouping)
    public const CATEGORIES = [
        'twilio' => 'Core Platform',
        'meta_app' => 'Core Platform',
        'ai_providers' => 'Core Platform',
        'storage_local' => 'Core Platform',
        'crm_hubspot' => 'CRM & Business Systems',
        'crm_salesforce' => 'CRM & Business Systems',
        'crm_zoho' => 'CRM & Business Systems',
        'crm_pipedrive' => 'CRM & Business Systems',
        'crm_freshsales' => 'CRM & Business Systems',
        'crm_dynamics' => 'CRM & Business Systems',
        'crm_gohighlevel' => 'CRM & Business Systems',
        'crm_custom' => 'CRM & Business Systems',
        'crm_webhook' => 'CRM & Business Systems',
        'google_places' => 'Optional Services',
        'storage_s3' => 'Advanced Storage',
        'storage_do' => 'Advanced Storage',
        'storage_wasabi' => 'Advanced Storage',
        'oauth_linkedin' => 'Social OAuth',
        'oauth_twitter' => 'Social OAuth',
        'oauth_youtube' => 'Social OAuth',
        'oauth_tiktok' => 'Social OAuth',
        'oauth_shopify' => 'E-Commerce OAuth',
        'oauth_bigcommerce' => 'E-Commerce OAuth',
        'llm_openai_default' => 'AI / LLM',
        'llm_anthropic_default' => 'AI / LLM',
        'llm_gemini_default' => 'AI / LLM',
        'google_workspace' => 'Google Workspace',
        'qdrant' => 'Vector Store',
    ];

    // Field definitions per provider (used to build dynamic forms)
    public const FIELDS = [
        'twilio' => [
            ['key' => 'account_sid',            'label' => 'Twilio Account SID',            'type' => 'text',     'required' => true,  'hint' => 'From your Twilio Console dashboard (starts with AC...)'],
            ['key' => 'auth_token',             'label' => 'Twilio Auth Token',             'type' => 'password', 'required' => true,  'hint' => 'Primary Auth Token from your Twilio Console'],
            ['key' => 'api_key_sid',            'label' => 'Twilio API Key SID (Optional)', 'type' => 'text',     'required' => false, 'hint' => 'Optional: Starts with SK... for API key authentication'],
            ['key' => 'api_key_secret',         'label' => 'Twilio API Key Secret (Optional)', 'type' => 'password', 'required' => false],
            ['key' => 'messaging_service_sid',  'label' => 'Twilio Messaging Service SID (Optional)', 'type' => 'text', 'required' => false, 'hint' => 'Starts with MG... for pooled SMS/MMS distribution'],
            ['key' => 'voice_app_sid',          'label' => 'Default Voice Application SID (Optional)', 'type' => 'text', 'required' => false, 'hint' => 'Starts with AP... (TwiML App SID for browser/mobile voice calls)'],
            ['key' => 'webhook_base_url',       'label' => 'Webhook Base URL (Optional)',   'type' => 'text',     'required' => false, 'hint' => 'Override server URL for Twilio webhook callbacks. Leave blank to use APP_URL.'],
        ],
        'meta_app' => [
            ['key' => 'app_id',              'label' => 'Meta App ID',                           'type' => 'text',     'required' => true,  'hint' => 'From developers.facebook.com → My Apps → App Settings → Basic'],
            ['key' => 'app_secret',          'label' => 'Meta App Secret',                       'type' => 'password', 'required' => true,  'hint' => 'App Secret from App Settings → Basic'],
            ['key' => 'config_id_whatsapp',  'label' => 'Configuration ID (WhatsApp Embedded Signup)', 'type' => 'text', 'required' => false, 'hint' => 'From Meta App Dashboard → Facebook Login for Business → WhatsApp Embedded Signup configuration'],
            ['key' => 'verify_token',        'label' => 'Webhook Verify Token',                 'type' => 'text',     'required' => false, 'hint' => 'Custom string used to verify incoming Meta webhook subscriptions'],
            ['key' => 'system_user_token',   'label' => 'System User Access Token',             'type' => 'password', 'required' => false, 'hint' => 'Permanent system user token with whatsapp_business_messaging & management permissions'],
        ],
        'ai_providers' => [
            ['key' => 'default_provider',       'label' => 'Default AI Provider',                  'type' => 'select',   'required' => true,  'options' => ['openai' => 'OpenAI', 'gemini' => 'Google Gemini', 'anthropic' => 'Anthropic Claude'], 'hint' => 'Primary AI engine for Chatbots, Agents, and Auto-replies'],
            ['key' => 'openai_api_key',         'label' => 'OpenAI API Key',                       'type' => 'password', 'required' => false, 'hint' => 'From platform.openai.com/api-keys (starts with sk-...)'],
            ['key' => 'openai_model',           'label' => 'OpenAI Default Model',                 'type' => 'text',     'required' => false, 'hint' => 'e.g. gpt-4o, gpt-4o-mini'],
            ['key' => 'openai_organization_id', 'label' => 'OpenAI Organization / Project ID',     'type' => 'text',     'required' => false, 'hint' => 'Optional: org-... or proj-...'],
            ['key' => 'gemini_api_key',         'label' => 'Google Gemini API Key',                'type' => 'password', 'required' => false, 'hint' => 'From aistudio.google.com/app/apikey (starts with AIzaSy...)'],
            ['key' => 'gemini_model',           'label' => 'Google Gemini Default Model',          'type' => 'text',     'required' => false, 'hint' => 'e.g. gemini-1.5-flash, gemini-1.5-pro'],
            ['key' => 'anthropic_api_key',      'label' => 'Anthropic Claude API Key',             'type' => 'password', 'required' => false, 'hint' => 'From console.anthropic.com/keys (starts with sk-ant-...)'],
            ['key' => 'anthropic_model',        'label' => 'Anthropic Claude Default Model',        'type' => 'text',     'required' => false, 'hint' => 'e.g. claude-3-5-sonnet-20241022'],
        ],
        'crm_hubspot' => [
            ['key' => 'access_token',           'label' => 'HubSpot Private App Access Token / API Key', 'type' => 'password', 'required' => true,  'hint' => 'From HubSpot Settings → Integrations → Private Apps (starts with pat-...)'],
            ['key' => 'portal_id',              'label' => 'HubSpot Portal / Hub ID',             'type' => 'text',     'required' => false, 'hint' => 'Your HubSpot Account Portal ID'],
            ['key' => 'sync_direction',         'label' => 'Sync Direction',                      'type' => 'select',   'required' => true,  'options' => ['two_way' => 'Two-Way (Growbridge ↔ HubSpot)', 'outbound_only' => 'Growbridge → HubSpot Only', 'inbound_only' => 'HubSpot → Growbridge Only']],
            ['key' => 'sync_frequency',         'label' => 'Sync Frequency',                      'type' => 'select',   'required' => true,  'options' => ['realtime' => 'Real-Time Webhooks', 'hourly' => 'Hourly Background Sync', 'daily' => 'Daily Sync', 'manual' => 'Manual Only']],
        ],
        'crm_salesforce' => [
            ['key' => 'instance_url',           'label' => 'Salesforce Instance URL',             'type' => 'text',     'required' => true,  'hint' => 'e.g. https://yourcompany.my.salesforce.com'],
            ['key' => 'access_token',           'label' => 'OAuth Access Token / Session Token',  'type' => 'password', 'required' => false, 'hint' => 'Salesforce Connected App Bearer Token'],
            ['key' => 'client_id',              'label' => 'Connected App Consumer Key (Client ID)', 'type' => 'text', 'required' => false],
            ['key' => 'client_secret',          'label' => 'Connected App Consumer Secret',       'type' => 'password', 'required' => false],
            ['key' => 'sync_direction',         'label' => 'Sync Direction',                      'type' => 'select',   'required' => true,  'options' => ['two_way' => 'Two-Way (Growbridge ↔ Salesforce)', 'outbound_only' => 'Growbridge → Salesforce Only', 'inbound_only' => 'Salesforce → Growbridge Only']],
        ],
        'crm_zoho' => [
            ['key' => 'access_token',           'label' => 'Zoho CRM Access Token',               'type' => 'password', 'required' => false, 'hint' => 'Zoho OAuth Permanent / Refresh Token'],
            ['key' => 'client_id',              'label' => 'Zoho Client ID',                      'type' => 'text',     'required' => false],
            ['key' => 'client_secret',          'label' => 'Zoho Client Secret',                  'type' => 'password', 'required' => false],
            ['key' => 'data_center',            'label' => 'Zoho Data Center Domain',             'type' => 'select',   'required' => true,  'options' => ['com' => 'United States (.com)', 'in' => 'India (.in)', 'eu' => 'Europe (.eu)', 'com.au' => 'Australia (.com.au)']],
            ['key' => 'sync_direction',         'label' => 'Sync Direction',                      'type' => 'select',   'required' => true,  'options' => ['two_way' => 'Two-Way (Growbridge ↔ Zoho)', 'outbound_only' => 'Growbridge → Zoho Only', 'inbound_only' => 'Zoho → Growbridge Only']],
        ],
        'crm_pipedrive' => [
            ['key' => 'api_token',              'label' => 'Pipedrive Personal API Token',        'type' => 'password', 'required' => true,  'hint' => 'From Pipedrive Settings → Personal Preferences → API'],
            ['key' => 'company_domain',         'label' => 'Pipedrive Company Domain',            'type' => 'text',     'required' => false, 'hint' => 'e.g. yourcompany (from yourcompany.pipedrive.com)'],
            ['key' => 'sync_direction',         'label' => 'Sync Direction',                      'type' => 'select',   'required' => true,  'options' => ['two_way' => 'Two-Way (Growbridge ↔ Pipedrive)', 'outbound_only' => 'Growbridge → Pipedrive Only', 'inbound_only' => 'Pipedrive → Growbridge Only']],
        ],
        'crm_freshsales' => [
            ['key' => 'domain',                 'label' => 'Freshsales Domain / Bundle URL',      'type' => 'text',     'required' => true,  'hint' => 'e.g. yourcompany.freshsales.io'],
            ['key' => 'api_key',                'label' => 'Freshsales API Key',                  'type' => 'password', 'required' => true,  'hint' => 'From Freshsales Profile Settings → API Settings'],
            ['key' => 'sync_direction',         'label' => 'Sync Direction',                      'type' => 'select',   'required' => true,  'options' => ['two_way' => 'Two-Way (Growbridge ↔ Freshsales)', 'outbound_only' => 'Growbridge → Freshsales Only', 'inbound_only' => 'Freshsales → Growbridge Only']],
        ],
        'crm_dynamics' => [
            ['key' => 'resource_url',           'label' => 'Microsoft Dynamics 365 Org URL',      'type' => 'text',     'required' => true,  'hint' => 'e.g. https://orgXXXXX.crm.dynamics.com'],
            ['key' => 'tenant_id',              'label' => 'Azure AD Tenant ID',                  'type' => 'text',     'required' => false, 'hint' => 'Directory (tenant) ID from Azure Portal'],
            ['key' => 'client_id',              'label' => 'Azure App Registration Client ID',    'type' => 'text',     'required' => false],
            ['key' => 'client_secret',          'label' => 'Azure Client Secret',                 'type' => 'password', 'required' => false],
            ['key' => 'access_token',           'label' => 'Access Token (Optional Direct Token)','type' => 'password', 'required' => false],
            ['key' => 'sync_direction',         'label' => 'Sync Direction',                      'type' => 'select',   'required' => true,  'options' => ['two_way' => 'Two-Way (Growbridge ↔ Dynamics 365)', 'outbound_only' => 'Growbridge → Dynamics Only', 'inbound_only' => 'Dynamics → Growbridge Only']],
        ],
        'crm_gohighlevel' => [
            ['key' => 'api_key',                'label' => 'GoHighLevel API Key / Access Token',  'type' => 'password', 'required' => true,  'hint' => 'API v2 Key or OAuth Access Token'],
            ['key' => 'location_id',            'label' => 'Location / Sub-Account ID',           'type' => 'text',     'required' => false, 'hint' => 'Sub-account location ID in GoHighLevel'],
            ['key' => 'sync_direction',         'label' => 'Sync Direction',                      'type' => 'select',   'required' => true,  'options' => ['two_way' => 'Two-Way (Growbridge ↔ GoHighLevel)', 'outbound_only' => 'Growbridge → GoHighLevel Only', 'inbound_only' => 'GoHighLevel → Growbridge Only']],
        ],
        'crm_custom' => [
            ['key' => 'base_url',               'label' => 'Custom CRM API Base URL',             'type' => 'text',     'required' => true,  'hint' => 'e.g. https://api.yourcrm.com/v1'],
            ['key' => 'auth_type',              'label' => 'Authentication Type',                 'type' => 'select',   'required' => true,  'options' => ['bearer' => 'Bearer Token', 'api_key_header' => 'Custom Header (API Key)', 'basic' => 'HTTP Basic Auth']],
            ['key' => 'auth_token',             'label' => 'API Token / Key',                     'type' => 'password', 'required' => false, 'hint' => 'Secret token or API key value'],
            ['key' => 'auth_header_name',       'label' => 'Custom Header Name (if applicable)',  'type' => 'text',     'required' => false, 'hint' => 'Default: X-API-Key'],
            ['key' => 'contacts_endpoint',      'label' => 'Contacts Endpoint Path',              'type' => 'text',     'required' => false, 'hint' => 'Default: /contacts'],
            ['key' => 'leads_endpoint',         'label' => 'Leads Endpoint Path',                 'type' => 'text',     'required' => false, 'hint' => 'Default: /leads'],
            ['key' => 'activities_endpoint',    'label' => 'Activities Endpoint Path',            'type' => 'text',     'required' => false, 'hint' => 'Default: /activities'],
            ['key' => 'sync_direction',         'label' => 'Sync Direction',                      'type' => 'select',   'required' => true,  'options' => ['two_way' => 'Two-Way Sync', 'outbound_only' => 'Outbound Only', 'inbound_only' => 'Inbound Only']],
        ],
        'crm_webhook' => [
            ['key' => 'outbound_webhook_url',   'label' => 'Outbound Webhook URL',                'type' => 'text',     'required' => false, 'hint' => 'URL where Growbridge Connect will POST contact and conversation events'],
            ['key' => 'webhook_secret',         'label' => 'Webhook Secret / Signature Key',      'type' => 'password', 'required' => false, 'hint' => 'Used to sign payloads with HMAC-SHA256'],
        ],
        'oauth_linkedin' => [
            ['key' => 'client_id',     'label' => 'Client ID',     'type' => 'text',     'required' => true],
            ['key' => 'client_secret', 'label' => 'Client Secret', 'type' => 'password', 'required' => true],
        ],
        'oauth_twitter' => [
            ['key' => 'client_id',     'label' => 'Client ID',     'type' => 'text',     'required' => true],
            ['key' => 'client_secret', 'label' => 'Client Secret', 'type' => 'password', 'required' => true],
        ],
        'oauth_youtube' => [
            ['key' => 'client_id',     'label' => 'Client ID',     'type' => 'text',     'required' => true],
            ['key' => 'client_secret', 'label' => 'Client Secret', 'type' => 'password', 'required' => true],
        ],
        'oauth_tiktok' => [
            ['key' => 'client_key',    'label' => 'Client Key',    'type' => 'text',     'required' => true],
            ['key' => 'client_secret', 'label' => 'Client Secret', 'type' => 'password', 'required' => true],
        ],
        'oauth_shopify' => [
            ['key' => 'client_id',     'label' => 'API Key (Client ID)',        'type' => 'text',     'required' => true,  'hint' => 'From your Shopify Partner app → Client credentials'],
            ['key' => 'client_secret', 'label' => 'API Secret Key (Client Secret)', 'type' => 'password', 'required' => true],
        ],
        'oauth_bigcommerce' => [
            ['key' => 'client_id',     'label' => 'Client ID',     'type' => 'text',     'required' => true,  'hint' => 'From your BigCommerce Dev Portal app'],
            ['key' => 'client_secret', 'label' => 'Client Secret', 'type' => 'password', 'required' => true],
        ],
        'llm_openai_default' => [
            ['key' => 'api_key',        'label' => 'API Key',        'type' => 'password', 'required' => true],
            ['key' => 'organization_id', 'label' => 'Organization ID', 'type' => 'text',     'required' => false],
        ],
        'llm_anthropic_default' => [
            ['key' => 'api_key', 'label' => 'API Key', 'type' => 'password', 'required' => true],
        ],
        'llm_gemini_default' => [
            ['key' => 'api_key', 'label' => 'API Key', 'type' => 'password', 'required' => true],
        ],
        'google_places' => [
            ['key' => 'api_key', 'label' => 'API Key', 'type' => 'password', 'required' => true],
        ],
        'google_workspace' => [
            ['key' => 'client_id',     'label' => 'OAuth Client ID',     'type' => 'text',     'required' => true,  'hint' => 'Google Cloud Console → APIs & Services → Credentials → OAuth client (Web).'],
            ['key' => 'client_secret', 'label' => 'OAuth Client Secret', 'type' => 'password', 'required' => true],
            ['key' => 'refresh_token', 'label' => 'Refresh Token',       'type' => 'password', 'required' => true,  'hint' => 'Offline-access refresh token with Sheets, Docs, Drive, Calendar & Forms scopes (e.g. via the OAuth Playground).'],
        ],
        'qdrant' => [
            ['key' => 'url',     'label' => 'Qdrant URL',   'type' => 'text',     'required' => true],
            ['key' => 'api_key', 'label' => 'API Key',       'type' => 'password', 'required' => false],
        ],

        'storage_local' => [
            // No credentials required — uses server disk
        ],

        'storage_s3' => [
            ['key' => 'key',                    'label' => 'Access Key ID',          'type' => 'text',     'required' => true],
            ['key' => 'secret',                 'label' => 'Secret Access Key',      'type' => 'password', 'required' => true],
            ['key' => 'region',                 'label' => 'Region',                 'type' => 'text',     'required' => true],
            ['key' => 'bucket',                 'label' => 'Bucket Name',            'type' => 'text',     'required' => true],
            ['key' => 'url',                    'label' => 'Custom URL (optional)',   'type' => 'text',     'required' => false],
            ['key' => 'directory_prefix',       'label' => 'Directory Prefix',       'type' => 'text',     'required' => false],
        ],

        'storage_do' => [
            ['key' => 'key',                    'label' => 'Spaces Access Key',      'type' => 'text',     'required' => true],
            ['key' => 'secret',                 'label' => 'Spaces Secret Key',      'type' => 'password', 'required' => true],
            ['key' => 'region',                 'label' => 'Region (e.g. nyc3)',     'type' => 'text',     'required' => true],
            ['key' => 'bucket',                 'label' => 'Space Name (bucket)',    'type' => 'text',     'required' => true],
            ['key' => 'endpoint',               'label' => 'Endpoint URL',           'type' => 'text',     'required' => true],
            ['key' => 'url',                    'label' => 'CDN / Custom URL',       'type' => 'text',     'required' => false],
            ['key' => 'directory_prefix',       'label' => 'Directory Prefix',       'type' => 'text',     'required' => false],
        ],

        'storage_wasabi' => [
            ['key' => 'key',                    'label' => 'Access Key ID',          'type' => 'text',     'required' => true],
            ['key' => 'secret',                 'label' => 'Secret Access Key',      'type' => 'password', 'required' => true],
            ['key' => 'region',                 'label' => 'Region (e.g. us-east-1)', 'type' => 'text',     'required' => true],
            ['key' => 'bucket',                 'label' => 'Bucket Name',            'type' => 'text',     'required' => true],
            ['key' => 'endpoint',               'label' => 'Endpoint URL',           'type' => 'text',     'required' => true],
            ['key' => 'url',                    'label' => 'Custom URL (optional)',   'type' => 'text',     'required' => false],
            ['key' => 'directory_prefix',       'label' => 'Directory Prefix',       'type' => 'text',     'required' => false],
        ],
    ];

    protected $fillable = [
        'provider',
        'label',
        'mode',
        'enabled',
        'is_default',
        'credentials',
        'webhook_secret',
        'meta_json',
        'updated_by_admin_id',
        'last_tested_at',
        'last_test_status',
        'last_test_message',
    ];

    protected $hidden = ['credentials', 'webhook_secret'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'is_default' => 'boolean',
            'credentials' => 'encrypted:array',
            'webhook_secret' => 'encrypted',
            'meta_json' => 'array',
            'last_tested_at' => 'datetime',
        ];
    }

    public static function forProvider(string $provider, string $mode = 'live'): ?self
    {
        return static::where('provider', $provider)->where('mode', $mode)->first();
    }

    public function isConfigured(): bool
    {
        // Local storage needs no credentials — it is always considered configured
        if ($this->provider === 'storage_local') {
            return true;
        }

        $creds = $this->credentials ?? [];

        return ! empty($creds) && collect($creds)->filter()->isNotEmpty();
    }

    /** Returns a fixed-length masked preview — never reveals actual credential content. */
    public function maskedCredentials(): array
    {
        $creds = $this->credentials ?? [];
        $result = [];
        foreach ($creds as $k => $v) {
            $result[$k] = (string) $v === '' ? '' : '••••••••••••';
        }

        return $result;
    }
}
