<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('poojas', function (Blueprint $table) {
            $table->dropForeign(['deity_id']);
        });

        Schema::table('poojas', function (Blueprint $table) {
            $table->unsignedBigInteger('deity_id')->nullable()->change();
            $table->foreign('deity_id')->references('id')->on('deities')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('poojas', function (Blueprint $table) {
            $table->dropForeign(['deity_id']);
        });

        Schema::table('poojas', function (Blueprint $table) {
            $table->unsignedBigInteger('deity_id')->nullable(false)->change();
            $table->foreign('deity_id')->references('id')->on('deities')->onDelete('cascade');
        });
    }
};
