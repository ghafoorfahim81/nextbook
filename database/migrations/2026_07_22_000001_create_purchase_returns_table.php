<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Enums\PurchaseReturnReason;
use App\Enums\TransactionStatus;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('company_id')->index();
            $table->integer('number')->index();
            $table->ulid('purchase_id')->index();
            $table->ulid('supplier_id')->index();
            $table->date('date');
            $table->enum('reason', PurchaseReturnReason::values())->nullable();
            $table->text('description')->nullable();
            $table->enum('status', TransactionStatus::values())->default(TransactionStatus::POSTED->value);
            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // One numbering series per company and document type. Partial, because
        // a soft-deleted document must not hold its number against a new one
        // and unique([..., 'deleted_at']) never fires in PostgreSQL.
        DB::statement('CREATE UNIQUE INDEX purchase_returns_company_id_number_live_unique ON purchase_returns (company_id, number) WHERE deleted_at IS NULL');

        Schema::enableForeignKeyConstraints();

        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->foreign('purchase_id')->references('id')->on('purchases');
            $table->foreign('supplier_id')->references('id')->on('ledgers');
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
        Schema::dropIfExists('purchase_returns');
    }
};
