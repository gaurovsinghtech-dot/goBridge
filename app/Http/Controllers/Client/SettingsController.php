<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ClientSetting;
use App\Models\Currency;
use App\Models\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $supportedLocales = Locale::enabled()->orderByRaw('is_default DESC')->orderBy('sort_order')->get(['code', 'name']);
        if ($supportedLocales->isEmpty()) {
            $supportedLocales = collect([['code' => 'en', 'name' => 'English']]);
        }
        $supportedCurrencies = Currency::where('enabled', true)->orderBy('code')->get(['code', 'symbol']);

        $client = null;
        if ($user->client_id && $user->isClientAdministrator()) {
            $c = $user->client;
            if ($c) {
                $client = [
                    'id' => $c->id,
                    'name' => $c->name,
                    'email' => $c->email,
                    'phone' => $c->phone,
                    'address' => $c->address,
                ];
            }
        }

        $androidRelease = \App\Models\AppRelease::getLatestActive('android');

        return Inertia::render('client/Settings/Index', [
            'preferences' => [
                'locale' => $user->locale ?? config('app.locale', 'en'),
                'display_currency' => $user->display_currency ?? 'INR',
                'theme' => $user->theme ?? 'light',
                'timezone' => $user->timezone ?? 'Asia/Kolkata',
            ],
            'supportedLocales' => $supportedLocales->map(fn ($l) => [
                'code' => is_array($l) ? ($l['code'] ?? 'en') : ($l->code ?? 'en'),
                'name' => is_array($l) ? ($l['name'] ?? 'English') : ($l->name ?? 'English'),
            ]),
            'supportedCurrencies' => $supportedCurrencies->map(fn ($c) => [
                'code' => is_array($c) ? ($c['code'] ?? 'INR') : ($c->code ?? 'INR'),
                'name' => is_array($c) ? ($c['code'] ?? 'INR') : ($c->code ?? 'INR'),
                'symbol' => is_array($c) ? ($c['symbol'] ?? '₹') : ($c->symbol ?? $c->code ?? '₹'),
            ]),
            'client' => $client,
            'digestEnabled' => $user->client_id
                ? ClientSetting::get($user->client_id, 'weekly_digest_enabled', '1') !== '0'
                : true,
            'android_app' => [
                'version' => $androidRelease?->version ?? '1.0.0',
                'version_code' => $androidRelease?->version_code ?? 100,
                'file_size_mb' => $androidRelease?->file_size_mb ?? 28.50,
                'release_notes' => $androidRelease?->release_notes ?? "• Unified WhatsApp Chat & Live Omnichannel Inbox\n• Business VoIP Calling with In-App Dialpad\n• Real-Time AI Suggested Replies & Human Handoff\n• 360° Customer Profile (WhatsApp + Calls + CRM)",
                'published_at' => $androidRelease?->published_at ? $androidRelease->published_at->format('M d, Y') : 'Latest',
                'download_url' => route('download.android-apk'),
                'qr_code_url' => route('download.android-apk.qr'),
                'is_active' => (bool) ($androidRelease?->is_active ?? true),
            ],
        ]);
    }

    public function notifications(Request $request): Response
    {
        $user = $request->user();

        $preferences = $user->notificationPreferences
            ->groupBy('event')
            ->map(fn ($group) => $group->mapWithKeys(fn ($p) => [$p->channel => (bool) $p->enabled]));

        return Inertia::render('client/Settings/Notifications', [
            'preferences' => $preferences,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $localeCodes = Locale::enabled()->pluck('code')->all() ?: ['en'];
        $currencyCodes = Currency::where('enabled', true)->pluck('code')->all() ?: ['INR', 'USD'];

        if ($request->has('timezone') && is_string($request->input('timezone'))) {
            $rawTz = trim($request->input('timezone'));
            $cleanTz = preg_replace('/\s*\(.*?\)\s*/', '', $rawTz);
            try {
                $dtz = new \DateTimeZone($cleanTz);
                $request->merge(['timezone' => $dtz->getName()]);
            } catch (\Throwable $e) {
                // Keep as is so validator reports if invalid
            }
        }

        $validated = $request->validate([
            'locale' => ['nullable', 'string', 'max:16', Rule::in($localeCodes)],
            'display_currency' => ['nullable', 'string', 'max:10', Rule::in($currencyCodes)],
            'theme' => ['nullable', 'string', 'in:light,dark'],
            'timezone' => ['nullable', 'string', 'max:64', 'timezone:all_with_bc'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'client_phone' => ['nullable', 'string', 'max:64'],
            'client_address' => ['nullable', 'string'],
            'weekly_digest_enabled' => ['nullable', 'boolean'],
        ]);

        if (array_key_exists('locale', $validated) && $validated['locale'] !== null) {
            $user->locale = $validated['locale'];
        }
        if (array_key_exists('display_currency', $validated) && $validated['display_currency'] !== null) {
            $user->display_currency = $validated['display_currency'];
        }
        if (array_key_exists('theme', $validated) && $validated['theme'] !== null) {
            $user->theme = $validated['theme'];
        }
        if (array_key_exists('timezone', $validated) && $validated['timezone'] !== null) {
            $user->timezone = $validated['timezone'];
        }
        $user->save();

        if ($user->client_id && $user->isClientAdministrator() && $user->client) {
            $client = $user->client;
            if (array_key_exists('client_name', $validated)) {
                $client->name = $validated['client_name'] ?? $client->name;
            }
            if (array_key_exists('client_email', $validated)) {
                $client->email = $validated['client_email'];
            }
            if (array_key_exists('client_phone', $validated)) {
                $client->phone = $validated['client_phone'];
            }
            if (array_key_exists('client_address', $validated)) {
                $client->address = $validated['client_address'];
            }
            $client->save();
        }

        // Digest preference
        if ($user->client_id && array_key_exists('weekly_digest_enabled', $validated)) {
            ClientSetting::set($user->client_id, 'weekly_digest_enabled', $validated['weekly_digest_enabled'] ? '1' : '0');
        }

        return redirect()->route('client.settings.index')->with('success', __('Settings saved.'));
    }
}
