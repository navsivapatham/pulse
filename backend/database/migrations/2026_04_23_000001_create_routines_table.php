<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('cadence', 20); // daily, weekly, monthly
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->string('color', 7)->nullable();
            $table->string('icon', 50)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index(['user_id', 'cadence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routines');
    }
};
