<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutineMessage extends Model
{
    public const KINDS = ['reminder', 'checkin'];

    protected $fillable = [
        'routine_id',
        'message_date',
        'kind',
        'body',
        'source',
        'sent_at',
        'telegram_message_id',
    ];

    // message_date stays a plain 'Y-m-d' string (see RoutineCompletion).
    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function routine(): BelongsTo
    {
        return $this->belongsTo(Routine::class);
    }
}
