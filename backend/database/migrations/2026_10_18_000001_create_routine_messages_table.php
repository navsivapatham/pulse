<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routines', function (Blueprint $table) {
            $table->string('checkin_time', 5)->nullable()->after('scheduled_time');
        });

        // One row per message the reminder bot sends (or will send) for a routine on a date.
        // kind = 'reminder' (at scheduled_time) | 'checkin' (next morning, at checkin_time).
        // A row with sent_at NULL is pre-written text waiting to go out; the bot falls back
        // to its plain template when no row exists.
        Schema::create('routine_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routine_id')->constrained()->cascadeOnDelete();
            $table->date('message_date');
            $table->string('kind', 16);
            $table->text('body')->nullable();
            $table->string('source', 16)->default('generated');
            $table->timestamp('sent_at')->nullable();
            $table->unsignedBigInteger('telegram_message_id')->nullable();
            $table->timestamps();

            $table->unique(['routine_id', 'message_date', 'kind']);
            $table->index('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_messages');

        Schema::table('routines', function (Blueprint $table) {
            $table->dropColumn('checkin_time');
        });
    }
};
