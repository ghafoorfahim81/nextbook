<?php

namespace App\Services\Hr;

use App\Enums\ApplicationStatus;
use App\Enums\EmploymentStatus;
use App\Enums\InterviewStatus;
use App\Enums\InterviewType;
use App\Enums\JobOpeningStatus;
use App\Exceptions\Hr\RecruitmentException;
use App\Models\Hr\Employee;
use App\Models\Hr\Interview;
use App\Models\Hr\InterviewPanelist;
use App\Models\Hr\JobApplication;
use App\Models\Hr\JobOpening;
use Illuminate\Support\Facades\DB;

/**
 * Vacancies, candidates and interviews.
 *
 * The only part of this with consequences outside recruitment is hire(), which
 * creates a real Employee — and therefore, through EmployeeObserver, a real
 * ledger. Everything before that point is a pipeline, deliberately kept out of
 * the employees table so headcount and payroll never see people who were never
 * employed.
 */
class RecruitmentService
{
    // ==================================================
    // OPENINGS
    // ==================================================

    public function transitionOpening(JobOpening $opening, JobOpeningStatus $target): JobOpening
    {
        if (! $opening->canTransitionTo($target)) {
            throw RecruitmentException::make(
                'This job opening cannot move to that state.',
                [
                    'job_opening_id' => $opening->id,
                    'from' => $opening->statusEnum()->value,
                    'to' => $target->value,
                ]
            );
        }

        $attributes = ['status' => $target->value];

        if ($target === JobOpeningStatus::Published && ! $opening->posted_date) {
            $attributes['posted_date'] = now()->toDateString();
        }

        $opening->forceFill($attributes)->save();

        return $opening->fresh();
    }

    /**
     * Close openings whose closing date has passed.
     *
     * Closed, not cancelled: the advert is over, but candidates already in the
     * pipeline still need to be interviewed and hired.
     */
    public function closeOverdueOpenings(string $asOf): int
    {
        $openings = JobOpening::query()->overdue($asOf)->get();

        foreach ($openings as $opening) {
            $opening->forceFill(['status' => JobOpeningStatus::Closed->value])->save();
        }

        return $openings->count();
    }

    // ==================================================
    // APPLICATIONS
    // ==================================================

    /**
     * Move a candidate along the pipeline.
     */
    public function transitionApplication(
        JobApplication $application,
        ApplicationStatus $target,
        array $attributes = []
    ): JobApplication {
        if ($target === ApplicationStatus::Hired) {
            throw RecruitmentException::make(
                'Use hire() to hire a candidate — it has to create the employee record too.',
                ['job_application_id' => $application->id]
            );
        }

        if (! $application->canTransitionTo($target)) {
            throw RecruitmentException::make(
                'This application cannot move to that state.',
                [
                    'job_application_id' => $application->id,
                    'from' => $application->statusEnum()->value,
                    'to' => $target->value,
                ]
            );
        }

        $changes = ['status' => $target->value];

        if ($target === ApplicationStatus::Rejected) {
            $changes['rejection_reason'] = $attributes['rejection_reason'] ?? null;
        }

        if ($target === ApplicationStatus::Offered) {
            $changes['offered_date'] = $attributes['offered_date'] ?? now()->toDateString();
            $changes['offered_salary'] = $attributes['offered_salary'] ?? null;
        }

        $application->forceFill($changes)->save();

        return $application->fresh();
    }

    // ==================================================
    // INTERVIEWS
    // ==================================================

    /**
     * Book an interview, and move the candidate to `interviewing`.
     *
     * The round number is derived rather than supplied, so two people
     * scheduling at once cannot both claim round 2 — and the partial unique
     * index catches it in the database if they somehow do.
     *
     * @param  array<int, array<string, mixed>>  $panelists
     */
    public function scheduleInterview(
        JobApplication $application,
        array $attributes,
        array $panelists = []
    ): Interview {
        if (! $application->statusEnum()->canBeInterviewed()) {
            throw RecruitmentException::make(
                'Shortlist this candidate before booking an interview.',
                [
                    'job_application_id' => $application->id,
                    'status' => $application->statusEnum()->value,
                ]
            );
        }

        return DB::transaction(function () use ($application, $attributes, $panelists) {
            $interview = Interview::create([
                'job_application_id' => $application->id,
                'round' => $attributes['round'] ?? $application->nextInterviewRound(),
                // Defaulted here, not left to the column default: passing an
                // explicit null overrides a database default rather than
                // falling back to it.
                'interview_type' => $attributes['interview_type'] ?? InterviewType::InPerson->value,
                'scheduled_at' => $attributes['scheduled_at'],
                'duration_minutes' => $attributes['duration_minutes'] ?? 60,
                'location' => $attributes['location'] ?? null,
                'meeting_link' => $attributes['meeting_link'] ?? null,
                'status' => InterviewStatus::Scheduled->value,
                'remark' => $attributes['remark'] ?? null,
            ]);

            $this->syncPanelists($interview, $panelists);

            if ($application->statusEnum() === ApplicationStatus::Shortlisted) {
                $application->forceFill([
                    'status' => ApplicationStatus::Interviewing->value,
                ])->save();
            }

            return $interview->fresh(['panelists']);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $panelists
     */
    public function syncPanelists(Interview $interview, array $panelists): void
    {
        foreach ($panelists as $panelist) {
            InterviewPanelist::create([
                'interview_id' => $interview->id,
                'employee_id' => $panelist['employee_id'] ?? null,
                'user_id' => $panelist['user_id'] ?? null,
                'role' => $panelist['role'] ?? null,
                'is_lead' => (bool) ($panelist['is_lead'] ?? false),
            ]);
        }
    }

    /**
     * Record one panelist's verdict.
     *
     * Kept per person rather than merged into the interview: the disagreement
     * between two interviewers is the most useful thing in the record.
     */
    public function submitFeedback(InterviewPanelist $panelist, array $attributes): InterviewPanelist
    {
        if (! $panelist->interview?->statusEnum()->acceptsFeedback()) {
            throw RecruitmentException::make(
                'This interview is no longer accepting feedback.',
                ['interview_panelist_id' => $panelist->id]
            );
        }

        $panelist->forceFill([
            'score' => $attributes['score'] ?? null,
            'recommendation' => $attributes['recommendation'] ?? null,
            'feedback' => $attributes['feedback'] ?? null,
            'submitted_at' => now(),
        ])->save();

        return $panelist->fresh();
    }

    /**
     * Close an interview out, rolling the panel's verdict onto it.
     */
    public function completeInterview(Interview $interview, array $attributes = []): Interview
    {
        $panelScores = $interview->panelists()->whereNotNull('score')->pluck('score');

        $interview->forceFill([
            'status' => InterviewStatus::Completed->value,
            'score' => $attributes['score']
                ?? ($panelScores->isEmpty()
                    ? $interview->score
                    : round($panelScores->map(fn ($score) => (float) $score)->avg(), 2)),
            'recommendation' => $attributes['recommendation']
                ?? $interview->panelVerdict()?->value,
            'feedback' => $attributes['feedback'] ?? $interview->feedback,
        ])->save();

        $interview = $interview->fresh();

        // Keep the candidate's headline score in step with their rounds, so a
        // pipeline list can be sorted without loading every interview.
        $application = $interview->application;

        if ($application) {
            $application->forceFill(['score' => $application->averageScore()])->save();
        }

        return $interview;
    }

    // ==================================================
    // HIRING
    // ==================================================

    /**
     * Turn a candidate into an employee.
     *
     * Creates the Employee through the model rather than an insert, so
     * EmployeeObserver fires and the companion ledger is created exactly as it
     * would be for a manually added employee. The candidate's own details are
     * copied across as a starting point; HR completes the rest on the employee
     * form.
     */
    public function hire(JobApplication $application, array $attributes): Employee
    {
        if (! $application->canTransitionTo(ApplicationStatus::Hired)) {
            throw RecruitmentException::make(
                'Make this candidate an offer before hiring them.',
                [
                    'job_application_id' => $application->id,
                    'status' => $application->statusEnum()->value,
                ]
            );
        }

        if ($application->hired_employee_id) {
            throw RecruitmentException::make(
                'This candidate has already been hired.',
                ['job_application_id' => $application->id]
            );
        }

        $opening = $application->opening;

        if ($opening && $opening->remainingVacancies() < 1) {
            // Refused rather than warned: hiring past the approved headcount is
            // a budget decision, and the fix is to raise the vacancy count on
            // the opening so the overrun is visible.
            throw RecruitmentException::make(
                'This opening has no vacancies left.',
                ['job_opening_id' => $opening->id, 'vacancies' => $opening->vacancies]
            );
        }

        return DB::transaction(function () use ($application, $attributes, $opening) {
            $names = $this->splitName($application->full_name);

            $employee = Employee::create(array_merge([
                'code' => Employee::nextCode(),
                'first_name' => $names['first'],
                'last_name' => $names['last'],
                'father_name' => $application->father_name,
                'gender' => $application->gender?->value,
                'date_of_birth' => $application->date_of_birth?->toDateString(),
                'national_id' => $application->national_id,
                'phone_number' => $application->phone_number,
                'email' => $application->email,
                'present_address' => $application->address,
                'province_id' => $application->province_id,
                'department_id' => $opening?->department_id,
                'designation_id' => $opening?->designation_id,
                'employment_type' => $opening?->employment_type?->value,
                'employment_status' => EmploymentStatus::Probation->value,
                'basic_salary' => $application->offered_salary,
                'currency_id' => $application->currency_id ?? $opening?->currency_id,
                'is_active' => true,
            ], $attributes));

            $application->forceFill([
                'status' => ApplicationStatus::Hired->value,
                'hired_employee_id' => $employee->id,
            ])->save();

            // The last vacancy closes the opening. Earlier hires leave it open,
            // because a three-post vacancy is not finished at the first offer.
            if ($opening && $opening->fresh()->remainingVacancies() < 1) {
                $opening->forceFill(['status' => JobOpeningStatus::Filled->value])->save();
            }

            return $employee->fresh();
        });
    }

    /**
     * Best-effort split of a single name field into first and last.
     *
     * Afghan names frequently have no family name at all, so the last word is
     * a guess, not a fact. It is a starting point HR can correct on the
     * employee form — which is why the candidate's full_name is stored whole
     * and this is only ever applied at the moment of hiring.
     *
     * @return array{first: string, last: string}
     */
    private function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];

        if (count($parts) < 2) {
            return ['first' => $parts[0] ?? $fullName, 'last' => ''];
        }

        $last = array_pop($parts);

        return ['first' => implode(' ', $parts), 'last' => $last];
    }
}
