<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceUsage extends Model
{
    protected $table = 'workspace_usages';

    protected $fillable = [
        'workspace_id',
        'period_month',
        'contacts_count',
        'messages_count',
        'ai_requests_count',
        'ai_tokens_count',
        'voice_calls_count',
        'voice_minutes_count',
        'automation_executions_count',
        'campaigns_count',
        'api_requests_count',
    ];

    protected function casts(): array
    {
        return [
            'period_month' => 'date',
            'contacts_count' => 'integer',
            'messages_count' => 'integer',
            'ai_requests_count' => 'integer',
            'ai_tokens_count' => 'integer',
            'voice_calls_count' => 'integer',
            'voice_minutes_count' => 'integer',
            'automation_executions_count' => 'integer',
            'campaigns_count' => 'integer',
            'api_requests_count' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
