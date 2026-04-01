<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('temple_id')->constrained()->onDelete('cascade');
            $table->string('booking_number', 50)->unique();
            $table->date('booking_date');

            // Contact Details
            $table->string('contact_name', 255);
            $table->string('contact_number', 20)->nullable();
            $table->string('contact_email', 255)->nullable();
            $table->text('contact_address')->nullable();

            // Amount Summary
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('balance_amount', 12, 2)->default(0);

            // Status
            $table->enum('payment_status', ['pending', 'partial', 'paid'])->default('pending');
            $table->enum('booking_status', ['confirmed', 'cancelled', 'completed'])->default('confirmed');

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['temple_id', 'booking_date']);
            $table->index(['temple_id', 'payment_status']);
            $table->index(['temple_id', 'booking_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
