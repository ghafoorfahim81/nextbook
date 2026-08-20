<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ties a payslip to the general-ledger credit that represents it.
 *
 * The accrual is ONE voucher for the whole run, so the usual
 * reference_type/reference_id route that PaymentStatusService uses for sales
 * and purchases only gets as far as the payroll — it cannot say which of the
 * fifty credits inside belongs to which payslip.
 *
 * Storing the line id closes that gap in both directions: the salary payment
 * form can show "Payslip #12, 18,400 outstanding" instead of an anonymous
 * open item, and paid_amount can be derived from settlements rather than from
 * whatever the form claimed it paid.
 *
 * Correlating by ledger_id at read time would also work — there is exactly one
 * liability line per employee per run — but it would re-derive on every read a
 * fact that is already known for certain at the moment of posting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::table('payroll_lines', function (Blueprint $table) {
            $table->ulid('liability_line_id')->nullable()->index()->after('tax_bracket_set_id');
        });

        Schema::table('payroll_lines', function (Blueprint $table) {
            // set null, not cascade: if the GL line is ever force-deleted the
            // payslip is still a real record of what someone was paid. Losing
            // the pointer costs the paid badge; cascading would delete payroll
            // history as a side effect of a ledger cleanup.
            $table->foreign('liability_line_id')
                ->references('id')->on('transaction_lines')
                ->onDelete('set null');
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::table('payroll_lines', function (Blueprint $table) {
            $table->dropForeign(['liability_line_id']);
            $table->dropColumn('liability_line_id');
        });
    }
};
