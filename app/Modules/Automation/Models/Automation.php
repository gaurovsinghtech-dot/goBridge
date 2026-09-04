<?php

namespace App\Modules\Automation\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Automation extends Model
{
    protected $table = 'automations';

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->webhook_public_key)) {
                $model->webhook_public_key = 'wh_'.Str::random(32);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $fillable = [
        'workspace_id',
        'uuid',
        'name',
        'description',
        'category',
        'version',
        'status',
        'trigger_type',
        'trigger_config',
        'trigger_token',
        'webhook_public_key',
        'nodes',
        'edges',
        'run_count',
        'successful_runs',
        'failed_runs',
        'last_run_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'trigger_config' => 'array',
            'nodes' => 'array',
            'edges' => 'array',
            'version' => 'integer',
            'run_count' => 'integer',
            'successful_runs' => 'integer',
            'failed_runs' => 'integer',
            'last_run_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRun::class, 'automation_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function generateWebhookPublicKey(): string
    {
        $this->webhook_public_key = 'wh_'.Str::random(32);
        $this->save();

        return $this->webhook_public_key;
    }

    public function recordRunResult(bool $success): void
    {
        $this->increment('run_count');
        if ($success) {
            $this->increment('successful_runs');
        } else {
            $this->increment('failed_runs');
        }
        $this->update(['last_run_at' => now()]);
    }
}
