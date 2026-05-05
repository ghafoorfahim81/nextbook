<?php

namespace App\Traits;

use App\Models\ActivityLog;
use App\Services\ActivityLogService;
use Illuminate\Database\Eloquent\Model;

trait LogsActivity
{
    protected function activityLogs(): ActivityLogService
    {
        return app(ActivityLogService::class);
    }

    protected function logModelCreated(
        string $module,
        Model|string $reference,
        ?string $description = null,
        ?array $newValues = null,
        array $metadata = [],
        ?string $branchId = null,
        string $eventType = 'created',
    ): ActivityLog {
        return $this->activityLogs()->logCreate(
            reference: $reference,
            module: $module,
            description: $description,
            newValues: $newValues,
            metadata: $metadata,
            branchId: $branchId,
            eventType: $eventType,
        );
    }

    protected function logModelUpdated(
        string $module,
        Model|string $reference,
        array $before,
        array $after,
        ?string $description = null,
        array $metadata = [],
        ?string $branchId = null,
        array $only = [],
        array $except = [],
        string $eventType = 'updated',
    ): ?ActivityLog {
        return $this->activityLogs()->logUpdate(
            reference: $reference,
            before: $before,
            after: $after,
            module: $module,
            description: $description,
            metadata: $metadata,
            branchId: $branchId,
            only: $only,
            except: $except,
            eventType: $eventType,
        );
    }

    protected function logModelDeleted(
        string $module,
        Model|string $reference,
        ?string $description = null,
        ?array $oldValues = null,
        array $metadata = [],
        ?string $branchId = null,
        string $eventType = 'deleted',
    ): ActivityLog {
        return $this->activityLogs()->logDelete(
            reference: $reference,
            module: $module,
            description: $description,
            oldValues: $oldValues,
            metadata: $metadata,
            branchId: $branchId,
            eventType: $eventType,
        );
    }

    protected function logBusinessAction(
        string $eventType,
        ?string $module,
        Model|string|null $reference = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        array $metadata = [],
        ?string $branchId = null,
        ?string $referenceId = null,
    ): ActivityLog {
        return $this->activityLogs()->logAction(
            eventType: $eventType,
            reference: $reference,
            module: $module,
            description: $description,
            oldValues: $oldValues,
            newValues: $newValues,
            metadata: $metadata,
            branchId: $branchId,
            referenceId: $referenceId,
        );
    }
}
