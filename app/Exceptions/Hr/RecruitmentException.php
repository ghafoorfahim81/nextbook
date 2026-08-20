<?php

namespace App\Exceptions\Hr;

use Exception;

/**
 * A recruitment operation that cannot be carried out as asked.
 *
 * Covers pipeline violations — interviewing someone who was never
 * shortlisted, hiring a candidate twice, hiring past the approved headcount.
 * Distinct from a validation error: the input was well formed, the state
 * simply does not permit it.
 *
 * Kept separate from PayrollException because these are refusals about people
 * who are not yet employees, and the two have entirely different audiences:
 * a payroll refusal goes to finance, a recruitment one to a hiring manager.
 */
class RecruitmentException extends Exception
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
