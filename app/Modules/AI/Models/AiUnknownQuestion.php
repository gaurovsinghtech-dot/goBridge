<?php

namespace App\Modules\AI\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUnknownQuestion extends Model
{
    protected $table = 'ai_unknown_questions';

    protected $fillable = [
        'workspace_id',
        'ai_agent_id',
        'question',
        'occurrences',
        'category_suggested',
        'status',
        'last_asked_at',
    ];

    protected function casts(): array
    {
        return [
            'occurrences' => 'integer',
            'last_asked_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(AiChatbot::class, 'ai_agent_id');
    }
}
