<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmConnection extends Model
{
    use HasFactory;

    protected $table = 'crm_connections';

    protected $fillable = [
        'workspace_id',
        'provider',
        'name',
        'auth_type',
        'credentials',
        'status',
        'sync_direction',
        'sync_mode',
        'conflict_resolution',
        'last_sync_at',
        'last_sync_status',
        'last_sync_message',
        'settings_json',
    ];

    protected $casts = [
        'credentials' => 'encrypted:array',
        'settings_json' => 'array',
        'last_sync_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function isConnected(): bool
    {
        return $this->status === 'active' && ! empty($this->credentials);
    }

    /**
     * Return masked credentials safe for frontend presentation
     */
    public function maskedCredentials(): array
    {
        $creds = $this->credentials ?? [];
        $masked = [];

        foreach ($creds as $key => $value) {
            if (empty($value)) {
                $masked[$key] = '';
            } elseif (in_array($key, ['domain', 'instance_url', 'base_url', 'data_center', 'auth_type', 'sync_direction'], true)) {
                $masked[$key] = $value;
            } else {
                $str = (string) $value;
                $masked[$key] = strlen($str) > 4 ? '••••••••••••'.substr($str, -4) : '••••••••••••';
            }
        }

        return $masked;
    }
}
