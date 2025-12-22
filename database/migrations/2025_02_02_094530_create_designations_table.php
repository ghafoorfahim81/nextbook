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

        Schema::create('designations', function (Blueprint $table) {
            $table->char('id', 26)->primary();
           $table->string('name')->index();
           $table->text('remark')->nullable();
           $table->char('branch_id', 26)->index();
           $table->char('created_by', 26)->index();
           $table->char('updated_by', 26)->nullable();
           $table->unique(['branch_id', 'name', 'deleted_at']);
           $table->timestamps();
           $table->softDeletes();
           $table->char('deleted_by', 26)->nullable();
        });

        Schema::enableForeignKeyConstraints();
        Schema::table('designations', function (Blueprint $table) {
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('designations');
    }
};
