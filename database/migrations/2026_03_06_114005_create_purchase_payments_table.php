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
        Schema::disableForeignKeyConstraints();

        Schema::create('purchase_payments', function (Blueprint $table) {
            $table->string('id');
            $table->string('purchase_id')->index();
            $table->string('payment_id')->index();
            $table->decimal('amount');
            $table->foreignId('created_by')->constrained('users', 'by');
            $table->foreignId('updated_by')->nullable()->constrained('users', 'by');
            $table->foreignId('deleted_by')->nullable()->constrained('users', 'by');
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_payments');
    }
};
