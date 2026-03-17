<?php

namespace App\Jobs;

use App\Services\NotificationService;

class RunExpiryCheckJob
{
    public function handle(NotificationService $notificationService): void
    {
        $notificationService->runExpiryCheck();
    }
}
