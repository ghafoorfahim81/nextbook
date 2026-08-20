<?php

namespace App\Enums;

enum SalaryComponentType: string
{
    case Earning = 'earning';
    case Deduction = 'deduction';
    /** Employer-side cost that is not paid to the employee. */
    case EmployerContribution = 'employer_contribution';

    public function getLabel(): string
    {
        return match ($this) {
            self::Earning => __('enums.salary_component_type.earning'),
            self::Deduction => __('enums.salary_component_type.deduction'),
            self::EmployerContribution => __('enums.salary_component_type.employer_contribution'),
        };
    }

    /** Whether the amount increases what the employee is owed. */
    public function isPositive(): bool
    {
        return $this === self::Earning;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
