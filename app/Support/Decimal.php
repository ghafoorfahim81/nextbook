<?php

namespace App\Support;

/**
 * Fixed-point arithmetic for money and rates.
 *
 * Everything financial in this system is a decimal string, never a float. A
 * rate like 0.0000017 (IRR to AFN) and an amount like 12,345,678.9012 cannot
 * both survive a float round-trip, and a ledger that is off by 0.0001 is a
 * ledger that does not balance.
 *
 * This exists so posting and settlement round the SAME way. TransactionService
 * rejects any line where |base - amount x rate| > 0.0001, so if SettlementService
 * computed a base amount with different rounding it would post entries that the
 * boundary then refuses. One implementation, one rule.
 */
final class Decimal
{
    /** Amounts are stored at 4 decimal places. */
    public const AMOUNT_SCALE = 4;

    /** Rates are stored at 8 decimal places. */
    public const RATE_SCALE = 8;

    /**
     * amount x rate, rounded half away from zero to the stored amount scale.
     */
    public static function toBase(string $amount, string $rate): string
    {
        $product = bcmul($amount, $rate, self::AMOUNT_SCALE + self::RATE_SCALE);

        return self::roundHalfUp($product, self::AMOUNT_SCALE);
    }

    /**
     * bcmath truncates, so nudge by half a unit before cutting.
     *
     * Signed: a negative value is rounded away from zero, matching the positive
     * case. Forex results are routinely negative and rounding them toward zero
     * would quietly shed a hundredth of an afghani per settlement.
     */
    public static function roundHalfUp(string $value, int $scale): string
    {
        if (bccomp($value, '0', self::AMOUNT_SCALE + self::RATE_SCALE) < 0) {
            return bcmul(self::roundHalfUp(bcmul($value, '-1', self::AMOUNT_SCALE + self::RATE_SCALE), $scale), '-1', $scale);
        }

        $half = '0.' . str_repeat('0', $scale) . '5';

        return bcadd($value, $half, $scale);
    }

    /**
     * Coerce any numeric input to a plain decimal string at the given scale.
     *
     * A small rate like 0.0000017 stringifies to "1.7E-6", which bcmath rejects
     * outright — and small rates are exactly what this supports.
     */
    public static function scale(mixed $value, int $scale): string
    {
        if (is_string($value) && $value !== '' && ! preg_match('/[eE]/', $value)) {
            return self::roundHalfUp($value, $scale);
        }

        if ($value === '' || $value === null) {
            return self::roundHalfUp('0', $scale);
        }

        return sprintf('%.' . $scale . 'F', (float) $value);
    }

    /** Coerce to an amount at AMOUNT_SCALE. */
    public static function amount(mixed $value): string
    {
        return self::scale($value, self::AMOUNT_SCALE);
    }

    /** Coerce to a rate at RATE_SCALE. */
    public static function rate(mixed $value): string
    {
        return self::scale($value, self::RATE_SCALE);
    }

    public static function add(string $a, string $b, int $scale = self::AMOUNT_SCALE): string
    {
        return bcadd($a, $b, $scale);
    }

    public static function sub(string $a, string $b, int $scale = self::AMOUNT_SCALE): string
    {
        return bcsub($a, $b, $scale);
    }

    public static function cmp(string $a, string $b, int $scale = self::AMOUNT_SCALE): int
    {
        return bccomp($a, $b, $scale);
    }

    public static function isPositive(string $value, int $scale = self::AMOUNT_SCALE): bool
    {
        return bccomp($value, '0', $scale) > 0;
    }

    public static function isZero(string $value, int $scale = self::AMOUNT_SCALE): bool
    {
        return bccomp($value, '0', $scale) === 0;
    }

    public static function abs(string $value, int $scale = self::AMOUNT_SCALE): string
    {
        return bccomp($value, '0', $scale) < 0
            ? bcmul($value, '-1', $scale)
            : $value;
    }

    /** Largest of the two, by value. */
    public static function max(string $a, string $b, int $scale = self::AMOUNT_SCALE): string
    {
        return bccomp($a, $b, $scale) >= 0 ? $a : $b;
    }
}
