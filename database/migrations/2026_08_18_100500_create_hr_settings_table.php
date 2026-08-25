<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per branch, resolved with firstOrCreate.
 *
 * These knobs could have been scattered across `branches`, `shifts` and the
 * user preferences blob, but they are branch-level HR policy rather than
 * per-user taste, and keeping them together means a new one is a column here
 * instead of a migration against three tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('hr_settings', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Jalali month number (1 = Hamal) the leave year opens on. Afghan
            // leave entitlement runs with the solar year, not January.
            $table->smallInteger('leave_year_start_month')->default(1);
            $table->smallInteger('payroll_cutoff_day')->nullable();
            $table->decimal('overtime_multiplier', 10, 4)->default(1.25);
            $table->smallInteger('default_probation_months')->default(3);
            $table->smallInteger('default_notice_period_days')->default(30);

            // Self-service location controls. OFF by default: a wrong radius
            // locks out an entire workforce on day one, and indoor GPS accuracy
            // is poor enough that this needs to be a deliberate choice.
            $table->boolean('self_service_enabled')->default(false);
            $table->boolean('enforce_geofence')->default(false);
            $table->decimal('geofence_latitude', 10, 7)->nullable();
            $table->decimal('geofence_longitude', 10, 7)->nullable();
            $table->integer('geofence_radius_meters')->nullable();
            $table->jsonb('allowed_ip_ranges')->nullable();

            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['branch_id', 'deleted_at']);
        });

        Schema::enableForeignKeyConstraints();

        Schema::table('hr_settings', function (Blueprint $table) {
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_settings');
    }
};
