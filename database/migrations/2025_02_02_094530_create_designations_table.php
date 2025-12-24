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
            $table->ulid('id')->primary();
           $table->string('name')->index();
           $table->text('remark')->nullable();
           $table->ulid('branch_id')->index();
           $table->ulid('created_by')->index();
           $table->ulid('updated_by')->nullable();
           $table->unique(['branch_id', 'name', 'deleted_at']);
           $table->timestamps();
           $table->softDeletes();
           $table->ulid('deleted_by')->nullable();
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
