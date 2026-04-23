<?php

namespace App\Http\Controllers\Api;

use App\Enums\InboxStatus;
use App\Models\InboxItem;
use App\Models\Routine;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function review(Request $request, int $id): JsonResponse
    {
        $item = InboxItem::where('user_id', $request->user()->id)->findOrFail($id);

        $validated = $request->validate([
            'action' => 'required|in:convert_routine,convert_task,keep,dismiss',
            'title' => 'required_if:action,convert_routine,convert_task|string|max:255',
            'cadence' => 'required_if:action,convert_routine|in:daily,weekly,monthly',
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
        $routine = Routine::create([
            'user_id' => $request->user()->id,
            'title' => $data['title'],
            'description' => $item->body,
            'cadence' => $data['cadence'],
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
}
