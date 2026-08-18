<?php

namespace App\Enums;

enum ContractType: string
{
    case Permanent = 'permanent';
    case FixedTerm = 'fixed_term';
    case Probation = 'probation';
    case Consultancy = 'consultancy';
    case Internship = 'internship';

    public function getLabel(): string
    {
        return match ($this) {
            self::Permanent => __('enums.contract_type.permanent'),
            self::FixedTerm => __('enums.contract_type.fixed_term'),
            self::Probation => __('enums.contract_type.probation'),
            self::Consultancy => __('enums.contract_type.consultancy'),
            self::Internship => __('enums.contract_type.internship'),
        };
    }

    /**
     * Whether an end date is mandatory for this contract type.
     *
     * A permanent contract with no end date is normal; a fixed-term one without
     * an end date is a data-entry error that would never trigger a renewal
     * reminder.
     */
    public function requiresEndDate(): bool
    {
        return $this !== self::Permanent;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
