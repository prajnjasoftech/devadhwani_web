<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('temple_id')->constrained()->onDelete('cascade');
            $table->string('purchase_number', 50)->unique();
            $table->date('purchase_date');
            $table->foreignId('vendor_id')->constrained()->onDelete('restrict');
            $table->foreignId('category_id')->constrained('purchase_categories')->onDelete('restrict');
            $table->foreignId('purpose_id')->constrained('purchase_purposes')->onDelete('restrict');
            $table->string('item_description', 500);
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('unit', 50)->nullable(); // kg, litre, nos, packet, etc.
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->enum('payment_status', ['pending', 'partial', 'paid'])->default('pending');
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->enum('payment_method', ['cash', 'upi', 'bank_transfer', 'credit', 'other'])->nullable();
            $table->string('bill_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['temple_id', 'purchase_date']);
            $table->index(['temple_id', 'vendor_id']);
            $table->index(['temple_id', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
