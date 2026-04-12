<?php

namespace App\Services;

use App\Models\ActivityLog;
use DateTimeInterface;
use Illuminate\Database\ClassMorphViolationException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use JsonSerializable;
use UnitEnum;

class ActivityLogService
{
    /**
     * Persist a business activity log record.
     */
    public function log(array $data): ActivityLog
    {
        $reference = $this->resolveReference(
            $data['reference'] ?? null,
            $data['reference_type'] ?? null,
            $data['reference_id'] ?? null,
        );

        $payload = [
            'event_type' => (string) ($data['event_type'] ?? throw new \InvalidArgumentException('The [event_type] field is required.')),
            'module' => $this->resolveModule($data['module'] ?? null, $reference['type']),
            'reference_type' => $reference['type'],
            'reference_id' => $reference['id'],
            'user_id' => $this->resolveUserId($data['user_id'] ?? null),
            'branch_id' => $this->resolveBranchId(
                explicitBranchId: $data['branch_id'] ?? null,
                referenceBranchId: $reference['branch_id'],
            ),
            'ip_address' => $this->resolveIpAddress($data['ip_address'] ?? null),
            'user_agent' => $this->resolveUserAgent($data['user_agent'] ?? null),
            'description' => $data['description'] ?? null,
            'old_values' => $this->normalizePayloadArray($data['old_values'] ?? null),
            'new_values' => $this->normalizePayloadArray($data['new_values'] ?? null),
            'metadata' => $this->normalizePayloadArray($data['metadata'] ?? null),
            'created_at' => $data['created_at'] ?? now(),
        ];

        return ActivityLog::create($payload);
    }

    /**
     * Log a create event.
     */
    public function logCreate(
        Model|string $reference,
        ?string $module = null,
        ?string $description = null,
        ?array $newValues = null,
        array $metadata = [],
        ?string $branchId = null,
        string $eventType = 'created',
    ): ActivityLog {
        return $this->log([
            'event_type' => $eventType,
            'module' => $module,
            'reference' => $reference,
            'branch_id' => $branchId,
            'description' => $description,
            'new_values' => $newValues ?? $this->snapshot($reference),
            'metadata' => $metadata,
        ]);
    }

    /**
     * Log an update event, storing only changed fields.
     */
    public function logUpdate(
        Model|string $reference,
        array $before,
        array $after,
        ?string $module = null,
        ?string $description = null,
        array $metadata = [],
        ?string $branchId = null,
        array $only = [],
        array $except = [],
        string $eventType = 'updated',
    ): ?ActivityLog {
        $changes = $this->diff($before, $after, $only, $except);

        if ($changes['old_values'] === null && $changes['new_values'] === null) {
            return null;
        }

        return $this->log([
            'event_type' => $eventType,
            'module' => $module,
            'reference' => $reference,
            'branch_id' => $branchId,
            'description' => $description,
            'old_values' => $changes['old_values'],
            'new_values' => $changes['new_values'],
            'metadata' => $metadata,
        ]);
    }

    /**
     * Log a delete event.
     */
    public function logDelete(
        Model|string $reference,
        ?string $module = null,
        ?string $description = null,
        ?array $oldValues = null,
        array $metadata = [],
        ?string $branchId = null,
        string $eventType = 'deleted',
    ): ActivityLog {
        return $this->log([
            'event_type' => $eventType,
            'module' => $module,
            'reference' => $reference,
            'branch_id' => $branchId,
            'description' => $description,
            'old_values' => $oldValues ?? $this->snapshot($reference),
            'metadata' => $metadata,
        ]);
    }

    /**
     * Log a business action such as posting, approval, printing, export, or login.
     */
    public function logAction(
        string $eventType,
        Model|string|null $reference = null,
        ?string $module = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        array $metadata = [],
        ?string $branchId = null,
        ?string $referenceId = null,
    ): ActivityLog {
        return $this->log([
            'event_type' => $eventType,
            'module' => $module,
            'reference' => $reference,
            'reference_id' => $referenceId,
            'branch_id' => $branchId,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Create a model attribute snapshot for create/delete logs.
     */
    public function snapshot(Model|string $reference, array $only = [], array $except = []): ?array
    {
        if (! $reference instanceof Model) {
            return null;
        }

        $attributes = $this->normalizeValue($reference->attributesToArray());

        if ($only !== []) {
            $attributes = Arr::only($attributes, $only);
        }

        if ($except !== []) {
            $attributes = Arr::except($attributes, $except);
        }

        return $attributes === [] ? null : $attributes;
    }

    /**
     * Compute an old/new diff using only changed fields.
     *
     * @return array{old_values:?array,new_values:?array}
     */
    public function diff(array $before, array $after, array $only = [], array $except = []): array
    {
        $before = $this->normalizeArray($before);
        $after = $this->normalizeArray($after);

        if ($only !== []) {
            $before = Arr::only($before, $only);
            $after = Arr::only($after, $only);
        }

        if ($except !== []) {
            $before = Arr::except($before, $except);
            $after = Arr::except($after, $except);
        }

        $changedKeys = [];

        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $key) {
            if ($this->valuesDiffer($before[$key] ?? null, $after[$key] ?? null)) {
                $changedKeys[] = $key;
            }
        }

        if ($changedKeys === []) {
            return ['old_values' => null, 'new_values' => null];
        }

        return [
            'old_values' => Arr::only($before, $changedKeys),
            'new_values' => Arr::only($after, $changedKeys),
        ];
    }

    protected function resolveReference(Model|string|null $reference, ?string $referenceType = null, ?string $referenceId = null): array
    {
        if ($reference instanceof Model) {
            return [
                'type' => $this->referenceType($reference),
                'id' => (string) $reference->getKey(),
                'branch_id' => $this->stringOrNull($reference->getAttribute('branch_id')),
            ];
        }

        if (is_string($reference) && class_exists($reference) && is_subclass_of($reference, Model::class)) {
            return [
                'type' => $this->referenceType($reference),
                'id' => $referenceId,
                'branch_id' => null,
            ];
        }

        return [
            'type' => $referenceType ?? $reference,
            'id' => $referenceId,
            'branch_id' => null,
        ];
    }

    public function referenceType(Model|string $reference): string
    {
        if ($reference instanceof Model) {
            return $this->safeMorphType($reference);
        }

        if (class_exists($reference) && is_subclass_of($reference, Model::class)) {
            /** @var \Illuminate\Database\Eloquent\Model $instance */
            $instance = new $reference();

            return $this->safeMorphType($instance);
        }

        return (string) $reference;
    }

    protected function resolveModule(?string $module, string|null $referenceType): string
    {
        if ($module) {
            return $module;
        }

        if ($referenceType) {
            return Str::snake(class_basename(str_replace('\\', '/', $referenceType)));
        }

        throw new \InvalidArgumentException('The [module] field is required when no reference is provided.');
    }

    protected function safeMorphType(Model $model): string
    {
        try {
            return $model->getMorphClass();
        } catch (ClassMorphViolationException) {
            return $model::class;
        }
    }

    protected function resolveUserId(?string $userId): ?string
    {
        return $userId ?? $this->stringOrNull(Auth::id());
    }

    protected function resolveBranchId(?string $explicitBranchId, ?string $referenceBranchId): ?string
    {
        if ($explicitBranchId) {
            return $explicitBranchId;
        }

        if ($referenceBranchId) {
            return $referenceBranchId;
        }

        if (app()->bound('active_branch_id')) {
            return $this->stringOrNull(app('active_branch_id'));
        }

        return $this->stringOrNull(Auth::user()?->branch_id);
    }

    protected function resolveIpAddress(?string $ipAddress): ?string
    {
        if ($ipAddress) {
            return $ipAddress;
        }

        return $this->request()?->ip();
    }

    protected function resolveUserAgent(?string $userAgent): ?string
    {
        if ($userAgent) {
            return $userAgent;
        }

        return $this->request()?->userAgent();
    }

    protected function request(): ?Request
    {
        return app()->bound('request') ? app(Request::class) : null;
    }

    protected function normalizePayloadArray(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        $normalized = $this->normalizeValue($value);

        if (! is_array($normalized) || $normalized === []) {
            return null;
        }

        return $normalized;
    }

    protected function normalizeArray(array $values): array
    {
        $normalized = $this->normalizeValue($values);

        return is_array($normalized) ? $normalized : [];
    }

    protected function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof Model) {
            return $value->attributesToArray();
        }

        if ($value instanceof Arrayable) {
            return $this->normalizeValue($value->toArray());
        }

        if ($value instanceof JsonSerializable) {
            return $this->normalizeValue($value->jsonSerialize());
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if ($value instanceof UnitEnum) {
            return $value instanceof \BackedEnum ? $value->value : $value->name;
        }

        if (is_array($value)) {
            $normalized = [];

            foreach ($value as $key => $item) {
                $normalized[$key] = $this->normalizeValue($item);
            }

            return $normalized;
        }

        return $value;
    }

    protected function valuesDiffer(mixed $before, mixed $after): bool
    {
        return json_encode($before) !== json_encode($after);
    }

    protected function stringOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
