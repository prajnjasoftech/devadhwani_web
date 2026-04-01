<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('temple_id')->nullable()->constrained('temples')->onDelete('cascade');
            $table->enum('user_type', ['platform_admin', 'temple_user']);
            $table->string('name');
            $table->string('contact_number', 20)->unique();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->foreignId('role_id')->nullable()->constrained('roles')->onDelete('set null');
            $table->string('password');
            $table->boolean('must_reset_password')->default(true);
            $table->boolean('is_active')->default(true);
            $table->string('profile_image')->nullable();
            $table->enum('id_proof_type', ['aadhaar', 'pan', 'driving_license'])->nullable();
            $table->string('id_proof_number', 100)->nullable();
            $table->string('id_proof_file')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('contact_number', 20)->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
