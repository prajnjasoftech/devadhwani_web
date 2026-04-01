<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_items', function (Blueprint $table) {
            // For weekly scheduling - day of week (0=Sunday, 6=Saturday)
            $table->tinyInteger('weekly_day')->nullable()->after('frequency');
        });
    }

    public function down(): void
    {
        Schema::table('booking_items', function (Blueprint $table) {
            $table->dropColumn('weekly_day');
        });
    }
};
