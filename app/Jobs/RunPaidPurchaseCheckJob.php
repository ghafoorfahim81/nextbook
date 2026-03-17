<?php

namespace App\Jobs;

use App\Services\NotificationService;

class RunPaidPurchaseCheckJob
{
    public function handle(NotificationService $notificationService): void
    {
        $notificationService->runPaidPurchaseCheck();
    }
}
