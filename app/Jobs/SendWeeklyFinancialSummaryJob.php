<?php

namespace App\Jobs;

use App\Services\NotificationService;

class SendWeeklyFinancialSummaryJob
{
    public function handle(NotificationService $notificationService): void
    {
        $notificationService->sendWeeklyFinancialSummaries();
    }
}
