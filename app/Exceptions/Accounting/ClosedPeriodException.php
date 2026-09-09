<?php

namespace App\Exceptions\Accounting;

/**
 * Raised when a financial event would land in a period that is no longer open.
 *
 * Carries the offending date and the period's name because "the period is
 * closed" on its own sends the user hunting: they need to know WHICH date on
 * the voucher was rejected and which month it fell into, especially on an edit
 * where the date they are being told about is the one already saved.
 */
class ClosedPeriodException extends AccountingException
{
    protected ?string $date = null;

    protected ?string $periodName = null;

    public static function closed(string $date, string $periodName): self
    {
        $exception = new self(__('general.period_closed', [
            'date' => $date,
            'period' => $periodName,
        ]));

        $exception->date = $date;
        $exception->periodName = $periodName;

        return $exception;
    }

    public static function yearClosed(string $yearName): self
    {
        $exception = new self(__('general.fiscal_year_closed', ['year' => $yearName]));
        $exception->periodName = $yearName;

        return $exception;
    }

    public function date(): ?string
    {
        return $this->date;
    }

    public function periodName(): ?string
    {
        return $this->periodName;
    }
}
