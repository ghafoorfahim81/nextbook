<?php

namespace App\Jobs;

use App\Services\DeletedRecordService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunDeletedRecordsCleanupJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 300;

    public int $timeout = 600;

    public function handle(DeletedRecordService $deletedRecordService): void
    {
        $deletedRecordService->cleanupExpired();
    }
}
