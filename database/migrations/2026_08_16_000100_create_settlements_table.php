<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Settlement: which receipt line relieved which receivable line, and at what rates.
 *
 * Settlement is recorded against JOURNAL LINES, not documents. Every receivable
 * — a sales invoice, an opening balance, a credit note, a manual journal debit
 * to AR — is a transaction_lines row with account_id = AR, a ledger_id, a
 * currency_id and its own rate. That row is already a complete description of
 * the claim, which is why opening balances need no special case here.
 *
 * ONE table serves both sides. An AP settlement simply points target_line_id at
 * a credit line instead of a debit line. Separate customer/supplier tables would
 * duplicate every query and drift apart.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // The receipt / payment voucher this application belongs to.
            $table->ulid('transaction_id')->index();

            // Its AR credit (receipt) or AP debit (payment) line.
            $table->ulid('settling_line_id')->index();

            // The AR debit / AP credit line being relieved.
            $table->ulid('target_line_id')->index();

            $table->ulid('ledger_id')->index();

            // The TARGET's currency. amount_applied is always denominated in it,
            // even when the cash came in as something else.
            $table->ulid('currency_id');

            $table->decimal('amount_applied', 19, 4);

            // The rate the claim was BOOKED at, and the rate the cash moved at.
            // Both are stored because the difference between them is the whole
            // reason this table exists, and re-deriving them later from the
            // lines would break the moment a line is reversed.
            $table->decimal('target_rate', 19, 8);
            $table->decimal('settlement_rate', 19, 8);

            // amount_applied x target_rate — what leaves the subledger in AFN.
            $table->decimal('base_relieved', 19, 4);

            // cash_base - base_relieved. Negative is a loss, positive a gain.
            $table->decimal('forex_amount', 19, 4)->default(0);

            // A USD invoice settled with AFN cash. The entry balances in base
            // only, and reports need to tell these apart from same-currency
            // settlements where amount_applied and the cash agree.
            $table->boolean('is_cross_currency')->default(false);

            $table->ulid('created_by')->nullable();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->ulid('branch_id')->index();

            $table->timestamps();
            $table->softDeletes();

            // One application per (settling line, target line) pair. A receipt
            // that pays the same invoice twice is a single larger amount, not
            // two rows — otherwise "how much is left" depends on which rows you
            // happened to read.
            $table->unique(['settling_line_id', 'target_line_id'], 'settlements_line_pair_unique');

            // Open-item lookup: everything this customer owes in this currency.
            $table->index(['ledger_id', 'currency_id'], 'settlements_ledger_currency_idx');
        });

        Schema::table('settlements', function (Blueprint $table) {
            $table->foreign('transaction_id')->references('id')->on('transactions');
            $table->foreign('settling_line_id')->references('id')->on('transaction_lines');
            $table->foreign('target_line_id')->references('id')->on('transaction_lines');
            $table->foreign('ledger_id')->references('id')->on('ledgers');
            $table->foreign('currency_id')->references('id')->on('currencies');
            $table->foreign('branch_id')->references('id')->on('branches');
        });

        // An applied amount of zero settles nothing and would still consume the
        // unique pair slot, blocking the real application that follows it.
        \Illuminate\Support\Facades\DB::statement(<<<'SQL'
            ALTER TABLE settlements
            ADD CONSTRAINT chk_settlement_amount_positive
            CHECK (amount_applied > 0)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};
