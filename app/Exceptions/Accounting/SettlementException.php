<?php

namespace App\Exceptions\Accounting;

use Exception;

/**
 * A settlement that cannot be applied as asked.
 *
 * Distinct from InvalidPostingException: that one means the journal entry is
 * malformed, this one means the allocation is wrong — over-applying an invoice,
 * mixing target currencies in one voucher, settling a line that is not a claim.
 */
class SettlementException extends Exception
{
    /** @var array<int, array<string, mixed>> */
    protected array $context = [];

    /**
     * @param  array<int, array<string, mixed>>  $context
     */
    public static function make(string $summary, array $context = []): self
    {
        $exception = new self($summary . ($context === [] ? '' : ' ' . self::describe($context)));
        $exception->context = $context;

        return $exception;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function context(): array
    {
        return $this->context;
    }

    /**
     * @param  array<int, array<string, mixed>>  $context
     */
    private static function describe(array $context): string
    {
        return collect($context)
            ->map(fn (array $row) => '[' . collect($row)
                ->map(fn ($value, $key) => $key . '=' . (is_scalar($value) ? $value : json_encode($value)))
                ->implode(', ') . ']')
            ->implode(' ');
    }
}
