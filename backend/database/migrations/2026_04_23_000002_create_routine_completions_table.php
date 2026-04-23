<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routine_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('completed_date');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['routine_id', 'completed_date']);
            $table->index(['user_id', 'completed_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_completions');
    }
};
