<?php

namespace App\Exceptions\Accounting;

use RuntimeException;

/**
 * Raised when an entry fails one of the posting invariants.
 *
 * Carries the offending lines so the caller (and the log) can see which line
 * broke which rule, rather than just "transaction is not balanced".
 */
class InvalidPostingException extends RuntimeException
{
    /** @var array<int, array<string, mixed>> */
    protected array $violations = [];

    /**
     * @param  array<int, array<string, mixed>>  $violations
     */
    public static function make(string $summary, array $violations = []): self
    {
        $exception = new self($summary . ($violations === [] ? '' : ' ' . self::describe($violations)));
        $exception->violations = $violations;

        return $exception;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function violations(): array
    {
        return $this->violations;
    }

    /**
     * @param  array<int, array<string, mixed>>  $violations
     */
    private static function describe(array $violations): string
    {
        $parts = array_map(function (array $violation): string {
            $index = $violation['line'] ?? '?';
            unset($violation['line']);

            $detail = implode(', ', array_map(
                fn ($value, $key) => $key . '=' . (is_scalar($value) ? $value : json_encode($value)),
                $violation,
                array_keys($violation)
            ));

            return "[line {$index}: {$detail}]";
        }, $violations);

        return implode(' ', $parts);
    }
}
