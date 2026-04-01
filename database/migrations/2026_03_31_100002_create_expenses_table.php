<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('temple_id')->constrained()->onDelete('cascade');
            $table->string('expense_number')->unique();
            $table->date('expense_date');
            $table->foreignId('category_id')->constrained('expense_categories')->onDelete('restrict');
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->enum('payment_status', ['pending', 'partial', 'paid'])->default('pending');
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->enum('payment_method', ['cash', 'upi', 'bank_transfer', 'cheque', 'other'])->nullable();
            $table->string('reference_number')->nullable(); // Bill/Invoice/Cheque number
            $table->string('paid_to')->nullable(); // Vendor/Person name
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['temple_id', 'expense_date']);
            $table->index(['temple_id', 'category_id']);
            $table->index(['temple_id', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
