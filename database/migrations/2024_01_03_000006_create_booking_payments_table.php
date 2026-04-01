<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 12, 2);
            $table->enum('payment_method', ['cash', 'card', 'upi', 'bank_transfer', 'other'])->default('cash');
            $table->date('payment_date');
            $table->string('reference_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Indexes
            $table->index(['booking_id', 'payment_date']);
            $table->index('payment_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_payments');
    }
};
