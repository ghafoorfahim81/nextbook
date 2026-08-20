<?php

use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Enums\InterviewRecommendation;
use App\Enums\InterviewStatus;
use App\Enums\InterviewType;
use App\Enums\JobOpeningStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recruitment: vacancies, candidates, interviews.
 *
 * Candidates deliberately do NOT get a ledger row. They are not parties the
 * company transacts with — no money moves until they are hired, and at that
 * point EmployeeObserver creates the ledger as it does for anyone else.
 * Creating one at application time would fill the chart of accounts with
 * people who were never employed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('job_openings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code')->index();
            $table->string('title');

            $table->ulid('department_id')->nullable()->index();
            $table->ulid('designation_id')->nullable()->index();
            $table->enum('employment_type', EmploymentType::values())
                ->default(EmploymentType::Permanent->value);

            $table->smallInteger('vacancies')->default(1);

            $table->text('description')->nullable();
            $table->text('requirements')->nullable();
            $table->text('responsibilities')->nullable();

            $table->decimal('min_salary', 18, 4)->nullable();
            $table->decimal('max_salary', 18, 4)->nullable();
            $table->ulid('currency_id')->nullable()->index();

            $table->string('location')->nullable();
            $table->date('posted_date')->nullable();
            // Indexed: the "closing soon" list and the auto-close command both
            // scan on it.
            $table->date('closing_date')->nullable()->index();

            $table->enum('status', JobOpeningStatus::values())
                ->default(JobOpeningStatus::Draft->value)
                ->index();

            $table->ulid('hiring_manager_id')->nullable()->index();
            $table->text('remark')->nullable();

            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
        });

        Schema::create('job_applications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('job_opening_id')->index();
            $table->string('application_number')->index();

            // Candidate details live here rather than in `employees`: most
            // applicants never become employees, and an employees table full
            // of people who were rejected makes every headcount report wrong.
            $table->string('full_name');
            $table->string('father_name')->nullable();
            $table->enum('gender', Gender::values())->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('national_id')->nullable();
            $table->string('phone_number')->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->string('address')->nullable();
            $table->ulid('province_id')->nullable()->index();

            $table->string('current_employer')->nullable();
            $table->string('current_position')->nullable();
            $table->decimal('years_of_experience', 10, 2)->nullable();
            $table->string('highest_education')->nullable();
            $table->decimal('expected_salary', 18, 4)->nullable();
            $table->ulid('currency_id')->nullable()->index();
            $table->smallInteger('notice_period_days')->nullable();

            $table->enum('source', ApplicationSource::values())
                ->default(ApplicationSource::Other->value);
            $table->string('referred_by')->nullable();

            $table->enum('status', ApplicationStatus::values())
                ->default(ApplicationStatus::Applied->value)
                ->index();
            $table->decimal('score', 10, 2)->nullable();
            $table->text('rejection_reason')->nullable();

            $table->date('applied_date')->nullable();
            $table->date('offered_date')->nullable();
            $table->decimal('offered_salary', 18, 4)->nullable();

            // Set when the candidate is hired, closing the loop between the
            // pipeline and the person.
            $table->ulid('hired_employee_id')->nullable()->index();

            $table->text('remark')->nullable();

            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['branch_id', 'job_opening_id', 'status']);
        });

        Schema::create('interviews', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('job_application_id')->index();

            $table->smallInteger('round')->default(1);
            $table->enum('interview_type', InterviewType::values())
                ->default(InterviewType::InPerson->value);

            $table->timestamp('scheduled_at')->nullable()->index();
            $table->smallInteger('duration_minutes')->default(60);
            $table->string('location')->nullable();
            $table->string('meeting_link')->nullable();

            $table->enum('status', InterviewStatus::values())
                ->default(InterviewStatus::Scheduled->value)
                ->index();

            $table->decimal('score', 10, 2)->nullable();
            $table->enum('recommendation', InterviewRecommendation::values())->nullable();
            $table->text('feedback')->nullable();
            $table->text('remark')->nullable();

            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['branch_id', 'scheduled_at']);
        });

        Schema::create('interview_panelists', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('interview_id')->index();
            // An employee, so the panel can be drawn from the org chart; a
            // user_id as well, because the reminder has to reach an inbox and
            // not every panelist has a login.
            $table->ulid('employee_id')->nullable()->index();
            $table->ulid('user_id')->nullable()->index();

            $table->string('role')->nullable();
            $table->boolean('is_lead')->default(false);

            $table->decimal('score', 10, 2)->nullable();
            $table->enum('recommendation', InterviewRecommendation::values())->nullable();
            $table->text('feedback')->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::table('job_openings', function (Blueprint $table) {
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
            $table->foreign('designation_id')->references('id')->on('designations')->onDelete('set null');
            $table->foreign('currency_id')->references('id')->on('currencies')->onDelete('set null');
            $table->foreign('hiring_manager_id')->references('id')->on('employees')->onDelete('set null');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::table('job_applications', function (Blueprint $table) {
            $table->foreign('job_opening_id')->references('id')->on('job_openings')->onDelete('cascade');
            $table->foreign('province_id')->references('id')->on('provinces')->onDelete('set null');
            $table->foreign('currency_id')->references('id')->on('currencies')->onDelete('set null');
            $table->foreign('hired_employee_id')->references('id')->on('employees')->onDelete('set null');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::table('interviews', function (Blueprint $table) {
            $table->foreign('job_application_id')->references('id')->on('job_applications')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::table('interview_panelists', function (Blueprint $table) {
            $table->foreign('interview_id')->references('id')->on('interviews')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });

        // Partial unique indexes over LIVE rows only.
        //
        // Postgres treats NULLs as distinct, so the usual unique(..., deleted_at)
        // shape accepts unlimited duplicates among live rows — the deleted_at
        // column is NULL for every one of them. These three are load-bearing:
        // a duplicate application number breaks the candidate's reference, and
        // a duplicate panelist would let one person's feedback be counted twice.
        DB::statement(
            'CREATE UNIQUE INDEX job_openings_live_code_unique '.
            'ON job_openings (branch_id, code) WHERE deleted_at IS NULL'
        );

        DB::statement(
            'CREATE UNIQUE INDEX job_applications_live_number_unique '.
            'ON job_applications (branch_id, application_number) WHERE deleted_at IS NULL'
        );

        DB::statement(
            'CREATE UNIQUE INDEX interviews_live_round_unique '.
            'ON interviews (job_application_id, round) WHERE deleted_at IS NULL'
        );

        DB::statement(
            'CREATE UNIQUE INDEX interview_panelists_live_employee_unique '.
            'ON interview_panelists (interview_id, employee_id) '.
            'WHERE deleted_at IS NULL AND employee_id IS NOT NULL'
        );

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS interview_panelists_live_employee_unique');
        DB::statement('DROP INDEX IF EXISTS interviews_live_round_unique');
        DB::statement('DROP INDEX IF EXISTS job_applications_live_number_unique');
        DB::statement('DROP INDEX IF EXISTS job_openings_live_code_unique');

        Schema::dropIfExists('interview_panelists');
        Schema::dropIfExists('interviews');
        Schema::dropIfExists('job_applications');
        Schema::dropIfExists('job_openings');
    }
};
