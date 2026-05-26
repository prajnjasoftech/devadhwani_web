<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('booking_items', function (Blueprint $table) {
            // Schedule type: how to determine schedule dates
            $table->enum('schedule_type', [
                'once',
                'daily',
                'weekly',
                'monthly_same_date',
                'monthly_nakshatra',
                'monthly_malayalam_weekday',
                'monthly_pooja_schedule',
            ])->default('once')->after('frequency');

            // Schedule rule: JSON with type-specific parameters
            // Examples:
            // monthly_same_date: {"day": 15}
            // monthly_nakshatra: {"nakshatra_id": 5}
            // monthly_malayalam_weekday: {"weekday": 1} (1=Monday)
            // monthly_pooja_schedule: {} (uses pooja's next_pooja_date)
            $table->json('schedule_rule')->nullable()->after('schedule_type');

            // Track occurrences for recurring bookings
            $table->unsignedInteger('occurrences_total')->default(1)->after('schedule_rule');
            $table->unsignedInteger('occurrences_completed')->default(0)->after('occurrences_total');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_items', function (Blueprint $table) {
            $table->dropColumn([
                'schedule_type',
                'schedule_rule',
                'occurrences_total',
                'occurrences_completed',
            ]);
        });
    }
};
