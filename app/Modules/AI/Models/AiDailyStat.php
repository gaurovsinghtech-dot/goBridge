<?php

namespace App\Modules\AI\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiDailyStat extends Model
{
    protected $table = 'ai_daily_stats';

    protected $fillable = [
        'workspace_id',
        'date',
        'ai_agent_id',
        'channel',
        'conversations',
        'ai_messages',
        'resolved',
        'handoffs',
        'failed',
        'avg_response_ms',
        'positive_feedback',
        'negative_feedback',
        'input_tokens',
        'output_tokens',
        'estimated_cost',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'conversations' => 'integer',
            'ai_messages' => 'integer',
            'resolved' => 'integer',
            'handoffs' => 'integer',
            'failed' => 'integer',
            'avg_response_ms' => 'integer',
            'positive_feedback' => 'integer',
            'negative_feedback' => 'integer',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'estimated_cost' => 'decimal:4',
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
