<?php

namespace App\Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AiKbDocument extends Model
{
    protected $table = 'ai_kb_documents';

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->category)) {
                $model->category = 'general';
            }
            if (empty($model->priority)) {
                $model->priority = 5;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $fillable = [
        'kb_id',
        'uuid',
        'source_type',
        'source_ref',
        'title',
        'category',
        'priority',
        'assigned_agents',
        'status',
        'visibility',
        'error_message',
        'tokens',
        'file_size',
        'meta',
        'version',
        'last_indexed_at',
    ];

    protected function casts(): array
    {
        return [
            'last_indexed_at' => 'datetime',
            'tokens' => 'integer',
            'file_size' => 'integer',
            'priority' => 'integer',
            'version' => 'integer',
            'meta' => 'array',
            'assigned_agents' => 'array',
        ];
    }

    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(AiKnowledgeBase::class, 'kb_id');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(AiKbChunk::class, 'document_id');
    }
}
