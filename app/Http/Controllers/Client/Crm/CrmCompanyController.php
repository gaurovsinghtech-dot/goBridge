<?php

namespace App\Http\Controllers\Client\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmCompany;
use App\Models\User;
use App\Services\Crm\CrmAuditService;
use App\Services\Crm\CrmCustomFieldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CrmCompanyController extends Controller
{
    public function __construct(
        private readonly CrmCustomFieldService $customFieldService,
        private readonly CrmAuditService $auditService
    ) {}

    public function index(Request $request): Response|JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $companies = CrmCompany::where('workspace_id', $workspace->id)
            ->with(['owner'])
            ->withCount(['contacts', 'deals'])
            ->withSum('deals', 'value')
            ->when($request->search, function ($q) use ($request) {
                $q->where(function ($sub) use ($request) {
                    $sub->where('name', 'like', '%'.$request->search.'%')
                        ->orWhere('phone', 'like', '%'.$request->search.'%')
                        ->orWhere('email', 'like', '%'.$request->search.'%')
                        ->orWhere('industry', 'like', '%'.$request->search.'%');
                });
            })
            ->when($request->industry, fn ($q) => $q->where('industry', $request->industry))
            ->when($request->owner_id, fn ($q) => $q->where('owner_user_id', $request->owner_id))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $companies->items(),
                'meta' => [
                    'current_page' => $companies->currentPage(),
                    'total' => $companies->total(),
                ],
            ]);
        }

        $teamMembers = User::where('workspace_id', $workspace->id)->where('status', 'active')->get(['id', 'name', 'email']);
        $customFields = $this->customFieldService->getFields($workspace->id, 'company');

        return Inertia::render('Crm/Companies/Index', [
            'companies' => $companies,
            'teamMembers' => $teamMembers,
            'customFields' => $customFields,
            'filters' => $request->only('search', 'industry', 'owner_id'),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'owner_user_id' => ['nullable', 'exists:users,id'],
            'industry' => ['nullable', 'string', 'max:128'],
            'website' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:191'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:128'],
            'country' => ['nullable', 'string', 'max:64'],
            'custom_fields' => ['nullable', 'array'],
        ]);

        $customFieldValues = $this->customFieldService->validateValues(
            $workspace->id,
            'company',
            $validated['custom_fields'] ?? []
        );

        $company = CrmCompany::create([
            'workspace_id' => $workspace->id,
            'name' => $validated['name'],
            'owner_user_id' => $validated['owner_user_id'] ?? $request->user()->id,
            'industry' => $validated['industry'] ?? null,
            'website' => $validated['website'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'address' => $validated['address'] ?? null,
            'city' => $validated['city'] ?? null,
            'country' => $validated['country'] ?? null,
            'custom_fields' => $customFieldValues,
        ]);

        $this->auditService->log(
            workspace: $workspace,
            actor: $request->user(),
            action: 'company_created',
            entityType: 'company',
            entityId: $company->id,
            newValues: $company->toArray(),
            description: "Company {$company->name} created."
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Company created successfully.',
                'data' => $company->load('owner'),
            ], 201);
        }

        return back()->with('success', 'Company created successfully.');
    }

    public function show(Request $request, CrmCompany $company): Response|JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless((int) $company->workspace_id === (int) $workspace->id, 403);

        $company->load(['owner', 'contacts.stage', 'deals.stage']);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $company,
            ]);
        }

        return Inertia::render('Crm/Companies/Show', [
            'company' => $company,
        ]);
    }

    public function update(Request $request, CrmCompany $company): RedirectResponse|JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless((int) $company->workspace_id === (int) $workspace->id, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'owner_user_id' => ['nullable', 'exists:users,id'],
            'industry' => ['nullable', 'string', 'max:128'],
            'website' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:191'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:128'],
            'country' => ['nullable', 'string', 'max:64'],
            'custom_fields' => ['nullable', 'array'],
        ]);

        $oldValues = $company->toArray();

        if (isset($validated['custom_fields'])) {
            $validated['custom_fields'] = $this->customFieldService->validateValues(
                $workspace->id,
                'company',
                $validated['custom_fields']
            );
        }

        $company->update($validated);

        $this->auditService->log(
            workspace: $workspace,
            actor: $request->user(),
            action: 'company_updated',
            entityType: 'company',
            entityId: $company->id,
            oldValues: $oldValues,
            newValues: $company->toArray(),
            description: "Company {$company->name} updated."
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Company updated successfully.',
                'data' => $company->fresh('owner'),
            ]);
        }

        return back()->with('success', 'Company updated successfully.');
    }

    public function destroy(Request $request, CrmCompany $company): RedirectResponse|JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless((int) $company->workspace_id === (int) $workspace->id, 403);

        $companyName = $company->name;
        $companyId = $company->id;
        $company->delete();

        $this->auditService->log(
            workspace: $workspace,
            actor: $request->user(),
            action: 'company_deleted',
            entityType: 'company',
            entityId: $companyId,
            description: "Company {$companyName} archived/deleted."
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Company deleted successfully.',
            ]);
        }

        return back()->with('success', 'Company deleted successfully.');
    }
}
