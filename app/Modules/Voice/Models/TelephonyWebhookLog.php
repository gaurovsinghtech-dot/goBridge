<?php

namespace App\Modules\Voice\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelephonyWebhookLog extends Model
{
    protected $fillable = [
        'workspace_id',
        'provider',
        'event_name',
        'call_id',
        'payload_json',
        'status',
        'error_message',
        'received_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
