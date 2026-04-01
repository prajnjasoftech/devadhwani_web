<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('temple_id')->constrained()->onDelete('cascade');
            $table->string('name'); // Gold, Silver, Land, Vehicle, Furniture, etc.
            $table->string('unit')->nullable(); // grams, kg, sq.ft, nos, etc.
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['temple_id', 'is_active']);
            $table->unique(['temple_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_types');
    }
};
