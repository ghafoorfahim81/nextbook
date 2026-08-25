<?php

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\PunchDirection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance is stored twice on purpose.
 *
 * `attendance_punches` is the raw, append-only record of what a device or a
 * person reported. `attendances` is the derived one-row-per-employee-per-day
 * result that payroll and reporting read. Keeping them apart means a pairing
 * rule can be corrected and the day recomputed without the original evidence
 * having been overwritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('attendances', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('employee_id')->index();
            $table->date('date')->index();
            $table->ulid('shift_id')->nullable()->index();

            $table->timestamp('check_in')->nullable();
            $table->timestamp('check_out')->nullable();

            $table->decimal('worked_hours', 10, 2)->default(0);
            $table->decimal('overtime_hours', 10, 2)->default(0);
            $table->integer('break_minutes')->default(0);
            $table->integer('late_minutes')->default(0);
            $table->integer('early_out_minutes')->default(0);

            $table->enum('status', AttendanceStatus::values())
                ->default(AttendanceStatus::Absent->value);
            $table->ulid('leave_request_id')->nullable()->index();
            $table->enum('source', AttendanceSource::values())
                ->default(AttendanceSource::Manual->value);

            // A single punch with no matching pair. Surfaced for a human rather
            // than guessed at, because guessing produces a wrong worked-hours
            // figure that then flows into pay.
            $table->boolean('needs_review')->default(false);

            // The lock. Once a posted payroll has consumed a day, editing it
            // would silently desync the payslip from the attendance it was
            // calculated from.
            $table->ulid('payroll_id')->nullable()->index();

            $table->text('remark')->nullable();

            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // The load-bearing constraint of the whole module.
            $table->unique(['branch_id', 'employee_id', 'date', 'deleted_at'], 'attendances_employee_date_unique');
            $table->index(['branch_id', 'date', 'status']);
            $table->index(['branch_id', 'employee_id', 'date']);
        });

        Schema::create('attendance_punches', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('attendance_device_id')->nullable()->index();

            // Nullable ON PURPOSE: a punch from an unmapped device ID must still
            // land so it can be mapped and re-paired later. Dropping it would
            // lose evidence that someone was at work.
            $table->ulid('employee_id')->nullable()->index();
            $table->string('device_user_id')->nullable()->index();

            $table->timestamp('punched_at')->index();
            $table->enum('punch_direction', PunchDirection::values())
                ->default(PunchDirection::Unknown->value);
            $table->enum('source', AttendanceSource::values())
                ->default(AttendanceSource::Device->value);

            // sha256 of device + device user + timestamp. The dedupe guarantee
            // lives in this unique index rather than in application logic, so
            // re-uploading the same export twice is genuinely a no-op.
            $table->string('fingerprint', 64);

            $table->ulid('attendance_id')->nullable()->index();
            $table->ulid('import_batch_id')->nullable()->index();
            $table->boolean('is_ignored')->default(false);

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->jsonb('raw_payload')->nullable();

            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->nullable()->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['branch_id', 'fingerprint', 'deleted_at'], 'attendance_punches_fingerprint_unique');
            $table->index(['branch_id', 'employee_id', 'punched_at']);
        });

        Schema::enableForeignKeyConstraints();

        Schema::table('attendances', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('shift_id')->references('id')->on('shifts')->onDelete('set null');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::table('attendance_punches', function (Blueprint $table) {
            $table->foreign('attendance_device_id')->references('id')->on('attendance_devices')->onDelete('set null');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('attendance_id')->references('id')->on('attendances')->onDelete('set null');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_punches');
        Schema::dropIfExists('attendances');
    }
};
