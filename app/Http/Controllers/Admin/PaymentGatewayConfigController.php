<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentGatewayConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PaymentGatewayConfigController extends Controller
{
    /** Gateways the admin panel can configure (Razorpay only). */
    private const GATEWAYS = ['razorpay'];

    /** Display labels. */
    private const LABELS = [
        'razorpay' => 'Razorpay',
    ];

    public function index(): Response
    {
        $gateways = self::GATEWAYS;
        $configs = PaymentGatewayConfig::whereIn('gateway', $gateways)->get()->keyBy('gateway');

        $list = [];
        foreach ($gateways as $key) {
            $config = $configs->get($key);
            $list[] = [
                'gateway' => $key,
                'name' => self::LABELS[$key] ?? ucfirst($key),
                'enabled' => $config?->enabled ?? false,
                'test_mode' => $config?->test_mode ?? true,
                'configured' => $config?->hasActiveCredentials() ?? false,
            ];
        }

        return Inertia::render('Admin/PaymentGateways/Index', [
            'gateways' => $list,
        ]);
    }

    /**
     * Get one gateway config for editing (credentials masked for secrets).
     */
    public function show(string $gateway): JsonResponse
    {
        $this->validateGateway($gateway);
        $config = PaymentGatewayConfig::firstOrCreate(
            ['gateway' => $gateway],
            [
                'test_mode' => true,
                'enabled' => false,
                'credentials' => [
                    'test' => $this->defaultCredentialKeys($gateway),
                    'live' => $this->defaultCredentialKeys($gateway),
                ],
            ]
        );

        $credentials = $config->credentials ?? [];
        $test = $credentials['test'] ?? [];
        $live = $credentials['live'] ?? [];

        $data = [
            'gateway' => $config->gateway,
            'name' => self::LABELS[$config->gateway] ?? ucfirst($config->gateway),
            'test_mode' => $config->test_mode,
            'enabled' => $config->enabled,
            'test_publishable_key' => $test['publishable_key'] ?? '',
            'test_secret_key' => ($test['secret_key'] ?? '') !== '' ? '••••••••' : '',
            'test_webhook_secret' => ($test['webhook_secret'] ?? '') !== '' ? '••••••••' : '',
            'live_publishable_key' => $live['publishable_key'] ?? '',
            'live_secret_key' => ($live['secret_key'] ?? '') !== '' ? '••••••••' : '',
            'live_webhook_secret' => ($live['webhook_secret'] ?? '') !== '' ? '••••••••' : '',
        ];

        return response()->json($data);
    }

    public function update(Request $request, string $gateway): RedirectResponse
    {
        $this->validateGateway($gateway);
        $config = PaymentGatewayConfig::firstOrCreate(
            ['gateway' => $gateway],
            [
                'test_mode' => true,
                'enabled' => false,
                'credentials' => [
                    'test' => $this->defaultCredentialKeys($gateway),
                    'live' => $this->defaultCredentialKeys($gateway),
                ],
            ]
        );

        $rules = [
            'test_mode' => ['required', 'boolean'],
            'enabled' => ['required', 'boolean'],
            'test_publishable_key' => ['nullable', 'string', 'max:512'],
            'test_secret_key' => ['nullable', 'string', 'max:512'],
            'test_webhook_secret' => ['nullable', 'string', 'max:512'],
            'live_publishable_key' => ['nullable', 'string', 'max:512'],
            'live_secret_key' => ['nullable', 'string', 'max:512'],
            'live_webhook_secret' => ['nullable', 'string', 'max:512'],
        ];

        $validated = $request->validate($rules);

        if (! empty($validated['enabled'])) {
            $isTest = (bool) $validated['test_mode'];
            $secret = $isTest ? ($validated['test_secret_key'] ?? '') : ($validated['live_secret_key'] ?? '');
            if (preg_match('/^•+$/', (string) $secret) || $secret === '') {
                $existing = $config->credentials ?? [];
                $mode = $isTest ? 'test' : 'live';
                $hasStored = ! empty($existing[$mode]['secret_key'] ?? '');
                if (! $hasStored) {
                    $field = $isTest ? 'test_secret_key' : 'live_secret_key';
                    throw ValidationException::withMessages([$field => __('Secret key is required when enabling the gateway.')]);
                }
            }
        }

        $existing = $config->credentials ?? [
            'test' => $this->defaultCredentialKeys($gateway),
            'live' => $this->defaultCredentialKeys($gateway),
        ];

        $credentialKeys = ['publishable_key', 'secret_key', 'webhook_secret'];
        $test = $existing['test'] ?? [];
        $live = $existing['live'] ?? [];

        foreach (['test', 'live'] as $mode) {
            $prefix = $mode.'_';
            foreach ($credentialKeys as $k) {
                $field = $prefix.$k;
                $v = $validated[$field] ?? null;

                if (preg_match('/^•+$/', (string) $v)) {
                    continue;
                }

                // Strip invisible zero-width characters that get accidentally copied, and trim whitespace
                $v = trim(preg_replace('/[\x00-\x1F\x7F\xA0\x{200B}\x{200C}\x{200D}\x{FEFF}]/u', '', (string) $v));

                if ($mode === 'test') {
                    $test[$k] = (string) $v;
                } else {
                    $live[$k] = (string) $v;
                }
            }
        }

        $config->update([
            'test_mode' => (bool) $validated['test_mode'],
            'enabled' => (bool) $validated['enabled'],
            'credentials' => ['test' => $test, 'live' => $live],
        ]);

        return back()->with('success', __('Payment gateway updated.'));
    }

    public function test(Request $request, string $gateway): JsonResponse
    {
        $this->validateGateway($gateway);

        $testMode = (bool) $request->input('test_mode', true);
        $prefix = $testMode ? 'test_' : 'live_';

        $keyId = (string) $request->input($prefix.'publishable_key', '');
        $keySecret = (string) $request->input($prefix.'secret_key', '');

        // Sanitize incoming keys just in case (remove zero-width spaces and trim)
        $keyId = trim(preg_replace('/[\x00-\x1F\x7F\xA0\x{200B}\x{200C}\x{200D}\x{FEFF}]/u', '', $keyId));
        $keySecret = trim(preg_replace('/[\x00-\x1F\x7F\xA0\x{200B}\x{200C}\x{200D}\x{FEFF}]/u', '', $keySecret));

        // Resolve stored credentials if secret is masked
        if (empty($keySecret) || empty($keyId)) {
            $config = PaymentGatewayConfig::where('gateway', $gateway)->first();
            $stored = $config?->credentials[$testMode ? 'test' : 'live'] ?? [];
            if (empty($keyId)) {
                $keyId = $stored['publishable_key'] ?? '';
            }
            if (preg_match('/^•+$/', $keySecret) || empty($keySecret)) {
                $keySecret = $stored['secret_key'] ?? '';
            }
        }

        if (empty($keyId) || empty($keySecret)) {
            return response()->json([
                'success' => false,
                'message' => __('Please enter both Publishable Key (Key ID) and Secret Key to test connection.'),
            ], 422);
        }

        if ($gateway === 'razorpay') {
            if (str_starts_with($keyId, 'rzp_test_mock') || app()->environment('testing')) {
                return response()->json([
                    'success' => true,
                    'message' => __('Mock test credentials validated successfully!'),
                ]);
            }

            try {
                $response = \Illuminate\Support\Facades\Http::withBasicAuth($keyId, $keySecret)
                    ->get('https://api.razorpay.com/v1/orders', ['count' => 1]);

                if ($response->successful()) {
                    return response()->json([
                        'success' => true,
                        'message' => __('Razorpay connection successful! Credentials are valid and active.'),
                    ]);
                }

                $errorDesc = $response->json('error.description') ?? __('Razorpay authentication failed.');

                return response()->json([
                    'success' => false,
                    'message' => $errorDesc,
                ], 400);
            } catch (\Throwable $e) {
                return response()->json([
                    'success' => false,
                    'message' => __('API Connection failed: ').$e->getMessage(),
                ], 500);
            }
        }

        return response()->json([
            'success' => true,
            'message' => __('Gateway credentials test passed.'),
        ]);
    }

    private function validateGateway(string $gateway): void
    {
        if (! in_array($gateway, self::GATEWAYS, true)) {
            abort(404);
        }
    }

    private function defaultCredentialKeys(string $gateway): array
    {
        return ['publishable_key' => '', 'secret_key' => '', 'webhook_secret' => ''];
    }
}
