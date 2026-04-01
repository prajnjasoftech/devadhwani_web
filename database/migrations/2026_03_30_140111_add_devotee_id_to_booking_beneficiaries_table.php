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
        Schema::table('booking_beneficiaries', function (Blueprint $table) {
            $table->foreignId('devotee_id')->nullable()->after('booking_item_id')
                ->constrained('devotees')->nullOnDelete();
            $table->index('devotee_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_beneficiaries', function (Blueprint $table) {
            $table->dropForeign(['devotee_id']);
            $table->dropIndex(['devotee_id']);
            $table->dropColumn('devotee_id');
        });
    }
};
