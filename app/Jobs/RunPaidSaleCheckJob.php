<?php

namespace App\Jobs;

use App\Services\NotificationService;

class RunPaidSaleCheckJob
{
    public function handle(NotificationService $notificationService): void
    {
        $notificationService->runPaidSaleCheck();
    }
}
