<?php

use App\Enums\ComponentCalculationType;
use App\Enums\PayFrequency;
use App\Enums\PayrollLinePaymentStatus;
use App\Enums\PayrollStatus;
use App\Enums\SalaryComponentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('payrolls', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('number')->index();
            $table->string('name')->nullable();

            $table->date('period_start');
            $table->date('period_end');
            $table->date('pay_date')->nullable();
            // Denormalised Jalali label, e.g. "1405-05". Users search payroll by
            // Afghan month, and `WHERE period_label = ?` beats converting every
            // row to compare.
            $table->string('period_label', 20)->nullable()->index();

            $table->enum('pay_frequency', PayFrequency::values())
                ->default(PayFrequency::Monthly->value);
            $table->ulid('currency_id')->nullable()->index();
            $table->decimal('rate', 19, 8)->default(1);

            $table->enum('status', PayrollStatus::values())
                ->default(PayrollStatus::Draft->value)
                ->index();

            $table->decimal('total_gross', 18, 4)->default(0);
            $table->decimal('total_deductions', 18, 4)->default(0);
            $table->decimal('total_tax', 18, 4)->default(0);
            $table->decimal('total_net', 18, 4)->default(0);
            $table->integer('employee_count')->default(0);

            // Optional scoping, so a branch can run one department at a time.
            $table->ulid('department_id')->nullable()->index();
            $table->string('employment_type')->nullable();

            $table->ulid('transaction_id')->nullable()->index();
            $table->ulid('reversal_transaction_id')->nullable()->index();

            $table->ulid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->ulid('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->ulid('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->text('remark')->nullable();

            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['branch_id', 'number', 'deleted_at']);
            $table->index(['branch_id', 'status', 'period_start']);
        });

        // One payslip.
        Schema::create('payroll_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('payroll_id')->index();
            $table->ulid('employee_id')->index();

            $table->ulid('salary_structure_id')->nullable()->index();
            // Which bracket set produced the tax on this payslip. Without it a
            // re-run after a rate change could not reproduce the original
            // figure, and a reprint would silently disagree with what was paid.
            $table->ulid('tax_bracket_set_id')->nullable()->index();

            $table->ulid('currency_id')->nullable()->index();
            $table->decimal('rate', 19, 8)->default(1);

            $table->decimal('working_days', 10, 2)->default(0);
            $table->decimal('present_days', 10, 2)->default(0);
            $table->decimal('absent_days', 10, 2)->default(0);
            $table->decimal('paid_leave_days', 10, 2)->default(0);
            $table->decimal('unpaid_leave_days', 10, 2)->default(0);
            $table->decimal('overtime_hours', 10, 2)->default(0);

            $table->decimal('basic_salary', 18, 4)->default(0);
            $table->decimal('gross_earnings', 18, 4)->default(0);
            $table->decimal('total_deductions', 18, 4)->default(0);
            $table->decimal('taxable_income', 18, 4)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('net_payable', 18, 4)->default(0);

            // Base-currency snapshot, frozen at calculation. Lets a mixed
            // AFN/USD run be summed for reporting without re-rating, and keeps
            // a reprint showing the number that was actually posted.
            $table->decimal('base_gross', 18, 4)->default(0);
            $table->decimal('base_net', 18, 4)->default(0);

            $table->decimal('paid_amount', 18, 4)->default(0);
            $table->enum('payment_status', PayrollLinePaymentStatus::values())
                ->default(PayrollLinePaymentStatus::Unpaid->value)
                ->index();

            $table->text('remark')->nullable();

            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['branch_id', 'employee_id']);
        });

        // The payslip breakdown. Required, not optional: a payslip reprinted two
        // years later must show what was paid, under the name it was paid
        // under — not what the component happens to be called today, or that it
        // has since been deleted.
        Schema::create('payroll_line_components', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('payroll_line_id')->index();
            $table->ulid('salary_component_id')->nullable()->index();

            $table->string('component_code');
            $table->string('component_name');
            $table->enum('component_type', SalaryComponentType::values());
            $table->enum('calculation_type', ComponentCalculationType::values())
                ->default(ComponentCalculationType::Fixed->value);

            $table->decimal('rate_or_percentage', 19, 8)->nullable();
            $table->decimal('base_amount', 18, 4)->nullable();
            $table->decimal('amount', 18, 4)->default(0);

            $table->boolean('is_taxable')->default(true);
            $table->ulid('account_id')->nullable()->index();
            $table->smallInteger('sequence')->default(0);

            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->nullable()->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();

        Schema::table('payrolls', function (Blueprint $table) {
            $table->foreign('currency_id')->references('id')->on('currencies')->onDelete('set null');
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
            $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('set null');
            $table->foreign('reversal_transaction_id')->references('id')->on('transactions')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('posted_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('cancelled_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::table('payroll_lines', function (Blueprint $table) {
            $table->foreign('payroll_id')->references('id')->on('payrolls')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees');
            $table->foreign('salary_structure_id')->references('id')->on('salary_structures')->onDelete('set null');
            $table->foreign('tax_bracket_set_id')->references('id')->on('tax_bracket_sets')->onDelete('set null');
            $table->foreign('currency_id')->references('id')->on('currencies')->onDelete('set null');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::table('payroll_line_components', function (Blueprint $table) {
            $table->foreign('payroll_line_id')->references('id')->on('payroll_lines')->onDelete('cascade');
            $table->foreign('salary_component_id')->references('id')->on('salary_components')->onDelete('set null');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('set null');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });

        // Attendance days a posted payroll has consumed.
        Schema::table('attendances', function (Blueprint $table) {
            $table->foreign('payroll_id')->references('id')->on('payrolls')->onDelete('set null');
        });

        // One payslip per employee per run, enforced over LIVE rows only.
        //
        // Postgres compares NULLs as DISTINCT, so the usual
        // unique(..., deleted_at) shape would accept duplicates. Payroll
        // calculation rebuilds lines by deleting and re-inserting, so a
        // duplicate here would double someone's pay — the constraint has to be
        // real rather than decorative.
        DB::statement(
            'CREATE UNIQUE INDEX payroll_lines_live_employee_unique '.
            'ON payroll_lines (payroll_id, employee_id) WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropForeign(['payroll_id']);
        });

        DB::statement('DROP INDEX IF EXISTS payroll_lines_live_employee_unique');

        Schema::dropIfExists('payroll_line_components');
        Schema::dropIfExists('payroll_lines');
        Schema::dropIfExists('payrolls');
    }
};
