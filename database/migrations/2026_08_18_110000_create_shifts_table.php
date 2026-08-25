<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('shifts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('code')->index();

            $table->time('start_time');
            $table->time('end_time');
            // A night shift ending at 02:00 is not a shift that ran backwards
            // for 22 hours; the pairing window shifts a day when this is set.
            $table->boolean('crosses_midnight')->default(false);

            $table->smallInteger('break_minutes')->default(0);
            $table->smallInteger('grace_in_minutes')->default(0);
            $table->smallInteger('grace_out_minutes')->default(0);

            $table->decimal('full_day_hours', 10, 2)->default(8);
            $table->decimal('half_day_hours', 10, 2)->nullable();

            // ISO weekday numbers (1 = Monday … 7 = Sunday). jsonb rather than a
            // child table: a fixed seven-element set read on every attendance
            // computation and never queried by day on its own, so a child table
            // would cost a join per employee per day for nothing.
            $table->jsonb('working_days');

            $table->boolean('is_default')->default(false);
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

        Schema::enableForeignKeyConstraints();

        Schema::table('shifts', function (Blueprint $table) {
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });

        // The employee's current shift. A denormalised pointer so the roster
        // grid does not resolve effective-dated assignments row by row.
        Schema::table('employees', function (Blueprint $table) {
            $table->ulid('shift_id')->nullable()->index()->after('designation_id');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->foreign('shift_id')->references('id')->on('shifts')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['shift_id']);
            $table->dropColumn('shift_id');
        });

        Schema::dropIfExists('shifts');
    }
};
