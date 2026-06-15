<?php

namespace App\Exceptions;

use App\Enums\TransactionStatus;
use RuntimeException;

class InvalidStatusTransitionException extends RuntimeException
{
    public static function for(TransactionStatus $from, TransactionStatus $to): self
    {
        return new self("Invalid transaction status transition from {$from->value} to {$to->value}.");
    }
}
