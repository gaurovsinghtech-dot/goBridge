<?php

namespace App\Models\Crm;

use App\Models\User;
use App\Models\Workspace;
use App\Modules\Shared\Models\Contact;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrmCompany extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'crm_companies';

    protected $fillable = [
        'workspace_id',
        'name',
        'owner_user_id',
        'industry',
        'website',
        'phone',
        'email',
        'address',
        'city',
        'country',
        'custom_fields',
    ];

    protected function casts(): array
    {
        return [
            'custom_fields' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'company_id');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(CrmDeal::class, 'company_id');
    }
}
