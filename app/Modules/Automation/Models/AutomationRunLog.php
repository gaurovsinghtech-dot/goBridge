<?php

namespace App\Modules\Automation\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationRunLog extends Model
{
    protected $table = 'automation_run_logs';

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->step_id)) {
                $model->step_id = 'step_'.uniqid();
            }
        });
    }

    protected $fillable = [
        'run_id',
        'step_id',
        'step_index',
        'node_id',
        'node_type',
        'category',
        'result',
        'message',
        'output',
        'provider_payload',
        'provider_response',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'output' => 'array',
            'provider_payload' => 'array',
            'provider_response' => 'array',
            'step_index' => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    public function run()
    {
        return $this->belongsTo(AutomationRun::class, 'run_id');
    }
}
