<?php
// app/Services/DateConversionService.php

namespace App\Services;
 
use Illuminate\Support\Collection;

class DecimalNumberFormat
{
    /**
     * Convert a decimal number to its integer representation,
     * removing the decimal part if it is zero.
     * 
     * Examples:
     *   200.0000 -> 200
     *   123.4500 -> 123.45
     *   '600.00' -> 600
     *   12       -> 12
     * 
     * @param mixed $number
     * @return string
     */
    public function removeTrailingDecimalZeros($number): string
    {
        // Convert to string in case it's numeric
        $str = (string)$number;
        // Remove unnecessary trailing zeros after the decimal
        if (strpos($str, '.') !== false) {
            // Remove trailing zeros and decimal if needed
            $str = rtrim($str, '0');
            $str = rtrim($str, '.');
        }
        return $str;
    }
}
