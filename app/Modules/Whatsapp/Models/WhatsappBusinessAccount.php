<?php

namespace App\Modules\Whatsapp\Models;

use App\Models\Workspace;
use App\Modules\Integrations\Services\CredentialResolver;
use App\Modules\Shared\Models\ChannelAccount;
use Database\Factories\WhatsappBusinessAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsappBusinessAccount extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return WhatsappBusinessAccountFactory::new();
    }

    protected $table = 'whatsapp_business_accounts';

    protected $fillable = [
        'workspace_id', 'waba_id', 'credentials', 'webhook_verify_token', 'webhook_verify_token_hash', 'status', 'meta_json',
    ];

    protected $hidden = ['credentials', 'webhook_verify_token'];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'webhook_verify_token' => 'encrypted',
            'meta_json' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Workspace::class);
    }

    public function phoneNumbers(): HasMany
    {
        return $this->hasMany(WhatsappPhoneNumber::class, 'waba_id_fk');
    }

    public function templates(): HasMany
    {
        return $this->hasMany(WhatsappTemplate::class, 'waba_id', 'waba_id');
    }

    public static function hashWebhookToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /** O(1) lookup for per-WABA webhook routes (token is stored encrypted) with fallback for un-hashed rows. */
    public static function findByWebhookToken(string $token): ?self
    {
        $hash = static::hashWebhookToken($token);
        $waba = static::where('webhook_verify_token_hash', $hash)->first();

        if ($waba) {
            return $waba;
        }

        // Fallback for legacy / un-hashed rows in database
        foreach (static::whereNull('webhook_verify_token_hash')->orWhere('webhook_verify_token_hash', '')->cursor() as $account) {
            try {
                if ($account->webhook_verify_token && hash_equals((string) $account->webhook_verify_token, $token)) {
                    $account->forceFill(['webhook_verify_token_hash' => $hash])->saveQuietly();
                    return $account;
                }
            } catch (\Throwable $e) {
                // Ignore decryption exceptions
            }
        }

        return null;
    }

    /** Access token for Graph API (embedded OAuth or manual system user). */
    public function accessToken(): ?string
    {
        $creds = $this->credentials ?? [];

        return $creds['system_user_token'] ?? $creds['access_token'] ?? null;
    }

    public static function resolveAccessTokenForWorkspace(int $workspaceId): ?string
    {
        $waba = static::where('workspace_id', $workspaceId)
            ->where('status', 'active')
            ->first();

        if ($waba) {
            $token = $waba->accessToken();
            if ($token) {
                return $token;
            }
        }

        $fromChannel = ChannelAccount::where('workspace_id', $workspaceId)
            ->where('channel', 'whatsapp')
            ->where('status', 'active')
            ->first();

        if ($fromChannel && ! empty($fromChannel->credentials['access_token'])) {
            return (string) $fromChannel->credentials['access_token'];
        }

        return CredentialResolver::system()->meta()?->systemUserToken()
            ?? env('META_SYSTEM_USER_TOKEN');
    }

    public static function defaultPhoneNumberIdForWorkspace(int $workspaceId): ?string
    {
        $fromChannel = ChannelAccount::where('workspace_id', $workspaceId)
            ->where('channel', 'whatsapp')
            ->where('status', 'active')
            ->whereNotNull('phone_number_id')
            ->orderBy('id')
            ->value('phone_number_id');

        if ($fromChannel) {
            return (string) $fromChannel;
        }

        $waba = static::where('workspace_id', $workspaceId)
            ->where('status', 'active')
            ->with('phoneNumbers')
            ->first();

        return $waba?->phoneNumbers->first()?->phone_number_id;
    }

    protected static function booted(): void
    {
        static::saving(function (self $waba) {
            if ($waba->webhook_verify_token && (empty($waba->webhook_verify_token_hash) || $waba->isDirty('webhook_verify_token'))) {
                $waba->webhook_verify_token_hash = static::hashWebhookToken($waba->webhook_verify_token);
            }
        });
    }
}
