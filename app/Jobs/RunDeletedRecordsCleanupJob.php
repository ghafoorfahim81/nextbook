<?php

namespace App\Jobs;

use App\Services\DeletedRecordService;

class RunDeletedRecordsCleanupJob
{
    public function handle(DeletedRecordService $deletedRecordService): void
    {
        $deletedRecordService->cleanupExpired();
    }
}
