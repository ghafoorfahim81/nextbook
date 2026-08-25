<?php

namespace Tests\Feature\Hr;

use App\Models\Hr\Attendance;
use App\Models\Hr\AttendanceDevice;
use App\Models\Hr\AttendanceDeviceUser;
use App\Models\Hr\AttendancePunch;
use App\Models\Hr\Employee;
use App\Models\Hr\Shift;
use App\Services\Hr\PunchImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Importing biometric punch logs.
 *
 * The behaviours that matter are the ones a careless implementation gets wrong:
 * re-uploading the same export must not double-count, and a punch from an
 * unrecognised device ID must not vanish.
 */
class AttendanceImportFeatureTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    private Employee $employee;

    private AttendanceDevice $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ctx = $this->bootstrapErpContext();

        $shift = Shift::factory()->create(['branch_id' => $this->ctx['branch']->id]);

        $this->employee = Employee::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'currency_id' => $this->ctx['currency']->id,
            'shift_id' => $shift->id,
        ]);

        $this->device = AttendanceDevice::factory()->create(['branch_id' => $this->ctx['branch']->id]);
    }

    private function csv(string $body, string $name = 'punches.csv'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'punch').'.csv';
        file_put_contents($path, $body);

        return new UploadedFile($path, $name, 'text/csv', null, true);
    }

    private function map(): AttendanceDeviceUser
    {
        return AttendanceDeviceUser::create([
            'attendance_device_id' => $this->device->id,
            'employee_id' => $this->employee->id,
            'device_user_id' => '17',
        ]);
    }

    private function importer(): PunchImportService
    {
        return app(PunchImportService::class);
    }

    private function columns(): array
    {
        return ['device_user_id' => 'user_id', 'timestamp' => 'time', 'direction' => 'status'];
    }

    private const CSV = <<<'CSV'
    user_id,time,status
    17,2026-08-17 08:00:00,in
    17,2026-08-17 16:00:00,out
    CSV;

    public function test_it_imports_and_pairs_punches(): void
    {
        $this->map();

        $summary = $this->importer()->import($this->csv(self::CSV), $this->device->id, $this->columns());

        $this->assertSame(2, $summary['parsed']);
        $this->assertSame(0, $summary['unmapped']);
        $this->assertSame(2, AttendancePunch::withoutGlobalScopes()->count());

        $day = Attendance::withoutGlobalScopes()->first();
        $this->assertNotNull($day);
        // 08:00–16:00 is eight hours on the clock, less the shift's 60-minute
        // unpaid break.
        $this->assertEquals(7.0, (float) $day->worked_hours);
        $this->assertSame(60, (int) $day->break_minutes);
    }

    /**
     * The dedupe guarantee lives in the fingerprint unique index, so this must
     * hold even though the second import has no knowledge of the first.
     */
    public function test_reimporting_the_same_file_is_a_no_op(): void
    {
        $this->map();

        $this->importer()->import($this->csv(self::CSV), $this->device->id, $this->columns());
        $countAfterFirst = AttendancePunch::withoutGlobalScopes()->count();

        $this->importer()->import($this->csv(self::CSV), $this->device->id, $this->columns());

        $this->assertSame($countAfterFirst, AttendancePunch::withoutGlobalScopes()->count());
        $this->assertSame(2, $countAfterFirst);
    }

    /**
     * A punch is evidence someone was at work. An unmapped device ID must land
     * so it can be resolved, never be discarded.
     */
    public function test_unmapped_device_ids_still_land(): void
    {
        $summary = $this->importer()->import($this->csv(self::CSV), $this->device->id, $this->columns());

        $this->assertSame(2, $summary['parsed']);
        $this->assertSame(2, $summary['unmapped']);
        $this->assertSame(2, AttendancePunch::withoutGlobalScopes()->whereNull('employee_id')->count());
    }

    /**
     * Mapping after the fact must not require re-uploading the file.
     */
    public function test_mapping_afterwards_backfills_and_pairs(): void
    {
        $this->importer()->import($this->csv(self::CSV), $this->device->id, $this->columns());

        $this->assertSame(0, Attendance::withoutGlobalScopes()->count());

        $applied = $this->importer()->applyMapping($this->map());

        $this->assertSame(2, $applied);
        $this->assertSame(0, AttendancePunch::withoutGlobalScopes()->whereNull('employee_id')->count());

        $day = Attendance::withoutGlobalScopes()->first();
        $this->assertNotNull($day);
        $this->assertEquals(7.0, (float) $day->worked_hours);
    }

    public function test_it_sniffs_a_semicolon_delimited_export(): void
    {
        $this->map();

        $body = "user_id;time;status\n17;2026-08-17 08:00:00;in\n17;2026-08-17 16:00:00;out\n";

        $summary = $this->importer()->import($this->csv($body), $this->device->id, $this->columns());

        $this->assertSame(2, $summary['parsed']);
    }

    public function test_it_strips_a_utf8_bom_from_the_header(): void
    {
        $this->map();

        $body = "\xEF\xBB\xBF".self::CSV;

        $summary = $this->importer()->import($this->csv($body), $this->device->id, $this->columns());

        $this->assertSame(2, $summary['parsed'], 'A BOM must not become part of the first header name.');
    }

    public function test_it_accepts_jalali_timestamps(): void
    {
        $this->map();

        // 1405-05-26 is 2026-08-17.
        $body = "user_id,time,status\n17,1405-05-26 08:00:00,in\n17,1405-05-26 16:00:00,out\n";

        $this->importer()->import($this->csv($body), $this->device->id, $this->columns());

        $punch = AttendancePunch::withoutGlobalScopes()->orderBy('punched_at')->first();

        $this->assertSame('2026-08-17', $punch->punched_at->toDateString());
        $this->assertSame('08:00', $punch->punched_at->format('H:i'));
    }

    public function test_unparseable_rows_are_skipped_not_fatal(): void
    {
        $this->map();

        $body = "user_id,time,status\n17,not-a-date,in\n,,\n17,2026-08-17 16:00:00,out\n";

        $summary = $this->importer()->import($this->csv($body), $this->device->id, $this->columns());

        $this->assertSame(1, $summary['parsed']);
        $this->assertGreaterThanOrEqual(1, $summary['skipped']);
    }

    public function test_the_import_endpoint_accepts_a_csv(): void
    {
        $this->map();

        $this->post(route('attendances.import'), [
            'file' => $this->csv(self::CSV),
            'attendance_device_id' => $this->device->id,
            'column_device_user_id' => 'user_id',
            'column_timestamp' => 'time',
            'column_direction' => 'status',
        ])->assertRedirect();

        $this->assertSame(2, AttendancePunch::withoutGlobalScopes()->count());
    }

    public function test_headers_can_be_detected_for_column_mapping(): void
    {
        $headers = $this->importer()->detectHeaders($this->csv(self::CSV));

        $this->assertSame(['user_id', 'time', 'status'], $headers);
    }
}
