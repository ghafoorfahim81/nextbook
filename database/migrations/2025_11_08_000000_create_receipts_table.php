<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('receipts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('company_id')->index();
            $table->string('number')->index();
            $table->date('date');
            $table->ulid('ledger_id')->index(); 
            $table->string('cheque_no')->nullable();
            $table->text('narration')->nullable();
            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->ulid('deleted_by')->nullable();
        });

        // One numbering series per company and document type. Partial, because
        // a soft-deleted document must not hold its number against a new one
        // and unique([..., 'deleted_at']) never fires in PostgreSQL.
        DB::statement('CREATE UNIQUE INDEX receipts_company_id_number_live_unique ON receipts (company_id, number) WHERE deleted_at IS NULL');

        Schema::enableForeignKeyConstraints();

        Schema::table('receipts', function (Blueprint $table) {
            $table->foreign('ledger_id')->references('id')->on('ledgers'); 
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};


