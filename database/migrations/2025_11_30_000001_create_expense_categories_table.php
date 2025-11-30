<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->string('name');
            $table->text('remarks')->nullable();
            $table->boolean('is_active')->default(true);
            $table->char('branch_id', 26);
            $table->char('created_by', 26);
            $table->char('updated_by', 26)->nullable();
            $table->char('deleted_by', 26)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['branch_id', 'name', 'deleted_at']);
        });
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};

