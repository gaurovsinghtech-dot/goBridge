<?php

namespace App\Models\Crm;

use App\Models\User;
use App\Models\Workspace;
use App\Modules\Shared\Models\Contact;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmTeam extends Model
{
    use HasFactory;

    protected $table = 'crm_teams';

    protected $fillable = [
        'workspace_id',
        'name',
        'description',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'crm_team_user', 'team_id', 'user_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'assigned_team_id');
    }
}
