<?php

namespace App\Models\Crm;

use App\Models\Workspace;
use App\Modules\Shared\Models\Contact;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmPipelineStage extends Model
{
    use HasFactory;

    protected $table = 'crm_pipeline_stages';

    public const DEFAULTS = [
        ['name' => 'New Lead', 'color' => 'neutral', 'is_won' => false, 'is_lost' => false],
        ['name' => 'Contacted', 'color' => 'blue', 'is_won' => false, 'is_lost' => false],
        ['name' => 'Qualified', 'color' => 'indigo', 'is_won' => false, 'is_lost' => false],
        ['name' => 'Proposal', 'color' => 'purple', 'is_won' => false, 'is_lost' => false],
        ['name' => 'Negotiation', 'color' => 'amber', 'is_won' => false, 'is_lost' => false],
        ['name' => 'Won', 'color' => 'emerald', 'is_won' => true, 'is_lost' => false],
        ['name' => 'Lost', 'color' => 'rose', 'is_won' => false, 'is_lost' => true],
    ];

    public const COLORS = ['neutral', 'blue', 'indigo', 'purple', 'amber', 'emerald', 'rose', 'cyan'];

    protected $fillable = [
        'workspace_id',
        'pipeline_id',
        'name',
        'color',
        'position',
        'is_won',
        'is_lost',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_won' => 'boolean',
            'is_lost' => 'boolean',
        ];
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(CrmPipeline::class, 'pipeline_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'stage_id');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(CrmDeal::class, 'stage_id');
    }

    public function isTerminal(): bool
    {
        return $this->is_won || $this->is_lost;
    }
}
