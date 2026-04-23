<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\InboxController;
use App\Http\Controllers\Api\RoutineController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\TaskController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/auth-user', [AuthController::class, 'getUser']);

    Route::get('/routines', [RoutineController::class, 'index']);
    Route::post('/routines', [RoutineController::class, 'store']);
    Route::get('/routines/{id}', [RoutineController::class, 'show']);
    Route::patch('/routines/{id}', [RoutineController::class, 'update']);
    Route::delete('/routines/{id}', [RoutineController::class, 'destroy']);
    Route::post('/routines/{id}/complete', [RoutineController::class, 'complete']);
    Route::post('/routines/{id}/uncomplete', [RoutineController::class, 'uncomplete']);

    Route::get('/inbox', [InboxController::class, 'index']);
    Route::post('/inbox', [InboxController::class, 'store']);
    Route::post('/inbox/{id}/review', [InboxController::class, 'review']);

    Route::get('/tasks', [TaskController::class, 'index']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::patch('/tasks/{id}', [TaskController::class, 'update']);
    Route::delete('/tasks/{id}', [TaskController::class, 'destroy']);

    Route::get('/stats/daily', [StatsController::class, 'daily']);
    Route::get('/stats/weekly', [StatsController::class, 'weekly']);
    Route::get('/stats/streaks', [StatsController::class, 'streaks']);
});
