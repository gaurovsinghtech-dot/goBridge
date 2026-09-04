<?php

namespace App\Modules\Voice\Models;

use App\Models\Workspace;
use App\Models\PhoneNumber;
use App\Modules\Shared\Models\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class VoiceCall extends Model
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
        'phone_number_id',
        'voice_agent_id',
        'assigned_ai_agent_id',
        'contact_id',
        'direction',
        'provider',
        'provider_call_id',
        'from_number',
        'to_number',
        'status',
        'duration_sec',
        'recording_url',
        'transcript',
        'summary',
        'lead_score',
        'outcome',
        'handoff_reason',
        'intent',
        'lead_interest',
        'conversation_signal',
        'topics',
        'important_moments',
        'next_action',
        'analyzed_at',
        'recording_retention_days',
        'transcript_retention_days',
        'extracted_data',
        'error_json',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'extracted_data' => 'array',
            'error_json' => 'array',
            'topics' => 'array',
            'important_moments' => 'array',
            'duration_sec' => 'integer',
            'lead_score' => 'integer',
            'recording_retention_days' => 'integer',
            'transcript_retention_days' => 'integer',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'analyzed_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(PhoneNumber::class, 'phone_number_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(VoiceAgent::class, 'voice_agent_id');
    }

    public function voiceAgent(): BelongsTo
    {
        return $this->belongsTo(VoiceAgent::class, 'voice_agent_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(VoiceCallLog::class);
    }
}
