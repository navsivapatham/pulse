<?php

namespace Database\Seeders;

use App\Models\InboxItem;
use App\Models\Routine;
use App\Models\RoutineCompletion;
use App\Models\RoutineStreak;
use App\Models\User;
use App\Services\StreakService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::create([
            'name' => 'Nav',
            'email' => 'nav@pulse.test',
            'password' => Hash::make('password'),
        ]);

        $routines = [
            ['title' => 'Exercise', 'cadence' => 'daily', 'color' => '#EF4444', 'icon' => 'dumbbell'],
            ['title' => 'Meditate', 'cadence' => 'daily', 'color' => '#8B5CF6', 'icon' => 'brain'],
            ['title' => 'Read 30min', 'cadence' => 'daily', 'color' => '#3B82F6', 'icon' => 'book-open'],
            ['title' => 'Journal', 'cadence' => 'daily', 'color' => '#F59E0B', 'icon' => 'pen-line'],
            ['title' => 'Skincare routine', 'cadence' => 'daily', 'color' => '#EC4899', 'icon' => 'sparkles'],
            ['title' => 'Plan the week', 'cadence' => 'weekly', 'color' => '#10B981', 'icon' => 'calendar'],
            ['title' => 'Weekly review', 'cadence' => 'weekly', 'color' => '#6366F1', 'icon' => 'clipboard-check'],
            ['title' => 'Monthly retro', 'cadence' => 'monthly', 'color' => '#14B8A6', 'icon' => 'bar-chart-3'],
        ];

        $streakService = new StreakService();

        foreach ($routines as $i => $data) {
            $routine = Routine::create([
                ...$data,
                'user_id' => $user->id,
                'sort_order' => $i,
            ]);

            if ($routine->cadence->value === 'daily') {
                for ($d = 14; $d >= 0; $d--) {
                    if (rand(0, 100) > 20) {
                        RoutineCompletion::create([
                            'routine_id' => $routine->id,
                            'user_id' => $user->id,
                            'completed_date' => Carbon::today()->subDays($d),
                        ]);
                    }
                }
            }

            $streakService->recalculate($routine);
        }

        $inboxItems = [
            'Look into morning cold shower routine — heard it helps with focus',
            'Research meal prep for the week — too much takeout lately',
            'Setup automated backups for the DO server',
            'Read that article about compound interest someone shared',
            'Think about adding a hydration tracker to Pulse',
        ];

        foreach ($inboxItems as $body) {
            InboxItem::create([
                'user_id' => $user->id,
                'body' => $body,
            ]);
        }
    }
}
