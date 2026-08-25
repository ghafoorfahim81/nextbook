<?php

namespace App\Enums;

/**
 * How a candidate reached the company.
 *
 * Worth recording rather than guessing at: it is the only way to tell whether
 * the money spent advertising a role produced anyone, and in Afghanistan the
 * honest answer is often a referral or a walk-in rather than a job board.
 */
enum ApplicationSource: string
{
    case Website = 'website';
    case JobBoard = 'job_board';
    case Referral = 'referral';
    case WalkIn = 'walk_in';
    case Agency = 'agency';
    case SocialMedia = 'social_media';
    case University = 'university';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Website => __('enums.application_source.website'),
            self::JobBoard => __('enums.application_source.job_board'),
            self::Referral => __('enums.application_source.referral'),
            self::WalkIn => __('enums.application_source.walk_in'),
            self::Agency => __('enums.application_source.agency'),
            self::SocialMedia => __('enums.application_source.social_media'),
            self::University => __('enums.application_source.university'),
            self::Other => __('enums.application_source.other'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
