<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // For non-salary payments: Bonus, Advance, Reimbursement, etc.
        Schema::create('employee_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('temple_id')->constrained()->onDelete('cascade');
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->string('payment_number')->unique();
            $table->date('payment_date');
            $table->enum('payment_type', ['bonus', 'advance', 'reimbursement', 'incentive', 'other']);
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->enum('payment_method', ['cash', 'upi', 'bank_transfer', 'cheque'])->nullable();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->onDelete('set null');
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['temple_id', 'employee_id']);
            $table->index(['temple_id', 'payment_date']);
            $table->index(['temple_id', 'payment_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_payments');
    }
};
