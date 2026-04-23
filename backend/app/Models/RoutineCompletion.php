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

    protected $casts = [
        'completed_date' => 'date',
    ];

    public function routine(): BelongsTo
    {
        return $this->belongsTo(Routine::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
