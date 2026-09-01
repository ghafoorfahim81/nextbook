<?php

use App\Jobs\PruneNotificationsJob;
use App\Jobs\RunExpiryCheckJob;
use App\Jobs\RunLowBalanceCheckJob;
use App\Jobs\RunDeletedRecordsCleanupJob;
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

/*
|--------------------------------------------------------------------------
| Notification checks
|--------------------------------------------------------------------------
|
| These were previously invoked with Schedule::call(), which ran each check
| synchronously inside the schedule:run process: a single exception aborted
| the tick, a slow branch blocked every later task, and nothing retried.
| Schedule::job() pushes them onto the queue instead.
|
| REQUIRES a queue worker (`php artisan queue:work`). Without one, nothing
| below executes — and notification emails, which are now queued too, will
| also sit unsent.
|
*/

Schedule::job(new RunLowBalanceCheckJob)
    ->name('notifications:low-balance')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::job(new RunDeletedRecordsCleanupJob)
    ->name('records:deleted-cleanup')
    ->dailyAt('00:00')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::job(new RunLowStockCheckJob)
    ->name('notifications:low-stock')
    ->dailyAt('07:05')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::job(new RunExpiryCheckJob)
    ->name('notifications:expiry')
    ->dailyAt('14:05')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::job(new RunOverdueChecksJob)
    ->name('notifications:overdue')
    ->dailyAt('07:15')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::job(new RunPaidSaleCheckJob)
    ->name('notifications:paid-sale')
    ->dailyAt('07:20')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::job(new RunPaidPurchaseCheckJob)
    ->name('notifications:paid-purchase')
    ->dailyAt('07:25')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::job(new SendDailyTransactionSummaryJob)
    ->name('notifications:daily-summary')
    ->dailyAt('18:00')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::job(new SendWeeklyFinancialSummaryJob)
    ->name('notifications:weekly-summary')
    ->weeklyOn(6, '18:00')
    ->withoutOverlapping()
    ->onOneServer();

// Nothing used to remove old notifications, so the table grew forever and the
// 'forever' dedupe window scanned all of it on every check.
Schedule::job(new PruneNotificationsJob)
    ->name('notifications:prune')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->onOneServer();

// HR compliance. Runs after the finance checks so a busy 07:00 slot does not
// delay them; the reminders themselves dedupe per day, so an occasional
// overlap is harmless.
Schedule::command('hr:reminders')
    ->name('notifications:hr-reminders')
    ->dailyAt('07:30')
    ->withoutOverlapping()
    ->onOneServer();
