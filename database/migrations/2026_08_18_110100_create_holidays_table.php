<?php

use App\Enums\HolidayType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('holidays', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->date('date')->index();
            // Eid runs several days. Storing a range rather than one row per day
            // keeps the calendar readable and the entry to a single record.
            $table->date('end_date')->nullable();

            $table->enum('holiday_type', HolidayType::values())
                ->default(HolidayType::Public->value);
            $table->boolean('is_recurring')->default(false);
            $table->boolean('is_paid')->default(true);
            $table->text('remark')->nullable();

            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['branch_id', 'date', 'name', 'deleted_at']);
            $table->index(['branch_id', 'date', 'end_date']);
        });

        Schema::enableForeignKeyConstraints();

        Schema::table('holidays', function (Blueprint $table) {
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
