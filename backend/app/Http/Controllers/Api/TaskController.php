<?php

namespace App\Http\Controllers\Api;

use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Task::where('user_id', $request->user()->id)
            ->orderBy('is_completed')
            ->orderByDesc('created_at');

        if ($request->has('completed')) {
            $query->where('is_completed', $request->boolean('completed'));
        }

        return $this->success($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'notes' => 'nullable|string',
            'due_date' => 'nullable|date',
        ]);

        $task = Task::create([
            ...$validated,
            'user_id' => $request->user()->id,
        ]);

        return $this->success($task, 'Task created', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $task = Task::where('user_id', $request->user()->id)->findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'notes' => 'nullable|string',
            'due_date' => 'nullable|date',
            'is_completed' => 'sometimes|boolean',
        ]);

        if (isset($validated['is_completed']) && $validated['is_completed'] && ! $task->is_completed) {
            $validated['completed_at'] = now();
        } elseif (isset($validated['is_completed']) && ! $validated['is_completed']) {
            $validated['completed_at'] = null;
        }

        $task->update($validated);

        return $this->success($task);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $task = Task::where('user_id', $request->user()->id)->findOrFail($id);
        $task->delete();

        return $this->success(null, 'Task deleted');
    }
}
