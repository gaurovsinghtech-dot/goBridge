<?php

namespace App\Modules\Voice\Models;

use App\Models\Workspace;
use App\Modules\AI\Models\AiKnowledgeBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class VoiceAgent extends Model
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
        'description',
        'status',
        'language',
        'tone',
        'voice_id',
        'provider',
        'phone_number',
        'system_prompt',
        'greeting_message',
        'ai_kb_id',
        'tools_config',
        'call_flow_json',
        'working_hours_json',
        'human_transfer_number',
        'max_duration_sec',
        'ai_model',
        'total_calls',
        'successful_calls',
    ];

    protected function casts(): array
    {
        return [
            'tools_config' => 'array',
            'call_flow_json' => 'array',
            'working_hours_json' => 'array',
            'total_calls' => 'integer',
            'successful_calls' => 'integer',
            'max_duration_sec' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(AiKnowledgeBase::class, 'ai_kb_id');
    }

    public function calls(): HasMany
    {
        return $this->hasMany(VoiceCall::class);
    }

    public function getSuccessRateAttribute(): float
    {
        if ($this->total_calls === 0) {
            return 100.0;
        }

        return round(($this->successful_calls / $this->total_calls) * 100, 1);
    }

    /**
     * Default Voice Studio Call Flow Configuration
     */
    public function getResolvedCallFlowAttribute(): array
    {
        $defaults = [
            'purpose' => $this->description ?: 'Qualify incoming leads and answer questions about our services.',
            'primary_language' => $this->language ?: 'en-US',
            'additional_languages' => ['hi-IN'],
            'detect_language' => true,
            'ai_disclosure' => true,
            'personality' => $this->tone ?: 'professional',
            'objective' => 'lead_qualification',
            'objective_description' => 'Qualify leads interested in WhatsApp API and AI automation solutions.',
            'response_style' => 'balanced',
            'allow_interruption' => true,
            'ask_one_question' => true,
            'confirm_important_info' => true,
            'max_ai_turns' => 50,
            'recording_enabled' => true,
            'recording_notice' => 'Please note that this call may be recorded for quality and training purposes.',
            'handoff_triggers' => ['customer_request', 'low_confidence', 'complaint', 'payment_issue', 'high_value_lead'],
            'handoff_sales_number' => $this->human_transfer_number ?: '',
            'handoff_support_number' => '',
            'fallback_message' => "I don't have that specific information in my business knowledge. Would you like me to connect you with our human team?",
            'fallback_action' => 'whatsapp_callback',
            'knowledge_categories' => ['business', 'products', 'services', 'pricing', 'faq', 'policies'],
            'phone_number_id' => null,
        ];

        return array_merge($defaults, (array) ($this->call_flow_json ?? []));
    }

    /**
     * Default Working Hours Schedule
     */
    public function getResolvedWorkingHoursAttribute(): array
    {
        $defaultSchedule = [
            ['day' => 'Monday', 'enabled' => true, 'start' => '09:00', 'end' => '18:00'],
            ['day' => 'Tuesday', 'enabled' => true, 'start' => '09:00', 'end' => '18:00'],
            ['day' => 'Wednesday', 'enabled' => true, 'start' => '09:00', 'end' => '18:00'],
            ['day' => 'Thursday', 'enabled' => true, 'start' => '09:00', 'end' => '18:00'],
            ['day' => 'Friday', 'enabled' => true, 'start' => '09:00', 'end' => '18:00'],
            ['day' => 'Saturday', 'enabled' => true, 'start' => '10:00', 'end' => '14:00'],
            ['day' => 'Sunday', 'enabled' => false, 'start' => '09:00', 'end' => '18:00'],
        ];

        $existing = (array) ($this->working_hours_json ?? []);

        return [
            'schedule' => $existing['schedule'] ?? $defaultSchedule,
            'outside_action' => $existing['outside_action'] ?? 'whatsapp_callback',
        ];
    }
}
