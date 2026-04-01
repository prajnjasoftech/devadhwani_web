<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('temple_id')->constrained()->onDelete('cascade');
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->integer('year');
            $table->integer('month'); // 1-12
            $table->decimal('basic_salary', 12, 2);
            $table->decimal('allowances', 12, 2)->default(0);
            $table->decimal('deductions', 12, 2)->default(0);
            $table->decimal('net_salary', 12, 2);
            $table->enum('payment_status', ['pending', 'partial', 'paid'])->default('pending');
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->date('payment_date')->nullable();
            $table->enum('payment_method', ['cash', 'upi', 'bank_transfer', 'cheque'])->nullable();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->onDelete('set null');
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->unique(['employee_id', 'year', 'month']);
            $table->index(['temple_id', 'year', 'month']);
            $table->index(['temple_id', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salaries');
    }
};
