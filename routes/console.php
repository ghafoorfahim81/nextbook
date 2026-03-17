<?php

use App\Jobs\RunExpiryCheckJob;
use App\Jobs\RunLowBalanceCheckJob;
use App\Jobs\RunLowStockCheckJob;
use App\Jobs\RunOverdueChecksJob;
use App\Jobs\RunPaidPurchaseCheckJob;
use App\Jobs\RunPaidSaleCheckJob;
use App\Jobs\SendDailyTransactionSummaryJob;
use App\Jobs\SendWeeklyFinancialSummaryJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::call(fn () => app()->call([app(RunLowBalanceCheckJob::class), 'handle']))
    ->name('notifications:low-balance')
    ->dailyAt('07:00');

Schedule::call(fn () => app()->call([app(RunLowStockCheckJob::class), 'handle']))
    ->name('notifications:low-stock')
    ->dailyAt('07:05');

Schedule::call(fn () => app()->call([app(RunExpiryCheckJob::class), 'handle']))
    ->name('notifications:expiry')
    ->dailyAt('14:05');

Schedule::call(fn () => app()->call([app(RunOverdueChecksJob::class), 'handle']))
    ->name('notifications:overdue')
    ->dailyAt('07:15');

Schedule::call(fn () => app()->call([app(RunPaidSaleCheckJob::class), 'handle']))
    ->name('notifications:paid-sale')
    ->dailyAt('07:20');

Schedule::call(fn () => app()->call([app(RunPaidPurchaseCheckJob::class), 'handle']))
    ->name('notifications:paid-purchase')
    ->dailyAt('07:25');

Schedule::call(fn () => app()->call([app(SendDailyTransactionSummaryJob::class), 'handle']))
    ->name('notifications:daily-summary')
    ->dailyAt('18:00');

Schedule::call(fn () => app()->call([app(SendWeeklyFinancialSummaryJob::class), 'handle']))
    ->name('notifications:weekly-summary')
    ->weeklyOn(6, '18:00');
