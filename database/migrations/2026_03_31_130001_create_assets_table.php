<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('temple_id')->constrained()->onDelete('cascade');
            $table->string('asset_number')->unique();
            $table->foreignId('asset_type_id')->constrained('asset_types')->onDelete('restrict');
            $table->string('name'); // Gold Chain, Silver Lamp, Temple Land, etc.
            $table->text('description')->nullable();
            $table->decimal('quantity', 12, 3);
            $table->decimal('estimated_value', 14, 2)->nullable();
            $table->date('acquisition_date')->nullable();
            $table->enum('acquisition_type', ['existing', 'donation', 'purchase'])->default('existing');
            $table->foreignId('donation_id')->nullable()->constrained('donations')->onDelete('set null');
            $table->string('location')->nullable(); // Where it's stored
            $table->enum('condition', ['excellent', 'good', 'fair', 'poor'])->default('good');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['temple_id', 'asset_type_id']);
            $table->index(['temple_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
