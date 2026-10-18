<?php

namespace Tests\Feature;

use App\Models\Routine;
use App\Models\RoutineMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoutineMessageTest extends TestCase
{
    use RefreshDatabase;

    private function routine(User $user): Routine
    {
        return Routine::create([
            'user_id' => $user->id,
            'title' => 'Wind down',
            'cadence' => 'daily',
            'scheduled_time' => '23:00',
        ]);
    }

    public function test_upsert_creates_then_replaces_unsent_message(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $routine = $this->routine($user);

        $this->putJson("/api/routines/{$routine->id}/messages", [
            'date' => '2026-10-19', 'kind' => 'reminder', 'body' => 'first',
        ])->assertStatus(201);

        $this->putJson("/api/routines/{$routine->id}/messages", [
            'date' => '2026-10-19', 'kind' => 'reminder', 'body' => 'second',
        ])->assertOk()->assertJsonPath('data.body', 'second');

        $this->assertSame(1, RoutineMessage::count());
        $this->assertSame('2026-10-19', RoutineMessage::first()->message_date);
    }

    public function test_upsert_refuses_once_sent(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $routine = $this->routine($user);

        RoutineMessage::create([
            'routine_id' => $routine->id, 'message_date' => '2026-10-19',
            'kind' => 'reminder', 'body' => 'gone', 'sent_at' => now(),
        ]);

        $this->putJson("/api/routines/{$routine->id}/messages", [
            'date' => '2026-10-19', 'kind' => 'reminder', 'body' => 'late',
        ])->assertStatus(409);
    }

    public function test_upsert_validates_kind_and_ownership(): void
    {
        $owner = User::factory()->create();
        $routine = $this->routine($owner);

        Sanctum::actingAs($owner);
        $this->putJson("/api/routines/{$routine->id}/messages", [
            'date' => '2026-10-19', 'kind' => 'shout', 'body' => 'x',
        ])->assertStatus(422);

        Sanctum::actingAs(User::factory()->create());
        $this->putJson("/api/routines/{$routine->id}/messages", [
            'date' => '2026-10-19', 'kind' => 'reminder', 'body' => 'x',
        ])->assertNotFound();
    }

    public function test_checkin_time_is_settable(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $routine = $this->routine($user);

        $this->patchJson("/api/routines/{$routine->id}", ['checkin_time' => '08:00'])
            ->assertOk()->assertJsonPath('data.checkin_time', '08:00');
    }

    public function test_complete_with_explicit_date_records_that_date(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $routine = $this->routine($user);

        $this->postJson("/api/routines/{$routine->id}/complete", ['date' => '2026-10-18'])->assertOk();

        $this->assertDatabaseHas('routine_completions', [
            'routine_id' => $routine->id, 'completed_date' => '2026-10-18',
        ]);
    }
}
