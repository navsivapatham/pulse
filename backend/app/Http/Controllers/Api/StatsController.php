<?php

namespace App\Http\Controllers\Api;

use App\Models\Routine;
use App\Models\RoutineCompletion;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatsController extends BaseApiController
{
    public function daily(Request $request): JsonResponse
    {
        $date = Carbon::parse($request->get('date', Carbon::today(config('app.user_timezone'))->toDateString()));
        $userId = $request->user()->id;

        $activeRoutines = Routine::where('user_id', $userId)
            ->where('is_active', true)
            ->get();

        $dailyRoutines = $activeRoutines->where('cadence.value', 'daily');
        $completedIds = RoutineCompletion::where('user_id', $userId)
            ->where('completed_date', $date->toDateString())
            ->pluck('routine_id')
            ->toArray();

        $completed = $dailyRoutines->filter(fn ($r) => in_array($r->id, $completedIds))->count();
        $total = $dailyRoutines->count();

        return $this->success([
            'date' => $date->toDateString(),
            'total_routines' => $total,
            'completed' => $completed,
            'completion_percentage' => $total > 0 ? round(($completed / $total) * 100) : 0,
            'streak_leaders' => Routine::where('user_id', $userId)
                ->with('streak')
                ->where('is_active', true)
                ->get()
                ->sortByDesc(fn ($r) => $r->streak?->current_streak ?? 0)
                ->take(5)
                ->values()
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'title' => $r->title,
                    'current_streak' => $r->streak?->current_streak ?? 0,
                    'longest_streak' => $r->streak?->longest_streak ?? 0,
                ]),
        ]);
    }

    public function weekly(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $startOfWeek = Carbon::parse(Carbon::today(config('app.user_timezone'))->toDateString())->startOfWeek();

        $days = [];
        $period = CarbonPeriod::create($startOfWeek, $startOfWeek->copy()->endOfWeek());

        $dailyRoutineCount = Routine::where('user_id', $userId)
            ->where('is_active', true)
            ->where('cadence', 'daily')
            ->count();

        foreach ($period as $day) {
            $completed = RoutineCompletion::where('user_id', $userId)
                ->where('completed_date', $day->toDateString())
                ->whereHas('routine', fn ($q) => $q->where('cadence', 'daily'))
                ->count();

            $days[] = [
                'date' => $day->toDateString(),
                'day_name' => $day->format('D'),
                'completed' => $completed,
                'total' => $dailyRoutineCount,
                'percentage' => $dailyRoutineCount > 0 ? round(($completed / $dailyRoutineCount) * 100) : 0,
            ];
        }

        return $this->success([
            'week_start' => $startOfWeek->toDateString(),
            'days' => $days,
        ]);
    }

    public function streaks(Request $request): JsonResponse
    {
        $routines = Routine::where('user_id', $request->user()->id)
            ->with('streak')
            ->where('is_active', true)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'title' => $r->title,
                'cadence' => $r->cadence->value,
                'color' => $r->color,
                'current_streak' => $r->streak?->current_streak ?? 0,
                'longest_streak' => $r->streak?->longest_streak ?? 0,
                'last_completed' => $r->streak?->last_completed_date?->toDateString(),
            ])
            ->sortByDesc('current_streak')
            ->values();

        return $this->success($routines);
    }
}
