<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('module_key', 50);
            $table->string('module_name', 100);
            $table->enum('action', ['create', 'read', 'update', 'delete']);
            $table->timestamps();

            $table->unique(['module_key', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
