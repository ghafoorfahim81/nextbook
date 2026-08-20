<?php

namespace Tests\Feature\Hr;

use App\Enums\AttendanceStatus;
use App\Models\Hr\Attendance;
use App\Models\Hr\Employee;
use App\Models\Hr\HrSetting;
use App\Models\Hr\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

class AttendanceRosterFeatureTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    private Employee $employee;

    private Shift $shift;

    /** A Monday. */
    private const WORKDAY = '2026-08-17';

    protected function setUp(): void
    {
        parent::setUp();

        $this->ctx = $this->bootstrapErpContext();

        $this->shift = Shift::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'break_minutes' => 0,
        ]);

        $this->employee = Employee::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'currency_id' => $this->ctx['currency']->id,
            'shift_id' => $this->shift->id,
        ]);
    }

    private function rosterPayload(array $overrides = []): array
    {
        return array_merge([
            'date' => self::WORKDAY,
            'shift_id' => $this->shift->id,
            'rows' => [[
                'employee_id' => $this->employee->id,
                'status' => AttendanceStatus::Present->value,
                'check_in' => '08:00',
                'check_out' => '16:00',
            ]],
        ], $overrides);
    }

    public function test_the_roster_screen_lists_employees_for_a_date(): void
    {
        $response = $this->get(route('attendances.roster', ['date' => self::WORKDAY]));

        $response->assertOk();

        $rows = $response->viewData('page')['props']['roster']['rows'];
        $this->assertCount(1, $rows);
        $this->assertSame($this->employee->id, $rows[0]['employee_id']);
    }

    public function test_it_saves_a_roster_row(): void
    {
        $this->post(route('attendances.roster.store'), $this->rosterPayload())
            ->assertRedirect();

        $day = Attendance::withoutGlobalScopes()->first();

        $this->assertNotNull($day);
        $this->assertSame(AttendanceStatus::Present->value, $day->status->value);
        $this->assertEquals(8.0, (float) $day->worked_hours);
    }

    public function test_saving_twice_updates_rather_than_duplicating(): void
    {
        $this->post(route('attendances.roster.store'), $this->rosterPayload());
        $this->post(route('attendances.roster.store'), $this->rosterPayload([
            'rows' => [[
                'employee_id' => $this->employee->id,
                'status' => AttendanceStatus::HalfDay->value,
                'check_in' => '08:00',
                'check_out' => '12:00',
            ]],
        ]));

        $this->assertSame(1, Attendance::withoutGlobalScopes()->count());
        $this->assertSame(
            AttendanceStatus::HalfDay->value,
            Attendance::withoutGlobalScopes()->first()->status->value
        );
    }

    /**
     * The unique index is what stops the same employee having two rows for one
     * day, which would make every attendance total ambiguous.
     */
    public function test_one_employee_cannot_have_two_rows_for_a_date(): void
    {
        $this->post(route('attendances.roster.store'), $this->rosterPayload());

        $this->expectException(\Illuminate\Database\QueryException::class);

        Attendance::withoutGlobalScopes()->create([
            'id' => (string) Str::ulid(),
            'employee_id' => $this->employee->id,
            'date' => self::WORKDAY,
            'status' => AttendanceStatus::Absent->value,
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
        ]);
    }

    public function test_a_present_row_requires_a_check_in_time(): void
    {
        $this->post(route('attendances.roster.store'), $this->rosterPayload([
            'rows' => [[
                'employee_id' => $this->employee->id,
                'status' => AttendanceStatus::Present->value,
            ]],
        ]))->assertSessionHasErrors('rows.0.check_in');
    }

    public function test_an_absent_row_cannot_carry_times(): void
    {
        $this->post(route('attendances.roster.store'), $this->rosterPayload([
            'rows' => [[
                'employee_id' => $this->employee->id,
                'status' => AttendanceStatus::Absent->value,
                'check_in' => '08:00',
            ]],
        ]))->assertSessionHasErrors('rows.0.status');
    }

    /**
     * Refusing loudly rather than skipping: the user needs to know their edit
     * did not take, not discover it later in a payslip.
     */
    public function test_a_payroll_locked_day_is_refused(): void
    {
        $this->post(route('attendances.roster.store'), $this->rosterPayload());

        Attendance::withoutGlobalScopes()->first()
            ->forceFill(['payroll_id' => (string) Str::ulid()])->save();

        $this->post(route('attendances.roster.store'), $this->rosterPayload([
            'rows' => [[
                'employee_id' => $this->employee->id,
                'status' => AttendanceStatus::Absent->value,
            ]],
        ]))->assertSessionHasErrors('rows.0.status');
    }

    public function test_deleting_a_locked_day_is_refused(): void
    {
        $this->post(route('attendances.roster.store'), $this->rosterPayload());

        $day = Attendance::withoutGlobalScopes()->first();
        $day->forceFill(['payroll_id' => (string) Str::ulid()])->save();

        $this->delete(route('attendances.destroy', $day->id))->assertSessionHas('error');

        $this->assertDatabaseHas('attendances', ['id' => $day->id, 'deleted_at' => null]);
    }
}
