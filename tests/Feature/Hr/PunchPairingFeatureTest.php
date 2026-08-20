<?php

namespace Tests\Feature\Hr;

use App\Enums\AttendanceStatus;
use App\Enums\PunchDirection;
use App\Models\Hr\AttendancePunch;
use App\Models\Hr\Employee;
use App\Models\Hr\Holiday;
use App\Models\Hr\LeaveType;
use App\Models\Hr\Shift;
use App\Services\Hr\LeaveRequestService;
use App\Services\Hr\PunchPairingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Turning raw punches into a day.
 *
 * This is where the module is most likely to be quietly wrong: a mis-paired
 * punch produces a plausible-looking worked-hours figure that flows straight
 * into pay, with nothing to signal it was a guess.
 */
class PunchPairingFeatureTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    private Employee $employee;

    private Shift $shift;

    /** A Monday, so the default Sat–Thu shift is working. */
    private const WORKDAY = '2026-08-17';

    /** The Friday of that week — the Afghan weekly rest day. */
    private const RESTDAY = '2026-08-21';

    protected function setUp(): void
    {
        parent::setUp();

        $this->ctx = $this->bootstrapErpContext();

        $this->shift = Shift::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'break_minutes' => 0,
            'grace_in_minutes' => 15,
            'full_day_hours' => 8,
            'half_day_hours' => 4,
        ]);

        $this->employee = Employee::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'currency_id' => $this->ctx['currency']->id,
            'shift_id' => $this->shift->id,
        ]);
    }

    private function punch(string $dateTime, PunchDirection $direction = PunchDirection::Unknown): void
    {
        $at = Carbon::parse($dateTime);

        AttendancePunch::withoutGlobalScopes()->create([
            'id' => (string) Str::ulid(),
            'employee_id' => $this->employee->id,
            'punched_at' => $at,
            'punch_direction' => $direction->value,
            'fingerprint' => AttendancePunch::makeFingerprint(null, $this->employee->id, $at->toIso8601String()),
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
        ]);
    }

    private function pair(string $date = self::WORKDAY)
    {
        return app(PunchPairingService::class)->pairForDate($this->employee->fresh(), Carbon::parse($date));
    }

    public function test_a_clean_in_and_out_produces_a_present_day(): void
    {
        $this->punch(self::WORKDAY.' 08:00:00', PunchDirection::In);
        $this->punch(self::WORKDAY.' 16:00:00', PunchDirection::Out);

        $day = $this->pair();

        $this->assertSame(AttendanceStatus::Present->value, $day->status->value);
        $this->assertEquals(8.0, (float) $day->worked_hours);
        $this->assertSame(0, (int) $day->late_minutes);
        $this->assertFalse((bool) $day->needs_review);
    }

    /**
     * Most cheap terminals report a bare timestamp, so direction has to be
     * inferred: first punch in, last punch out.
     */
    public function test_undirected_punches_infer_first_in_and_last_out(): void
    {
        $this->punch(self::WORKDAY.' 07:58:00');
        $this->punch(self::WORKDAY.' 16:05:00');

        $day = $this->pair();

        $this->assertSame('07:58', $day->check_in->format('H:i'));
        $this->assertSame('16:05', $day->check_out->format('H:i'));
        $this->assertSame(AttendanceStatus::Present->value, $day->status->value);
    }

    /**
     * Four undirected punches are arrive / leave for lunch / return / leave.
     * The interior pair is a break and must not count as worked time.
     */
    public function test_interior_punches_are_treated_as_a_break(): void
    {
        $this->punch(self::WORKDAY.' 08:00:00');
        $this->punch(self::WORKDAY.' 12:00:00');
        $this->punch(self::WORKDAY.' 13:00:00');
        $this->punch(self::WORKDAY.' 17:00:00');

        $day = $this->pair();

        $this->assertSame(60, (int) $day->break_minutes);
        $this->assertEquals(8.0, (float) $day->worked_hours);
    }

    public function test_arriving_after_the_grace_period_is_late(): void
    {
        $this->punch(self::WORKDAY.' 08:45:00', PunchDirection::In);
        $this->punch(self::WORKDAY.' 17:00:00', PunchDirection::Out);

        $day = $this->pair();

        // 08:00 start + 15 minutes grace = 08:15; arriving at 08:45 is 30 late.
        $this->assertSame(30, (int) $day->late_minutes);
        $this->assertSame(AttendanceStatus::Late->value, $day->status->value);
    }

    public function test_arriving_within_the_grace_period_is_not_late(): void
    {
        $this->punch(self::WORKDAY.' 08:10:00', PunchDirection::In);
        $this->punch(self::WORKDAY.' 16:10:00', PunchDirection::Out);

        $day = $this->pair();

        $this->assertSame(0, (int) $day->late_minutes);
        $this->assertSame(AttendanceStatus::Present->value, $day->status->value);
    }

    /**
     * The important one: a single punch is ambiguous, and the day is flagged
     * rather than silently recorded as zero hours worked.
     */
    public function test_a_lone_punch_is_flagged_for_review(): void
    {
        $this->punch(self::WORKDAY.' 08:00:00', PunchDirection::In);

        $day = $this->pair();

        $this->assertTrue((bool) $day->needs_review);
        $this->assertNotNull($day->check_in);
        $this->assertNull($day->check_out);
        $this->assertEquals(0.0, (float) $day->worked_hours);
    }

    public function test_working_beyond_the_shift_records_overtime(): void
    {
        $this->punch(self::WORKDAY.' 08:00:00', PunchDirection::In);
        $this->punch(self::WORKDAY.' 18:30:00', PunchDirection::Out);

        $day = $this->pair();

        $this->assertEquals(10.5, (float) $day->worked_hours);
        $this->assertEquals(2.5, (float) $day->overtime_hours);
    }

    public function test_working_less_than_half_a_day_is_absent(): void
    {
        $this->punch(self::WORKDAY.' 08:00:00', PunchDirection::In);
        $this->punch(self::WORKDAY.' 10:00:00', PunchDirection::Out);

        $day = $this->pair();

        $this->assertSame(AttendanceStatus::Absent->value, $day->status->value);
    }

    public function test_working_at_least_half_a_day_is_a_half_day(): void
    {
        $this->punch(self::WORKDAY.' 08:00:00', PunchDirection::In);
        $this->punch(self::WORKDAY.' 12:30:00', PunchDirection::Out);

        $day = $this->pair();

        $this->assertSame(AttendanceStatus::HalfDay->value, $day->status->value);
    }

    public function test_no_punches_at_all_is_absent(): void
    {
        $day = $this->pair();

        $this->assertSame(AttendanceStatus::Absent->value, $day->status->value);
        $this->assertEquals(0.0, (float) $day->worked_hours);
    }

    public function test_friday_is_a_weekend_for_the_default_afghan_shift(): void
    {
        $this->assertSame('Friday', Carbon::parse(self::RESTDAY)->format('l'));

        $day = $this->pair(self::RESTDAY);

        $this->assertSame(AttendanceStatus::Weekend->value, $day->status->value);
    }

    public function test_a_holiday_outranks_an_ordinary_working_day(): void
    {
        Holiday::factory()->on(self::WORKDAY)->create(['branch_id' => $this->ctx['branch']->id]);

        $day = $this->pair();

        $this->assertSame(AttendanceStatus::Holiday->value, $day->status->value);
    }

    /**
     * Approved leave beats everything, including a badge swipe. Someone who
     * dropped by the office on their day off was still on leave.
     */
    public function test_approved_leave_outranks_a_punch(): void
    {
        $type = LeaveType::factory()->create(['branch_id' => $this->ctx['branch']->id]);

        $request = $this->employee->leaveRequests()->create([
            'number' => '1',
            'leave_type_id' => $type->id,
            'from_date' => self::WORKDAY,
            'to_date' => self::WORKDAY,
            'days' => 1,
            'status' => \App\Enums\LeaveRequestStatus::Approved->value,
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
        ]);

        $this->punch(self::WORKDAY.' 08:00:00', PunchDirection::In);
        $this->punch(self::WORKDAY.' 16:00:00', PunchDirection::Out);

        $day = $this->pair();

        $this->assertSame(AttendanceStatus::OnLeave->value, $day->status->value);
        $this->assertNotNull($request->id);
    }

    /**
     * A night shift ending at 04:00 has to reach into the next calendar day for
     * its check-out, or every night worker looks like a lone-punch anomaly.
     */
    public function test_a_night_shift_pairs_across_midnight(): void
    {
        $night = Shift::factory()->night()->create([
            'branch_id' => $this->ctx['branch']->id,
            'break_minutes' => 0,
            'full_day_hours' => 8,
            'working_days' => [6, 7, 1, 2, 3, 4],
        ]);

        $this->employee->update(['shift_id' => $night->id]);

        $this->punch(self::WORKDAY.' 20:00:00', PunchDirection::In);
        $this->punch('2026-08-18 04:00:00', PunchDirection::Out);

        $day = $this->pair();

        $this->assertEquals(8.0, (float) $day->worked_hours);
        $this->assertFalse((bool) $day->needs_review);
    }

    public function test_pairing_is_idempotent(): void
    {
        $this->punch(self::WORKDAY.' 08:00:00', PunchDirection::In);
        $this->punch(self::WORKDAY.' 16:00:00', PunchDirection::Out);

        $first = $this->pair();
        $second = $this->pair();

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $this->employee->attendances()->count());
    }

    /**
     * A day a posted payroll already consumed must not be silently rewritten
     * under the payslip.
     */
    public function test_pairing_refuses_a_payroll_locked_day(): void
    {
        $this->punch(self::WORKDAY.' 08:00:00', PunchDirection::In);
        $this->punch(self::WORKDAY.' 16:00:00', PunchDirection::Out);

        $day = $this->pair();
        $day->forceFill(['payroll_id' => (string) Str::ulid()])->save();

        $this->assertNull($this->pair());
    }

    public function test_punches_are_linked_to_the_day_they_produced(): void
    {
        $this->punch(self::WORKDAY.' 08:00:00', PunchDirection::In);
        $this->punch(self::WORKDAY.' 16:00:00', PunchDirection::Out);

        $day = $this->pair();

        $this->assertSame(
            2,
            AttendancePunch::withoutGlobalScopes()->where('attendance_id', $day->id)->count()
        );
    }
}
