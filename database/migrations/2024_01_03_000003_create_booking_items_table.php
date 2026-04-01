<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->onDelete('cascade');
            $table->foreignId('pooja_id')->constrained()->onDelete('restrict');
            $table->foreignId('deity_id')->constrained()->onDelete('restrict');

            // Schedule
            $table->date('start_date');
            $table->date('end_date')->nullable(); // null for one-time
            $table->enum('frequency', ['once', 'daily', 'weekly', 'monthly'])->default('once');

            // For monthly recurrence
            $table->enum('monthly_type', ['by_date', 'by_nakshathra'])->nullable();
            $table->integer('monthly_day')->nullable(); // 1-31 for by_date

            // Pricing
            $table->decimal('unit_amount', 10, 2); // Amount per occurrence per beneficiary
            $table->integer('beneficiary_count')->default(1);
            $table->integer('occurrence_count')->default(1); // Number of scheduled dates
            $table->decimal('total_amount', 12, 2);

            $table->text('notes')->nullable();
            $table->enum('status', ['active', 'cancelled'])->default('active');

            $table->timestamps();

            // Indexes
            $table->index(['booking_id', 'status']);
            $table->index(['pooja_id', 'deity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_items');
    }
};
