<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devotees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('temple_id')->constrained()->onDelete('cascade');
            $table->string('name', 255);
            $table->foreignId('nakshathra_id')->nullable()->constrained('nakshathras')->nullOnDelete();
            $table->string('gothram', 100)->nullable();
            $table->timestamps();

            // Index for temple scoping
            $table->index('temple_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devotees');
    }
};
