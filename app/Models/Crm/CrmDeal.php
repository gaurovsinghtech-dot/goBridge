<?php

namespace App\Models\Crm;

use App\Models\User;
use App\Models\Workspace;
use App\Modules\Shared\Models\Contact;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmDeal extends Model
{
    use HasFactory;

    protected $table = 'crm_deals';

    protected $fillable = [
        'workspace_id',
        'contact_id',
        'company_id',
        'lead_id',
        'pipeline_id',
        'stage_id',
        'assigned_user_id',
        'name',
        'value',
        'currency',
        'probability',
        'expected_close_date',
        'status',
        'loss_reason',
        'notes',
        'custom_fields',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'float',
            'probability' => 'integer',
            'expected_close_date' => 'date',
            'custom_fields' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(CrmCompany::class, 'company_id');
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(CrmPipeline::class, 'pipeline_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(CrmPipelineStage::class, 'stage_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(CrmTask::class, 'deal_id');
    }

    public function getWeightedValueAttribute(): float
    {
        return round(($this->value * ($this->probability / 100)), 2);
    }
}
