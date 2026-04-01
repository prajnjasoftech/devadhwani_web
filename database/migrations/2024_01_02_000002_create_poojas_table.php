<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poojas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('temple_id')->constrained()->onDelete('cascade');
            $table->foreignId('deity_id')->constrained()->onDelete('cascade');
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->enum('frequency', ['once', 'daily', 'weekly', 'monthly'])->default('once');
            $table->date('next_pooja_date')->nullable();
            $table->decimal('amount', 10, 2)->default(0);
            $table->boolean('devotee_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poojas');
    }
};
