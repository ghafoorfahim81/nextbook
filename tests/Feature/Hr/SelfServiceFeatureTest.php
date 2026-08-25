<?php

namespace Tests\Feature\Hr;

use App\Models\Hr\Attendance;
use App\Models\Hr\AttendancePunch;
use App\Models\Hr\Employee;
use App\Models\Hr\HrSetting;
use App\Models\Hr\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Employee self-service check-in.
 *
 * Authorisation here is deliberately NOT `attendances.create` — it is the
 * employee link plus their own self_service_enabled flag, so clocking yourself
 * in never confers the ability to edit anyone else's day.
 */
class SelfServiceFeatureTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ctx = $this->bootstrapErpContext();

        $shift = Shift::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'break_minutes' => 0,
        ]);

        $this->employee = Employee::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'currency_id' => $this->ctx['currency']->id,
            'shift_id' => $shift->id,
            'user_id' => $this->ctx['user']->id,
            'self_service_enabled' => true,
        ]);
    }

    private function punchCount(): int
    {
        return AttendancePunch::withoutGlobalScopes()->count();
    }

    public function test_an_employee_can_view_their_own_attendance(): void
    {
        $this->get(route('self-service.index'))->assertOk();
    }

    public function test_a_user_without_an_employee_record_is_refused(): void
    {
        $this->employee->forceFill(['user_id' => null])->save();

        $this->get(route('self-service.index'))->assertForbidden();
    }

    public function test_self_service_can_be_disabled_per_employee(): void
    {
        $this->employee->forceFill(['self_service_enabled' => false])->save();

        $this->get(route('self-service.index'))->assertForbidden();
    }

    public function test_check_in_then_check_out_records_the_day(): void
    {
        $this->post(route('self-service.check-in'))->assertRedirect();

        $day = Attendance::withoutGlobalScopes()->first();
        $this->assertNotNull($day->check_in);
        // One punch so far, so the day is flagged rather than counted as zero.
        $this->assertTrue((bool) $day->needs_review);

        $this->travel(4)->hours();
        $this->post(route('self-service.check-out'))->assertRedirect();

        $day = $day->fresh();
        $this->assertNotNull($day->check_out);
        $this->assertFalse((bool) $day->needs_review);
    }

    /**
     * A double tap on a slow connection must not create two punches.
     */
    public function test_checking_in_twice_in_the_same_minute_is_idempotent(): void
    {
        $this->post(route('self-service.check-in'));
        $this->post(route('self-service.check-in'));

        $this->assertSame(1, $this->punchCount());
    }

    public function test_geofencing_rejects_a_punch_from_too_far_away(): void
    {
        HrSetting::forBranch($this->ctx['branch']->id)->forceFill([
            'enforce_geofence' => true,
            // Kabul city centre.
            'geofence_latitude' => 34.5553,
            'geofence_longitude' => 69.2075,
            'geofence_radius_meters' => 200,
        ])->save();

        // Roughly 11 km north.
        $this->post(route('self-service.check-in'), [
            'latitude' => 34.6553,
            'longitude' => 69.2075,
        ])->assertSessionHasErrors('latitude');

        $this->assertSame(0, $this->punchCount());
    }

    public function test_geofencing_accepts_a_punch_from_inside_the_radius(): void
    {
        HrSetting::forBranch($this->ctx['branch']->id)->forceFill([
            'enforce_geofence' => true,
            'geofence_latitude' => 34.5553,
            'geofence_longitude' => 69.2075,
            'geofence_radius_meters' => 500,
        ])->save();

        $this->post(route('self-service.check-in'), [
            'latitude' => 34.5555,
            'longitude' => 69.2077,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->punchCount());
    }

    /**
     * Off by default on purpose: a wrong radius would lock out an entire
     * workforce on day one.
     */
    public function test_geofencing_is_off_by_default(): void
    {
        $this->post(route('self-service.check-in'))->assertSessionHasNoErrors();

        $this->assertSame(1, $this->punchCount());
    }

    public function test_geofencing_requires_a_location_when_enforced(): void
    {
        HrSetting::forBranch($this->ctx['branch']->id)->forceFill([
            'enforce_geofence' => true,
            'geofence_latitude' => 34.5553,
            'geofence_longitude' => 69.2075,
            'geofence_radius_meters' => 200,
        ])->save();

        $this->post(route('self-service.check-in'))->assertSessionHasErrors('latitude');
    }
}
