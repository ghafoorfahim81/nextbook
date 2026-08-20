<?php

namespace Tests\Feature\Hr;

use App\Models\Hr\AttendanceDevice;
use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeContract;
use App\Models\Hr\EmployeeDocument;
use App\Models\Hr\Holiday;
use App\Models\Hr\LeaveAllocation;
use App\Models\Hr\LeaveRequest;
use App\Models\Hr\LeaveType;
use App\Models\Hr\Shift;
use App\Enums\LeaveRequestStatus;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->ctx = $this->bootstrapErpContext();

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
