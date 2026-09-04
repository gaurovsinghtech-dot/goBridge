<?php

namespace App\Modules\AI\Models;

use App\Models\Workspace;
use Database\Factories\AiKnowledgeBaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AiKnowledgeBase extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return AiKnowledgeBaseFactory::new();
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->published_at)) {
                $model->published_at = now();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $table = 'ai_knowledge_bases';

    protected $fillable = [
        'workspace_id',
        'uuid',
        'name',
        'category',
        'description',
        'version',
        'embedding_model',
        'dimensions',
        'status',
        'answer_policy',
        'allow_citations',
        'fallback_message',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'dimensions' => 'integer',
            'version' => 'integer',
            'allow_citations' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(AiKbDocument::class, 'kb_id');
    }

    public function chatbots(): HasMany
    {
        return $this->hasMany(AiChatbot::class, 'ai_kb_id');
    }

    public function isPublished(): bool
    {
        return $this->status === 'active' || $this->status === 'published';
    }
}
