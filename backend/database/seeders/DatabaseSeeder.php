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
            ['title' => 'Exercise', 'cadence' => 'daily', 'scheduled_time' => '07:00', 'color' => '#EF4444', 'icon' => 'dumbbell'],
            ['title' => 'Meditate', 'cadence' => 'daily', 'scheduled_time' => '07:45', 'color' => '#8B5CF6', 'icon' => 'brain'],
            ['title' => 'Skincare routine', 'cadence' => 'daily', 'scheduled_time' => '08:00', 'color' => '#EC4899', 'icon' => 'sparkles'],
            ['title' => 'Read 30min', 'cadence' => 'daily', 'scheduled_time' => '21:00', 'color' => '#3B82F6', 'icon' => 'book-open'],
            ['title' => 'Journal', 'cadence' => 'daily', 'scheduled_time' => '21:30', 'color' => '#F59E0B', 'icon' => 'pen-line'],
            ['title' => 'Plan the week', 'cadence' => 'weekly', 'scheduled_time' => '09:00', 'color' => '#10B981', 'icon' => 'calendar'],
            ['title' => 'Weekly review', 'cadence' => 'weekly', 'scheduled_time' => '17:00', 'color' => '#6366F1', 'icon' => 'clipboard-check'],
            ['title' => 'Monthly retro', 'cadence' => 'monthly', 'color' => '#14B8A6', 'icon' => 'bar-chart-3'],
        ];

        foreach ($routines as $i => $data) {
            Routine::create([
                ...$data,
                'user_id' => $user->id,
                'sort_order' => $i,
            ]);
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
