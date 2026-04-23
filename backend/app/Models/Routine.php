<?php

namespace App\Models;

use App\Enums\Cadence;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Routine extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'cadence',
        'is_active',
        'sort_order',
        'color',
        'icon',
    ];

    protected $casts = [
        'cadence' => Cadence::class,
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function completions(): HasMany
    {
        return $this->hasMany(RoutineCompletion::class);
    }

    public function streak(): HasOne
    {
        return $this->hasOne(RoutineStreak::class);
    }
}
