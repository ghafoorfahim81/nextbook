<?php

namespace App\Jobs;

use App\Services\NotificationService;

class SendDailyTransactionSummaryJob
{
    public function handle(NotificationService $notificationService): void
    {
        $notificationService->sendDailyTransactionSummaries();
    }
}
