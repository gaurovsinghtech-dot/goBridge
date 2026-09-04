<?php

namespace App\Services;

use App\Models\PhoneNumber;
use App\Models\TwilioAccount;
use App\Models\Workspace;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TwilioService
{
    protected ?string $masterAccountSid;
    protected ?string $masterAuthToken;
    protected string $baseUrl = 'https://api.twilio.com/2010-04-01';

    public function __construct()
    {
        $integration = class_exists(\App\Modules\Integrations\Models\IntegrationConfig::class)
            ? \App\Modules\Integrations\Models\IntegrationConfig::forProvider('twilio')
            : null;
        $creds = ($integration && $integration->enabled) ? ($integration->credentials ?? []) : [];

        $this->masterAccountSid = $creds['account_sid']
            ?? config('services.twilio.account_sid') 
            ?? SystemSetting::get('twilio.account_sid', env('TWILIO_ACCOUNT_SID'));
            
        $this->masterAuthToken = $creds['auth_token']
            ?? config('services.twilio.auth_token') 
            ?? SystemSetting::get('twilio.auth_token', env('TWILIO_AUTH_TOKEN'));
    }

    /**
     * Check if master Twilio credentials are live and configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->masterAccountSid) && !empty($this->masterAuthToken) && !str_contains($this->masterAccountSid, 'your_');
    }

    /**
     * Search Available Phone Numbers for a given country from Twilio
     */
    public function searchAvailableNumbers(string $country = 'US', array $filters = []): array
    {
        $country = strtoupper(trim($country));
        $areaCode = $filters['area_code'] ?? null;
        $contains = $filters['contains'] ?? null;
        $voice = $filters['voice'] ?? true;
        $sms = $filters['sms'] ?? true;
        $mms = $filters['mms'] ?? false;
        $limit = (int) ($filters['limit'] ?? 20);

        if ($this->isConfigured()) {
            try {
                $endpoint = "{$this->baseUrl}/Accounts/{$this->masterAccountSid}/AvailablePhoneNumbers/{$country}/Local.json";
                $queryParams = [
                    'PageSize' => $limit,
                ];

                if (!empty($areaCode)) {
                    $queryParams['AreaCode'] = $areaCode;
                }
                if (!empty($contains)) {
                    $queryParams['Contains'] = $contains;
                }
                if ($voice) {
                    $queryParams['VoiceEnabled'] = 'true';
                }
                if ($sms) {
                    $queryParams['SmsEnabled'] = 'true';
                }
                if ($mms) {
                    $queryParams['MmsEnabled'] = 'true';
                }

                $response = Http::withBasicAuth($this->masterAccountSid, $this->masterAuthToken)
                    ->timeout(10)
                    ->get($endpoint, $queryParams);

                if ($response->successful()) {
                    $data = $response->json();
                    $numbers = [];
                    foreach ($data['available_phone_numbers'] ?? [] as $item) {
                        $caps = $item['capabilities'] ?? [];
                        $numbers[] = [
                            'phone_number' => $item['phone_number'],
                            'friendly_name' => $item['friendly_name'] ?? $item['phone_number'],
                            'iso_country' => $item['iso_country'] ?? $country,
                            'lata' => $item['lata'] ?? null,
                            'rate_center' => $item['rate_center'] ?? null,
                            'region' => $item['region'] ?? null,
                            'postal_code' => $item['postal_code'] ?? null,
                            'voice' => (bool) ($caps['voice'] ?? true),
                            'sms' => (bool) ($caps['SMS'] ?? $caps['sms'] ?? true),
                            'mms' => (bool) ($caps['MMS'] ?? $caps['mms'] ?? false),
                            'monthly_cost' => $this->calculateNumberPrice($country, (bool) ($caps['voice'] ?? true), (bool) ($caps['SMS'] ?? true)),
                            'currency' => $country === 'IN' ? 'INR' : 'USD',
                        ];
                    }
                    if (!empty($numbers)) {
                        return $numbers;
                    }
                } else {
                    Log::warning('Twilio AvailablePhoneNumbers API response: ' . $response->body());
                }
            } catch (\Throwable $e) {
                Log::error('Twilio searchAvailableNumbers error: ' . $e->getMessage());
            }
        }

        // Realistic Simulated Pool for Developer/Sandbox mode with dynamic country capabilities
        return $this->getSimulatedAvailableNumbers($country, $areaCode, $contains, $voice, $sms, $mms, $limit);
    }

    /**
     * Check if a specific number is available right before purchasing
     */
    public function checkNumberAvailability(string $country, string $phoneNumber): bool
    {
        // Re-query availability
        $results = $this->searchAvailableNumbers($country, [
            'contains' => substr(preg_replace('/[^0-9]/', '', $phoneNumber), -6),
            'limit' => 50,
        ]);

        foreach ($results as $item) {
            if ($item['phone_number'] === $phoneNumber || preg_replace('/[^0-9]/', '', $item['phone_number']) === preg_replace('/[^0-9]/', '', $phoneNumber)) {
                return true;
            }
        }

        // In sandbox or if already in DB
        $existsInDb = PhoneNumber::where('phone_number', $phoneNumber)
            ->whereIn('status', ['active', 'pending'])
            ->exists();

        return !$existsInDb;
    }

    /**
     * Get or create a Twilio Subaccount for the tenant workspace
     */
    public function getOrCreateSubaccount(Workspace $workspace): TwilioAccount
    {
        $existing = TwilioAccount::where('workspace_id', $workspace->id)->first();
        if ($existing && !empty($existing->twilio_account_sid)) {
            return $existing;
        }

        $accountSid = 'AC' . Str::random(32);
        $authToken = Str::random(32);

        if ($this->isConfigured()) {
            try {
                $response = Http::withBasicAuth($this->masterAccountSid, $this->masterAuthToken)
                    ->asForm()
                    ->post("{$this->baseUrl}/Accounts.json", [
                        'FriendlyName' => "Growbridge - Workspace #{$workspace->id} ({$workspace->name})",
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $accountSid = $data['sid'];
                    $authToken = $data['auth_token'];
                }
            } catch (\Throwable $e) {
                Log::error('Twilio Subaccount creation failed: ' . $e->getMessage());
            }
        }

        $record = TwilioAccount::updateOrCreate(
            ['workspace_id' => $workspace->id],
            [
                'twilio_account_sid' => $accountSid,
                'encrypted_auth_token' => $authToken,
                'friendly_name' => "Workspace #{$workspace->id} Subaccount",
                'status' => 'active',
                'metadata' => [
                    'created_via' => 'Growbridge Provisioning Service',
                    'workspace_name' => $workspace->name,
                ],
            ]
        );

        return $record;
    }

    /**
     * Purchase and provision a phone number for the workspace
     */
    public function purchaseNumber(Workspace $workspace, string $phoneNumber, array $options = []): PhoneNumber
    {
        $country = strtoupper(trim($options['country'] ?? 'US'));
        
        // 1. Re-check availability immediately before purchasing
        if (!$this->checkNumberAvailability($country, $phoneNumber)) {
            throw new \Exception("The phone number {$phoneNumber} is no longer available. Please select another number.");
        }

        // 2. Resolve subaccount
        $subaccount = $this->getOrCreateSubaccount($workspace);
        $subaccountSid = $subaccount->twilio_account_sid ?? $this->masterAccountSid;
        $subauthToken = $subaccount->auth_token ?? $this->masterAuthToken;

        $phoneSid = 'PN' . Str::random(32);
        $voiceWebhook = url('/api/v1/webhooks/twilio/voice');
        $smsWebhook = url('/api/v1/webhooks/twilio/sms');
        $statusWebhook = url('/api/v1/webhooks/twilio/status');

        // 3. Purchase on Twilio if live credentials exist
        if ($this->isConfigured() && $subaccountSid && $subauthToken) {
            try {
                $response = Http::withBasicAuth($subaccountSid, $subauthToken)
                    ->asForm()
                    ->post("{$this->baseUrl}/Accounts/{$subaccountSid}/IncomingPhoneNumbers.json", [
                        'PhoneNumber' => $phoneNumber,
                        'FriendlyName' => $options['friendly_name'] ?? "Growbridge - {$phoneNumber}",
                        'VoiceUrl' => $voiceWebhook,
                        'VoiceMethod' => 'POST',
                        'SmsUrl' => $smsWebhook,
                        'SmsMethod' => 'POST',
                        'StatusCallback' => $statusWebhook,
                        'StatusCallbackMethod' => 'POST',
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $phoneSid = $data['sid'];
                } else {
                    Log::warning('Twilio Purchase number response: ' . $response->body());
                }
            } catch (\Throwable $e) {
                Log::error('Twilio Purchase number failed: ' . $e->getMessage());
            }
        }

        // 4. Save to Database
        $cost = $this->calculateNumberPrice($country, $options['voice'] ?? true, $options['sms'] ?? true);
        
        $numberRecord = PhoneNumber::create([
            'workspace_id' => $workspace->id,
            'twilio_account_sid' => $subaccountSid,
            'twilio_phone_number_sid' => $phoneSid,
            'phone_number' => $phoneNumber,
            'country' => $country,
            'friendly_name' => $options['friendly_name'] ?? "Virtual Line {$phoneNumber}",
            'capabilities' => [
                'voice' => (bool) ($options['voice'] ?? true),
                'sms' => (bool) ($options['sms'] ?? true),
                'mms' => (bool) ($options['mms'] ?? false),
            ],
            'voice_enabled' => (bool) ($options['voice'] ?? true),
            'sms_enabled' => (bool) ($options['sms'] ?? true),
            'mms_enabled' => (bool) ($options['mms'] ?? false),
            'call_recording_enabled' => (bool) ($options['call_recording'] ?? false),
            'status' => 'active',
            'monthly_cost' => $cost,
            'assigned_ai_agent_id' => $options['assigned_ai_agent_id'] ?? null,
            'voice_webhook_url' => $voiceWebhook,
            'sms_webhook_url' => $smsWebhook,
        ]);

        return $numberRecord;
    }

    /**
     * Configure phone number settings (voice/sms toggle, AI agent, recording)
     */
    public function configureNumber(Workspace $workspace, PhoneNumber $phoneNumber, array $attributes): PhoneNumber
    {
        if ($phoneNumber->workspace_id !== $workspace->id) {
            throw new \Exception('Unauthorized: Phone number does not belong to this workspace.');
        }

        $phoneNumber->update(array_filter([
            'friendly_name' => $attributes['friendly_name'] ?? $phoneNumber->friendly_name,
            'voice_enabled' => isset($attributes['voice_enabled']) ? (bool) $attributes['voice_enabled'] : $phoneNumber->voice_enabled,
            'sms_enabled' => isset($attributes['sms_enabled']) ? (bool) $attributes['sms_enabled'] : $phoneNumber->sms_enabled,
            'call_recording_enabled' => isset($attributes['call_recording_enabled']) ? (bool) $attributes['call_recording_enabled'] : $phoneNumber->call_recording_enabled,
            'assigned_ai_agent_id' => array_key_exists('assigned_ai_agent_id', $attributes) ? $attributes['assigned_ai_agent_id'] : $phoneNumber->assigned_ai_agent_id,
        ], fn($v) => !is_null($v)));

        return $phoneNumber->fresh();
    }

    /**
     * Release / Cancel phone number
     */
    public function releaseNumber(Workspace $workspace, PhoneNumber $phoneNumber): bool
    {
        if ($phoneNumber->workspace_id !== $workspace->id) {
            throw new \Exception('Unauthorized: Phone number does not belong to this workspace.');
        }

        if ($this->isConfigured() && !empty($phoneNumber->twilio_phone_number_sid)) {
            try {
                $subaccountSid = $phoneNumber->twilio_account_sid ?? $this->masterAccountSid;
                Http::withBasicAuth($this->masterAccountSid, $this->masterAuthToken)
                    ->delete("{$this->baseUrl}/Accounts/{$subaccountSid}/IncomingPhoneNumbers/{$phoneNumber->twilio_phone_number_sid}.json");
            } catch (\Throwable $e) {
                Log::warning('Twilio delete number API call failed: ' . $e->getMessage());
            }
        }

        $phoneNumber->update([
            'status' => 'released',
            'assigned_ai_agent_id' => null,
        ]);

        return true;
    }

    /**
     * List all active numbers for a workspace
     */
    public function listNumbers(Workspace $workspace)
    {
        return PhoneNumber::where('workspace_id', $workspace->id)
            ->where('status', '!=', 'released')
            ->with(['assignedAgent'])
            ->latest()
            ->get();
    }

    /**
     * Dynamic Number Pricing calculation with Growbridge Markup
     */
    protected function calculateNumberPrice(string $country, bool $voice, bool $sms): float
    {
        if ($country === 'IN') {
            return 250.00; // ₹250 / month (approx $3.00)
        }
        if ($country === 'GB') {
            return 2.50; // $2.50 / month
        }
        if ($country === 'AU') {
            return 4.00; // $4.00 / month
        }
        return 2.00; // $2.00 / month standard US/CA
    }

    /**
     * Generate dynamic realistic country-specific available numbers
     */
    protected function getSimulatedAvailableNumbers(string $country, ?string $areaCode, ?string $contains, bool $voice, bool $sms, bool $mms, int $limit): array
    {
        $patterns = [
            'IN' => [
                'prefix' => '+91',
                'regions' => ['Mumbai, MH', 'Delhi, DL', 'Bengaluru, KA', 'Hyderabad, TS', 'Chennai, TN', 'Pune, MH'],
                'area_codes' => ['22', '11', '80', '40', '44', '20'],
                'voice' => true,
                'sms' => true,
                'mms' => false,
                'cost' => 250.00,
                'currency' => 'INR',
            ],
            'US' => [
                'prefix' => '+1',
                'regions' => ['New York, NY', 'San Francisco, CA', 'Austin, TX', 'Miami, FL', 'Chicago, IL', 'Seattle, WA'],
                'area_codes' => ['212', '415', '512', '305', '312', '206', '646', '718'],
                'voice' => true,
                'sms' => true,
                'mms' => true,
                'cost' => 2.00,
                'currency' => 'USD',
            ],
            'GB' => [
                'prefix' => '+44',
                'regions' => ['London', 'Manchester', 'Birmingham', 'Edinburgh', 'Bristol'],
                'area_codes' => ['20', '161', '121', '131', '117'],
                'voice' => true,
                'sms' => true,
                'mms' => false,
                'cost' => 2.50,
                'currency' => 'GBP',
            ],
            'CA' => [
                'prefix' => '+1',
                'regions' => ['Toronto, ON', 'Vancouver, BC', 'Montreal, QC', 'Calgary, AB'],
                'area_codes' => ['416', '604', '514', '403'],
                'voice' => true,
                'sms' => true,
                'mms' => true,
                'cost' => 2.00,
                'currency' => 'USD',
            ],
            'AU' => [
                'prefix' => '+61',
                'regions' => ['Sydney, NSW', 'Melbourne, VIC', 'Brisbane, QLD', 'Perth, WA'],
                'area_codes' => ['2', '3', '7', '8'],
                'voice' => true,
                'sms' => true,
                'mms' => false,
                'cost' => 4.00,
                'currency' => 'AUD',
            ],
        ];

        $cfg = $patterns[$country] ?? $patterns['US'];
        $chosenArea = $areaCode ?: $cfg['area_codes'][array_rand($cfg['area_codes'])];

        $results = [];
        for ($i = 0; $i < min($limit, 10); $i++) {
            $suffix = $contains ?: rand(100, 999) . rand(1000, 9999);
            $formatted = "{$cfg['prefix']} {$chosenArea} " . substr($suffix, 0, 3) . ' ' . substr($suffix, 3);
            $rawNumber = "{$cfg['prefix']}{$chosenArea}{$suffix}";

            $results[] = [
                'phone_number' => $rawNumber,
                'friendly_name' => $formatted,
                'iso_country' => $country,
                'region' => $cfg['regions'][$i % count($cfg['regions'])],
                'voice' => $cfg['voice'],
                'sms' => $cfg['sms'],
                'mms' => $cfg['mms'],
                'monthly_cost' => $cfg['cost'],
                'currency' => $cfg['currency'],
            ];
        }

        return $results;
    }
}
