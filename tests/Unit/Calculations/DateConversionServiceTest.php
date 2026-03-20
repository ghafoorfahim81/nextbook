<?php

namespace Tests\Unit\Calculations;

use App\Enums\CalendarType;
use App\Services\DateConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

class DateConversionServiceTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $ctx = $this->bootstrapErpContext();
        $ctx['company']->update(['calendar_type' => CalendarType::GREGORIAN->value]);
    }

    public function test_it_formats_gregorian_dates_for_storage(): void
    {
        $service = app(DateConversionService::class);

        $this->assertEquals('2026-03-19', $service->toGregorian('2026-03-19T12:44:00'));
        $this->assertEquals('2026-03-19', $service->toDisplay('2026-03-19'));
    }

    public function test_it_converts_jalali_like_dates_based_on_year_detection(): void
    {
        $service = app(DateConversionService::class);

        $gregorian = $service->toGregorian('1404-01-01');

        $this->assertTrue((bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $gregorian));
        $this->assertTrue($gregorian !== '1404-01-01');
    }

    public function test_to_display_returns_null_for_missing_date(): void
    {
        $service = app(DateConversionService::class);

        $this->assertEquals(null, $service->toDisplay(null));
        $this->assertEquals(null, $service->toDisplay(''));
    }
}
