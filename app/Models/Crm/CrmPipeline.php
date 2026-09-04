<?php

namespace App\Models\Crm;

use App\Models\Workspace;
use App\Modules\Shared\Models\Contact;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmPipeline extends Model
{
    use HasFactory;

    protected $table = 'crm_pipelines';

    protected $fillable = [
        'workspace_id',
        'name',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(CrmPipelineStage::class, 'pipeline_id')->orderBy('position');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(CrmDeal::class, 'pipeline_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'pipeline_id');
    }
}
