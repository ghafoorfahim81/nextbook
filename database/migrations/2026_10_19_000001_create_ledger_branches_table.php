<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which branches may pick a customer or supplier.
 *
 * A ledger is company master data: one record, one balance, so a payment in
 * Herat settles a debt run up in Kabul. What a branch decides is only whether
 * the party shows up in its own document forms. A party starts in the branch
 * that created it and can be opened to others from its form.
 *
 * Both foreign keys carry company_id, so a ledger can only ever be opened to a
 * branch of its own company. Employee and system ledgers have no rows here;
 * they follow the employee and the company.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_branches', function (Blueprint $table) {
            $table->ulid('company_id');
            $table->ulid('ledger_id');
            $table->ulid('branch_id');
            $table->timestamps();

            $table->primary(['ledger_id', 'branch_id']);
            // The picker's lookup: this branch's parties within the company.
            $table->index(['company_id', 'branch_id', 'ledger_id']);

            $table->foreign(['company_id', 'ledger_id'])
                ->references(['company_id', 'id'])->on('ledgers')
                ->cascadeOnDelete();
            $table->foreign(['company_id', 'branch_id'])
                ->references(['company_id', 'id'])->on('branches')
                ->cascadeOnDelete();
        });

        // Parties that exist before this table have no record of where they
        // were created, so they stay available everywhere they were before:
        // every branch of their company.
        DB::statement("
            INSERT INTO ledger_branches (company_id, ledger_id, branch_id, created_at, updated_at)
            SELECT l.company_id, l.id, b.id, NOW(), NOW()
            FROM ledgers l
            JOIN branches b ON b.company_id = l.company_id
            WHERE l.type IN ('customer', 'supplier')
              AND l.deleted_at IS NULL
              AND b.deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_branches');
    }
};
