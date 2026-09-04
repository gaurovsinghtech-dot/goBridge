<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PhoneNumberAssignment extends Model
{
    use HasFactory;

    protected $table = 'phone_number_assignments';

    protected $fillable = [
        'phone_number_id',
        'workspace_id',
        'assigned_to_type',
        'assigned_to_id',
    ];

    public function phoneNumber()
    {
        return $this->belongsTo(PhoneNumber::class, 'phone_number_id');
    }

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }
}
