<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\TransferStatus;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('item_transfers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->date('date')->index();
            $table->ulid('from_store_id')->index();
            $table->ulid('to_store_id')->index();
            $table->enum('status', TransferStatus::values())->default(TransferStatus::PENDING->value);
            $table->decimal('transfer_cost', 19, 4)->nullable();
            $table->ulid('branch_id')->index();
            $table->text('remarks')->nullable();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('item_transfers', function (Blueprint $table) {
            $table->foreign('from_store_id')->references('id')->on('stores');
            $table->foreign('to_store_id')->references('id')->on('stores');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('set null');
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_transfers');
    }
};
