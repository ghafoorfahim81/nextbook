<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_details', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('expense_id', 26)->index();
            $table->decimal('amount', 15, 2);
            $table->string('title'); 
            $table->char('created_by', 26)->index();
            $table->char('updated_by', 26)->nullable();
            $table->char('deleted_by', 26)->nullable(); 
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::table('expense_details', function (Blueprint $table) {
            $table->foreign('expense_id')->references('id')->on('expenses');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_details');
    }
};

