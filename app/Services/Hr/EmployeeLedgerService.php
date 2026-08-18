<?php

namespace App\Services\Hr;

use App\Enums\LedgerType;
use App\Models\Hr\Employee;
use App\Models\Ledger\Ledger;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * Keeps each employee's companion `ledgers` row in step.
 *
 * Employees carry money: accrued salary, advances, staff loans. Rather than
 * reinventing balances and statements for HR, an employee gets an EMPLOYEE-type
 * ledger, which makes SettlementService, LedgerStatementService, LedgerOpening
 * and the payment vouchers work on them unchanged.
 *
 * Invoked from EmployeeObserver rather than a controller, because employees are
 * also created by importers, seeders, tests and console commands — any of which
 * would otherwise produce an employee with no financial identity.
 */
class EmployeeLedgerService
{
    /**
     * Create or update the ledger backing this employee.
     */
    public function syncFor(Employee $employee): Ledger
    {
        $ledger = $employee->ledger_id
            ? Ledger::withoutGlobalScopes()->withTrashed()->find($employee->ledger_id)
            : null;

        if ($ledger) {
            $this->applyAttributes($ledger, $employee);

            if ($ledger->isDirty()) {
                $ledger->saveQuietly();
            }

            return $ledger;
        }

        $ledger = new Ledger();
        $this->applyAttributes($ledger, $employee);
        $ledger->created_by = $employee->created_by ?? auth()->id();
        $ledger->saveQuietly();

        // saveQuietly on the employee: writing back the FK must not re-enter the
        // observer, and must not bump updated_by as though a user had edited.
        $employee->forceFill(['ledger_id' => $ledger->id])->saveQuietly();

        return $ledger;
    }

    /**
     * Mirror the handful of employee fields the ledger cares about.
     *
     * Note what is NOT copied: phone_no and email. `ledgers` is unique per
     * branch on name, code, phone_no and email. An employee sharing a phone
     * number or a shared office email with a customer would violate those
     * constraints and block the employee from being saved at all. Contact
     * details live on `employees`, which is where every HR screen reads them.
     */
    private function applyAttributes(Ledger $ledger, Employee $employee): void
    {
        $ledger->forceFill([
            'type' => LedgerType::EMPLOYEE->value,
            'name' => $this->ledgerName($employee),
            'code' => $this->ledgerCode($employee),
            // The employee's OWN branch, never the acting branch: an admin
            // creating an employee while switched to another branch must not
            // strand the ledger somewhere the employee cannot be settled from.
            'branch_id' => $employee->branch_id,
            'currency_id' => $employee->currency_id
                ?? BranchContext::homeCurrency($employee->branch_id)?->id,
            'is_active' => (bool) $employee->is_active,
            'is_main' => false,
        ]);
    }

    /**
     * Suffixed with the employee code so two people with the same name cannot
     * collide on the branch-scoped unique index. Deterministic — no retry loop,
     * because employees.code is already unique per branch.
     */
    private function ledgerName(Employee $employee): string
    {
        $name = trim((string) $employee->full_name) ?: trim((string) $employee->first_name);

        return trim($name.' ('.$employee->code.')');
    }

    private function ledgerCode(Employee $employee): string
    {
        $code = (string) $employee->code;

        return str_starts_with($code, 'EMP-') ? $code : 'EMP-'.$code;
    }

    /**
     * Cascade the employee's soft delete onto its ledger.
     *
     * Safe for the general ledger: reports read `transaction_lines`, which are
     * untouched. It only removes the party from pickers and statements.
     */
    public function softDeleteFor(Employee $employee): void
    {
        $this->query($employee)->first()?->delete();
    }

    /**
     * Bring back only a ledger removed by the employee's own cascade.
     *
     * The threshold is the employee's deleted_at: a ledger deleted separately
     * and earlier was a deliberate act and stays deleted. Same rule
     * HasAttachments applies to attachments.
     */
    public function restoreFor(Employee $employee, mixed $deletedAtThreshold = null): void
    {
        $query = $this->query($employee)->onlyTrashed();

        if ($deletedAtThreshold) {
            $query->where('deleted_at', '>=', $deletedAtThreshold);
        }

        $query->first()?->restore();
    }

    /**
     * Hard-delete the ledger, but only when it carries no accounting history.
     *
     * A ledger with transaction lines is referenced by posted vouchers; removing
     * it would orphan them. The caller is expected to surface this as a refusal.
     */
    public function forceDeleteFor(Employee $employee): bool
    {
        $ledger = $this->query($employee)->onlyTrashed()->first()
            ?? $this->query($employee)->first();

        if (! $ledger) {
            return true;
        }

        $hasHistory = $ledger->transactionLines()->withoutGlobalScopes()->exists();

        if ($hasHistory) {
            return false;
        }

        $ledger->forceDelete();

        return true;
    }

    private function query(Employee $employee): Builder
    {
        return Ledger::withoutGlobalScopes()
            ->withTrashed()
            ->where('id', $employee->ledger_id);
    }
}
