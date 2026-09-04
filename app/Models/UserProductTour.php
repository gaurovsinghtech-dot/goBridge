<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProductTour extends Model
{
    use HasFactory;

    protected $table = 'user_product_tours';

    protected $fillable = [
        'user_id',
        'tour_key',
        'current_step',
        'completed_at',
        'skipped_at',
    ];

    protected function casts(): array
    {
        return [
            'current_step' => 'integer',
            'completed_at' => 'datetime',
            'skipped_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCompleted(): bool
    {
        return ! is_null($this->completed_at);
    }

    public function isSkipped(): bool
    {
        return ! is_null($this->skipped_at);
    }
}
