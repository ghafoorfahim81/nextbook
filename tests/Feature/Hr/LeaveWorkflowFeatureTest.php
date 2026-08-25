<?php

namespace Tests\Feature\Hr;

use App\Enums\AttendanceStatus;
use App\Enums\LeaveRequestStatus;
use App\Models\Hr\Attendance;
use App\Models\Hr\Employee;
use App\Models\Hr\Holiday;
use App\Models\Hr\LeaveAllocation;
use App\Models\Hr\LeaveRequest;
use App\Models\Hr\LeaveType;
use App\Models\Hr\Shift;
use App\Services\Hr\LeaveBalanceService;
use App\Services\Hr\LeaveRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

class LeaveWorkflowFeatureTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    private Employee $employee;

    private LeaveType $annual;

    private Shift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ctx = $this->bootstrapErpContext();

        $this->shift = Shift::factory()->create(['branch_id' => $this->ctx['branch']->id]);

        $this->employee = Employee::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'currency_id' => $this->ctx['currency']->id,
            'shift_id' => $this->shift->id,
            'joining_date' => '2020-01-01',
        ]);

        $this->annual = LeaveType::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'code' => 'ANNUAL',
            'min_notice_days' => null,
        ]);

        LeaveAllocation::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->annual->id,
            'entitled_days' => 20,
        ]);
    }

    private function service(): LeaveRequestService
    {
        return app(LeaveRequestService::class);
    }

    private function makeRequest(string $from, string $to, ?LeaveType $type = null, float $days = 1): LeaveRequest
    {
        $type = $type ?? $this->annual;

        return LeaveRequest::create([
            'number' => (string) LeaveRequest::nextNumber(),
            'employee_id' => $this->employee->id,
            'leave_type_id' => $type->id,
            'from_date' => $from,
            'to_date' => $to,
            'days' => $days,
            'status' => LeaveRequestStatus::Draft->value,
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
        ]);
    }

    public function test_the_state_machine_allows_the_happy_path(): void
    {
        $request = $this->makeRequest('2026-09-07', '2026-09-07');

        $this->service()->transition($request, LeaveRequestStatus::Pending);
        $this->assertSame(LeaveRequestStatus::Pending, $request->fresh()->statusEnum());

        $this->service()->transition($request->fresh(), LeaveRequestStatus::Approved);
        $this->assertSame(LeaveRequestStatus::Approved, $request->fresh()->statusEnum());
    }

    /**
     * The state machine has to be a real guard, not documentation — approving
     * something nobody submitted would bypass the whole review step.
     */
    public function test_a_draft_cannot_be_approved_directly(): void
    {
        $request = $this->makeRequest('2026-09-07', '2026-09-07');

        $this->expectException(ValidationException::class);
        $this->service()->transition($request, LeaveRequestStatus::Approved);
    }

    public function test_a_rejected_request_is_terminal(): void
    {
        $request = $this->makeRequest('2026-09-07', '2026-09-07');
        $this->service()->transition($request, LeaveRequestStatus::Pending);
        $this->service()->transition($request->fresh(), LeaveRequestStatus::Rejected, ['reason' => 'Too busy']);

        $this->expectException(ValidationException::class);
        $this->service()->transition($request->fresh(), LeaveRequestStatus::Approved);
    }

    public function test_approving_generates_on_leave_attendance(): void
    {
        // Mon 7 Sep to Wed 9 Sep 2026 — three ordinary working days.
        $request = $this->makeRequest('2026-09-07', '2026-09-09', null, 3);

        $this->service()->transition($request, LeaveRequestStatus::Pending);
        $this->service()->transition($request->fresh(), LeaveRequestStatus::Approved);

        $rows = Attendance::withoutGlobalScopes()
            ->where('leave_request_id', $request->id)
            ->get();

        $this->assertCount(3, $rows);
        $this->assertTrue($rows->every(fn ($r) => $r->status->value === AttendanceStatus::OnLeave->value));
    }

    /**
     * Leave that excludes weekends must not consume entitlement for the Friday
     * an employee was never expected to work.
     */
    public function test_approval_skips_the_weekly_rest_day(): void
    {
        // Thu 10 Sep through Sat 12 Sep 2026 — Friday 11th is the rest day.
        $this->assertSame('Friday', Carbon::parse('2026-09-11')->format('l'));

        $request = $this->makeRequest('2026-09-10', '2026-09-12', null, 2);

        $this->service()->transition($request, LeaveRequestStatus::Pending);
        $this->service()->transition($request->fresh(), LeaveRequestStatus::Approved);

        $dates = Attendance::withoutGlobalScopes()
            ->where('leave_request_id', $request->id)
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->all();

        $this->assertNotContains('2026-09-11', $dates);
        $this->assertCount(2, $dates);
    }

    public function test_approval_skips_a_public_holiday(): void
    {
        Holiday::factory()->on('2026-09-08')->create(['branch_id' => $this->ctx['branch']->id]);

        $request = $this->makeRequest('2026-09-07', '2026-09-09', null, 2);

        $this->service()->transition($request, LeaveRequestStatus::Pending);
        $this->service()->transition($request->fresh(), LeaveRequestStatus::Approved);

        $dates = Attendance::withoutGlobalScopes()
            ->where('leave_request_id', $request->id)
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->all();

        $this->assertNotContains('2026-09-08', $dates);
    }

    public function test_day_counting_excludes_weekends_and_holidays(): void
    {
        Holiday::factory()->on('2026-09-08')->create(['branch_id' => $this->ctx['branch']->id]);

        $days = $this->service()->countLeaveDays(
            $this->employee,
            $this->annual,
            Carbon::parse('2026-09-07'),
            Carbon::parse('2026-09-12'),
        );

        // Mon 7 (holiday-free), Tue 8 holiday, Wed 9, Thu 10, Fri 11 rest,
        // Sat 12 working -> 4 chargeable days.
        $this->assertSame(4.0, $days);
    }

    public function test_a_leave_type_that_includes_non_working_days_counts_them(): void
    {
        $maternity = LeaveType::factory()->includesNonWorkingDays()->create([
            'branch_id' => $this->ctx['branch']->id,
            'code' => 'MAT',
        ]);

        $days = $this->service()->countLeaveDays(
            $this->employee,
            $maternity,
            Carbon::parse('2026-09-07'),
            Carbon::parse('2026-09-13'),
        );

        $this->assertSame(7.0, $days);
    }

    public function test_approval_is_refused_when_the_balance_is_short(): void
    {
        $request = $this->makeRequest('2026-09-07', '2026-10-30', null, 40);

        $this->service()->transition($request, LeaveRequestStatus::Pending);

        $this->expectException(ValidationException::class);
        $this->service()->transition($request->fresh(), LeaveRequestStatus::Approved);
    }

    /**
     * Unpaid leave going negative is the point of it — it is how someone takes
     * time they have not earned, with pay docked instead.
     */
    public function test_unpaid_leave_is_allowed_beyond_the_balance(): void
    {
        $unpaid = LeaveType::factory()->unpaid()->create([
            'branch_id' => $this->ctx['branch']->id,
            'code' => 'UNPAID',
        ]);

        $request = $this->makeRequest('2026-09-07', '2026-10-30', $unpaid, 40);

        $this->service()->transition($request, LeaveRequestStatus::Pending);
        $this->service()->transition($request->fresh(), LeaveRequestStatus::Approved);

        $this->assertSame(LeaveRequestStatus::Approved, $request->fresh()->statusEnum());
    }

    public function test_the_balance_reflects_approved_leave_only(): void
    {
        $balances = app(LeaveBalanceService::class);

        $approved = $this->makeRequest('2026-09-07', '2026-09-09', null, 3);
        $this->service()->transition($approved, LeaveRequestStatus::Pending);
        $this->service()->transition($approved->fresh(), LeaveRequestStatus::Approved);

        $pending = $this->makeRequest('2026-09-21', '2026-09-22', null, 2);
        $this->service()->transition($pending, LeaveRequestStatus::Pending);

        $balance = $balances->forType($this->employee, $this->annual->id);

        $this->assertSame(20.0, $balance['entitled']);
        $this->assertSame(3.0, $balance['taken']);
        // Pending is shown but NOT deducted — nobody has agreed to it yet.
        $this->assertSame(2.0, $balance['pending']);
        $this->assertSame(17.0, $balance['available']);
    }

    /**
     * Past leave already happened. Cancelling today does not retroactively put
     * someone back at their desk last week.
     */
    public function test_cancelling_removes_future_attendance_but_keeps_the_past(): void
    {
        $from = Carbon::today()->subDays(2);
        $to = Carbon::today()->addDays(2);

        $request = $this->makeRequest($from->toDateString(), $to->toDateString(), null, 5);

        $this->service()->transition($request, LeaveRequestStatus::Pending);
        $this->service()->transition($request->fresh(), LeaveRequestStatus::Approved);

        $before = Attendance::withoutGlobalScopes()->where('leave_request_id', $request->id)->count();
        $this->assertGreaterThan(0, $before);

        $this->service()->transition($request->fresh(), LeaveRequestStatus::Cancelled);

        // whereNull('deleted_at') is required: withoutGlobalScopes() drops the
        // soft-delete scope too, so the cancelled future days would otherwise
        // still be returned here.
        $remaining = Attendance::withoutGlobalScopes()
            ->where('leave_request_id', $request->id)
            ->whereNull('deleted_at')
            ->get();

        $this->assertGreaterThan(0, $remaining->count(), 'Past leave days should survive a cancellation.');

        foreach ($remaining as $row) {
            $this->assertTrue(
                Carbon::parse($row->date)->lt(Carbon::today()),
                'Only past days should remain after cancelling.'
            );
        }
    }

    public function test_overlapping_requests_are_rejected_by_validation(): void
    {
        $existing = $this->makeRequest('2026-09-07', '2026-09-09', null, 3);
        $this->service()->transition($existing, LeaveRequestStatus::Pending);

        $this->post(route('leave-requests.store'), [
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->annual->id,
            'from_date' => '2026-09-08',
            'to_date' => '2026-09-10',
        ])->assertSessionHasErrors('from_date');
    }

    public function test_a_rejected_request_frees_the_dates_again(): void
    {
        $existing = $this->makeRequest('2026-09-07', '2026-09-09', null, 3);
        $this->service()->transition($existing, LeaveRequestStatus::Pending);
        $this->service()->transition($existing->fresh(), LeaveRequestStatus::Rejected, ['reason' => 'no']);

        $this->post(route('leave-requests.store'), [
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->annual->id,
            'from_date' => '2026-09-08',
            'to_date' => '2026-09-10',
        ])->assertSessionHasNoErrors();
    }

    public function test_a_half_day_must_be_a_single_date(): void
    {
        $this->post(route('leave-requests.store'), [
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->annual->id,
            'from_date' => '2026-09-07',
            'to_date' => '2026-09-09',
            'is_half_day' => true,
            'half_day_period' => 'first_half',
        ])->assertSessionHasErrors('is_half_day');
    }

    public function test_minimum_notice_is_enforced(): void
    {
        $this->annual->update(['min_notice_days' => 7]);

        $this->post(route('leave-requests.store'), [
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->annual->id,
            'from_date' => Carbon::today()->addDay()->toDateString(),
            'to_date' => Carbon::today()->addDay()->toDateString(),
        ])->assertSessionHasErrors('from_date');
    }

    public function test_a_gender_restricted_leave_type_is_refused(): void
    {
        $this->employee->update(['gender' => 'male']);

        $maternity = LeaveType::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'code' => 'MAT',
            'applicable_gender' => 'female',
        ]);

        $this->post(route('leave-requests.store'), [
            'employee_id' => $this->employee->id,
            'leave_type_id' => $maternity->id,
            'from_date' => '2026-09-07',
            'to_date' => '2026-09-08',
        ])->assertSessionHasErrors('leave_type_id');
    }

    public function test_the_approve_route_requires_the_approve_permission(): void
    {
        $request = $this->makeRequest('2026-09-07', '2026-09-07');
        $this->service()->transition($request, LeaveRequestStatus::Pending);

        $this->patch(route('leave-requests.approve', $request->id))
            ->assertRedirect();

        $this->assertSame(LeaveRequestStatus::Approved, $request->fresh()->statusEnum());
    }

    public function test_only_a_draft_can_be_deleted(): void
    {
        $request = $this->makeRequest('2026-09-07', '2026-09-07');
        $this->service()->transition($request, LeaveRequestStatus::Pending);

        $this->delete(route('leave-requests.destroy', $request->id))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('leave_requests', ['id' => $request->id, 'deleted_at' => null]);
    }
}
