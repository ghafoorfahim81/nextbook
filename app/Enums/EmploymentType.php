<?php

namespace App\Enums;

/**
 * How someone is engaged — which also decides where their salary is expensed.
 *
 * The mapping to a GL account slug lives here rather than in payroll so that
 * adding a type cannot silently fall through to the permanent-staff account.
 */
enum EmploymentType: string
{
    case Permanent = 'permanent';
    case Temporary = 'temporary';
    case Contract = 'contract';
    case Consultant = 'consultant';
    case Intern = 'intern';
    case DailyWage = 'daily_wage';

    public function getLabel(): string
    {
        return match ($this) {
            self::Permanent => __('enums.employment_type.permanent'),
            self::Temporary => __('enums.employment_type.temporary'),
            self::Contract => __('enums.employment_type.contract'),
            self::Consultant => __('enums.employment_type.consultant'),
            self::Intern => __('enums.employment_type.intern'),
            self::DailyWage => __('enums.employment_type.daily_wage'),
        };
    }

    /**
     * The default salary expense account slug for this engagement type.
     *
     * Overridable per salary structure; this is only the fallback.
     */
    public function salaryExpenseSlug(): string
    {
        return match ($this) {
            self::Permanent => 'permanent-staff-salary',
            self::Temporary, self::Intern, self::DailyWage => 'temporary-staff-salary',
            self::Contract, self::Consultant => 'consultant-professional-salary',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
