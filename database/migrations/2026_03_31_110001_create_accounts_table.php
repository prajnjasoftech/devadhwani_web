<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('temple_id')->constrained()->onDelete('cascade');
            $table->enum('account_type', ['cash', 'bank']);
            $table->string('account_name'); // "Cash", "SBI Savings", "HDFC Current"
            $table->string('bank_name')->nullable(); // For bank accounts
            $table->string('account_number')->nullable(); // Bank account number
            $table->string('ifsc_code')->nullable();
            $table->string('branch')->nullable();
            $table->boolean('is_upi_account')->default(false); // Only one bank can be UPI
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->decimal('current_balance', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['temple_id', 'account_type']);
            $table->index(['temple_id', 'is_active']);
        });

        // Track if accounts setup is completed for a temple
        Schema::table('temples', function (Blueprint $table) {
            $table->boolean('accounts_setup_completed')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');

        Schema::table('temples', function (Blueprint $table) {
            $table->dropColumn('accounts_setup_completed');
        });
    }
};
