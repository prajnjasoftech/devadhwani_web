<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_beneficiaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_item_id')->constrained()->onDelete('cascade');
            $table->string('name', 255);
            $table->foreignId('nakshathra_id')->nullable()->constrained('nakshathras')->nullOnDelete();
            $table->string('gothram', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Index
            $table->index('booking_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_beneficiaries');
    }
};
