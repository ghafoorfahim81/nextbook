<?php

use App\Enums\Gender;
use App\Enums\HalfDayPeriod;
use App\Enums\LeaveAccrualMethod;
use App\Enums\LeaveAllocationSource;
use App\Enums\LeaveRequestStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leave types, per-period allocations, and requests.
 *
 * There is deliberately NO `leave_balances` table. A balance is derived from
 * the allocation plus approved requests; a stored copy desyncs the moment a
 * request is cancelled, a payroll is reversed, or an admin edits an adjustment.
 * Allocations are one row per employee/type/period, so the derivation is a
 * single grouped query even for a whole list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('leave_types', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('code')->index();
            $table->string('colour', 20)->nullable();

            $table->boolean('is_paid')->default(true);
            $table->enum('accrual_method', LeaveAccrualMethod::values())
                ->default(LeaveAccrualMethod::AnnualGrant->value);
            $table->decimal('days_per_year', 10, 2)->nullable();
            $table->decimal('accrual_rate_per_month', 10, 2)->nullable();

            $table->decimal('max_carry_forward_days', 10, 2)->nullable();
            $table->smallInteger('carry_forward_expiry_months')->nullable();

            $table->smallInteger('max_consecutive_days')->nullable();
            $table->smallInteger('min_notice_days')->nullable();
            $table->smallInteger('min_service_months')->nullable();
            // Maternity leave applies to one gender; null means everyone.
            $table->enum('applicable_gender', Gender::values())->nullable();

            $table->boolean('requires_attachment')->default(false);
            $table->boolean('requires_approval')->default(true);
            $table->boolean('deduct_from_salary')->default(false);
            $table->boolean('is_encashable')->default(false);
            $table->boolean('pro_rata_on_join')->default(true);
            $table->boolean('excludes_holidays')->default(true);
            $table->boolean('excludes_weekends')->default(true);

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

        Schema::create('leave_allocations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('employee_id')->index();
            $table->ulid('leave_type_id')->index();

            $table->date('period_start');
            $table->date('period_end');

            $table->decimal('entitled_days', 10, 2)->default(0);
            $table->decimal('carried_forward_days', 10, 2)->default(0);
            $table->decimal('adjustment_days', 10, 2)->default(0);
            $table->decimal('encashed_days', 10, 2)->default(0);
            // Carry-forward that lapsed unused. Recorded rather than discarded
            // so the closing and opening periods reconcile to zero.
            $table->decimal('expired_days', 10, 2)->default(0);

            $table->enum('source', LeaveAllocationSource::values())
                ->default(LeaveAllocationSource::AutoAccrual->value);
            $table->text('remark')->nullable();

            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(
                ['branch_id', 'employee_id', 'leave_type_id', 'period_start', 'deleted_at'],
                'leave_allocations_period_unique'
            );
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('number')->index();
            $table->ulid('employee_id')->index();
            $table->ulid('leave_type_id')->index();
            $table->ulid('leave_allocation_id')->nullable()->index();

            $table->date('from_date');
            $table->date('to_date');
            $table->boolean('is_half_day')->default(false);
            $table->enum('half_day_period', HalfDayPeriod::values())->nullable();
            $table->decimal('days', 10, 2)->default(0);

            $table->text('reason')->nullable();
            $table->string('contact_during_leave')->nullable();
            $table->ulid('handover_to_id')->nullable()->index();

            $table->enum('status', LeaveRequestStatus::values())
                ->default(LeaveRequestStatus::Draft->value)
                ->index();

            $table->timestamp('applied_at')->nullable();
            $table->ulid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->ulid('rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->ulid('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['branch_id', 'number', 'deleted_at']);
            $table->index(['branch_id', 'employee_id', 'from_date', 'to_date']);
            $table->index(['branch_id', 'status', 'from_date']);
        });

        Schema::enableForeignKeyConstraints();

        Schema::table('leave_types', function (Blueprint $table) {
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::table('leave_allocations', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('leave_type_id')->references('id')->on('leave_types')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('leave_type_id')->references('id')->on('leave_types');
            $table->foreign('leave_allocation_id')->references('id')->on('leave_allocations')->onDelete('set null');
            $table->foreign('handover_to_id')->references('id')->on('employees')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('rejected_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('cancelled_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });

        // Closes the loop from Phase 2's attendance table: an attendance row
        // generated by an approved leave points back at the request that made it.
        Schema::table('attendances', function (Blueprint $table) {
            $table->foreign('leave_request_id')->references('id')->on('leave_requests')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropForeign(['leave_request_id']);
        });

        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_allocations');
        Schema::dropIfExists('leave_types');
    }
};
