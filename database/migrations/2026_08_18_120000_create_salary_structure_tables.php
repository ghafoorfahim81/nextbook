<?php

use App\Enums\ComponentCalculationType;
use App\Enums\PayFrequency;
use App\Enums\SalaryComponentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('salary_components', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('code')->index();

            $table->enum('component_type', SalaryComponentType::values())
                ->default(SalaryComponentType::Earning->value)
                ->index();
            $table->enum('calculation_type', ComponentCalculationType::values())
                ->default(ComponentCalculationType::Fixed->value);

            $table->decimal('amount', 18, 4)->nullable();
            $table->decimal('percentage', 10, 4)->nullable();

            $table->boolean('is_taxable')->default(true);
            $table->boolean('affects_gross')->default(true);
            // Whether a partial month reduces the amount. A transport allowance
            // usually prorates; a fixed phone bill usually does not.
            $table->boolean('is_prorated')->default(true);

            // Optional GL override. Null means the run falls back to the
            // employment-type default.
            $table->ulid('account_id')->nullable()->index();

            $table->smallInteger('sequence')->default(0);
            // System components (wage tax, unpaid leave, loan recovery) are
            // created by payroll itself and must not be deleted by a user.
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('remark')->nullable();

            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['branch_id', 'code', 'deleted_at']);
        });

        Schema::create('salary_structures', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('code')->nullable()->index();

            // Null employee means a template attached to a designation or
            // department rather than to one person.
            $table->ulid('employee_id')->nullable()->index();
            $table->ulid('designation_id')->nullable()->index();
            $table->ulid('department_id')->nullable()->index();

            $table->ulid('currency_id')->nullable()->index();
            $table->date('effective_from')->index();
            $table->date('effective_to')->nullable();

            $table->decimal('basic_salary', 18, 4)->default(0);
            $table->enum('pay_frequency', PayFrequency::values())
                ->default(PayFrequency::Monthly->value);

            $table->ulid('expense_account_id')->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->text('remark')->nullable();

            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['branch_id', 'employee_id', 'effective_from']);
        });

        Schema::create('salary_structure_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('salary_structure_id')->index();
            $table->ulid('salary_component_id')->index();

            // Copied from the component but overridable per structure, so one
            // employee can have a larger allowance without a new component.
            $table->enum('calculation_type', ComponentCalculationType::values())
                ->default(ComponentCalculationType::Fixed->value);
            $table->decimal('amount', 18, 4)->nullable();
            $table->decimal('percentage', 10, 4)->nullable();
            $table->smallInteger('sequence')->default(0);

            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(
                ['salary_structure_id', 'salary_component_id', 'deleted_at'],
                'salary_structure_line_unique'
            );
        });

        Schema::enableForeignKeyConstraints();

        Schema::table('salary_components', function (Blueprint $table) {
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('set null');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::table('salary_structures', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('designation_id')->references('id')->on('designations')->onDelete('set null');
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
            $table->foreign('currency_id')->references('id')->on('currencies')->onDelete('set null');
            $table->foreign('expense_account_id')->references('id')->on('accounts')->onDelete('set null');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::table('salary_structure_lines', function (Blueprint $table) {
            $table->foreign('salary_structure_id')->references('id')->on('salary_structures')->onDelete('cascade');
            $table->foreign('salary_component_id')->references('id')->on('salary_components');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_structure_lines');
        Schema::dropIfExists('salary_structures');
        Schema::dropIfExists('salary_components');
    }
};
