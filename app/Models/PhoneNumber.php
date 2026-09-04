<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Modules\Voice\Models\VoiceAgent;
use App\Modules\Voice\Models\VoiceCall;
use App\Modules\Whatsapp\Models\WhatsappBusinessAccount;
use App\Modules\Whatsapp\Models\WhatsappPhoneNumber;

class PhoneNumber extends Model
{
    use HasFactory;

    protected $table = 'phone_numbers';

    protected $fillable = [
        'uuid',
        'workspace_id',
        'twilio_account_sid',
        'twilio_phone_number_sid',
        'phone_number',
        'country',
        'friendly_name',
        'capabilities',
        'voice_enabled',
        'sms_enabled',
        'mms_enabled',
        'call_recording_enabled',
        'whatsapp_status',
        'whatsapp_account_id',
        'whatsapp_phone_number_id',
        'whatsapp_display_name',
        'status',
        'monthly_cost',
        'assigned_ai_agent_id',
        'assigned_chat_ai_agent_id',
        'voice_webhook_url',
        'sms_webhook_url',
    ];

    protected $casts = [
        'capabilities' => 'array',
        'voice_enabled' => 'boolean',
        'sms_enabled' => 'boolean',
        'mms_enabled' => 'boolean',
        'call_recording_enabled' => 'boolean',
        'monthly_cost' => 'decimal:2',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->whatsapp_status)) {
                $model->whatsapp_status = 'not_connected';
            }
        });
    }

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function assignedAgent()
    {
        return $this->belongsTo(VoiceAgent::class, 'assigned_ai_agent_id');
    }

    public function assignedVoiceAgent()
    {
        return $this->belongsTo(VoiceAgent::class, 'assigned_ai_agent_id');
    }

    public function assignedChatAgent()
    {
        return $this->belongsTo(VoiceAgent::class, 'assigned_chat_ai_agent_id');
    }

    public function whatsappAccount()
    {
        return $this->belongsTo(WhatsappBusinessAccount::class, 'whatsapp_account_id');
    }

    public function whatsappPhoneNumber()
    {
        return $this->belongsTo(WhatsappPhoneNumber::class, 'whatsapp_phone_number_id', 'phone_number_id');
    }

    public function calls()
    {
        return $this->hasMany(VoiceCall::class, 'phone_number_id');
    }

    public function assignments()
    {
        return $this->hasMany(PhoneNumberAssignment::class, 'phone_number_id');
    }

    /**
     * Check if WhatsApp is connected to this number
     */
    public function isWhatsappConnected(): bool
    {
        return $this->whatsapp_status === 'connected';
    }

    /**
     * Check if Voice is enabled on this number
     */
    public function isVoiceConnected(): bool
    {
        return (bool) $this->voice_enabled && $this->status === 'active';
    }

    /**
     * Unified Number Check: Has active Voice and connected WhatsApp
     */
    public function isUnified(): bool
    {
        return $this->isVoiceConnected() && $this->isWhatsappConnected();
    }
}
