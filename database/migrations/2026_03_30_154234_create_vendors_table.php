<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('temple_id')->constrained()->onDelete('cascade');
            $table->string('name', 255);
            $table->string('contact_person', 255)->nullable();
            $table->string('contact_number', 20)->nullable();
            $table->string('email', 255)->nullable();
            $table->text('address')->nullable();
            $table->string('gst_number', 50)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['temple_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
