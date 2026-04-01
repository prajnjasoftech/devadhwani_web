<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('panchang_data', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();

            // Malayalam Calendar
            $table->unsignedSmallInteger('malayalam_day')->nullable();
            $table->unsignedTinyInteger('malayalam_month')->nullable();
            $table->string('malayalam_month_name', 50)->nullable();
            $table->unsignedSmallInteger('malayalam_year')->nullable();

            // Sun/Moon timings
            $table->string('sunrise', 50)->nullable();
            $table->string('sunset', 50)->nullable();
            $table->string('moonrise', 50)->nullable();
            $table->string('moonset', 50)->nullable();

            // Vaara
            $table->string('vaara', 50)->nullable();

            // Panchang data as JSON
            $table->json('tithi')->nullable();
            $table->json('nakshatra')->nullable();
            $table->json('yoga')->nullable();
            $table->json('karana')->nullable();
            $table->json('auspicious')->nullable();
            $table->json('inauspicious')->nullable();

            $table->timestamps();

            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('panchang_data');
    }
};
