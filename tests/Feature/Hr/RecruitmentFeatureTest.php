<?php

namespace Tests\Feature\Hr;

use App\Enums\ApplicationStatus;
use App\Enums\EmploymentStatus;
use App\Enums\InterviewRecommendation;
use App\Enums\InterviewStatus;
use App\Enums\InterviewType;
use App\Enums\JobOpeningStatus;
use App\Exceptions\Hr\RecruitmentException;
use App\Models\Hr\Employee;
use App\Models\Hr\Interview;
use App\Models\Hr\InterviewPanelist;
use App\Models\Hr\JobApplication;
use App\Models\Hr\JobOpening;
use App\Models\Ledger\Ledger;
use App\Services\Hr\RecruitmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Recruitment: vacancy to candidate to employee.
 *
 * The load-bearing boundary is that candidates are NOT employees. Everything
 * up to hire() must leave the employees table and the chart of accounts alone,
 * and hire() must go through the Employee model so the companion ledger is
 * created exactly as it would be for a manual hire.
 */
class RecruitmentFeatureTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ctx = $this->bootstrapErpContext();
    }

    private function service(): RecruitmentService
    {
        return app(RecruitmentService::class);
    }

    private function opening(array $overrides = []): JobOpening
    {
        return JobOpening::factory()->create(array_merge([
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
            'currency_id' => $this->ctx['currency']->id,
        ], $overrides));
    }

    private function application(JobOpening $opening, array $overrides = []): JobApplication
    {
        return JobApplication::factory()->create(array_merge([
            'job_opening_id' => $opening->id,
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
            'currency_id' => $this->ctx['currency']->id,
        ], $overrides));
    }

    /** A candidate carried all the way to an accepted offer. */
    private function offeredCandidate(array $openingOverrides = []): JobApplication
    {
        $opening = $this->opening(array_merge(
            ['status' => JobOpeningStatus::Published->value],
            $openingOverrides
        ));

        $application = $this->application($opening, [
            'full_name' => 'Ahmad Shah Karimi',
            'status' => ApplicationStatus::Shortlisted->value,
        ]);

        return $this->service()->transitionApplication($application, ApplicationStatus::Offered, [
            'offered_salary' => 42000,
            'offered_date' => '2026-08-01',
        ]);
    }

    // ==================================================
    // OPENINGS
    // ==================================================

    public function test_publishing_an_opening_stamps_the_posted_date(): void
    {
        $opening = $this->opening(['posted_date' => null]);

        $opening = $this->service()->transitionOpening($opening, JobOpeningStatus::Published);

        $this->assertSame(JobOpeningStatus::Published, $opening->statusEnum());
        $this->assertNotNull($opening->posted_date);
    }

    public function test_a_draft_opening_cannot_jump_straight_to_filled(): void
    {
        $this->expectException(RecruitmentException::class);

        $this->service()->transitionOpening($this->opening(), JobOpeningStatus::Filled);
    }

    /**
     * Closing the advert is not abandoning the pipeline: candidates already
     * being interviewed still need to reach an outcome.
     */
    public function test_an_overdue_opening_is_closed_not_cancelled(): void
    {
        $this->opening([
            'status' => JobOpeningStatus::Published->value,
            'closing_date' => '2026-07-01',
        ]);

        $closed = $this->service()->closeOverdueOpenings('2026-08-20');

        $this->assertSame(1, $closed);
        $this->assertSame(
            JobOpeningStatus::Closed->value,
            JobOpening::query()->first()->status->value
        );
    }

    public function test_an_opening_still_within_its_dates_is_left_alone(): void
    {
        $this->opening([
            'status' => JobOpeningStatus::Published->value,
            'closing_date' => '2026-12-01',
        ]);

        $this->assertSame(0, $this->service()->closeOverdueOpenings('2026-08-20'));
    }

    public function test_an_opening_with_applications_cannot_be_deleted(): void
    {
        $opening = $this->opening();
        $this->application($opening);

        $this->assertFalse($opening->canBeDeleted());
        $this->assertNotNull($opening->getDependencyMessage());
    }

    // ==================================================
    // PIPELINE
    // ==================================================

    public function test_a_candidate_moves_from_applied_to_shortlisted(): void
    {
        $application = $this->application($this->opening());

        $application = $this->service()
            ->transitionApplication($application, ApplicationStatus::Shortlisted);

        $this->assertSame(ApplicationStatus::Shortlisted, $application->statusEnum());
    }

    public function test_a_fresh_applicant_cannot_be_offered_a_job_without_shortlisting(): void
    {
        $application = $this->application($this->opening());

        $this->expectException(RecruitmentException::class);
        $this->service()->transitionApplication($application, ApplicationStatus::Offered);
    }

    public function test_a_rejected_candidate_cannot_be_revived(): void
    {
        $application = $this->service()->transitionApplication(
            $this->application($this->opening()),
            ApplicationStatus::Rejected,
            ['rejection_reason' => 'insufficient experience']
        );

        $this->assertSame('insufficient experience', $application->rejection_reason);

        $this->expectException(RecruitmentException::class);
        $this->service()->transitionApplication($application, ApplicationStatus::Shortlisted);
    }

    /**
     * A candidate who took another job is someone to approach again; a
     * rejection list that quietly includes them is worse than useless.
     */
    public function test_withdrawing_is_distinct_from_being_rejected(): void
    {
        $application = $this->service()->transitionApplication(
            $this->application($this->opening()),
            ApplicationStatus::Withdrawn
        );

        $this->assertSame(ApplicationStatus::Withdrawn, $application->statusEnum());
        $this->assertFalse($application->statusEnum()->isActive());
    }

    public function test_hiring_cannot_be_done_through_the_generic_transition(): void
    {
        $application = $this->offeredCandidate();

        // It has to create an Employee, so it cannot be a status change.
        $this->expectException(RecruitmentException::class);
        $this->service()->transitionApplication($application, ApplicationStatus::Hired);
    }

    // ==================================================
    // INTERVIEWS
    // ==================================================

    public function test_scheduling_an_interview_moves_the_candidate_to_interviewing(): void
    {
        $application = $this->application($this->opening(), [
            'status' => ApplicationStatus::Shortlisted->value,
        ]);

        $interview = $this->service()->scheduleInterview($application, [
            'interview_type' => InterviewType::InPerson->value,
            'scheduled_at' => '2026-08-25 10:00:00',
        ]);

        $this->assertSame(1, $interview->round);
        $this->assertSame(InterviewStatus::Scheduled, $interview->statusEnum());
        $this->assertSame(
            ApplicationStatus::Interviewing->value,
            $application->fresh()->status->value
        );
    }

    public function test_an_unshortlisted_candidate_cannot_be_interviewed(): void
    {
        $application = $this->application($this->opening());

        $this->expectException(RecruitmentException::class);
        $this->service()->scheduleInterview($application, [
            'scheduled_at' => '2026-08-25 10:00:00',
        ]);
    }

    public function test_rounds_number_themselves(): void
    {
        $application = $this->application($this->opening(), [
            'status' => ApplicationStatus::Shortlisted->value,
        ]);

        $first = $this->service()->scheduleInterview($application, [
            'scheduled_at' => '2026-08-25 10:00:00',
        ]);
        $second = $this->service()->scheduleInterview($application->fresh(), [
            'scheduled_at' => '2026-08-28 10:00:00',
        ]);

        $this->assertSame(1, $first->round);
        $this->assertSame(2, $second->round);
    }

    /**
     * Postgres treats NULLs as distinct, so unique(..., deleted_at) would not
     * stop a duplicate round among live rows. The migration uses a partial
     * index instead — this proves it bites.
     */
    public function test_two_interviews_cannot_claim_the_same_round(): void
    {
        $application = $this->application($this->opening(), [
            'status' => ApplicationStatus::Shortlisted->value,
        ]);

        $this->service()->scheduleInterview($application, [
            'scheduled_at' => '2026-08-25 10:00:00',
            'round' => 1,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->service()->scheduleInterview($application->fresh(), [
            'scheduled_at' => '2026-08-26 10:00:00',
            'round' => 1,
        ]);
    }

    public function test_panelists_are_attached_to_the_interview(): void
    {
        $panelist = Employee::factory()->create(['branch_id' => $this->ctx['branch']->id]);

        $application = $this->application($this->opening(), [
            'status' => ApplicationStatus::Shortlisted->value,
        ]);

        $interview = $this->service()->scheduleInterview(
            $application,
            ['scheduled_at' => '2026-08-25 10:00:00'],
            [['employee_id' => $panelist->id, 'is_lead' => true, 'role' => 'Hiring Manager']]
        );

        $this->assertCount(1, $interview->panelists);
        $this->assertTrue($interview->panelists->first()->is_lead);
    }

    public function test_feedback_is_recorded_per_panelist(): void
    {
        [$interview, $panelists] = $this->interviewWithPanel(2);

        $this->service()->submitFeedback($panelists[0], [
            'score' => 8,
            'recommendation' => InterviewRecommendation::Hire->value,
            'feedback' => 'strong technically',
        ]);

        $this->assertTrue($panelists[0]->fresh()->hasSubmitted());
        $this->assertFalse($panelists[1]->fresh()->hasSubmitted());
    }

    /**
     * One strong objection blocks, however positive the rest were — that is
     * exactly the signal an averaged score erases.
     */
    public function test_a_single_strong_objection_blocks_the_panel_verdict(): void
    {
        [$interview, $panelists] = $this->interviewWithPanel(3);

        $this->service()->submitFeedback($panelists[0], [
            'score' => 9, 'recommendation' => InterviewRecommendation::StrongHire->value,
        ]);
        $this->service()->submitFeedback($panelists[1], [
            'score' => 8, 'recommendation' => InterviewRecommendation::Hire->value,
        ]);
        $this->service()->submitFeedback($panelists[2], [
            'score' => 2, 'recommendation' => InterviewRecommendation::StrongNoHire->value,
        ]);

        $this->assertSame(
            InterviewRecommendation::StrongNoHire,
            $interview->fresh()->panelVerdict()
        );
    }

    public function test_a_positive_majority_carries_the_panel(): void
    {
        [$interview, $panelists] = $this->interviewWithPanel(3);

        $this->service()->submitFeedback($panelists[0], [
            'recommendation' => InterviewRecommendation::Hire->value,
        ]);
        $this->service()->submitFeedback($panelists[1], [
            'recommendation' => InterviewRecommendation::Hire->value,
        ]);
        $this->service()->submitFeedback($panelists[2], [
            'recommendation' => InterviewRecommendation::NoHire->value,
        ]);

        $this->assertSame(InterviewRecommendation::Hire, $interview->fresh()->panelVerdict());
    }

    public function test_completing_an_interview_averages_the_panel_score(): void
    {
        [$interview, $panelists] = $this->interviewWithPanel(2);

        $this->service()->submitFeedback($panelists[0], ['score' => 8]);
        $this->service()->submitFeedback($panelists[1], ['score' => 6]);

        $interview = $this->service()->completeInterview($interview);

        $this->assertEqualsWithDelta(7, (float) $interview->score, 0.01);
        $this->assertSame(InterviewStatus::Completed, $interview->statusEnum());
        // And the candidate's headline score keeps up, so a pipeline list can
        // sort without loading every round.
        $this->assertEqualsWithDelta(7, (float) $interview->application->fresh()->score, 0.01);
    }

    public function test_a_cancelled_interview_takes_no_more_feedback(): void
    {
        [$interview, $panelists] = $this->interviewWithPanel(1);

        $interview->forceFill(['status' => InterviewStatus::Cancelled->value])->save();

        $this->expectException(RecruitmentException::class);
        $this->service()->submitFeedback($panelists[0]->fresh(), ['score' => 5]);
    }

    /**
     * @return array{0: Interview, 1: array<int, InterviewPanelist>}
     */
    private function interviewWithPanel(int $count): array
    {
        $application = $this->application($this->opening(), [
            'status' => ApplicationStatus::Shortlisted->value,
        ]);

        $panel = [];
        for ($i = 0; $i < $count; $i++) {
            $panel[] = [
                'employee_id' => Employee::factory()->create([
                    'branch_id' => $this->ctx['branch']->id,
                ])->id,
            ];
        }

        $interview = $this->service()->scheduleInterview(
            $application,
            ['scheduled_at' => '2026-08-25 10:00:00'],
            $panel
        );

        return [$interview, $interview->panelists->all()];
    }

    // ==================================================
    // HIRING
    // ==================================================

    public function test_hiring_creates_an_employee_with_a_ledger(): void
    {
        $application = $this->offeredCandidate();

        $employee = $this->service()->hire($application, ['joining_date' => '2026-09-01']);

        $this->assertNotNull($employee->id);
        $this->assertSame('Ahmad Shah', $employee->first_name);
        $this->assertSame('Karimi', $employee->last_name);
        $this->assertEqualsWithDelta(42000, (float) $employee->basic_salary, 0.01);
        $this->assertSame(EmploymentStatus::Probation->value, $employee->employment_status->value);

        // The observer must have run, exactly as for a manually added employee.
        $ledger = Ledger::query()->find($employee->ledger_id);
        $this->assertNotNull($ledger);
        $this->assertSame('employee', $ledger->type->value);
    }

    public function test_hiring_closes_the_loop_back_to_the_application(): void
    {
        $application = $this->offeredCandidate();

        $employee = $this->service()->hire($application, ['joining_date' => '2026-09-01']);

        $application = $application->fresh();
        $this->assertSame(ApplicationStatus::Hired, $application->statusEnum());
        $this->assertSame($employee->id, $application->hired_employee_id);
    }

    public function test_the_last_vacancy_marks_the_opening_filled(): void
    {
        $application = $this->offeredCandidate(['vacancies' => 1]);

        $this->service()->hire($application, ['joining_date' => '2026-09-01']);

        $this->assertSame(
            JobOpeningStatus::Filled->value,
            $application->fresh()->opening->status->value
        );
    }

    /**
     * A three-post vacancy is not finished at the first offer.
     */
    public function test_an_opening_with_posts_left_stays_open(): void
    {
        $opening = $this->opening([
            'status' => JobOpeningStatus::Published->value,
            'vacancies' => 3,
        ]);

        $application = $this->service()->transitionApplication(
            $this->application($opening, ['status' => ApplicationStatus::Shortlisted->value]),
            ApplicationStatus::Offered,
            ['offered_salary' => 30000]
        );

        $this->service()->hire($application, ['joining_date' => '2026-09-01']);

        $opening = $opening->fresh();
        $this->assertSame(JobOpeningStatus::Published->value, $opening->status->value);
        $this->assertSame(2, $opening->remainingVacancies());
    }

    /**
     * Hiring past the approved headcount is a budget decision. Refusing makes
     * the overrun visible — raising the vacancy count is the fix.
     */
    public function test_hiring_past_the_headcount_is_refused(): void
    {
        $opening = $this->opening([
            'status' => JobOpeningStatus::Published->value,
            'vacancies' => 1,
        ]);

        foreach (['First Candidate', 'Second Candidate'] as $name) {
            $applications[] = $this->service()->transitionApplication(
                $this->application($opening, [
                    'full_name' => $name,
                    'status' => ApplicationStatus::Shortlisted->value,
                ]),
                ApplicationStatus::Offered,
                ['offered_salary' => 30000]
            );
        }

        $this->service()->hire($applications[0], ['joining_date' => '2026-09-01']);

        $this->expectException(RecruitmentException::class);
        $this->service()->hire($applications[1], ['joining_date' => '2026-09-01']);
    }

    public function test_an_unoffered_candidate_cannot_be_hired(): void
    {
        $opening = $this->opening(['status' => JobOpeningStatus::Published->value]);
        $application = $this->application($opening, [
            'status' => ApplicationStatus::Shortlisted->value,
        ]);

        $this->expectException(RecruitmentException::class);
        $this->service()->hire($application, ['joining_date' => '2026-09-01']);
    }

    public function test_a_candidate_cannot_be_hired_twice(): void
    {
        $application = $this->offeredCandidate(['vacancies' => 5]);

        $this->service()->hire($application, ['joining_date' => '2026-09-01']);

        $this->expectException(RecruitmentException::class);
        $this->service()->hire($application->fresh(), ['joining_date' => '2026-09-01']);
    }

    /**
     * Candidates are not employees. Nothing before hire() may leave a trace in
     * the employees table or the chart of accounts — otherwise every headcount
     * report counts people who were never employed.
     */
    public function test_the_pipeline_creates_no_employees_and_no_ledgers(): void
    {
        $employeesBefore = Employee::query()->count();
        $ledgersBefore = Ledger::query()->count();

        [$interview, $panelists] = $this->interviewWithPanel(1);
        $this->service()->submitFeedback($panelists[0], ['score' => 7]);
        $this->service()->completeInterview($interview);

        // The panel member is a real employee; the candidate is not.
        $this->assertSame($employeesBefore + 1, Employee::query()->count());
        $this->assertSame($ledgersBefore + 1, Ledger::query()->count());
    }

    /**
     * Afghan names frequently have no family name, so the split is a starting
     * point HR corrects — never a claim about the person's name.
     */
    public function test_a_single_word_name_survives_hiring(): void
    {
        $opening = $this->opening(['status' => JobOpeningStatus::Published->value]);
        $application = $this->service()->transitionApplication(
            $this->application($opening, [
                'full_name' => 'Rahimullah',
                'status' => ApplicationStatus::Shortlisted->value,
            ]),
            ApplicationStatus::Offered,
            ['offered_salary' => 30000]
        );

        $employee = $this->service()->hire($application, ['joining_date' => '2026-09-01']);

        $this->assertSame('Rahimullah', $employee->first_name);
        $this->assertSame('Rahimullah', trim($employee->full_name));
    }
}
