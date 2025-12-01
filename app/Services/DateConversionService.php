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

        // Detect if the incoming date looks like a Jalali date (year 13xx–14xx)
        $normalized = str_replace('/', '-', trim($date));
        $isJalaliInput = false;
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $normalized, $matches)) {
            $year = (int) $matches[1];
            $isJalaliInput = $year >= 1300 && $year <= 1499;
        }

        // Convert when:
        // - company calendar is explicitly Jalali, OR
        // - the incoming value clearly looks like a Jalali year
        $result = ($this->calendarType === 'jalali' || $isJalaliInput)
            ? $this->jalaliToGregorian($date)
            : $date;

        $this->conversionCache[$cacheKey] = $result;
        return $result;
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
                // Last resort: return original date
                return $jalaliDate;
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

        $result = ($this->calendarType === 'jalali')
            ? $this->gregorianToJalali($gregorianDate) // Use custom method
            : $gregorianDate;

        $this->conversionCache[$cacheKey] = $result;
        return $result;
    }

    /**
     * Handle Gregorian to Jalali conversion
     */
    private function gregorianToJalali(string $gregorianDate): string
    {
        try {
            return Jalalian::fromCarbon(Carbon::parse($gregorianDate))
                ->format('Y-m-d'); // Return with slashes for display
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
            return $models; // No conversion needed
        }

        // Batch convert all dates at once
        $dates = $models->pluck($dateField)->toArray();
        $convertedDates = $this->batchToDisplay($dates);

        // Map back to models
        return $models->map(function ($model, $index) use ($convertedDates, $dateField) {
            $model->display_date = $convertedDates[$index];
            return $model;
        });
    }

    private function getCompanyCalendarType(): string
    {
        // Your company detection logic
        return auth()->check() && auth()->user()->company?->calendar_type
            ? auth()->user()->company?->calendar_type
            : 'gregorian';
    }
}
