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

        $result = ($this->calendarType === 'jalali')
            ? Jalalian::fromFormat('Y-m-d', $date)->toCarbon()->format('Y-m-d')
            : $date;

        $this->conversionCache[$cacheKey] = $result;
        return $result;
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
            ? Jalalian::fromCarbon(Carbon::parse($gregorianDate))->format('Y-m-d')
            : $gregorianDate;

        $this->conversionCache[$cacheKey] = $result;
        return $result;
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
        return auth()->check() && auth()->user()->company
            ? auth()->user()->company->calendar_type
            : 'gregorian';
    }
}
