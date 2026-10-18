<?php

namespace App\Http\Controllers\Api;

use App\Enums\InboxStatus;
use App\Models\InboxItem;
use App\Models\Routine;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InboxController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = InboxItem::where('user_id', $request->user()->id)
            ->orderByDesc('created_at');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $items = $query->paginate($request->get('per_page', 50));

        return $this->success($items);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'body' => 'required|string|max:1000',
        ]);

        $item = InboxItem::create([
            ...$validated,
            'user_id' => $request->user()->id,
        ]);

        return $this->success($item, 'Captured', 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $item = InboxItem::where('user_id', $request->user()->id)->findOrFail($id);
        $item->delete();

        return $this->success(null, 'Deleted');
    }

    public function review(Request $request, int $id): JsonResponse
    {
        $item = InboxItem::where('user_id', $request->user()->id)->findOrFail($id);

        $validated = $request->validate([
            'action' => 'required|in:convert_routine,convert_task,keep,dismiss',
            'title' => 'required_if:action,convert_routine,convert_task|string|max:255',
            'cadence' => 'required_if:action,convert_routine|in:daily,weekly,monthly',
            'scheduled_time' => 'nullable|date_format:H:i',
            'weekday' => 'nullable|integer|between:1,7',
            'month_day' => 'nullable|integer|between:1,28',
            'due_date' => 'nullable|date',
        ]);

        return match ($validated['action']) {
            'convert_routine' => $this->convertToRoutine($request, $item, $validated),
            'convert_task' => $this->convertToTask($request, $item, $validated),
            'keep' => $this->keepItem($item),
            'dismiss' => $this->dismissItem($item),
        };
    }

    private function convertToRoutine(Request $request, InboxItem $item, array $data): JsonResponse
    {
        $data = $this->applyCadenceRules($data);

        $routine = Routine::create([
            'user_id' => $request->user()->id,
            'title' => $data['title'],
            'description' => $item->body,
            'cadence' => $data['cadence'],
            'scheduled_time' => $data['scheduled_time'] ?? null,
            'weekday' => $data['weekday'] ?? null,
            'month_day' => $data['month_day'] ?? null,
        ]);

        $item->update([
            'status' => InboxStatus::Converted,
            'reviewed_at' => now(),
        ]);

        return $this->success(['inbox_item' => $item, 'routine' => $routine], 'Converted to routine');
    }

    private function convertToTask(Request $request, InboxItem $item, array $data): JsonResponse
    {
        $task = Task::create([
            'user_id' => $request->user()->id,
            'inbox_item_id' => $item->id,
            'title' => $data['title'],
            'notes' => $item->body,
            'due_date' => $data['due_date'] ?? null,
        ]);

        $item->update([
            'status' => InboxStatus::Converted,
            'reviewed_at' => now(),
        ]);

        return $this->success(['inbox_item' => $item, 'task' => $task], 'Converted to task');
    }

    private function keepItem(InboxItem $item): JsonResponse
    {
        $item->update([
            'status' => InboxStatus::Reviewed,
            'reviewed_at' => now(),
        ]);

        return $this->success($item, 'Kept for later');
    }

    private function dismissItem(InboxItem $item): JsonResponse
    {
        $item->update([
            'status' => InboxStatus::Dismissed,
            'reviewed_at' => now(),
        ]);

        return $this->success($item, 'Dismissed');
    }

    private function applyCadenceRules(array $data): array
    {
        if (($data['cadence'] ?? null) === 'weekly' && empty($data['weekday'])) {
            throw ValidationException::withMessages([
                'weekday' => 'Please choose a day of the week for weekly routines.',
            ]);
        }

        if (($data['cadence'] ?? null) === 'monthly' && empty($data['month_day'])) {
            throw ValidationException::withMessages([
                'month_day' => 'Please choose a day of the month (1-28) for monthly routines.',
            ]);
        }

        if (($data['cadence'] ?? null) === 'daily') {
            $data['weekday'] = null;
            $data['month_day'] = null;
        } elseif ($data['cadence'] === 'weekly') {
            $data['weekday'] = (int) $data['weekday'];
            $data['month_day'] = null;
        } elseif ($data['cadence'] === 'monthly') {
            $data['month_day'] = (int) $data['month_day'];
            $data['weekday'] = null;
        }

        return $data;
    }
}
