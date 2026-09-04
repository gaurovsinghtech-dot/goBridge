<?php

namespace App\Modules\Voice\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class VoiceFollowUpRule extends Model
{
    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $fillable = [
        'workspace_id',
        'name',
        'voice_agent_id',
        'voice_campaign_id',
        'trigger_event',
        'conditions',
        'actions',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'actions' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(VoiceAgent::class, 'voice_agent_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(VoiceCampaign::class, 'voice_campaign_id');
    }
}
