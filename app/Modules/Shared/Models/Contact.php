<?php

namespace App\Modules\Shared\Models;

use App\Services\StorageManager;
use App\Support\Concerns\MasksDemoData;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $workspace_id
 * @property string|null $phone_e164
 * @property string|null $email
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $avatar
 * @property string|null $country
 * @property string|null $language
 * @property bool $opt_in_whatsapp
 * @property bool $opt_in_sms
 * @property bool $opt_in_email
 * @property array<string, mixed>|null $custom_fields
 * @property Carbon|null $last_seen_at
 * @property string|null $source
 * @property int|null $lead_id
 * @property-read string $full_name
 * @property-read string|null $avatar_url
 */
class Contact extends Model
{
    use HasFactory, MasksDemoData, SoftDeletes;

    protected static function newFactory()
    {
        return ContactFactory::new();
    }

    /**
     * Contact PII masked in demo mode (see App\Support\Concerns\MasksDemoData).
     *
     * @return array<string, string>
     */
    protected function demoMask(): array
    {
        return [
            'phone_e164' => 'phone',
            'email' => 'email',
            'first_name' => 'name',
            'last_name' => 'name',
            'full_name' => 'name',
            'custom_fields' => 'array',
            'avatar' => 'null',
            'avatar_url' => 'null',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
        static::created(function (self $model) {
            \App\Events\ContactCreated::dispatch($model);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $appends = ['full_name', 'avatar_url'];

    protected $fillable = [
        'workspace_id', 'company_id', 'phone_e164', 'email', 'first_name', 'last_name', 'company',
        'avatar', 'country', 'language', 'opt_in_whatsapp', 'opt_in_sms', 'opt_in_email',
        'marketing_opt_out', 'opt_out_channel', 'opt_out_at',
        'lead_score', 'lead_score_band', 'lead_intent', 'duplicate_of_id',
        'external_ids', 'custom_fields', 'last_seen_at', 'source', 'lead_id',
        'deal_value', 'pipeline_id', 'stage_id', 'assigned_user_id', 'assigned_team_id',
        'loss_reason', 'next_follow_up_at', 'priority',
    ];

    protected function casts(): array
    {
        return [
            'opt_in_whatsapp' => 'boolean',
            'opt_in_sms' => 'boolean',
            'opt_in_email' => 'boolean',
            'marketing_opt_out' => 'boolean',
            'lead_score' => 'integer',
            'deal_value' => 'float',
            'external_ids' => 'array',
            'custom_fields' => 'array',
            'last_seen_at' => 'datetime',
            'opt_out_at' => 'datetime',
            'next_follow_up_at' => 'datetime',
        ];
    }

    public function crmCompany(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Crm\CrmCompany::class, 'company_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ContactTag::class, 'contact_tag_pivot', 'contact_id', 'tag_id');
    }

    public function contactTags(): BelongsToMany
    {
        return $this->tags();
    }

    public function segments(): BelongsToMany
    {
        return $this->belongsToMany(Segment::class, 'segment_contact');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function timelineEvents(): HasMany
    {
        return $this->hasMany(ContactTimelineEvent::class)->latest('occurred_at');
    }

    public function voiceCalls(): HasMany
    {
        return $this->hasMany(\App\Modules\Voice\Models\VoiceCall::class)->latest('created_at');
    }

    public function workspace(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Workspace::class);
    }

    public function pipeline(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Crm\CrmPipeline::class, 'pipeline_id');
    }

    public function stage(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Crm\CrmPipelineStage::class, 'stage_id');
    }

    public function assignedUser(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_user_id');
    }

    public function assignedTeam(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Crm\CrmTeam::class, 'assigned_team_id');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(\App\Models\Crm\CrmDeal::class, 'contact_id');
    }

    public function crmTasks(): HasMany
    {
        return $this->hasMany(\App\Models\Crm\CrmTask::class, 'contact_id')->orderBy('due_at');
    }

    public function crmNotes(): HasMany
    {
        return $this->hasMany(\App\Models\Crm\CrmNote::class, 'contact_id')->latest('created_at');
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if (! $this->avatar) {
            return null;
        }
        // External URLs (from WhatsApp/Instagram/Messenger) returned as-is
        if (str_starts_with($this->avatar, 'http')) {
            return $this->avatar;
        }

        $manager = app(StorageManager::class);
        $diskName = $manager->diskName();

        if ($diskName === 's3' || $diskName === 'do_spaces' || $diskName === 'wasabi') {
            try {
                return $manager->disk()->temporaryUrl($this->avatar, now()->addMinutes(60));
            } catch (\Throwable $e) {
                // fallback
            }
        }

        return $manager->disk()->url($this->avatar);
    }
}
