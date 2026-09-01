<?php

namespace App\Jobs;

use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PruneNotificationsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 300;

    public int $timeout = 600;

    public function handle(NotificationService $notificationService): void
    {
        $notificationService->pruneOldNotifications();
    }
}
