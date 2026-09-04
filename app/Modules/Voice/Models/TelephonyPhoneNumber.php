<?php

namespace App\Modules\Voice\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TelephonyPhoneNumber extends Model
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
        'phone_number',
        'provider',
        'status',
        'assigned_voice_agent_id',
        'direction',
        'is_default',
        'config_json',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'config_json' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function voiceAgent(): BelongsTo
    {
        return $this->belongsTo(VoiceAgent::class, 'assigned_voice_agent_id');
    }
}
