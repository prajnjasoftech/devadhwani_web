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
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('temple_id')->constrained()->onDelete('cascade');
            $table->foreignId('account_id')->constrained()->onDelete('cascade');

            // Entry identification
            $table->string('entry_number', 50);
            $table->date('entry_date');

            // Transaction details
            $table->enum('type', ['credit', 'debit']);
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_after', 14, 2);

            // Polymorphic source reference
            $table->string('source_type', 50); // booking, donation, purchase, expense, salary, employee_payment, opening_balance
            $table->unsignedBigInteger('source_id')->nullable();

            // Description
            $table->string('narration', 500);

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Indexes for performance
            $table->index(['temple_id', 'account_id', 'entry_date']);
            $table->index(['temple_id', 'entry_number']);
            $table->index(['source_type', 'source_id']);
            $table->index(['temple_id', 'entry_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
