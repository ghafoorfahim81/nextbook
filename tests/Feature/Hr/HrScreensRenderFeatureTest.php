<?php

namespace Tests\Feature\Hr;

use App\Models\Administration\Country;
use App\Models\Administration\Province;
use App\Models\Hr\AttendanceDevice;
use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeContract;
use App\Models\Hr\EmployeeDocument;
use App\Models\Hr\Holiday;
use App\Models\Hr\Interview;
use App\Models\Hr\InterviewPanelist;
use App\Models\Hr\JobApplication;
use App\Models\Hr\JobOpening;
use App\Models\Hr\LeaveAllocation;
use App\Models\Hr\LeaveRequest;
use App\Models\Hr\LeaveType;
use App\Models\Hr\Shift;
use App\Models\Hr\TaxBracket;
use App\Models\Hr\TaxBracketSet;
use App\Enums\InterviewStatus;
use App\Enums\InterviewType;
use App\Enums\LeaveRequestStatus;
use App\Enums\TaxPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Renders every HR screen with real data behind it.
 *
 * Unit-testing services proves the domain logic; this proves the wiring — that
 * each controller ships the props its page actually reads, and that a page
 * exists at the path the controller names. Those two break silently: the page
 * renders blank or 500s in the browser while every service test still passes.
 */
class HrScreensRenderFeatureTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    private Employee $employee;

    private Province $province;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ctx = $this->bootstrapErpContext();

        $country = Country::create(['code' => 'AF', 'name_en' => 'Afghanistan', 'name_fa' => 'افغانستان']);
        $this->province = Province::create([
            'country_id' => $country->id,
            'name_en' => 'Kabul',
            'name_fa' => 'کابل',
        ]);

        $shift = Shift::factory()->create(['branch_id' => $this->ctx['branch']->id]);

        $this->employee = Employee::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'currency_id' => $this->ctx['currency']->id,
            'shift_id' => $shift->id,
            'user_id' => $this->ctx['user']->id,
            'self_service_enabled' => true,
        ]);

        EmployeeContract::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'employee_id' => $this->employee->id,
        ]);

        EmployeeDocument::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'employee_id' => $this->employee->id,
        ]);

        Holiday::factory()->create(['branch_id' => $this->ctx['branch']->id]);
        AttendanceDevice::factory()->create(['branch_id' => $this->ctx['branch']->id]);

        $type = LeaveType::factory()->create(['branch_id' => $this->ctx['branch']->id]);

        LeaveAllocation::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'employee_id' => $this->employee->id,
            'leave_type_id' => $type->id,
        ]);

        LeaveRequest::create([
            'number' => '1',
            'employee_id' => $this->employee->id,
            'leave_type_id' => $type->id,
            'from_date' => '2026-09-07',
            'to_date' => '2026-09-08',
            'days' => 2,
            'status' => LeaveRequestStatus::Draft->value,
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
        ]);

        $taxTable = TaxBracketSet::create([
            'name' => 'Afghan monthly wage tax',
            'jurisdiction' => 'AF',
            'period' => TaxPeriod::Monthly->value,
            'effective_from' => '2026-01-01',
            'currency_id' => $this->ctx['currency']->id,
            'is_active' => true,
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
        ]);

        foreach (TaxBracketSet::defaultAfghanMonthlyBrackets() as $bracket) {
            TaxBracket::create($bracket + [
                'tax_bracket_set_id' => $taxTable->id,
                'branch_id' => $this->ctx['branch']->id,
                'created_by' => $this->ctx['user']->id,
            ]);
        }

        $opening = JobOpening::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'currency_id' => $this->ctx['currency']->id,
        ]);

        // province_id is set deliberately: the show() eager-load only touches
        // the provinces table when a row actually points at one, so a fixture
        // that leaves it null would hide a wrong column list.
        $application = JobApplication::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'job_opening_id' => $opening->id,
            'province_id' => $this->province->id,
        ]);

        $interview = Interview::create([
            'job_application_id' => $application->id,
            'round' => 1,
            'interview_type' => InterviewType::InPerson->value,
            'scheduled_at' => now()->addDay(),
            'duration_minutes' => 45,
            'status' => InterviewStatus::Scheduled->value,
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
        ]);

        InterviewPanelist::create([
            'interview_id' => $interview->id,
            'employee_id' => $this->employee->id,
            'is_lead' => true,
            'branch_id' => $this->ctx['branch']->id,
        ]);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function screenProvider(): array
    {
        return [
            'employees index' => ['employees.index', 'Hr/Employees/Index'],
            'employees create' => ['employees.create', 'Hr/Employees/Create'],
            'employee contracts' => ['employee-contracts.index', 'Hr/EmployeeContracts/Index'],
            'employee documents' => ['employee-documents.index', 'Hr/EmployeeDocuments/Index'],
            'designations' => ['designations.index', 'Administration/Designations/Index'],
            'shifts' => ['shifts.index', 'Hr/Shifts/Index'],
            'holidays' => ['holidays.index', 'Hr/Holidays/Index'],
            'attendance register' => ['attendances.index', 'Hr/Attendances/Index'],
            'attendance roster' => ['attendances.roster', 'Hr/Attendances/Roster'],
            'unmapped punches' => ['attendances.unmapped-punches', 'Hr/Attendances/UnmappedPunches'],
            'attendance devices' => ['attendance-devices.index', 'Hr/AttendanceDevices/Index'],
            'leave types' => ['leave-types.index', 'Hr/LeaveTypes/Index'],
            'leave allocations' => ['leave-allocations.index', 'Hr/LeaveAllocations/Index'],
            'leave requests' => ['leave-requests.index', 'Hr/LeaveRequests/Index'],
            'leave request create' => ['leave-requests.create', 'Hr/LeaveRequests/Create'],
            'self service' => ['self-service.index', 'Hr/SelfService/Index'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('screenProvider')]
    public function test_the_screen_renders(string $routeName, string $component): void
    {
        $response = $this->get(route($routeName));

        $response->assertOk();
        $this->assertSame($component, $response->viewData('page')['component']);
    }

    /**
     * Every detail screen, rendered against a real row.
     *
     * These exist because a show() eager-loads constrained columns, and a
     * mistyped one (`province:id,name` on a table whose columns are name_en and
     * name_fa) only fails when a row actually has that relation set — never in
     * a service test, and not even here unless the fixture fills the column.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function detailScreenProvider(): array
    {
        return [
            'contract detail' => [EmployeeContract::class, 'employee-contracts.show', 'Hr/EmployeeContracts/Show'],
            'document detail' => [EmployeeDocument::class, 'employee-documents.show', 'Hr/EmployeeDocuments/Show'],
            'leave allocation detail' => [LeaveAllocation::class, 'leave-allocations.show', 'Hr/LeaveAllocations/Show'],
            'tax table detail' => [TaxBracketSet::class, 'tax-bracket-sets.show', 'Hr/TaxBracketSets/Show'],
            'job opening detail' => [JobOpening::class, 'job-openings.show', 'Hr/JobOpenings/Show'],
            'job application detail' => [JobApplication::class, 'job-applications.show', 'Hr/JobApplications/Show'],
            'interview detail' => [Interview::class, 'interviews.show', 'Hr/Interviews/Show'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('detailScreenProvider')]
    public function test_the_detail_screen_renders(string $model, string $routeName, string $component): void
    {
        $record = $model::query()->firstOrFail();

        $response = $this->get(route($routeName, $record->id));

        $response->assertOk();
        $this->assertSame($component, $response->viewData('page')['component']);
    }

    public function test_the_employee_profile_renders_with_a_country_and_province(): void
    {
        $this->employee->forceFill([
            'country_id' => $this->province->country_id,
            'province_id' => $this->province->id,
        ])->save();

        $response = $this->get(route('employees.show', $this->employee->id));

        $response->assertOk();
        $this->assertSame('Hr/Employees/Show', $response->viewData('page')['component']);
    }

    public function test_the_employee_profile_renders(): void
    {
        $response = $this->get(route('employees.show', $this->employee->id));

        $response->assertOk();
        $this->assertSame('Hr/Employees/Show', $response->viewData('page')['component']);
    }

    public function test_the_employee_edit_screen_renders(): void
    {
        $response = $this->get(route('employees.edit', $this->employee->id));

        $response->assertOk();
        $this->assertSame('Hr/Employees/Edit', $response->viewData('page')['component']);
    }

    public function test_the_leave_request_detail_renders(): void
    {
        $request = LeaveRequest::firstOrFail();

        $response = $this->get(route('leave-requests.show', $request->id));

        $response->assertOk();
        $this->assertSame('Hr/LeaveRequests/Show', $response->viewData('page')['component']);
    }

    public function test_the_leave_request_edit_screen_renders(): void
    {
        $request = LeaveRequest::firstOrFail();

        $response = $this->get(route('leave-requests.edit', $request->id));

        $response->assertOk();
        $this->assertSame('Hr/LeaveRequests/Edit', $response->viewData('page')['component']);
    }

    /**
     * The roster grid is the one screen whose props are a computed shape rather
     * than a resource collection, so its contract is worth asserting.
     */
    public function test_the_roster_ships_the_props_the_grid_reads(): void
    {
        $props = $this->get(route('attendances.roster'))->viewData('page')['props'];

        $this->assertArrayHasKey('roster', $props);
        $this->assertArrayHasKey('rows', $props['roster']);
        $this->assertArrayHasKey('departments', $props['options']);
        $this->assertArrayHasKey('shifts', $props['options']);
        $this->assertArrayHasKey('statuses', $props['options']);
        // The import dialog lives on this screen and reads its device list here.
        $this->assertArrayHasKey('devices', $props['options']);

        $row = $props['roster']['rows'][0];

        foreach (['employee_id', 'full_name', 'status', 'is_locked', 'needs_review'] as $key) {
            $this->assertArrayHasKey($key, $row);
        }
    }

    /**
     * The Show page renders its action buttons from allowed_transitions rather
     * than reimplementing the state machine, so the resource must publish it.
     */
    public function test_the_leave_request_resource_publishes_allowed_transitions(): void
    {
        $request = LeaveRequest::firstOrFail();

        $props = $this->get(route('leave-requests.show', $request->id))->viewData('page')['props'];

        $this->assertArrayHasKey('allowed_transitions', $props['leaveRequest']['data']);
        $this->assertContains('pending', $props['leaveRequest']['data']['allowed_transitions']);
    }

    public function test_self_service_ships_balances_and_check_in_flags(): void
    {
        $props = $this->get(route('self-service.index'))->viewData('page')['props'];

        $this->assertArrayHasKey('employee', $props);
        $this->assertArrayHasKey('balances', $props);
        $this->assertArrayHasKey('canCheckIn', $props);
        $this->assertArrayHasKey('canCheckOut', $props);
    }
}
