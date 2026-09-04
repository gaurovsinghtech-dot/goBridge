<?php

namespace App\Modules\Voice\Models;

use App\Models\Workspace;
use App\Modules\Leads\Models\Lead;
use App\Modules\Shared\Models\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoiceCampaignRecipient extends Model
{
    protected $table = 'voice_campaign_recipients';

    protected $fillable = [
        'workspace_id',
        'voice_campaign_id',
        'contact_id',
        'lead_id',
        'phone_e164',
        'contact_name',
        'status',
        'attempts_count',
        'max_attempts',
        'last_attempt_at',
        'next_attempt_at',
        'call_outcome',
        'lead_score',
        'voice_call_id',
        'priority_level',
        'priority_score',
        'queue_reason',
        'exclusion_reason',
        'preferred_calling_window',
        'timezone',
        'is_callback',
        'callback_scheduled_at',
        'locked_at',
        'locked_by',
        'notes',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'attempts_count' => 'integer',
            'max_attempts' => 'integer',
            'priority_score' => 'integer',
            'is_callback' => 'boolean',
            'last_attempt_at' => 'datetime',
            'next_attempt_at' => 'datetime',
            'callback_scheduled_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(VoiceCampaign::class, 'voice_campaign_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function voiceCall(): BelongsTo
    {
        return $this->belongsTo(VoiceCall::class, 'voice_call_id');
    }
}
