<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutineStreak extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'routine_id',
        'user_id',
        'current_streak',
        'longest_streak',
        'last_completed_date',
        'updated_at',
    ];

    protected $casts = [
        'last_completed_date' => 'date',
        'updated_at' => 'datetime',
    ];

    public function routine(): BelongsTo
    {
        return $this->belongsTo(Routine::class);
    }
}
