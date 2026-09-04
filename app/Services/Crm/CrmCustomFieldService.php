<?php

namespace App\Services\Crm;

use App\Models\Crm\CrmCustomField;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CrmCustomFieldService
{
    /**
     * Get all custom field definitions for an entity type in a workspace.
     */
    public function getFields(int $workspaceId, string $entityType): Collection
    {
        return CrmCustomField::where('workspace_id', $workspaceId)
            ->where('entity_type', $entityType)
            ->orderBy('order_position')
            ->orderBy('id')
            ->get();
    }

    /**
     * Validate an entity's custom fields array against defined schema.
     *
     * @throws ValidationException
     */
    public function validateValues(int $workspaceId, string $entityType, ?array $values): array
    {
        if (empty($values)) {
            $values = [];
        }

        $definitions = $this->getFields($workspaceId, $entityType);
        $validated = [];
        $errors = [];

        foreach ($definitions as $def) {
            $key = $def->key;
            $hasKey = array_key_exists($key, $values);
            $val = $hasKey ? $values[$key] : null;

            // Required check
            if ($def->is_required && (is_null($val) || $val === '' || $val === [])) {
                $errors["custom_fields.{$key}"] = "The field '{$def->name}' is required.";
                continue;
            }

            if (is_null($val) || $val === '') {
                if (! is_null($def->default_value)) {
                    $validated[$key] = $def->default_value;
                }
                continue;
            }

            // Type validation
            switch ($def->type) {
                case 'text':
                    $validated[$key] = (string) $val;
                    break;

                case 'number':
                    if (! is_numeric($val)) {
                        $errors["custom_fields.{$key}"] = "The field '{$def->name}' must be a number.";
                    } else {
                        $validated[$key] = (float) $val;
                    }
                    break;

                case 'currency':
                    if (! is_numeric($val) || (float) $val < 0) {
                        $errors["custom_fields.{$key}"] = "The field '{$def->name}' must be a positive currency value.";
                    } else {
                        $validated[$key] = round((float) $val, 2);
                    }
                    break;

                case 'date':
                    if (! strtotime((string) $val)) {
                        $errors["custom_fields.{$key}"] = "The field '{$def->name}' must be a valid date.";
                    } else {
                        $validated[$key] = date('Y-m-d', strtotime((string) $val));
                    }
                    break;

                case 'boolean':
                    $validated[$key] = filter_var($val, FILTER_VALIDATE_BOOLEAN);
                    break;

                case 'dropdown':
                    $options = is_array($def->options) ? $def->options : [];
                    if (! in_array($val, $options, true)) {
                        $errors["custom_fields.{$key}"] = "The selected option for '{$def->name}' is invalid.";
                    } else {
                        $validated[$key] = $val;
                    }
                    break;

                case 'multi-select':
                    $options = is_array($def->options) ? $def->options : [];
                    $selected = is_array($val) ? $val : [$val];
                    $invalid = array_diff($selected, $options);
                    if (! empty($invalid)) {
                        $errors["custom_fields.{$key}"] = "One or more selected values for '{$def->name}' are invalid.";
                    } else {
                        $validated[$key] = array_values($selected);
                    }
                    break;

                default:
                    $validated[$key] = $val;
                    break;
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        return $validated;
    }
}
