<?php

namespace App\Models;

use App\Enums\InboxStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InboxItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'body',
        'status',
        'reviewed_at',
    ];

    protected $casts = [
        'status' => InboxStatus::class,
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function task(): HasOne
    {
        return $this->hasOne(Task::class);
    }
}
