<?php

namespace App\Enums;

enum LoanType: string
{
    /** Salary paid before it is earned; recovered from the next run. */
    case SalaryAdvance = 'salary_advance';
    /** A general advance recovered over one or more runs. */
    case Advance = 'advance';
    /** A staff loan repaid in instalments. */
    case Loan = 'loan';

    public function getLabel(): string
    {
        return match ($this) {
            self::SalaryAdvance => __('enums.loan_type.salary_advance'),
            self::Advance => __('enums.loan_type.advance'),
            self::Loan => __('enums.loan_type.loan'),
        };
    }

    /**
     * Which control account the balance sits in.
     *
     * Advances and loans are kept apart so "money paid early" and "money lent"
     * do not net into one unreadable figure on the balance sheet.
     */
    public function accountSlug(): string
    {
        return match ($this) {
            self::SalaryAdvance, self::Advance => 'employee-advances',
            self::Loan => 'employee-loans-receivable',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
