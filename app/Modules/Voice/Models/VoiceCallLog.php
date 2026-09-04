<?php

namespace App\Modules\Voice\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoiceCallLog extends Model
{
    protected $fillable = [
        'voice_call_id',
        'speaker',
        'text',
        'timestamp_sec',
    ];

    protected function casts(): array
    {
        return [
            'timestamp_sec' => 'integer',
        ];
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(VoiceCall::class, 'voice_call_id');
    }
}
