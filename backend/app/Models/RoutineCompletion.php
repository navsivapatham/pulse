<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutineCompletion extends Model
{
    use HasFactory;

    protected $fillable = [
        'routine_id',
        'user_id',
        'completed_date',
        'notes',
    ];

    // No 'date' cast: Eloquent would store '2026-10-18 00:00:00', which never equals the
    // plain 'Y-m-d' strings every query (and reminder.py) compares against.

    public function routine(): BelongsTo
    {
        return $this->belongsTo(Routine::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
