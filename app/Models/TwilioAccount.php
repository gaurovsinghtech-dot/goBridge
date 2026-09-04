<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class TwilioAccount extends Model
{
    use HasFactory;

    protected $table = 'twilio_accounts';

    protected $fillable = [
        'workspace_id',
        'twilio_account_sid',
        'auth_token',
        'encrypted_auth_token',
        'friendly_name',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Get decrypted Auth Token safely
     */
    public function getAuthTokenAttribute(): ?string
    {
        if (empty($this->encrypted_auth_token)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->encrypted_auth_token);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Set encrypted Auth Token safely
     */
    public function setAuthTokenAttribute(?string $value): void
    {
        if (empty($value)) {
            $this->attributes['encrypted_auth_token'] = null;
        } else {
            $this->attributes['encrypted_auth_token'] = Crypt::encryptString($value);
        }
    }

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function phoneNumbers()
    {
        return $this->hasMany(PhoneNumber::class, 'workspace_id', 'workspace_id');
    }
}
