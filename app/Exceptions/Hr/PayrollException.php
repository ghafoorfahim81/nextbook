<?php

namespace App\Exceptions\Hr;

use Exception;

/**
 * A payroll operation that cannot be carried out as asked.
 *
 * Covers configuration gaps (no tax brackets, no salary structure, a missing
 * GL account) and lifecycle violations (recalculating a posted run, reversing
 * one that is already paid). Distinct from a validation error: these are states
 * the system refuses rather than input the user typed wrongly, and they must
 * never degrade into a silent zero — a payroll that quietly withholds no tax
 * or pays nobody is worse than one that stops.
 */
class PayrollException extends Exception
{
    /** @var array<string, mixed> */
    protected array $context = [];

    /**
     * @param  array<string, mixed>  $context
     */
    public static function make(string $summary, array $context = []): self
    {
        $exception = new self($summary.($context === [] ? '' : ' '.self::describe($context)));
        $exception->context = $context;

        return $exception;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function describe(array $context): string
    {
        return '['.collect($context)
            ->map(fn ($value, $key) => $key.'='.(is_scalar($value) || $value === null ? var_export($value, true) : json_encode($value)))
            ->implode(', ').']';
    }
}
