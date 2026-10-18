<?php

namespace App\Http\Controllers\Api;

use App\Models\Routine;
use App\Models\RoutineCompletion;
use App\Services\StreakService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RoutineController extends BaseApiController
{
    public function __construct(private StreakService $streakService) {}

    public function index(Request $request): JsonResponse
    {
        $query = Routine::where('user_id', $request->user()->id)
            ->with('streak')
            ->orderBy('sort_order')
            ->orderBy('created_at');

        if ($request->has('cadence')) {
            $query->where('cadence', $request->cadence);
        }

        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        $routines = $query->get();

        $date = Carbon::parse($request->get('date', Carbon::today(config('app.user_timezone'))->toDateString()))->toDateString();

        $completedIds = RoutineCompletion::where('user_id', $request->user()->id)
            ->where('completed_date', $date)
            ->pluck('routine_id')
            ->toArray();

        $routines->each(function ($routine) use ($completedIds) {
            $routine->is_completed_today = in_array($routine->id, $completedIds);
        });

        return $this->success($routines);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'cadence' => 'required|in:daily,weekly,monthly',
            'scheduled_time' => 'nullable|date_format:H:i',
            'checkin_time' => 'nullable|date_format:H:i',
            'weekday' => 'nullable|integer|between:1,7',
            'month_day' => 'nullable|integer|between:1,28',
            'color' => 'nullable|string|max:7',
            'icon' => 'nullable|string|max:50',
        ]);

        $validated = $this->applyCadenceRules($validated, true);

        $routine = Routine::create([
            ...$validated,
            'user_id' => $request->user()->id,
        ]);

        $routine->load('streak');

        return $this->success($routine, 'Routine created', 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $routine = Routine::where('user_id', $request->user()->id)
            ->with('streak')
            ->findOrFail($id);

        return $this->success($routine);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $routine = Routine::where('user_id', $request->user()->id)->findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'cadence' => 'sometimes|in:daily,weekly,monthly',
            'scheduled_time' => 'nullable|date_format:H:i',
            'checkin_time' => 'nullable|date_format:H:i',
            'weekday' => 'nullable|integer|between:1,7',
            'month_day' => 'nullable|integer|between:1,28',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
            'color' => 'nullable|string|max:7',
            'icon' => 'nullable|string|max:50',
        ]);

        $validated = $this->applyCadenceRules($validated, false, $routine);

        $routine->update($validated);
        $routine->load('streak');

        return $this->success($routine);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $routine = Routine::where('user_id', $request->user()->id)->findOrFail($id);
        $routine->delete();

        return $this->success(null, 'Routine deleted');
    }

    public function complete(Request $request, int $id): JsonResponse
    {
        $routine = Routine::where('user_id', $request->user()->id)->findOrFail($id);

        $date = Carbon::parse($request->get('date', Carbon::today(config('app.user_timezone'))->toDateString()))->toDateString();

        RoutineCompletion::firstOrCreate(
            ['routine_id' => $routine->id, 'completed_date' => $date],
            ['user_id' => $request->user()->id, 'notes' => $request->get('notes')]
        );

        $this->streakService->recalculate($routine);
        $routine->load('streak');

        return $this->success($routine, 'Routine completed');
    }

    public function uncomplete(Request $request, int $id): JsonResponse
    {
        $routine = Routine::where('user_id', $request->user()->id)->findOrFail($id);

        $date = Carbon::parse($request->get('date', Carbon::today(config('app.user_timezone'))->toDateString()))->toDateString();

        RoutineCompletion::where('routine_id', $routine->id)
            ->where('completed_date', $date)
            ->delete();

        $this->streakService->recalculate($routine);
        $routine->load('streak');

        return $this->success($routine, 'Completion removed');
    }

    private function applyCadenceRules(array $validated, bool $isCreate, ?Routine $routine = null): array
    {
        $cadence = $validated['cadence'] ?? ($routine?->cadence?->value ?? null);

        if (!$cadence) {
            return $validated;
        }

        $hasCadenceFields = array_key_exists('cadence', $validated)
            || array_key_exists('weekday', $validated)
            || array_key_exists('month_day', $validated);

        if (!$isCreate && !$hasCadenceFields) {
            return $validated;
        }

        $weekday = $validated['weekday'] ?? $routine?->weekday;
        $monthDay = $validated['month_day'] ?? $routine?->month_day;

        if ($cadence === 'weekly' && !$weekday) {
            throw ValidationException::withMessages([
                'weekday' => 'Please choose a day of the week for weekly routines.',
            ]);
        }

        if ($cadence === 'monthly' && !$monthDay) {
            throw ValidationException::withMessages([
                'month_day' => 'Please choose a day of the month (1-28) for monthly routines.',
            ]);
        }

        if ($cadence === 'daily') {
            $validated['weekday'] = null;
            $validated['month_day'] = null;
        } elseif ($cadence === 'weekly') {
            $validated['weekday'] = (int) $weekday;
            $validated['month_day'] = null;
        } elseif ($cadence === 'monthly') {
            $validated['month_day'] = (int) $monthDay;
            $validated['weekday'] = null;
        }

        return $validated;
    }
}
