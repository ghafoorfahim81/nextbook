<?php

namespace App\Enums;

enum LeaveAccrualMethod: string
{
    /** The whole year's entitlement granted at the start of the leave year. */
    case AnnualGrant = 'annual_grant';

    /** Earned month by month, capped at the annual figure. */
    case MonthlyAccrual = 'monthly_accrual';

    /** No entitlement tracked; requests are recorded but never limited. */
    case Unlimited = 'unlimited';

    /** No accrual at all — allocations are entered by hand. */
    case Manual = 'manual';

    public function getLabel(): string
    {
        return match ($this) {
            self::AnnualGrant => __('enums.leave_accrual_method.annual_grant'),
            self::MonthlyAccrual => __('enums.leave_accrual_method.monthly_accrual'),
            self::Unlimited => __('enums.leave_accrual_method.unlimited'),
            self::Manual => __('enums.leave_accrual_method.manual'),
        };
    }

    /** Whether the accrual command should generate allocations for this type. */
    public function isAutomatic(): bool
    {
        return $this === self::AnnualGrant || $this === self::MonthlyAccrual;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
