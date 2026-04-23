<?php

namespace App\Services;

use App\Enums\Cadence;
use App\Models\Routine;
use App\Models\RoutineStreak;
use Carbon\Carbon;

class StreakService
{
    public function recalculate(Routine $routine): RoutineStreak
    {
        $completions = $routine->completions()
            ->orderBy('completed_date', 'desc')
            ->pluck('completed_date')
            ->map(fn ($date) => Carbon::parse($date));

        if ($completions->isEmpty()) {
            return $this->updateStreak($routine, 0, 0, null);
        }

        $currentStreak = $this->calculateCurrentStreak($completions, $routine->cadence);
        $longestStreak = $this->calculateLongestStreak($completions, $routine->cadence);
        $lastCompleted = $completions->first();

        return $this->updateStreak($routine, $currentStreak, max($currentStreak, $longestStreak), $lastCompleted);
    }

    private function calculateCurrentStreak(\Illuminate\Support\Collection $dates, Cadence $cadence): int
    {
        $today = Carbon::today();
        $streak = 0;

        foreach ($dates as $date) {
            $expected = $this->subtractPeriod($today, $cadence, $streak);

            if ($this->isSamePeriod($date, $expected, $cadence)) {
                $streak++;
            } elseif ($streak === 0 && $this->isSamePeriod($date, $this->subtractPeriod($today, $cadence, 1), $cadence)) {
                // Allow starting from yesterday's period if today isn't completed yet
                $streak = 1;
            } else {
                break;
            }
        }

        return $streak;
    }

    private function calculateLongestStreak(\Illuminate\Support\Collection $dates, Cadence $cadence): int
    {
        if ($dates->isEmpty()) {
            return 0;
        }

        $sorted = $dates->sortBy(fn ($d) => $d->timestamp)->values();
        $longest = 1;
        $current = 1;

        for ($i = 1; $i < $sorted->count(); $i++) {
            $prev = $sorted[$i - 1];
            $curr = $sorted[$i];

            if ($this->isConsecutivePeriod($prev, $curr, $cadence)) {
                $current++;
                $longest = max($longest, $current);
            } else {
                $current = 1;
            }
        }

        return $longest;
    }

    private function isSamePeriod(Carbon $a, Carbon $b, Cadence $cadence): bool
    {
        return match ($cadence) {
            Cadence::Daily => $a->isSameDay($b),
            Cadence::Weekly => $a->isoWeek() === $b->isoWeek() && $a->isoWeekYear() === $b->isoWeekYear(),
            Cadence::Monthly => $a->month === $b->month && $a->year === $b->year,
        };
    }

    private function isConsecutivePeriod(Carbon $prev, Carbon $curr, Cadence $cadence): bool
    {
        return match ($cadence) {
            Cadence::Daily => $prev->copy()->addDay()->isSameDay($curr),
            Cadence::Weekly => $prev->copy()->addWeek()->isoWeek() === $curr->isoWeek()
                && $prev->copy()->addWeek()->isoWeekYear() === $curr->isoWeekYear(),
            Cadence::Monthly => $prev->copy()->addMonth()->month === $curr->month
                && $prev->copy()->addMonth()->year === $curr->year,
        };
    }

    private function subtractPeriod(Carbon $date, Cadence $cadence, int $count): Carbon
    {
        return match ($cadence) {
            Cadence::Daily => $date->copy()->subDays($count),
            Cadence::Weekly => $date->copy()->subWeeks($count),
            Cadence::Monthly => $date->copy()->subMonths($count),
        };
    }

    private function updateStreak(Routine $routine, int $current, int $longest, ?Carbon $lastCompleted): RoutineStreak
    {
        return RoutineStreak::updateOrCreate(
            ['routine_id' => $routine->id],
            [
                'user_id' => $routine->user_id,
                'current_streak' => $current,
                'longest_streak' => $longest,
                'last_completed_date' => $lastCompleted,
                'updated_at' => now(),
            ]
        );
    }
}
