<?php
// app/Services/DateConversionService.php

namespace App\Services;

use Carbon\Carbon;
use Morilog\Jalali\Jalalian;
use Illuminate\Support\Collection;

class DateConversionService
{
    private $calendarType;
    private $conversionCache = [];

    public function __construct()
    {
        $this->calendarType = $this->getCompanyCalendarType();
    }

    /**
     * Convert single date to Gregorian for storage
     */
    public function toGregorian(string $date): string
    {
        $cacheKey = "toGregorian:{$date}";

        if (isset($this->conversionCache[$cacheKey])) {
            return $this->conversionCache[$cacheKey];
        }

        // Clean the input date - remove time portion if present
        $cleanedDate = $this->extractDatePart($date);

        // Detect if the incoming date looks like a Jalali date (year 13xx–14xx)
        $normalized = str_replace('/', '-', trim($cleanedDate));
        $isJalaliInput = false;

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $normalized, $matches)) {
            $year = (int) $matches[1];
            $isJalaliInput = $year >= 1300 && $year <= 1499;
        }

        // Convert when:
        // - company calendar is explicitly Jalali, OR
        // - the incoming value clearly looks like a Jalali year
        $result = ($this->calendarType === 'jalali' || $isJalaliInput)
            ? $this->jalaliToGregorian($cleanedDate)
            : $this->formatGregorianDate($cleanedDate); // Format Gregorian dates too

        $this->conversionCache[$cacheKey] = $result;
        return $result;
    }

    /**
     * Extract just the date part from a datetime string
     */
    private function extractDatePart(string $dateTime): string
    {
        // If it contains 'T' or has time portion, extract just the date
        if (strpos($dateTime, 'T') !== false) {
            $parts = explode('T', $dateTime);
            return $parts[0];
        }

        // If it has time portion with space separator
        if (strpos($dateTime, ' ') !== false) {
            $parts = explode(' ', $dateTime);
            return $parts[0];
        }

        // Already just a date
        return $dateTime;
    }

    /**
     * Format a Gregorian date to Y-m-d
     */
    private function formatGregorianDate(string $date): string
    {
        try {
            // Parse and format to ensure consistent Y-m-d format
            return Carbon::parse($date)->format('Y-m-d');
        } catch (\Exception $e) {
            // If parsing fails, try to extract date part
            $datePart = $this->extractDatePart($date);
            return $datePart;
        }
    }

    /**
     * Handle Jalali to Gregorian conversion with proper formatting
     */
    private function jalaliToGregorian(string $jalaliDate): string
    {
        // Normalize the date format - handle both 1404/08/18 and 1404-08-18
        $normalizedDate = str_replace('/', '-', $jalaliDate);

        try {
            // Parse the normalized date (always in Y-m-d format with dashes)
            return Jalalian::fromFormat('Y-m-d', $normalizedDate)
                ->toCarbon()
                ->format('Y-m-d');
        } catch (\Exception $e) {
            // If parsing fails, try alternative formats
            try {
                // Try parsing as Y/m/d format directly
                return Jalalian::fromFormat('Y/m/d', $jalaliDate)
                    ->toCarbon()
                    ->format('Y-m-d');
            } catch (\Exception $e2) {
                // Last resort: return original date (formatted)
                return $this->formatGregorianDate($jalaliDate);
            }
        }
    }

    /**
     * Convert single Gregorian date to display format
     */
    public function toDisplay(string $gregorianDate): string
    {
        $cacheKey = "toDisplay:{$gregorianDate}";

        if (isset($this->conversionCache[$cacheKey])) {
            return $this->conversionCache[$cacheKey];
        }

        // Always format Gregorian dates to Y-m-d first
        $formattedDate = $this->formatGregorianDate($gregorianDate);

        $result = ($this->calendarType === 'jalali')
            ? $this->gregorianToJalali($formattedDate) // Use custom method
            : $formattedDate; // Return formatted Gregorian date

        $this->conversionCache[$cacheKey] = $result;
        return $result;
    }

    /**
     * Handle Gregorian to Jalali conversion
     */
    private function gregorianToJalali(string $gregorianDate): string
    {
        try {
            // First ensure the input is properly formatted
            $formattedDate = $this->formatGregorianDate($gregorianDate);

            return Jalalian::fromCarbon(Carbon::parse($formattedDate))
                ->format('Y-m-d'); // Return with dashes for consistency
        } catch (\Exception $e) {
            return $gregorianDate; // Fallback to original
        }
    }

    /**
     * Batch convert multiple dates for display (HIGH PERFORMANCE)
     */
    public function batchToDisplay(array $gregorianDates): array
    {
        $results = [];

        foreach ($gregorianDates as $key => $date) {
            $results[$key] = $this->toDisplay($date);
        }

        return $results;
    }

    /**
     * Convert entire collection of models (OPTIMAL FOR LISTS)
     */
    public function convertCollection(Collection $models, string $dateField = 'date'): Collection
    {
        if ($this->calendarType === 'gregorian') {
            // Even for Gregorian, we should format dates consistently
            return $models->map(function ($model) use ($dateField) {
                if (isset($model->{$dateField})) {
                    $model->display_date = $this->formatGregorianDate($model->{$dateField});
                }
                return $model;
            });
        }

        // For Jalali calendar, convert dates
        return $models->map(function ($model) use ($dateField) {
            if (isset($model->{$dateField})) {
                $model->display_date = $this->toDisplay($model->{$dateField});
            }
            return $model;
        });
    }

    /**
     * Get display format for dates (useful for forms, etc.)
     */
    public function getDisplayFormat(): string
    {
        return $this->calendarType === 'jalali' ? 'Y-m-d' : 'Y-m-d';
    }

    /**
     * Get storage format for dates (always Y-m-d)
     */
    public function getStorageFormat(): string
    {
        return 'Y-m-d';
    }

    private function getCompanyCalendarType(): string
    {
        // Your company detection logic
        return auth()->check() && auth()->user()->company?->calendar_type
            ? auth()->user()->company?->calendar_type
            : 'gregorian';
    }
}
