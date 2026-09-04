<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Services\Storage\SecureUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceSettingsController extends Controller
{
    public function __construct(
        protected SecureUploadService $uploadService
    ) {}

    protected function resolveWorkspace(Request $request): Workspace
    {
        $user = $request->user();
        $workspaceId = (int) ($request->attributes->get('current_workspace_id') ?: ($request->session()->get('current_workspace_id') ?: ($user->current_workspace_id ?? $user->workspace_id)));

        $workspace = Workspace::findOrFail($workspaceId);

        if (! $workspace->isAccessibleBy($user)) {
            abort(403, 'Unauthorized access to workspace.');
        }

        return $workspace;
    }

    /**
     * Display workspace business profile & configuration.
     */
    public function show(Request $request): Response
    {
        $workspace = $this->resolveWorkspace($request);
        $user = $request->user();

        $workspace->loadCount('members');
        $role = $workspace->owner_id === $user->id
            ? 'owner'
            : ($workspace->members()->where('user_id', $user->id)->first()?->pivot?->role ?? 'member');

        return Inertia::render('client/Settings/Workspace', [
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'logo_url' => $workspace->logoUrl(),
                'industry' => $workspace->industry ?? 'General',
                'website' => $workspace->website,
                'country' => $workspace->country ?? 'India',
                'timezone' => $workspace->timezone ?? 'Asia/Kolkata',
                'business_hours' => $workspace->business_hours ?? [
                    'monday' => ['open' => '09:00', 'close' => '18:00', 'closed' => false],
                    'tuesday' => ['open' => '09:00', 'close' => '18:00', 'closed' => false],
                    'wednesday' => ['open' => '09:00', 'close' => '18:00', 'closed' => false],
                    'thursday' => ['open' => '09:00', 'close' => '18:00', 'closed' => false],
                    'friday' => ['open' => '09:00', 'close' => '18:00', 'closed' => false],
                    'saturday' => ['open' => '10:00', 'close' => '16:00', 'closed' => false],
                    'sunday' => ['open' => '00:00', 'close' => '00:00', 'closed' => true],
                ],
                'default_locale' => $workspace->default_locale ?? 'en',
                'currency_code' => $workspace->currency_code ?? 'INR',
                'service_type' => $workspace->service_type ?? 'whatsapp_only',
                'members_count' => $workspace->members_count,
                'current_user_role' => $role,
                'is_owner' => $workspace->owner_id === $user->id,
            ],
            'industries' => [
                'Retail & E-commerce',
                'Real Estate & Property',
                'Healthcare & Clinics',
                'Education & Coaching',
                'Financial & Insurance Services',
                'Travel & Hospitality',
                'Automotive & Dealerships',
                'Marketing & Digital Agency',
                'Manufacturing & Wholesale',
                'Professional Consulting',
                'Other / General Business',
            ],
        ]);
    }

    /**
     * Update workspace business profile and operational parameters.
     */
    public function update(Request $request): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $user = $request->user();

        // Only owner and administrator can update workspace profile
        if ($workspace->owner_id !== $user->id && ! $user->isClientAdministrator()) {
            abort(403, 'Only workspace administrators can modify workspace profile.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:128'],
            'website' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:100'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'business_hours' => ['nullable', 'array'],
        ]);

        $workspace->update($validated);

        return redirect()->back()->with('success', 'Workspace profile updated successfully.');
    }

    /**
     * Upload and store workspace brand logo in private S3 storage.
     */
    public function uploadLogo(Request $request): RedirectResponse|JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $user = $request->user();

        if ($workspace->owner_id !== $user->id && ! $user->isClientAdministrator()) {
            abort(403, 'Only workspace administrators can upload brand logo.');
        }

        $request->validate([
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:5120'],
        ]);

        $file = $request->file('logo');
        $storedFile = $this->uploadService->upload(
            $file,
            $workspace->id,
            $user->id,
            'logos',
            ['workspace_id' => $workspace->id]
        );

        $workspace->update([
            'logo_path' => $storedFile->key,
        ]);

        $logoUrl = $workspace->logoUrl();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Workspace logo uploaded successfully.',
                'logo_url' => $logoUrl,
            ]);
        }

        return redirect()->back()->with('success', 'Workspace logo uploaded successfully.');
    }

    /**
     * Remove workspace logo.
     */
    public function removeLogo(Request $request): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $user = $request->user();

        if ($workspace->owner_id !== $user->id && ! $user->isClientAdministrator()) {
            abort(403, 'Only workspace administrators can remove brand logo.');
        }

        $workspace->update(['logo_path' => null]);

        return redirect()->back()->with('success', 'Workspace logo removed.');
    }
}
