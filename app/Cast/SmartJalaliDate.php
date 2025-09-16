<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Carbon\Carbon;
use Morilog\Jalali\Jalalian;

class SmartJalaliDate implements CastsAttributes
{
    /**
     * Common formats we’ll try for string inputs (date only + date-time).
     * You can extend these based on your forms.
     */
    protected array $formats = [
        'Y/m/d',
        'Y-m-d',
        'Y/m/d H:i',
        'Y-m-d H:i',
        'Y/m/d H:i:s',
        'Y-m-d H:i:s',
    ];

    public function get($model, string $key, $value, array $attributes)
    {
        // Return a Carbon instance (DB is Gregorian). Let Resources/Frontend format for UI.
        return $value ? Carbon::parse($value) : null;
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if (empty($value)) {
            return null;
        }

        // Already Carbon/DateTime? Assume Gregorian.
        if ($value instanceof \DateTimeInterface) {
            return Carbon::parse($value);
        }

        // Normalize digits (convert Persian/Arabic numerals to Latin)
        $value = $this->normalizeDigits((string) $value);

        // Fast-path ISO-8601 → Gregorian
        if ($this->looksIso8601($value)) {
            return Carbon::parse($value);
        }

        // Heuristic: year range suggests Jalali (1300–1499)
        if ($this->yearSuggestsJalali($value)) {
            if ($c = $this->tryParseJalali($value)) return $c;
        }

        // Try Jalali first (for common forms)
        if ($c = $this->tryParseJalali($value)) {
            return $c;
        }

        // Fallback: Gregorian
        return Carbon::parse($value);
    }

    // ----------------- helpers -----------------

    protected function tryParseJalali(string $value): ?Carbon
    {
        foreach ($this->formats as $fmt) {
            try {
                // Jalalian::fromFormat throws on mismatch
                return Jalalian::fromFormat($fmt, $value)->toCarbon();
            } catch (\Throwable $e) {
                // keep trying
            }
        }
        return null;
    }

    protected function looksIso8601(string $v): bool
    {
        // Very permissive check for 2025-09-16 or 2025-09-16T12:34:56Z
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+\-]\d{2}:\d{2})?)?$/', $v);
    }

    protected function yearSuggestsJalali(string $v): bool
    {
        // Extract first 4-digit year number after normalizing digits
        if (preg_match('/\b(\d{4})\b/u', $v, $m)) {
            $y = (int) $m[1];
            return $y >= 1300 && $y <= 1499;
        }
        return false;
    }

    protected function normalizeDigits(string $v): string
    {
        // Persian ۰-۹ \x{06F0}-\x{06F9}, Arabic ٠-٩ \x{0660}-\x{0669}
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $latin   = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $v = str_replace($persian, $latin, $v);
        $v = str_replace($arabic,  $latin, $v);
        return trim($v);
    }
}
