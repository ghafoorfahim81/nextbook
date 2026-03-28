<?php

namespace App\Jobs;

use App\Services\NotificationService;

class RunLowStockCheckJob
{
    public function handle(NotificationService $notificationService): void
    {
        $notificationService->runLowStockCheck();
    }
}
