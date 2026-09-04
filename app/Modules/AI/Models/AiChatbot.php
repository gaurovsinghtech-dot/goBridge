<?php

namespace App\Modules\AI\Models;

use App\Models\User;
use App\Models\Workspace;
use Database\Factories\AiChatbotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AiChatbot extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return AiChatbotFactory::new();
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->status)) {
                $model->status = 'active';
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $table = 'ai_chatbots';

    protected $fillable = [
        'workspace_id',
        'uuid',
        'name',
        'purpose',
        'description',
        'agent_type',
        'language',
        'languages',
        'provider',
        'model',
        'temperature',
        'max_tokens',
        'status',
        'response_mode',
        'response_style',
        'emoji_style',
        'response_delay_mode',
        'response_delay_seconds',
        'objectives',
        'guardrails',
        'confidence_threshold',
        'strict_knowledge_mode',
        'memory_mode',
        'business_hours_mode',
        'business_hours_schedule',
        'outside_hours_action',
        'human_handoff_enabled',
        'human_handoff_user_id',
        'human_handoff_message',
        'handoff_conditions',
        'handoff_target_type',
        'handoff_target_team',
        'qualification_rules',
        'lead_qualification_fields',
        'crm_actions',
        'crm_tag',
        'crm_lead_score_boost',
        'tools_enabled',
        'ai_kb_id',
        'knowledge_source_ids',
        'voice_config',
        'system_prompt',
        'tone',
        'max_context_chunks',
        'fallback_reply',
        'channels',
        'enabled',
        'version',
        'published_version',
        'published_at',
        'updated_by_user_id',
        'total_conversations',
        'total_resolutions',
        'total_handoffs',
        'last_active_at',
    ];

    protected function casts(): array
    {
        return [
            'temperature' => 'float',
            'max_tokens' => 'integer',
            'confidence_threshold' => 'integer',
            'strict_knowledge_mode' => 'boolean',
            'human_handoff_enabled' => 'boolean',
            'qualification_rules' => 'array',
            'lead_qualification_fields' => 'array',
            'crm_actions' => 'array',
            'crm_lead_score_boost' => 'integer',
            'tools_enabled' => 'array',
            'channels' => 'array',
            'languages' => 'array',
            'objectives' => 'array',
            'guardrails' => 'array',
            'knowledge_source_ids' => 'array',
            'business_hours_schedule' => 'array',
            'handoff_conditions' => 'array',
            'voice_config' => 'array',
            'enabled' => 'boolean',
            'version' => 'integer',
            'published_version' => 'integer',
            'published_at' => 'datetime',
            'response_delay_seconds' => 'integer',
            'total_conversations' => 'integer',
            'total_resolutions' => 'integer',
            'total_handoffs' => 'integer',
            'last_active_at' => 'datetime',
            'max_context_chunks' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(AiKnowledgeBase::class, 'ai_kb_id');
    }

    public function humanAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'human_handoff_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AiRun::class, 'chatbot_id');
    }

    public function resolutionRate(): float
    {
        if ($this->total_conversations === 0) {
            return 85.0;
        }

        return round(($this->total_resolutions / $this->total_conversations) * 100, 1);
    }

    public function handoffRate(): float
    {
        if ($this->total_conversations === 0) {
            return 12.0;
        }

        return round(($this->total_handoffs / $this->total_conversations) * 100, 1);
    }

    public function canPublish(): array
    {
        $reasons = [];

        if (empty(trim($this->name))) {
            $reasons[] = 'Agent name is required.';
        }
        if (empty($this->channels) || count($this->channels) === 0) {
            $reasons[] = 'Select at least one active channel (e.g. WhatsApp, Voice, Messenger, Instagram, Email).';
        }
        if (empty(trim($this->system_prompt ?? '')) && empty(trim($this->purpose ?? ''))) {
            $reasons[] = 'Provide agent instructions or a clear purpose.';
        }

        return [
            'can_publish' => empty($reasons),
            'reasons' => $reasons,
        ];
    }

    public function duplicate(?string $newName = null): self
    {
        $clone = $this->replicate([
            'uuid',
            'total_conversations',
            'total_resolutions',
            'total_handoffs',
            'last_active_at',
            'published_at',
        ]);

        $clone->name = $newName ?? ($this->name . ' (Copy)');
        $clone->status = 'draft';
        $clone->version = 1;
        $clone->published_version = 1;
        $clone->save();

        return $clone;
    }

    public function recordConversation(bool $resolved = true, bool $handoff = false): void
    {
        $this->increment('total_conversations');
        if ($resolved) {
            $this->increment('total_resolutions');
        }
        if ($handoff) {
            $this->increment('total_handoffs');
        }
        $this->update(['last_active_at' => now()]);
    }
}
