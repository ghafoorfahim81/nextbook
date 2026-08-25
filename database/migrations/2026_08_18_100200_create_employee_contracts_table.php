<?php

use App\Enums\ContractStatus;
use App\Enums\ContractType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('employee_contracts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('employee_id')->index();
            $table->string('contract_number')->index();
            $table->enum('contract_type', ContractType::values())
                ->default(ContractType::FixedTerm->value);

            $table->date('start_date')->index();
            // Indexed because the renewal reminder scans this column daily.
            $table->date('end_date')->nullable()->index();

            $table->boolean('is_current')->default(true);

            $table->decimal('basic_salary', 18, 4)->default(0);
            $table->ulid('currency_id')->nullable()->index();

            $table->smallInteger('probation_months')->nullable();
            $table->smallInteger('notice_period_days')->nullable();
            $table->decimal('working_hours_per_day', 10, 2)->default(8);
            // Six, not five: the Afghan private-sector week runs Saturday to
            // Thursday with Friday as the rest day.
            $table->smallInteger('working_days_per_week')->default(6);
            $table->decimal('annual_leave_entitlement', 10, 2)->nullable();

            $table->enum('status', ContractStatus::values())
                ->default(ContractStatus::Draft->value)
                ->index();

            $table->ulid('renewed_from_id')->nullable()->index();
            $table->date('terminated_on')->nullable();
            $table->text('termination_reason')->nullable();

            $table->smallInteger('reminder_days_before')->default(30);
            $table->timestamp('last_reminded_at')->nullable();
            $table->text('remark')->nullable();

            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['branch_id', 'contract_number', 'deleted_at']);
            $table->index(['branch_id', 'end_date', 'status']);
        });

        Schema::enableForeignKeyConstraints();

        Schema::table('employee_contracts', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('currency_id')->references('id')->on('currencies')->onDelete('set null');
            $table->foreign('renewed_from_id')->references('id')->on('employee_contracts')->onDelete('set null');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_contracts');
    }
};
