<?php

namespace App\Models\Crm;

use App\Models\User;
use App\Models\Workspace;
use App\Modules\Shared\Models\Contact;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmNote extends Model
{
    use HasFactory;

    protected $table = 'crm_notes';

    protected $fillable = [
        'workspace_id',
        'contact_id',
        'lead_id',
        'user_id',
        'content',
        'is_private',
        'mentions',
    ];

    protected function casts(): array
    {
        return [
            'is_private' => 'boolean',
            'mentions' => 'array',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
