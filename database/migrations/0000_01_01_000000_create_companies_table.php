<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Enums\CalendarType;
use App\Enums\BusinessType;
use App\Enums\Locale;
use App\Enums\WorkingStyle;
use App\Enums\CostingMethod;

/**
 * Companies is the root of the organisational tree, so it is created before
 * every other table.
 *
 * It carries no foreign keys of its own here. `currency_id` points at
 * `currencies`, which is company-scoped and therefore created later, and the
 * audit columns point at `users`, which is branch-scoped and also later. Both
 * constraints are added once those tables exist — see the
 * `add_deferred_foreign_keys_to_companies` migration. Declaring them here
 * would need foreign key checks disabled, which only hides the cycle instead
 * of breaking it.
 *
 * `currency_id` is nullable because a company row exists before its chart of
 * accounts and currency set are provisioned; the home currency is filled in by
 * the provisioning step.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name_en')->index();
            $table->string('name_fa')->nullable()->index();
            $table->string('name_pa')->nullable()->index();
            $table->string('abbreviation')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('logo')->nullable();
            $table->enum('calendar_type', CalendarType::values())->nullable()->default(CalendarType::JALALI->value);
            $table->enum('working_style', WorkingStyle::values())->nullable()->default(WorkingStyle::NORMAL->value);
            $table->enum('business_type', BusinessType::values())->nullable()->default(BusinessType::PHARMACY->value);
            $table->enum('locale', Locale::values())->nullable()->default(Locale::EN->value);
            $table->ulid('currency_id')->nullable()->index();
            $table->enum('costing_method', CostingMethod::values())->nullable()->default(CostingMethod::FIFO->value);
            // The trade licence is issued to the legal entity, so branches of
            // one company all operate under it and printed documents carry the
            // company's number — the issuing branch's name and address appear
            // beside it, not a second licence.
            $table->string('license_number')->nullable();
            $table->date('license_issued_at')->nullable();
            $table->date('license_expires_at')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->text('invoice_description')->nullable();
            $table->ulid('created_by')->nullable()->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // One licence number cannot belong to two companies. A partial index
        // over live rows, because `unique([..., 'deleted_at'])` does not work
        // in PostgreSQL — NULL != NULL, so it never fires — and because the
        // number is optional until the company records it.
        DB::statement('CREATE UNIQUE INDEX companies_license_number_live_unique
            ON companies (license_number)
            WHERE deleted_at IS NULL AND license_number IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
