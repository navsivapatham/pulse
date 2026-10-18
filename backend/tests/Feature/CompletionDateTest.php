<?php

namespace Tests\Feature;

use App\Models\Routine;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompletionDateTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_routine_shows_as_completed_and_completing_twice_is_safe(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $routine = Routine::create(['user_id' => $user->id, 'title' => 'R', 'cadence' => 'daily']);

        $this->postJson("/api/routines/{$routine->id}/complete", ['date' => '2026-10-18'])->assertOk();
        $this->postJson("/api/routines/{$routine->id}/complete", ['date' => '2026-10-18'])->assertOk();

        $this->getJson('/api/routines?date=2026-10-18')
            ->assertOk()->assertJsonPath('data.0.is_completed_today', true);

        $this->postJson("/api/routines/{$routine->id}/uncomplete", ['date' => '2026-10-18'])->assertOk();
        $this->assertDatabaseCount('routine_completions', 0);
    }

    public function test_today_is_the_users_local_date_not_utc(): void
    {
        config(['app.user_timezone' => 'America/New_York']);
        Carbon::setTestNow(Carbon::parse('2026-10-19 03:30:00', 'UTC')); // 23:30 in New York

        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $routine = Routine::create(['user_id' => $user->id, 'title' => 'R', 'cadence' => 'daily']);

        $this->postJson("/api/routines/{$routine->id}/complete")
            ->assertOk()->assertJsonPath('data.streak.current_streak', 1);

        $this->assertDatabaseHas('routine_completions', ['completed_date' => '2026-10-18']);
        Carbon::setTestNow();
    }
}
