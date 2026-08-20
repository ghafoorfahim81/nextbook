<?php

namespace App\Enums;

enum LeaveAllocationSource: string
{
    case AutoAccrual = 'auto_accrual';
    case Manual = 'manual';
    case CarryForward = 'carry_forward';

    public function getLabel(): string
    {
        return match ($this) {
            self::AutoAccrual => __('enums.leave_allocation_source.auto_accrual'),
            self::Manual => __('enums.leave_allocation_source.manual'),
            self::CarryForward => __('enums.leave_allocation_source.carry_forward'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
