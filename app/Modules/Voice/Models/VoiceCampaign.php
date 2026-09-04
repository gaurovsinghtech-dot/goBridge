<?php

namespace App\Modules\Voice\Models;

use App\Models\PhoneNumber;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class VoiceCampaign extends Model
{
    protected $table = 'voice_campaigns';

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
        'uuid',
        'name',
        'type',
        'description',
        'voice_agent_id',
        'phone_number_id',
        'caller_id_number',
        'status',
        'start_at',
        'end_at',
        'timezone',
        'calling_days',
        'calling_start_time',
        'calling_end_time',
        'max_attempts',
        'retry_delay_hours',
        'call_timeout_sec',
        'max_duration_sec',
        'concurrent_limit',
        'daily_limit',
        'max_campaign_calls',
        'compliance_confirmed',
        'ai_disclosure_enabled',
        'whatsapp_followup_enabled',
        'whatsapp_template_name',
        'audience_filters',
        'total_contacts',
        'completed_calls',
        'answered_calls',
        'interested_calls',
        'qualified_calls',
        'callback_calls',
        'not_interested_calls',
        'no_answer_calls',
        'failed_calls',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'calling_days' => 'array',
            'audience_filters' => 'array',
            'compliance_confirmed' => 'boolean',
            'ai_disclosure_enabled' => 'boolean',
            'whatsapp_followup_enabled' => 'boolean',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'max_attempts' => 'integer',
            'retry_delay_hours' => 'integer',
            'call_timeout_sec' => 'integer',
            'max_duration_sec' => 'integer',
            'concurrent_limit' => 'integer',
            'daily_limit' => 'integer',
            'max_campaign_calls' => 'integer',
            'total_contacts' => 'integer',
            'completed_calls' => 'integer',
            'answered_calls' => 'integer',
            'interested_calls' => 'integer',
            'qualified_calls' => 'integer',
            'callback_calls' => 'integer',
            'not_interested_calls' => 'integer',
            'no_answer_calls' => 'integer',
            'failed_calls' => 'integer',
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

    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(PhoneNumber::class, 'phone_number_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(VoiceCampaignRecipient::class, 'voice_campaign_id');
    }

    public function getProgressPercentAttribute(): float
    {
        if ($this->total_contacts === 0) {
            return 0.0;
        }

        return round(($this->completed_calls / $this->total_contacts) * 100, 1);
    }

    public function getAnswerRateAttribute(): float
    {
        if ($this->completed_calls === 0) {
            return 0.0;
        }

        return round(($this->answered_calls / $this->completed_calls) * 100, 1);
    }

    public function getQualificationRateAttribute(): float
    {
        if ($this->answered_calls === 0) {
            return 0.0;
        }

        return round(($this->qualified_calls / $this->answered_calls) * 100, 1);
    }

    public function getInterestRateAttribute(): float
    {
        if ($this->answered_calls === 0) {
            return 0.0;
        }

        return round(($this->interested_calls / $this->answered_calls) * 100, 1);
    }
}
