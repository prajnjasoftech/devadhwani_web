<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temples', function (Blueprint $table) {
            $table->id();
            $table->string('temple_name');
            $table->string('temple_code', 50)->unique();
            $table->string('contact_person_name');
            $table->string('contact_number', 20);
            $table->string('alternate_contact_number', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('district', 100)->nullable();
            $table->string('place', 100)->nullable();
            $table->string('image')->nullable();
            $table->enum('id_proof_type', ['aadhaar', 'pan', 'driving_license'])->nullable();
            $table->string('id_proof_number', 100)->nullable();
            $table->string('id_proof_file')->nullable();
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temples');
    }
};
