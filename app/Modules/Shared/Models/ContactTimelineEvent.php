<?php

namespace App\Modules\Shared\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ContactTimelineEvent extends Model
{
    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->occurred_at)) {
                $model->occurred_at = now();
            }
        });
    }

    protected $fillable = [
        'workspace_id',
        'contact_id',
        'channel',
        'event_type',
        'title',
        'description',
        'metadata_json',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata_json' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
