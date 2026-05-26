<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Change monthly_type from ENUM to VARCHAR to support new values
        DB::statement("ALTER TABLE booking_items MODIFY monthly_type VARCHAR(50) NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE booking_items MODIFY monthly_type ENUM('by_date', 'by_nakshathra') NULL");
    }
};
