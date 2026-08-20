<?php

use App\Enums\LoanRepaymentSource;
use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Enums\PaymentMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('employee_loans', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('number')->index();
            $table->ulid('employee_id')->index();

            $table->enum('loan_type', LoanType::values())->default(LoanType::Advance->value);
            $table->ulid('currency_id')->nullable()->index();
            $table->decimal('rate', 19, 8)->default(1);

            $table->decimal('principal_amount', 18, 4)->default(0);
            $table->decimal('installment_amount', 18, 4)->default(0);
            $table->smallInteger('installments_count')->default(1);
            $table->boolean('deduct_from_payroll')->default(true);

            $table->date('issue_date');
            $table->date('first_deduction_period')->nullable();
            $table->decimal('interest_rate', 10, 4)->default(0);

            // Denormalised running balance, recomputed from repayments on every
            // write. Payroll reads it per employee per run, and a SUM subquery
            // there would be an N+1 across the whole workforce.
            $table->decimal('outstanding_amount', 18, 4)->default(0);

            $table->enum('status', LoanStatus::values())
                ->default(LoanStatus::Draft->value)
                ->index();

            $table->ulid('bank_account_id')->nullable()->index();
            $table->ulid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->ulid('transaction_id')->nullable()->index();
            $table->text('remark')->nullable();

            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['branch_id', 'number', 'deleted_at']);
            $table->index(['branch_id', 'employee_id', 'status']);
        });

        Schema::create('employee_loan_repayments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('employee_loan_id')->index();
            // Set when the instalment came out of a payroll run, so reversing
            // that run can find and undo exactly its own repayments.
            $table->ulid('payroll_line_id')->nullable()->index();
            $table->ulid('salary_payment_id')->nullable()->index();

            $table->date('date');
            $table->decimal('amount', 18, 4)->default(0);
            $table->ulid('currency_id')->nullable()->index();
            $table->decimal('rate', 19, 8)->default(1);

            $table->enum('source', LoanRepaymentSource::values())
                ->default(LoanRepaymentSource::Payroll->value);
            $table->ulid('transaction_id')->nullable()->index();
            $table->text('remark')->nullable();

            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // Named salary_payments because `payments` is the supplier voucher.
        Schema::create('salary_payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('number')->index();
            $table->date('date');

            $table->ulid('payroll_id')->nullable()->index();
            $table->ulid('employee_id')->nullable()->index();
            // The employee's companion ledger — what SettlementService matches
            // open payslip items against.
            $table->ulid('ledger_id')->nullable()->index();

            $table->ulid('currency_id')->nullable()->index();
            $table->decimal('rate', 19, 8)->default(1);
            $table->decimal('amount', 18, 4)->default(0);

            $table->enum('payment_mode', PaymentMode::values())
                ->default(PaymentMode::OnAccount->value);
            $table->ulid('bank_account_id')->nullable()->index();
            $table->string('cheque_no')->nullable();

            $table->ulid('transaction_id')->nullable()->index();
            $table->text('narration')->nullable();

            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['branch_id', 'number', 'deleted_at']);
        });

        Schema::create('salary_payment_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('salary_payment_id')->index();
            $table->ulid('payroll_line_id')->index();
            $table->ulid('employee_id')->index();

            $table->decimal('amount', 18, 4)->default(0);
            $table->ulid('currency_id')->nullable()->index();
            $table->decimal('rate', 19, 8)->default(1);

            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(
                ['salary_payment_id', 'payroll_line_id', 'deleted_at'],
                'salary_payment_line_unique'
            );
        });

        Schema::enableForeignKeyConstraints();

        Schema::table('employee_loans', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('currency_id')->references('id')->on('currencies')->onDelete('set null');
            $table->foreign('bank_account_id')->references('id')->on('accounts')->onDelete('set null');
            $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::table('employee_loan_repayments', function (Blueprint $table) {
            $table->foreign('employee_loan_id')->references('id')->on('employee_loans')->onDelete('cascade');
            $table->foreign('payroll_line_id')->references('id')->on('payroll_lines')->onDelete('set null');
            $table->foreign('salary_payment_id')->references('id')->on('salary_payments')->onDelete('set null');
            $table->foreign('currency_id')->references('id')->on('currencies')->onDelete('set null');
            $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('set null');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::table('salary_payments', function (Blueprint $table) {
            $table->foreign('payroll_id')->references('id')->on('payrolls')->onDelete('set null');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('set null');
            $table->foreign('ledger_id')->references('id')->on('ledgers')->onDelete('set null');
            $table->foreign('currency_id')->references('id')->on('currencies')->onDelete('set null');
            $table->foreign('bank_account_id')->references('id')->on('accounts')->onDelete('set null');
            $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('set null');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::table('salary_payment_lines', function (Blueprint $table) {
            $table->foreign('salary_payment_id')->references('id')->on('salary_payments')->onDelete('cascade');
            $table->foreign('payroll_line_id')->references('id')->on('payroll_lines');
            $table->foreign('employee_id')->references('id')->on('employees');
            $table->foreign('currency_id')->references('id')->on('currencies')->onDelete('set null');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_payment_lines');
        Schema::dropIfExists('salary_payments');
        Schema::dropIfExists('employee_loan_repayments');
        Schema::dropIfExists('employee_loans');
    }
};
