<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routines', function (Blueprint $table) {
            $table->unsignedTinyInteger('weekday')->nullable()->after('scheduled_time');
            $table->unsignedTinyInteger('month_day')->nullable()->after('weekday');
        });
    }

    public function down(): void
    {
        Schema::table('routines', function (Blueprint $table) {
            $table->dropColumn(['weekday', 'month_day']);
        });
    }
};
