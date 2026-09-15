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

        Schema::create('item_transfer_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('item_transfer_id')->index();
            $table->ulid('item_id')->index();
            $table->string('batch')->nullable();
            $table->date('expire_date')->nullable();
            $table->decimal('quantity', 19, 4);
            $table->ulid('measure_id')->index();
            $table->decimal('unit_price', 19, 4)->nullable();
            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('item_transfer_items', function (Blueprint $table) {
            $table->foreign('item_transfer_id')->references('id')->on('item_transfers')->onDelete('cascade');
            $table->foreign('item_id')->references('id')->on('items');
            $table->foreign('measure_id')->references('id')->on('unit_measures');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_transfer_items');
    }
};
