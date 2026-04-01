<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_item_id')->constrained()->onDelete('cascade');
            $table->date('scheduled_date');
            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('pending');

            // Completion tracking
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();

            // Cancellation tracking
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            // Critical indexes for daily queries
            $table->index(['scheduled_date', 'status']);
            $table->index('booking_item_id');

            // Composite index for temple-wise daily queries (via booking_items -> bookings)
            $table->unique(['booking_item_id', 'scheduled_date'], 'booking_schedule_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_schedules');
    }
};
