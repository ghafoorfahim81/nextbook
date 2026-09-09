<?php

namespace App\Services\Accounting;

use App\Exceptions\Accounting\ClosedPeriodException;
use Illuminate\Support\Facades\Auth;

/**
 * The one place that answers "may the books be changed on this date?".
 *
 * It is called from TransactionService — post(), reverse() and void() — because
 * that is already the single boundary every financial event crosses. Guarding
 * there covers sales, purchases, receipts, payments, expenses, payroll, ledger
 * openings, transfers and manual journals at once, and a new module gets the
 * lock for free rather than having to remember it.
 *
 * A closed period blocks WRITES, never reads. Reports over closed years must
 * keep working — that is the point of closing one.
 */
class PeriodGuard
{
    /**
     * Users holding this may post into a closed period.
     *
     * Not a way around the control so much as a named, grantable exception to
     * it: an auditor's year-end adjustment arrives after the books are shut,
     * and the alternative to a permission is everyone reopening the year, which
     * is worse. Every use is still an ordinary audit-logged posting.
     */
    public const OVERRIDE_PERMISSION = 'financial_periods.post_to_closed';

    public function __construct(
        private readonly FiscalYearService $fiscalYears,
    ) {
    }

    /**
     * Throw unless the books may be written on $date for this branch.
     *
     * A date with no period at all generates one rather than failing. The years
     * are created open, so nothing is unlocked that was locked — it just means
     * the first posting of a new year does not have to wait for an admin. Only
     * a period someone has deliberately CLOSED refuses the write.
     */
    public function assertOpen(?string $date, ?string $branchId): void
    {
        if ($date === null || $branchId === null) {
            return;
        }

        if ($this->canOverride()) {
            return;
        }

        $period = $this->fiscalYears->periodFor($date, $branchId);

        if (! $period) {
            $this->fiscalYears->ensureYearFor($date, $branchId);
            $period = $this->fiscalYears->periodFor($date, $branchId);
        }

        // Generation covers a whole year in twelve tiling months, so a missing
        // period here means the date fell outside what was generated — treat it
        // as open rather than inventing a lock the user cannot see or lift.
        if (! $period) {
            return;
        }

        if ($period->isClosed()) {
            throw ClosedPeriodException::closed($date, $period->name);
        }
    }

    /**
     * Whether the acting user may write to a closed period.
     *
     * Console commands, queued jobs and seeders run with no user. They are not
     * end users typing a backdated invoice — they are migrations and backfills,
     * and failing them on a period lock would make a closed year impossible to
     * re-derive. They pass.
     */
    private function canOverride(): bool
    {
        $user = Auth::user();

        if ($user === null) {
            return true;
        }

        return $user->can(self::OVERRIDE_PERMISSION);
    }
}
