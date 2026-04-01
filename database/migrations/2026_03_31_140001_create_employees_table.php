<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('temple_id')->constrained()->onDelete('cascade');
            $table->string('employee_code')->unique();
            $table->string('name');
            $table->string('designation'); // Priest, Peon, Manager, etc.
            $table->string('contact_number', 20);
            $table->string('alternate_contact', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->date('date_of_joining');
            $table->date('date_of_leaving')->nullable();
            $table->decimal('basic_salary', 12, 2)->default(0);
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('ifsc_code')->nullable();
            $table->string('pan_number', 20)->nullable();
            $table->string('aadhaar_number', 20)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null'); // If employee is also a user
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['temple_id', 'is_active']);
            $table->index(['temple_id', 'designation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
