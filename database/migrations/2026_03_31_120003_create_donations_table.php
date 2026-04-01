<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('temple_id')->constrained()->onDelete('cascade');
            $table->string('donation_number')->unique();
            $table->date('donation_date');
            $table->foreignId('donation_head_id')->constrained('donation_heads')->onDelete('restrict');
            $table->enum('donation_type', ['financial', 'asset']);

            // Donor details
            $table->string('donor_name');
            $table->string('donor_contact', 20)->nullable();
            $table->text('donor_address')->nullable();

            // For financial donations
            $table->decimal('amount', 14, 2)->nullable();
            $table->enum('payment_method', ['cash', 'upi', 'bank_transfer', 'cheque', 'other'])->nullable();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->onDelete('set null');
            $table->string('reference_number')->nullable(); // Cheque no, transaction id

            // For asset donations
            $table->foreignId('asset_type_id')->nullable()->constrained('asset_types')->onDelete('restrict');
            $table->string('asset_description')->nullable(); // Gold Chain, Silver Lamp, etc.
            $table->decimal('quantity', 12, 3)->nullable();
            $table->decimal('estimated_value', 14, 2)->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['temple_id', 'donation_date']);
            $table->index(['temple_id', 'donation_type']);
            $table->index(['temple_id', 'donation_head_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
