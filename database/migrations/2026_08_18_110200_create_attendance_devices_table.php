<?php

use App\Enums\AttendanceDeviceType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('attendance_devices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('code')->index();
            $table->enum('device_type', AttendanceDeviceType::values())
                ->default(AttendanceDeviceType::Csv->value);
            $table->string('serial_number')->nullable();
            $table->string('location')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sync_at')->nullable();
            $table->text('remark')->nullable();

            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['branch_id', 'code', 'deleted_at']);
        });

        // Maps the identifier a terminal reports (usually a small integer set
        // by whoever enrolled the fingerprint) to an actual employee.
        Schema::create('attendance_device_users', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('attendance_device_id')->index();
            $table->ulid('employee_id')->index();
            $table->string('device_user_id')->index();

            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['branch_id', 'attendance_device_id', 'device_user_id', 'deleted_at'], 'adu_device_user_unique');
        });

        Schema::enableForeignKeyConstraints();

        Schema::table('attendance_devices', function (Blueprint $table) {
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::table('attendance_device_users', function (Blueprint $table) {
            $table->foreign('attendance_device_id')->references('id')->on('attendance_devices')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_device_users');
        Schema::dropIfExists('attendance_devices');
    }
};
