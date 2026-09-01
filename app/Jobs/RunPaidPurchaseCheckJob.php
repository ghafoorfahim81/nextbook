<?php

namespace App\Jobs;

use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Retrying is safe: every notification carries a dedupe key, so rows already
 * written before a failure are not recreated and their emails are not resent.
 */
class RunPaidPurchaseCheckJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 300;

    public int $timeout = 600;

    public function handle(NotificationService $notificationService): void
    {
        $notificationService->runPaidPurchaseCheck();
    }
}
