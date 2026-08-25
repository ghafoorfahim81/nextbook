<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case HalfDay = 'half_day';
    case OnLeave = 'on_leave';
    case Holiday = 'holiday';
    case Weekend = 'weekend';
    case Remote = 'remote';
    case Mission = 'mission';

    public function getLabel(): string
    {
        return match ($this) {
            self::Present => __('enums.attendance_status.present'),
            self::Absent => __('enums.attendance_status.absent'),
            self::Late => __('enums.attendance_status.late'),
            self::HalfDay => __('enums.attendance_status.half_day'),
            self::OnLeave => __('enums.attendance_status.on_leave'),
            self::Holiday => __('enums.attendance_status.holiday'),
            self::Weekend => __('enums.attendance_status.weekend'),
            self::Remote => __('enums.attendance_status.remote'),
            self::Mission => __('enums.attendance_status.mission'),
        };
    }

    /**
     * Whether the day counts as worked for payroll proration.
     *
     * Leave is excluded here on purpose: whether a leave day is paid depends on
     * the LEAVE TYPE, not the attendance status, so payroll resolves it there.
     */
    public function isWorkedDay(): bool
    {
        return match ($this) {
            self::Present, self::Late, self::Remote, self::Mission => true,
            self::HalfDay, self::Absent, self::OnLeave, self::Holiday, self::Weekend => false,
        };
    }

    /**
     * Days nobody is expected to work — excluded from the denominator when
     * counting attendance, rather than counted as absence.
     */
    public function isNonWorkingDay(): bool
    {
        return $this === self::Holiday || $this === self::Weekend;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
