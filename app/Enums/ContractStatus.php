<?php

namespace App\Enums;

enum ContractStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Expired = 'expired';
    case Terminated = 'terminated';
    case Renewed = 'renewed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => __('enums.contract_status.draft'),
            self::Active => __('enums.contract_status.active'),
            self::Expired => __('enums.contract_status.expired'),
            self::Terminated => __('enums.contract_status.terminated'),
            self::Renewed => __('enums.contract_status.renewed'),
        };
    }

    /**
     * Only an active contract is worth reminding anyone about.
     */
    public function isLive(): bool
    {
        return $this === self::Active;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
