<?php

namespace App\Http\Controllers\Client\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmCustomField;
use App\Services\Crm\CrmAuditService;
use App\Services\Crm\CrmAuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CrmCustomFieldController extends Controller
{
    public function __construct(
        private readonly CrmAuthorizationService $authService,
        private readonly CrmAuditService $auditService
    ) {}

    public function index(Request $request): Response|JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $entityType = $request->query('entity_type', 'contact');

        $fields = CrmCustomField::where('workspace_id', $workspace->id)
            ->when($entityType, fn ($q) => $q->where('entity_type', $entityType))
            ->orderBy('order_position')
            ->orderBy('id')
            ->get();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $fields,
            ]);
        }

        return Inertia::render('Crm/Settings/CustomFields', [
            'fields' => $fields,
            'entityTypes' => CrmCustomField::ENTITY_TYPES,
            'fieldTypes' => CrmCustomField::TYPES,
            'currentEntityType' => $entityType,
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless($this->authService->canManageSettings($request->user(), $workspace), 403, 'Unauthorized to manage custom fields.');

        $validated = $request->validate([
            'entity_type' => ['required', 'in:lead,contact,company,deal'],
            'name' => ['required', 'string', 'max:128'],
            'key' => ['nullable', 'string', 'max:64'],
            'type' => ['required', 'in:text,number,date,dropdown,multi-select,boolean,currency'],
            'options' => ['nullable', 'array'],
            'is_required' => ['nullable', 'boolean'],
            'default_value' => ['nullable', 'string', 'max:255'],
            'order_position' => ['nullable', 'integer', 'min:0'],
        ]);

        $key = ! empty($validated['key']) ? Str::slug($validated['key'], '_') : Str::slug($validated['name'], '_');

        // Check key collision within same workspace & entity_type
        $existing = CrmCustomField::where('workspace_id', $workspace->id)
            ->where('entity_type', $validated['entity_type'])
            ->where('key', $key)
            ->first();

        if ($existing) {
            $key .= '_'.Str::random(4);
        }

        $field = CrmCustomField::create([
            'workspace_id' => $workspace->id,
            'entity_type' => $validated['entity_type'],
            'name' => $validated['name'],
            'key' => $key,
            'type' => $validated['type'],
            'options' => $validated['options'] ?? null,
            'is_required' => $validated['is_required'] ?? false,
            'default_value' => $validated['default_value'] ?? null,
            'order_position' => $validated['order_position'] ?? 0,
        ]);

        $this->auditService->log(
            workspace: $workspace,
            actor: $request->user(),
            action: 'custom_field_created',
            entityType: 'custom_field',
            entityId: $field->id,
            description: "Custom field '{$field->name}' ({$field->type}) created for {$field->entity_type}."
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Custom field created successfully.',
                'data' => $field,
            ], 201);
        }

        return back()->with('success', 'Custom field created successfully.');
    }

    public function destroy(Request $request, CrmCustomField $customField): RedirectResponse|JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless((int) $customField->workspace_id === (int) $workspace->id, 403);
        abort_unless($this->authService->canManageSettings($request->user(), $workspace), 403);

        $fieldName = $customField->name;
        $fieldId = $customField->id;
        $customField->delete();

        $this->auditService->log(
            workspace: $workspace,
            actor: $request->user(),
            action: 'custom_field_deleted',
            entityType: 'custom_field',
            entityId: $fieldId,
            description: "Custom field '{$fieldName}' deleted."
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Custom field deleted successfully.',
            ]);
        }

        return back()->with('success', 'Custom field deleted successfully.');
    }
}
