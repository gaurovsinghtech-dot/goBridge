<?php

namespace App\Modules\Voice\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelephonyApiLog extends Model
{
    protected $fillable = [
        'workspace_id',
        'provider',
        'endpoint',
        'http_method',
        'status_code',
        'response_time_ms',
        'success',
        'request_payload',
        'response_body',
    ];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'status_code' => 'integer',
            'response_time_ms' => 'integer',
            'request_payload' => 'array',
            'response_body' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
