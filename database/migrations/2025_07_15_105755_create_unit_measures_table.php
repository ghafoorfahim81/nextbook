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

        Schema::create('unit_measures', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->string('name')->index();
            $table->string('unit')->index();
            $table->string('symbol')->index();
            $table->double('value')->nullable();
            $table->char('quantity_id',26);
            $table->boolean('is_system')->default(false);
            $table->char('branch_id',26);
            $table->char('created_by',26);
            $table->char('updated_by',26)->nullable();
            $table->char('deleted_by',26)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['branch_id', 'name', 'deleted_at']);
        });

        Schema::enableForeignKeyConstraints();

        Schema::table('unit_measures', function (Blueprint $table) {
            $table->foreign('branch_id')
                ->references('id')
                ->on('branches')
                ->onDelete('CASCADE');

            $table->foreign('quantity_id')->references('id')->on('quantities');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_measures');
    }
};
