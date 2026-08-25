<?php

namespace App\Enums;

enum EmploymentStatus: string
{
    case Probation = 'probation';
    case Active = 'active';
    case OnLeave = 'on_leave';
    case Suspended = 'suspended';
    case Resigned = 'resigned';
    case Terminated = 'terminated';
    case Retired = 'retired';

    public function getLabel(): string
    {
        return match ($this) {
            self::Probation => __('enums.employment_status.probation'),
            self::Active => __('enums.employment_status.active'),
            self::OnLeave => __('enums.employment_status.on_leave'),
            self::Suspended => __('enums.employment_status.suspended'),
            self::Resigned => __('enums.employment_status.resigned'),
            self::Terminated => __('enums.employment_status.terminated'),
            self::Retired => __('enums.employment_status.retired'),
        };
    }

    /**
     * Whether someone in this status is still on the payroll.
     *
     * Suspension deliberately counts as employed — a suspended employee is
     * usually still owed something, and excluding them here would quietly drop
     * them from a payroll run rather than letting HR decide.
     */
    public function isEmployed(): bool
    {
        return match ($this) {
            self::Probation, self::Active, self::OnLeave, self::Suspended => true,
            self::Resigned, self::Terminated, self::Retired => false,
        };
    }

    /**
     * Statuses that require a separation date.
     */
    public function isSeparated(): bool
    {
        return ! $this->isEmployed();
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
