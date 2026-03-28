<?php

namespace App\Jobs;

use App\Services\NotificationService;

class RunLowBalanceCheckJob
{
    public function handle(NotificationService $notificationService): void
    {
        $notificationService->runLowBalanceCheck();
    }
}
