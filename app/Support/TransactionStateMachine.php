<?php

namespace App\Support;

use App\Enums\TransactionStatus;

class TransactionStateMachine
{
    public static function canTransition(TransactionStatus $from, TransactionStatus $to): bool
    {
        return in_array($to, self::allowedTransitions()[$from->value] ?? [], true);
    }

    /**
     * @return array<string, array<int, TransactionStatus>>
     */
    private static function allowedTransitions(): array
    {
        return [
            TransactionStatus::DRAFT->value => [
                TransactionStatus::POSTED,
                TransactionStatus::CANCELLED,
            ],
            TransactionStatus::POSTED->value => [
                TransactionStatus::REVERSED,
            ],
            TransactionStatus::REVERSED->value => [],
            TransactionStatus::CANCELLED->value => [],
        ];
    }
}
