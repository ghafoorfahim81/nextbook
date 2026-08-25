<?php

namespace App\Enums;

enum InterviewType: string
{
    case PhoneScreen = 'phone_screen';
    case InPerson = 'in_person';
    case Video = 'video';
    case Technical = 'technical';
    case Panel = 'panel';
    case WrittenTest = 'written_test';
    case Final = 'final';

    public function getLabel(): string
    {
        return match ($this) {
            self::PhoneScreen => __('enums.interview_type.phone_screen'),
            self::InPerson => __('enums.interview_type.in_person'),
            self::Video => __('enums.interview_type.video'),
            self::Technical => __('enums.interview_type.technical'),
            self::Panel => __('enums.interview_type.panel'),
            self::WrittenTest => __('enums.interview_type.written_test'),
            self::Final => __('enums.interview_type.final'),
        };
    }

    /** Whether the form should ask for a meeting link rather than a room. */
    public function isRemote(): bool
    {
        return $this === self::Video || $this === self::PhoneScreen;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
