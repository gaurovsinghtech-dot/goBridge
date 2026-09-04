<?php

namespace App\Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Modules\Integrations\Models\IntegrationAuditLog;
use App\Modules\Integrations\Models\IntegrationConfig;
use App\Modules\Integrations\Services\ConnectionTester;
use App\Services\StorageManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationConfigController extends Controller
{
    /** MVP providers displayed on the main admin integrations hub */
    private const MVP_ORDERED_PROVIDERS = [
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
    ];

    public function index(): Response
    {
        // Ensure local storage default exists
        IntegrationConfig::firstOrCreate(
            ['provider' => 'storage_local', 'mode' => 'live'],
            [
                'label' => IntegrationConfig::LABELS['storage_local'],
                'enabled' => true,
                'is_default' => true,
                'last_test_status' => 'ok',
                'last_test_message' => 'Default local disk active (storage/app/public).',
            ]
        );

        $configs = IntegrationConfig::whereIn('provider', IntegrationConfig::PROVIDERS)->get()->keyBy('provider');

        // Build MVP grouped list
        $grouped = [
            'Core Platform' => [],
            'CRM & Business Systems' => [],
            'Optional Services' => [],
            'Advanced Storage' => [],
        ];

        foreach (self::MVP_ORDERED_PROVIDERS as $provider) {
            $config = $configs->get($provider);
            $category = IntegrationConfig::CATEGORIES[$provider] ?? 'Core Platform';

            $displayData = [
                'provider' => $provider,
                'label' => IntegrationConfig::LABELS[$provider] ?? $provider,
                'category' => $category,
                'enabled' => $config?->enabled ?? ($provider === 'storage_local'),
                'is_default' => $config?->is_default ?? ($provider === 'storage_local'),
                'mode' => $config?->mode ?? 'live',
                'configured' => $config?->isConfigured() ?? ($provider === 'storage_local'),
                'last_test_status' => $config?->last_test_status ?? ($provider === 'storage_local' ? 'ok' : 'untested'),
                'last_test_message' => $config?->last_test_message ?? ($provider === 'storage_local' ? 'Local storage is ready.' : null),
                'last_tested_at' => $config?->last_tested_at?->toISOString(),
            ];

            if ($provider === 'ai_providers' && $config) {
                $creds = $config->credentials ?? [];
                $displayData['default_provider'] = $creds['default_provider'] ?? 'openai';
            }

            if (str_starts_with($provider, 'crm_') && $config) {
                $creds = $config->credentials ?? [];
                $displayData['sync_direction'] = $creds['sync_direction'] ?? 'two_way';
            }

            $grouped[$category][] = $displayData;
        }

        // Calculate Platform Setup / Launch Readiness
        $launchReadiness = $this->calculateLaunchReadiness($configs);

        return Inertia::render('Admin/Integrations/Index', [
            'grouped' => $grouped,
            'launchReadiness' => $launchReadiness,
        ]);
    }

    public function edit(string $provider): Response
    {
        abort_unless(in_array($provider, IntegrationConfig::PROVIDERS, true), 404);

        $config = IntegrationConfig::forProvider($provider) ?? new IntegrationConfig([
            'provider' => $provider,
            'label' => IntegrationConfig::LABELS[$provider] ?? $provider,
            'mode' => 'live',
            'enabled' => ($provider === 'storage_local'),
        ]);

        $credentials = $config->exists ? $config->maskedCredentials() : [];

        // If editing ai_providers and it's new, populate defaults from legacy llm_* if available
        if ($provider === 'ai_providers' && empty($credentials)) {
            $openai = IntegrationConfig::forProvider('llm_openai_default');
            $gemini = IntegrationConfig::forProvider('llm_gemini_default');
            $anthropic = IntegrationConfig::forProvider('llm_anthropic_default');

            $credentials = [
                'default_provider' => 'openai',
                'openai_api_key' => $openai?->isConfigured() ? '••••••••••••' : '',
                'openai_model' => 'gpt-4o-mini',
                'gemini_api_key' => $gemini?->isConfigured() ? '••••••••••••' : '',
                'gemini_model' => 'gemini-1.5-flash',
                'anthropic_api_key' => $anthropic?->isConfigured() ? '••••••••••••' : '',
                'anthropic_model' => 'claude-3-5-sonnet-20241022',
            ];
        }

        // Webhook and callback helpers for each provider
        $appUrl = rtrim(config('app.url') ?? url('/'), '/');
        $providerSlug = str_starts_with($provider, 'crm_') ? substr($provider, 4) : $provider;

        $webhookUrls = match (true) {
            $provider === 'twilio' => [
                'voice_webhook' => "{$appUrl}/api/v1/webhooks/twilio/voice",
                'sms_webhook' => "{$appUrl}/api/v1/webhooks/twilio/sms",
                'status_webhook' => "{$appUrl}/api/v1/webhooks/twilio/status",
            ],
            $provider === 'meta_app' => [
                'webhook_url' => "{$appUrl}/api/v1/webhooks/whatsapp",
                'verify_token' => $config->credentials['verify_token'] ?? 'growbridge_verify_token',
            ],
            str_starts_with($provider, 'crm_') => [
                'inbound_webhook_url' => "{$appUrl}/api/v1/webhooks/crm/{$providerSlug}",
            ],
            default => null,
        };

        return Inertia::render('Admin/Integrations/Edit', [
            'provider' => $provider,
            'label' => IntegrationConfig::LABELS[$provider] ?? $provider,
            'category' => IntegrationConfig::CATEGORIES[$provider] ?? 'Other',
            'fields' => IntegrationConfig::FIELDS[$provider] ?? [],
            'webhookUrls' => $webhookUrls,
            'callbackUrl' => match ($provider) {
                'oauth_shopify' => route('client.ecommerce.oauth.shopify.callback'),
                'oauth_bigcommerce' => route('client.ecommerce.oauth.bigcommerce.callback'),
                default => null,
            },
            'storageStats' => $provider === 'storage_s3' ? app(\App\Services\Storage\StorageService::class)->getStorageStats() : null,
            'config' => [
                'enabled' => $config->enabled ?? ($provider === 'storage_local'),
                'mode' => $config->mode ?? 'live',
                'last_test_status' => $config->last_test_status ?? 'untested',
                'last_test_message' => $config->last_test_message,
                'last_tested_at' => $config->last_tested_at?->toISOString(),
                'credentials' => $credentials,
            ],
        ]);
    }

    public function update(Request $request, string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, IntegrationConfig::PROVIDERS, true), 404);

        $fields = IntegrationConfig::FIELDS[$provider] ?? [];
        $rules = ['enabled' => ['required', 'boolean'], 'mode' => ['required', 'in:test,live']];
        foreach ($fields as $f) {
            $rules['credentials.'.$f['key']] = ['nullable', 'string', 'max:1024'];
        }

        $validated = $request->validate($rules);

        $config = IntegrationConfig::firstOrNew(['provider' => $provider, 'mode' => $validated['mode']]);

        // Merge credentials: skip masked values (••••xxxx) to preserve existing
        $existing = $config->credentials ?? [];
        $incoming = $validated['credentials'] ?? [];
        $merged = $existing;
        $changedKeys = [];

        foreach ($incoming as $k => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            if (preg_match('/^•+/', (string) $v)) {
                continue; // keep existing masked value
            }
            $merged[$k] = $v;
            $changedKeys[] = $k;
        }

        $wasEnabled = $config->enabled ?? false;
        $config->fill([
            'label' => IntegrationConfig::LABELS[$provider] ?? $provider,
            'enabled' => (bool) $validated['enabled'],
            'mode' => $validated['mode'],
            'credentials' => $merged,
            'updated_by_admin_id' => auth('admin')->id(),
        ])->save();

        $this->auditLog($request, $config, $config->wasRecentlyCreated ? 'create' : 'update', $changedKeys);
        if ($wasEnabled !== $config->enabled) {
            $this->auditLog($request, $config, $config->enabled ? 'enable' : 'disable', []);
        }

        // Special handling for Twilio
        if ($provider === 'twilio') {
            if (! empty($merged['account_sid'])) {
                SystemSetting::set('twilio.account_sid', $merged['account_sid'], 'string', 'Twilio Account SID');
            }
            if (! empty($merged['auth_token'])) {
                SystemSetting::set('twilio.auth_token', $merged['auth_token'], 'string', 'Twilio Auth Token');
            }
        }

        // Special synchronization for AI Providers with legacy llm_* records
        if ($provider === 'ai_providers') {
            $this->syncAiProviders($merged, (bool) $validated['enabled'], $validated['mode']);
        }

        if (str_starts_with($provider, 'storage_')) {
            app(StorageManager::class)->clearCache();
        }

        return back()->with('success', (IntegrationConfig::LABELS[$provider] ?? $provider).' integration saved successfully.');
    }

    public function test(Request $request, string $provider): RedirectResponse|JsonResponse
    {
        abort_unless(in_array($provider, IntegrationConfig::PROVIDERS, true), 404);

        $config = IntegrationConfig::forProvider($provider);
        if (! $config) {
            return response()->json(['ok' => false, 'message' => 'Please configure credentials before testing.']);
        }

        $result = app(ConnectionTester::class)->test($config);
        $this->auditLog($request, $config, 'test', []);

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function toggle(Request $request, string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, IntegrationConfig::PROVIDERS, true), 404);

        $config = IntegrationConfig::forProvider($provider);
        if (! $config) {
            return back()->with('error', 'Configure credentials before enabling.');
        }

        $updates = ['enabled' => ! $config->enabled];
        if ($config->enabled && ($config->is_default ?? false) && str_starts_with($provider, 'storage_')) {
            $updates['is_default'] = false;
        }
        $config->update($updates);
        $this->auditLog($request, $config, $config->enabled ? 'enable' : 'disable', []);

        if (str_starts_with($provider, 'storage_')) {
            app(StorageManager::class)->clearCache();
        }

        return back()->with('success', (IntegrationConfig::LABELS[$provider] ?? $provider).' '.($config->enabled ? 'enabled' : 'disabled').'.');
    }

    public function setDefault(Request $request, string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, IntegrationConfig::STORAGE_PROVIDERS, true), 404);

        $config = IntegrationConfig::forProvider($provider);
        if (! $config || ! $config->enabled) {
            return back()->with('error', 'Only an enabled storage provider can be set as default.');
        }

        IntegrationConfig::whereIn('provider', IntegrationConfig::STORAGE_PROVIDERS)
            ->where('provider', '!=', $provider)
            ->update(['is_default' => false]);

        $config->update(['is_default' => true]);
        $this->auditLog($request, $config, 'update', ['is_default']);

        app(StorageManager::class)->clearCache();

        return back()->with('success', (IntegrationConfig::LABELS[$provider] ?? $provider).' set as default storage.');
    }

    public function rotate(Request $request, string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, IntegrationConfig::PROVIDERS, true), 404);

        $config = IntegrationConfig::forProvider($provider);
        if (! $config) {
            return back()->with('error', 'Not configured.');
        }

        $secret = bin2hex(random_bytes(32));
        $config->update(['webhook_secret' => $secret, 'updated_by_admin_id' => auth('admin')->id()]);
        $this->auditLog($request, $config, 'rotate', ['webhook_secret']);

        return back()->with('success', 'Webhook secret rotated.');
    }

    public function auditLogIndex(Request $request): Response
    {
        $logs = IntegrationAuditLog::with('admin')
            ->latest('created_at')
            ->paginate(50);

        return Inertia::render('Admin/Integrations/AuditLog', ['logs' => $logs]);
    }

    /**
     * Calculate Launch Readiness state for production launch
     */
    private function calculateLaunchReadiness($configs): array
    {
        // 1. Database Check
        $dbConnected = true;
        try {
            DB::connection()->getPdo();
        } catch (\Throwable) {
            $dbConnected = false;
        }

        // 2. Local Storage Check
        $localStorage = $configs->get('storage_local');
        $storageReady = (bool) ($localStorage?->enabled && $localStorage?->is_default);

        // 3. Twilio Check
        $twilio = $configs->get('twilio');
        $twilioCreds = $twilio?->credentials ?? [];
        $twilioReady = (bool) ($twilio?->enabled && ! empty($twilioCreds['account_sid']) && ! empty($twilioCreds['auth_token']));

        // 4. Meta WhatsApp Check
        $meta = $configs->get('meta_app');
        $metaCreds = $meta?->credentials ?? [];
        $metaReady = (bool) ($meta?->enabled && ! empty($metaCreds['app_id']) && ! empty($metaCreds['app_secret']));

        // 5. AI Provider Check
        $ai = $configs->get('ai_providers');
        $aiCreds = $ai?->credentials ?? [];
        $defaultAi = $aiCreds['default_provider'] ?? 'openai';
        $aiKey = match ($defaultAi) {
            'gemini' => $aiCreds['gemini_api_key'] ?? '',
            'anthropic' => $aiCreds['anthropic_api_key'] ?? '',
            default => $aiCreds['openai_api_key'] ?? '',
        };
        $legacyOpenai = $configs->get('llm_openai_default');
        $aiReady = (bool) (($ai?->enabled && ! empty($aiKey)) || ($legacyOpenai?->enabled && $legacyOpenai?->isConfigured()));

        // Optional checks
        $places = $configs->get('google_places');
        $placesConfigured = (bool) ($places?->enabled && $places?->isConfigured());

        $hasCloudStorage = $configs->whereIn('provider', ['storage_s3', 'storage_do', 'storage_wasabi'])
            ->where('enabled', true)
            ->isNotEmpty();

        $items = [
            [
                'key' => 'database',
                'name' => 'Database',
                'desc' => 'Primary SQL connection and migrations',
                'required' => true,
                'status' => $dbConnected ? 'connected' : 'error',
                'message' => $dbConnected ? 'Database active & operational' : 'Database connection error',
            ],
            [
                'key' => 'storage_local',
                'name' => 'Local Storage',
                'desc' => 'Default file & media storage disk',
                'required' => true,
                'status' => $storageReady ? 'connected' : 'warning',
                'message' => $storageReady ? 'Active default storage (storage/app/public)' : 'Local storage not set as default',
            ],
            [
                'key' => 'twilio',
                'name' => 'Twilio',
                'desc' => 'Phone numbers, SMS, and Voice calling',
                'required' => true,
                'status' => $twilioReady ? 'connected' : 'not_configured',
                'message' => $twilioReady ? 'Twilio credentials active' : 'Twilio Account SID & Auth Token required',
                'configure_provider' => 'twilio',
            ],
            [
                'key' => 'meta_app',
                'name' => 'Meta WhatsApp',
                'desc' => 'WhatsApp Cloud API & Embedded Signup',
                'required' => true,
                'status' => $metaReady ? 'connected' : 'not_configured',
                'message' => $metaReady ? 'Meta WhatsApp API configured' : 'Meta App ID & Secret required',
                'configure_provider' => 'meta_app',
            ],
            [
                'key' => 'ai_providers',
                'name' => 'AI Provider',
                'desc' => 'OpenAI, Gemini, Claude LLM engine',
                'required' => true,
                'status' => $aiReady ? 'connected' : 'not_configured',
                'message' => $aiReady ? "AI Engine active (Default: {$defaultAi})" : 'Default AI API key required',
                'configure_provider' => 'ai_providers',
            ],
            [
                'key' => 'google_places',
                'name' => 'Google Places',
                'desc' => 'Business locations & address lookup',
                'required' => false,
                'status' => $placesConfigured ? 'connected' : 'optional',
                'message' => $placesConfigured ? 'Google Places active' : 'Optional location services',
                'configure_provider' => 'google_places',
            ],
            [
                'key' => 'cloud_storage',
                'name' => 'Cloud Storage',
                'desc' => 'Amazon S3, DigitalOcean, Wasabi',
                'required' => false,
                'status' => $hasCloudStorage ? 'connected' : 'optional',
                'message' => $hasCloudStorage ? 'Cloud storage configured' : 'Optional cloud backup storage',
                'configure_provider' => 'storage_s3',
            ],
        ];

        $requiredItems = array_filter($items, fn ($i) => $i['required']);
        $completedRequired = count(array_filter($requiredItems, fn ($i) => $i['status'] === 'connected'));
        $totalRequired = count($requiredItems);

        $blockedItem = collect($requiredItems)->firstWhere('status', '!==', 'connected');
        $isReady = ($completedRequired === $totalRequired);

        return [
            'is_ready' => $isReady,
            'completed_count' => $completedRequired,
            'total_required' => $totalRequired,
            'items' => $items,
            'blocked_reason' => $blockedItem ? "{$blockedItem['name']} integration is required before launching production." : null,
            'blocked_provider' => $blockedItem['configure_provider'] ?? null,
        ];
    }

    /**
     * Synchronize AI Provider credentials with legacy individual records for seamless compatibility
     */
    private function syncAiProviders(array $credentials, bool $enabled, string $mode): void
    {
        $adminId = auth('admin')->id();

        if (! empty($credentials['openai_api_key'])) {
            IntegrationConfig::updateOrCreate(
                ['provider' => 'llm_openai_default', 'mode' => $mode],
                [
                    'label' => 'OpenAI (Default)',
                    'enabled' => $enabled,
                    'credentials' => [
                        'api_key' => $credentials['openai_api_key'],
                        'organization_id' => $credentials['openai_organization_id'] ?? null,
                    ],
                    'updated_by_admin_id' => $adminId,
                ]
            );
        }

        if (! empty($credentials['gemini_api_key'])) {
            IntegrationConfig::updateOrCreate(
                ['provider' => 'llm_gemini_default', 'mode' => $mode],
                [
                    'label' => 'Google Gemini (Default)',
                    'enabled' => $enabled,
                    'credentials' => [
                        'api_key' => $credentials['gemini_api_key'],
                    ],
                    'updated_by_admin_id' => $adminId,
                ]
            );
        }

        if (! empty($credentials['anthropic_api_key'])) {
            IntegrationConfig::updateOrCreate(
                ['provider' => 'llm_anthropic_default', 'mode' => $mode],
                [
                    'label' => 'Anthropic Claude (Default)',
                    'enabled' => $enabled,
                    'credentials' => [
                        'api_key' => $credentials['anthropic_api_key'],
                    ],
                    'updated_by_admin_id' => $adminId,
                ]
            );
        }
    }

    private function auditLog(Request $request, IntegrationConfig $config, string $action, array $changedKeys): void
    {
        IntegrationAuditLog::create([
            'admin_user_id' => auth('admin')->id(),
            'integration_config_id' => $config->id,
            'provider' => $config->provider,
            'action' => $action,
            'diff_json' => $changedKeys,
            'ip' => $request->ip(),
            'user_agent' => substr($request->userAgent() ?? '', 0, 512),
        ]);
    }
}
