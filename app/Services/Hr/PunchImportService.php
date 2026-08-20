<?php

namespace App\Services\Hr;

use App\Enums\AttendanceSource;
use App\Enums\PunchDirection;
use App\Models\Hr\AttendanceDevice;
use App\Models\Hr\AttendanceDeviceUser;
use App\Models\Hr\AttendancePunch;
use App\Models\Hr\Employee;
use App\Services\DateConversionService;
use App\Support\BranchContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Imports biometric punch logs.
 *
 * Three rules shape this:
 *
 *  1. Never drop a punch. An unrecognised device ID lands with a null
 *     employee_id and surfaces on the unmapped screen, because a punch is
 *     evidence someone was at work and discarding it loses that.
 *  2. Dedupe in the DATABASE. The fingerprint unique index is what makes
 *     re-uploading the same export a genuine no-op; application-side checks
 *     race and drift.
 *  3. Import and pairing are separate passes, so a mapping added after the
 *     fact can be applied without re-uploading the file.
 */
class PunchImportService
{
    /** Rows above this are chunked rather than held in memory. */
    private const CHUNK = 1000;

    public function __construct(
        private readonly DateConversionService $dates,
        private readonly PunchPairingService $pairing,
    ) {
    }

    /**
     * @param  array<string, string>  $columnMap  logical name => header/index
     * @return array<string, mixed> summary
     */
    public function import(UploadedFile $file, ?string $deviceId, array $columnMap): array
    {
        $branchId = BranchContext::branchId();
        $batchId = (string) Str::ulid();

        $device = $deviceId
            ? AttendanceDevice::query()->find($deviceId)
            : null;

        $mappings = $this->mappings($branchId, $device?->id);

        $rows = $this->readRows($file);
        $parsed = 0;
        $skipped = 0;
        $unmapped = 0;
        $pending = [];
        $touched = [];

        foreach ($rows as $row) {
            $deviceUserId = $this->value($row, $columnMap['device_user_id'] ?? null);
            $rawTimestamp = $this->value($row, $columnMap['timestamp'] ?? null);

            if ($deviceUserId === null || $rawTimestamp === null || $rawTimestamp === '') {
                $skipped++;
                continue;
            }

            $punchedAt = $this->parseTimestamp((string) $rawTimestamp);

            if (! $punchedAt) {
                $skipped++;
                continue;
            }

            $employeeId = $mappings[(string) $deviceUserId] ?? null;

            if (! $employeeId) {
                $unmapped++;
            }

            $direction = $this->parseDirection($this->value($row, $columnMap['direction'] ?? null));

            $pending[] = [
                'id' => (string) Str::ulid(),
                'attendance_device_id' => $device?->id,
                'employee_id' => $employeeId,
                'device_user_id' => (string) $deviceUserId,
                'punched_at' => $punchedAt->toDateTimeString(),
                'punch_direction' => $direction->value,
                'source' => AttendanceSource::Import->value,
                'fingerprint' => AttendancePunch::makeFingerprint(
                    $device?->id,
                    (string) $deviceUserId,
                    $punchedAt->toIso8601String()
                ),
                'import_batch_id' => $batchId,
                'is_ignored' => false,
                'branch_id' => $branchId,
                'created_by' => auth()->id(),
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ];

            $parsed++;

            if ($employeeId) {
                $touched[$employeeId][$punchedAt->toDateString()] = true;
            }

            if (count($pending) >= self::CHUNK) {
                $this->flush($pending);
                $pending = [];
            }
        }

        $this->flush($pending);

        $device?->forceFill(['last_sync_at' => now()])->saveQuietly();

        $paired = $this->repairDays($touched);

        return [
            'batch_id' => $batchId,
            'parsed' => $parsed,
            'skipped' => $skipped,
            'unmapped' => $unmapped,
            'days_paired' => $paired,
        ];
    }

    /**
     * insertOrIgnore against the fingerprint unique index. This is the dedupe:
     * a duplicate row is rejected by Postgres, not by a lookup that could race
     * a concurrent import.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function flush(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        AttendancePunch::withoutGlobalScopes()->insertOrIgnore($rows);
    }

    /**
     * Re-derive every (employee, date) the import touched.
     *
     * @param  array<string, array<string, bool>>  $touched
     */
    private function repairDays(array $touched): int
    {
        $count = 0;

        foreach ($touched as $employeeId => $dates) {
            $employee = Employee::withoutGlobalScopes()->with('shift')->find($employeeId);

            if (! $employee) {
                continue;
            }

            foreach (array_keys($dates) as $date) {
                if ($this->pairing->pairForDate($employee, Carbon::parse($date))) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Apply a newly created mapping to punches already imported, so a device ID
     * mapped after the fact does not require re-uploading the file.
     */
    public function applyMapping(AttendanceDeviceUser $mapping): int
    {
        $updated = AttendancePunch::withoutGlobalScopes()
            ->where('branch_id', $mapping->branch_id)
            ->where('attendance_device_id', $mapping->attendance_device_id)
            ->where('device_user_id', $mapping->device_user_id)
            ->whereNull('employee_id')
            ->update(['employee_id' => $mapping->employee_id]);

        if ($updated === 0) {
            return 0;
        }

        $dates = AttendancePunch::withoutGlobalScopes()
            ->where('branch_id', $mapping->branch_id)
            ->where('employee_id', $mapping->employee_id)
            ->whereNull('attendance_id')
            ->pluck('punched_at')
            ->map(fn ($t) => Carbon::parse($t)->toDateString())
            ->unique()
            ->values()
            ->all();

        $employee = Employee::withoutGlobalScopes()->with('shift')->find($mapping->employee_id);

        foreach ($dates as $date) {
            $employee && $this->pairing->pairForDate($employee, Carbon::parse($date));
        }

        return $updated;
    }

    /**
     * device_user_id => employee_id for this branch.
     *
     * @return array<string, string>
     */
    private function mappings(string $branchId, ?string $deviceId): array
    {
        return AttendanceDeviceUser::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->when($deviceId, fn ($q) => $q->where('attendance_device_id', $deviceId))
            ->whereNull('deleted_at')
            ->pluck('employee_id', 'device_user_id')
            ->all();
    }

    /**
     * Read a delimited export into associative rows keyed by header.
     *
     * Deliberately native rather than PhpSpreadsheet: this project has no
     * spreadsheet library (SpreadsheetExportService writes xlsx by hand with
     * ZipArchive), and attendance terminals export delimited text anyway —
     * ZKTeco and Fingerspot both produce .csv/.txt/.dat. Adding a dependency to
     * read a file format the devices do not emit would be the wrong trade.
     *
     * The delimiter is sniffed from the header line, so comma, semicolon and
     * tab separated exports all work without the user having to say which.
     *
     * @return array<int, array<string, mixed>>
     */
    public function readRows(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            return [];
        }

        try {
            $firstLine = fgets($handle);

            if ($firstLine === false) {
                return [];
            }

            // Strip a UTF-8 BOM, which Windows-generated exports routinely
            // carry and which would otherwise become part of the first header.
            $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine);

            $delimiter = $this->sniffDelimiter($firstLine);

            $headers = array_map(
                fn ($h) => Str::of((string) $h)->trim()->lower()->replace(' ', '_')->value(),
                str_getcsv(rtrim($firstLine, "\r\n"), $delimiter)
            );

            $rows = [];

            while (($line = fgetcsv($handle, 0, $delimiter)) !== false) {
                if ($line === [null] || collect($line)->filter(fn ($v) => $v !== null && trim((string) $v) !== '')->isEmpty()) {
                    continue;
                }

                $row = [];

                foreach ($line as $i => $value) {
                    $header = $headers[$i] ?? '';

                    if ($header !== '') {
                        $row[$header] = $value;
                    }

                    // Positional keys too, so a headerless export can be mapped
                    // by column index instead of by name.
                    $row["column_{$i}"] = $value;
                }

                $rows[] = $row;
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Pick the delimiter that splits the header into the most fields.
     */
    private function sniffDelimiter(string $headerLine): string
    {
        $best = ',';
        $bestCount = 0;

        foreach ([',', ';', "\t", '|'] as $candidate) {
            $count = count(str_getcsv(rtrim($headerLine, "\r\n"), $candidate));

            if ($count > $bestCount) {
                $best = $candidate;
                $bestCount = $count;
            }
        }

        return $best;
    }

    /**
     * The header names a file is likely to use, for the mapping step.
     *
     * @return array<int, string>
     */
    public function detectHeaders(UploadedFile $file): array
    {
        $rows = $this->readRows($file);

        return $rows === [] ? [] : array_values(array_filter(
            array_keys($rows[0]),
            fn (string $key) => ! str_starts_with($key, 'column_')
        ));
    }

    private function value(array $row, ?string $key): mixed
    {
        if (! $key) {
            return null;
        }

        return $row[$key] ?? null;
    }

    /**
     * Accepts the formats these exports actually use, including Jalali dates.
     *
     * toGregorian() is idempotent and decides by year range, so running a
     * Gregorian date through it is harmless.
     */
    private function parseTimestamp(string $value): ?Carbon
    {
        $value = trim(str_replace('/', '-', $value));

        if ($value === '') {
            return null;
        }

        // Split date and time so only the date half goes through the Jalali
        // converter — passing a full timestamp would lose the time.
        $parts = preg_split('/[ T]+/', $value, 2);
        $datePart = $parts[0] ?? '';
        $timePart = $parts[1] ?? '00:00:00';

        try {
            $gregorian = $this->dates->toGregorian($datePart);
        } catch (\Throwable) {
            $gregorian = null;
        }

        if (! $gregorian) {
            return null;
        }

        try {
            return Carbon::parse($gregorian.' '.$timePart);
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseDirection(mixed $value): PunchDirection
    {
        $normalised = Str::of((string) $value)->trim()->lower()->value();

        return match ($normalised) {
            'in', 'i', '0', 'check-in', 'check_in', 'checkin', 'entry' => PunchDirection::In,
            'out', 'o', '1', 'check-out', 'check_out', 'checkout', 'exit' => PunchDirection::Out,
            default => PunchDirection::Unknown,
        };
    }
}
