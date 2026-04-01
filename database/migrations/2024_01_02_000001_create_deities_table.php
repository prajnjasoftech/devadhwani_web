<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('temple_id')->constrained()->onDelete('cascade');
            $table->string('name', 255);
            $table->string('sanskrit_name', 255)->nullable();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->enum('deity_type', ['main', 'sub', 'upadevata'])->default('sub');
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deities');
    }
};
