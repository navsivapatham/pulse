<?php

namespace App\Http\Controllers\Api;

use App\Models\Routine;
use App\Models\RoutineMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoutineMessageController extends BaseApiController
{
    public function index(Request $request, int $id): JsonResponse
    {
        $routine = Routine::where('user_id', $request->user()->id)->findOrFail($id);

        $query = $routine->messages()->orderByDesc('message_date');

        if ($request->has('date')) {
            $query->where('message_date', $request->date);
        }

        return $this->success($query->limit(60)->get());
    }

    /**
     * Pre-write the text the reminder bot sends for a routine on a date.
     * Idempotent per (routine, date, kind); refuses once that message has gone out.
     */
    public function upsert(Request $request, int $id): JsonResponse
    {
        $routine = Routine::where('user_id', $request->user()->id)->findOrFail($id);

        $validated = $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'kind' => 'required|in:'.implode(',', RoutineMessage::KINDS),
            'body' => 'required|string|max:1000',
        ]);

        $message = $routine->messages()
            ->where('message_date', $validated['date'])
            ->where('kind', $validated['kind'])
            ->first();

        if ($message?->sent_at) {
            return $this->error('Message already sent', 409);
        }

        $message ??= new RoutineMessage([
            'routine_id' => $routine->id,
            'message_date' => $validated['date'],
            'kind' => $validated['kind'],
        ]);
        $message->fill(['body' => $validated['body'], 'source' => 'generated'])->save();

        return $this->success($message, 'Message saved', $message->wasRecentlyCreated ? 201 : 200);
    }
}
