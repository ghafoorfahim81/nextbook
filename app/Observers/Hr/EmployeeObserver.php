<?php

namespace App\Observers\Hr;

use App\Models\Hr\Employee;
use App\Services\Hr\EmployeeLedgerService;

/**
 * Keeps an employee and its companion ledger in lockstep.
 *
 * An observer rather than controller code: employees are created by the CSV
 * importer, seeders, factories and console commands too, and every one of those
 * paths must produce a ledger or the employee cannot be paid.
 */
class EmployeeObserver
{
    /**
     * Employee deleted_at values captured before a restore, so only the ledger
     * removed by this employee's own cascade is brought back.
     *
     * @var array<string, mixed>
     */
    private static array $restoreThresholds = [];

    public function __construct(private readonly EmployeeLedgerService $ledgers)
    {
    }

    public function created(Employee $employee): void
    {
        $this->ledgers->syncFor($employee);
    }

    public function updated(Employee $employee): void
    {
        // Only the mirrored fields matter. Re-syncing on every save would write
        // to `ledgers` each time someone edited an address.
        $mirrored = ['full_name', 'first_name', 'last_name', 'code', 'currency_id', 'is_active'];

        if (! $employee->wasChanged($mirrored)) {
            return;
        }

        $this->ledgers->syncFor($employee);
    }

    public function deleting(Employee $employee): void
    {
        // Force deletes are handled by forceDeleted().
        if ($employee->isForceDeleting()) {
            return;
        }

        $this->ledgers->softDeleteFor($employee);
    }

    public function restoring(Employee $employee): void
    {
        self::$restoreThresholds[(string) $employee->getKey()] = $employee->deleted_at;
    }

    public function restored(Employee $employee): void
    {
        $key = (string) $employee->getKey();
        $threshold = self::$restoreThresholds[$key] ?? null;
        unset(self::$restoreThresholds[$key]);

        $this->ledgers->restoreFor($employee, $threshold);
    }

    public function forceDeleted(Employee $employee): void
    {
        $this->ledgers->forceDeleteFor($employee);
    }
}
