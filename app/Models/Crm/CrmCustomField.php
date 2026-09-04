<?php

namespace App\Models\Crm;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmCustomField extends Model
{
    use HasFactory;

    protected $table = 'crm_custom_fields';

    public const TYPES = [
        'text',
        'number',
        'date',
        'dropdown',
        'multi-select',
        'boolean',
        'currency',
    ];

    public const ENTITY_TYPES = [
        'lead',
        'contact',
        'company',
        'deal',
    ];

    protected $fillable = [
        'workspace_id',
        'entity_type',
        'name',
        'key',
        'type',
        'options',
        'is_required',
        'default_value',
        'order_position',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
            'order_position' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
