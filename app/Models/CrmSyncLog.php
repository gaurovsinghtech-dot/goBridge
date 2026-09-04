<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmSyncLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'crm_sync_logs';

    protected $fillable = [
        'workspace_id',
        'provider',
        'object_type',
        'action',
        'direction',
        'status',
        'external_record_id',
        'internal_record_id',
        'error_message',
        'payload_json',
        'created_at',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'created_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
