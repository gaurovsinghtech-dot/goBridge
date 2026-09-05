<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Plan;
use App\Models\SmtpConfiguration;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/Register', [
            'plan_id' => $request->query('plan_id'),
            'cycle' => $request->query('cycle', 'month'),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password'      => ['required', 'confirmed', Rules\Password::defaults()],
            'business_name' => ['nullable', 'string', 'max:255'],
            'industry'      => ['nullable', 'string', 'max:128'],
            'agree_terms'   => ['accepted'],
            'timezone'      => ['nullable', 'string', 'max:64'],
        ], [
            'agree_terms.accepted' => 'You must accept the Terms & Conditions to create an account.',
        ]);

        // Use browser-detected timezone from signup; fall back to India/Dhaka.
        $timezone = $this->resolveTimezone($request->input('timezone'));

        $user = DB::transaction(function () use ($request, $timezone) {
            $client = Client::create([
                'name' => $request->business_name ?: $request->name,
                'email' => $request->email,
                'status' => Client::STATUS_ACTIVE,
            ]);

            // Create primary workspace first so User booted sync hooks link to it
            $workspaceName = $request->business_name ?: ($request->company_name ?: ($request->name . "'s Workspace"));
            $workspaceData = [
                'client_id' => $client->id,
                'name' => $workspaceName,
                'industry' => $request->industry ?: 'general',
                'default_locale' => 'en',
                'currency_code' => null,
                'timezone' => $timezone,
                'onboarding_completed' => false,
            ];
            if (\Illuminate\Support\Facades\Schema::hasColumn('workspaces', 'service_type')) {
                $workspaceData['service_type'] = 'whatsapp_only';
            }
            $workspace = \App\Models\Workspace::create($workspaceData);

            $newUser = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => User::ROLE_CLIENT,
                'status' => User::STATUS_ACTIVE,
                'client_id' => $client->id,
                'client_role' => User::CLIENT_ROLE_ADMINISTRATOR,
                'workspace_id' => $workspace->id,
                'timezone' => $timezone,
                // No active SMTP config = app cannot send verification mail,
                // so auto-verify the account instead of trapping the user.
                'email_verified_at' => SmtpConfiguration::isConfigured() ? null : now(),
            ]);

            $workspace->forceFill(['owner_id' => $newUser->id])->saveQuietly();
            $workspace->members()->syncWithoutDetaching([$newUser->id => ['role' => 'owner']]);

            return $newUser;
        });

        event(new Registered($user));
        Auth::login($user);

        if ($user->workspace_id) {
            $request->session()->put('current_workspace_id', $user->workspace_id);
        }

        // If registration came from the pricing page, redirect to checkout
        $planId = $request->input('plan_id');
        $cycle = $request->input('cycle', 'month');
        if ($planId) {
            $plan = Plan::find($planId);
            if ($plan && ! $plan->is_free) {
                return redirect()->route('client.pricing')->with([
                    'plan_id' => $planId,
                    'cycle' => $cycle,
                    'success' => 'Account created! Select a payment method to complete your subscription.',
                ]);
            }
        }

        return redirect(route('client.dashboard', absolute: false));
    }

    private function resolveTimezone(?string $tz): string
    {
        $default = 'Asia/Dhaka';
        if (! $tz) {
            return $default;
        }
        try {
            new \DateTimeZone($tz);
            return $tz;
        } catch (\Exception) {
            return $default;
        }
    }
}
