<?php

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\PaymentMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('employees', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code')->index();

            // The financial half of an employee. Created and kept in step by
            // EmployeeObserver so salary payable, advances and statements reuse
            // the machinery customers and suppliers already have.
            $table->ulid('ledger_id')->nullable()->index();

            // Optional: only employees who actually log in get a user account.
            $table->ulid('user_id')->nullable()->index();

            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('father_name')->nullable();
            $table->string('grand_father_name')->nullable();

            // Denormalised from the name parts in a saving hook. It is the value
            // mirrored onto ledgers.name, the column search hits, and the label
            // every picker renders — deriving it in three places is how the
            // ledger name drifts away from the employee's.
            $table->string('full_name')->index();

            $table->enum('gender', Gender::values())->nullable();
            $table->enum('marital_status', MaritalStatus::values())->nullable();
            $table->date('date_of_birth')->nullable();

            $table->string('national_id')->nullable()->index();
            $table->string('passport_number')->nullable();
            $table->string('tin')->nullable();

            $table->ulid('country_id')->nullable()->index();
            $table->ulid('province_id')->nullable()->index();
            $table->string('blood_group', 5)->nullable();

            // Contact details stay HERE and are never copied to the ledger:
            // `ledgers` is unique on phone_no and email per branch, so an
            // employee sharing a phone with a customer would collide.
            $table->string('phone_number')->nullable();
            $table->string('alternate_phone')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->string('email')->nullable();

            $table->text('present_address')->nullable();
            $table->text('permanent_address')->nullable();

            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('emergency_contact_relation')->nullable();

            $table->string('photo')->nullable();

            $table->ulid('department_id')->nullable()->index();
            $table->ulid('designation_id')->nullable()->index();
            $table->ulid('reports_to_id')->nullable()->index();

            $table->enum('employment_type', EmploymentType::values())
                ->default(EmploymentType::Permanent->value)
                ->index();
            $table->enum('employment_status', EmploymentStatus::values())
                ->default(EmploymentStatus::Probation->value)
                ->index();

            $table->date('joining_date')->index();
            $table->date('probation_end_date')->nullable();
            $table->date('confirmation_date')->nullable();
            $table->date('separation_date')->nullable();
            $table->text('separation_reason')->nullable();

            // Salary currency: expat packages are commonly denominated in USD
            // while local staff are paid in AFN, within the same payroll run.
            $table->ulid('currency_id')->nullable()->index();

            // Denormalised from the current salary_structures row (Phase 3) so
            // the employee list can show pay without an N+1 into effective-dated
            // structures. The structure remains authoritative.
            $table->decimal('basic_salary', 18, 4)->default(0);

            $table->enum('payment_method', PaymentMode::values())->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_account_title')->nullable();
            $table->string('iban')->nullable();

            $table->boolean('is_tax_exempt')->default(false);
            $table->boolean('self_service_enabled')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->text('remark')->nullable();

            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['branch_id', 'code', 'deleted_at']);
            $table->unique(['branch_id', 'national_id', 'deleted_at']);
            // One employee record per login. Without this, two employees could
            // point at the same user and self-service check-in would be
            // ambiguous.
            $table->unique(['branch_id', 'user_id', 'deleted_at']);

            $table->index(['branch_id', 'employment_status']);
            $table->index(['branch_id', 'department_id']);
            $table->index(['branch_id', 'joining_date']);
        });

        Schema::enableForeignKeyConstraints();

        Schema::table('employees', function (Blueprint $table) {
            $table->foreign('ledger_id')->references('id')->on('ledgers')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('country_id')->references('id')->on('countries')->onDelete('set null');
            $table->foreign('province_id')->references('id')->on('provinces')->onDelete('set null');
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
            $table->foreign('designation_id')->references('id')->on('designations')->onDelete('set null');
            $table->foreign('reports_to_id')->references('id')->on('employees')->onDelete('set null');
            $table->foreign('currency_id')->references('id')->on('currencies')->onDelete('set null');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
