<?php

namespace App\Modules\Automation\Models;

use App\Modules\Shared\Models\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationRun extends Model
{
    protected $table = 'automation_runs';

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->execution_id)) {
                $model->execution_id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    protected $fillable = [
        'execution_id',
        'automation_id',
        'contact_id',
        'trigger_event',
        'idempotency_key',
        'status',
        'step_count',
        'retry_count',
        'max_steps',
        'max_duration_seconds',
        'context',
        'current_node_id',
        'resume_node_id',
        'error',
        'started_at',
        'completed_at',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'duration_ms' => 'integer',
            'step_count' => 'integer',
            'retry_count' => 'integer',
            'max_steps' => 'integer',
            'max_duration_seconds' => 'integer',
        ];
    }

    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class, 'automation_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AutomationRunLog::class, 'run_id');
    }
}
