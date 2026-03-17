<?php

namespace App\Jobs;

use App\Services\NotificationService;

class RunOverdueChecksJob
{
    public function handle(NotificationService $notificationService): void
    {
        $notificationService->runOverdueChecks();
    }
}
