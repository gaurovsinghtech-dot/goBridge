<?php

namespace App\Modules\Voice\Models;

use App\Models\User;
use App\Models\Workspace;
use App\Modules\Shared\Models\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class VoiceFollowUp extends Model
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
        'voice_call_id',
        'voice_campaign_id',
        'voice_agent_id',
        'contact_id',
        'assigned_user_id',
        'type',
        'status',
        'priority',
        'due_at',
        'timezone',
        'title',
        'notes',
        'outcome_trigger',
        'execution_payload',
        'result_json',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'execution_payload' => 'array',
            'result_json' => 'array',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(VoiceCall::class, 'voice_call_id');
    }

    public function voiceCall(): BelongsTo
    {
        return $this->belongsTo(VoiceCall::class, 'voice_call_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(VoiceCampaign::class, 'voice_campaign_id');
    }

    public function voiceAgent(): BelongsTo
    {
        return $this->belongsTo(VoiceAgent::class, 'voice_agent_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
