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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use JsonSerializable;
use UnitEnum;

class ActivityLogService
{
    /**
     * Entries collected during the current request, or null when not batching.
     *
     * One user action saves many rows — a transfer writes the transfer and its
     * transaction, an item writes itself, its variants and its opening stock —
     * and each row used to become its own log entry. Inside a request the
     * entries are collected here and written as a single log when the request
     * ends (see flushBatch), with the other rows attached as related records.
     *
     * @var array<int, array>|null
     */
    protected ?array $batch = null;

    public function beginBatch(): void
    {
        $this->batch = [];
    }

    public function isBatching(): bool
    {
        return $this->batch !== null;
    }

    /**
     * Write the collected entries as one log row and stop batching.
     *
     * @param  Model|null  $routeModel  The record the request's URL points at
     *                                  (/items/{item}), used as the headline
     *                                  when only its children changed.
     */
    public function flushBatch(?string $routeName = null, ?Model $routeModel = null, ?array $before = null): void
    {
        $entries = $this->batch ?? [];
        $this->batch = null;

        // What the record's form (opening, lines, variants…) looked like
        // before and after, for the parts that changed.
        $changes = null;
        $coveredClasses = [];
        if ($before !== null && $routeModel) {
            $after = $this->relationSnapshot($routeModel->fresh() ?? $routeModel);
            $coveredClasses = $before['classes'];
            $changes = $this->diffSnapshots($before['data'], $after['data'] ?? []);
        }

        if ($entries === [] && ! $changes) {
            return;
        }

        $payload = $this->mergeEntries($entries, $routeName, $routeModel, $coveredClasses);

        if ($changes) {
            if ($payload === null) {
                $payload = $this->buildPayload([
                    'event_type' => $this->routeEvent($routeName),
                    'reference' => $routeModel,
                    'metadata' => ['source' => 'request'],
                ]);
            }

            $payload['old_values'] = array_merge($payload['old_values'] ?? [], $changes['old']) ?: null;
            $payload['new_values'] = array_merge($payload['new_values'] ?? [], $changes['new']) ?: null;
        }

        if ($payload !== null) {
            ActivityLog::create($payload);
        }
    }

    /**
     * The record's configured child data (activity_log.snapshot_relations) as
     * plain arrays, plus the model classes it covers. Null when the model has
     * nothing configured.
     *
     * @return array{data: array, classes: array<int, string>}|null
     */
    public function relationSnapshot(Model $model): ?array
    {
        $paths = config('activity_log.snapshot_relations.' . $model::class);
        if (! $paths) {
            return null;
        }

        $tree = [];
        foreach ($paths as $path) {
            Arr::set($tree, $path, []);
        }

        try {
            $model->load($paths);
        } catch (\Throwable $exception) {
            report($exception);

            return null;
        }

        $data = [];
        $classes = [];
        foreach ($tree as $relation => $children) {
            $data[$relation] = $this->serializeRelation($model, $relation, $children, $classes);
        }

        return ['data' => $data, 'classes' => array_values(array_unique($classes))];
    }

    protected function serializeRelation(Model $parent, string $relation, array $children, array &$classes): mixed
    {
        try {
            $classes[] = $parent->{$relation}()->getRelated()::class;
        } catch (\Throwable) {
            // Not a relation after all; the loaded value is still serialised.
        }

        $value = $parent->getRelation($relation);

        if ($value instanceof \Illuminate\Support\Collection) {
            return $value->map(fn (Model $row) => $this->serializeModel($row, $children, $parent, $classes))->values()->all();
        }

        return $value instanceof Model ? $this->serializeModel($value, $children, $parent, $classes) : null;
    }

    protected function serializeModel(Model $model, array $children, Model $parent, array &$classes): array
    {
        $noise = ['id', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by', 'deleted_by',
            'branch_id', 'company_id', 'posting_payload', 'slug', 'reference_type', 'reference_id', 'transaction_id',
            // Generated translations of the remark, not something anyone typed.
            'remark_fa', 'remark_ps', 'remark_en'];

        $attributes = Arr::except($this->normalizeValue($model->attributesToArray()), $noise);

        // Polymorphic plumbing (ledgerable_type / ledgerable_id).
        $attributes = array_filter(
            $attributes,
            fn ($key) => ! preg_match('/able_(type|id)$/', (string) $key),
            ARRAY_FILTER_USE_KEY,
        );

        // Drop the key pointing back at the parent (sale_id on a sale line).
        $parentKey = (string) $parent->getKey();
        $attributes = array_filter(
            $attributes,
            fn ($value, $key) => ! (Str::endsWith((string) $key, '_id') && (string) $value === $parentKey),
            ARRAY_FILTER_USE_BOTH,
        );

        foreach ($children as $relation => $grandChildren) {
            $attributes[$relation] = $this->serializeRelation($model, $relation, $grandChildren, $classes);
        }

        return $attributes;
    }

    /** @return array{old: array, new: array}|null */
    protected function diffSnapshots(array $before, array $after): ?array
    {
        $old = [];
        $new = [];

        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $key) {
            if ($this->valuesDiffer($before[$key] ?? null, $after[$key] ?? null)) {
                $old[$key] = $before[$key] ?? null;
                $new[$key] = $after[$key] ?? null;
            }
        }

        return $old === [] && $new === [] ? null : ['old' => $old, 'new' => $new];
    }

    /**
     * Persist a business activity log record.
     */
    public function log(array $data): ActivityLog
    {
        $payload = $this->buildPayload($data);

        if ($this->isBatching()) {
            // Only work that actually commits belongs in the audit trail. Run
            // straight away outside a transaction; dropped if it rolls back.
            DB::afterCommit(function () use ($payload) {
                if ($this->batch !== null) {
                    $this->batch[] = $payload;
                } else {
                    ActivityLog::create($payload);
                }
            });

            return new ActivityLog($payload);
        }

        return ActivityLog::create($payload);
    }

    /**
     * Fold one request's entries into a single log payload.
     */
    protected function mergeEntries(array $entries, ?string $routeName, ?Model $routeModel, array $coveredClasses = []): ?array
    {
        // Rows the before/after snapshot already shows (the opening's
        // transaction, a sale's lines) would only repeat it as "created" and
        // "deleted" entries.
        if ($coveredClasses !== []) {
            $entries = array_values(array_filter($entries, function (array $entry) use ($coveredClasses) {
                $class = $entry['reference_type']
                    ? (\Illuminate\Database\Eloquent\Relations\Relation::getMorphedModel($entry['reference_type']) ?? $entry['reference_type'])
                    : null;

                return ! in_array($class, $coveredClasses, true);
            }));

            if ($entries === []) {
                return null;
            }
        }

        // Same record logged more than once (created, then updated while its
        // variants were saved): keep one entry per record, oldest "before"
        // values and newest "after" values.
        $byReference = [];
        foreach ($entries as $entry) {
            $key = $this->referenceKey($entry);

            if (! isset($byReference[$key])) {
                $byReference[$key] = $entry;
                continue;
            }

            $byReference[$key] = $this->foldEntry($byReference[$key], $entry);
        }

        $entries = array_values($byReference);
        $primaryIndex = $this->primaryIndex($entries, $routeName, $routeModel);

        if ($primaryIndex === null) {
            // Nothing in the request is about the record in the URL — e.g. an
            // opening balance added while editing an account. Head the log with
            // that record so it reads as what the user did.
            $primary = $this->buildPayload([
                'event_type' => $this->routeEvent($routeName),
                'reference' => $routeModel,
                'metadata' => ['source' => 'request'],
            ]);
            $related = $entries;
        } else {
            $primary = $entries[$primaryIndex];
            $related = array_values(array_filter(
                $entries,
                fn ($_, $index) => $index !== $primaryIndex,
                ARRAY_FILTER_USE_BOTH,
            ));
        }

        if ($related !== []) {
            $metadata = $primary['metadata'] ?? [];
            $metadata['related'] = array_map(fn (array $entry) => [
                'event_type' => $entry['event_type'],
                'module' => $entry['module'],
                'reference_type' => $entry['reference_type'],
                'reference_id' => $entry['reference_id'],
                'description' => $entry['description'],
                'old_values' => $entry['old_values'],
                'new_values' => $entry['new_values'],
            ], $related);
            $primary['metadata'] = $metadata;
        }

        return $primary;
    }

    protected function referenceKey(array $entry): string
    {
        return $entry['reference_type'] && $entry['reference_id']
            ? $entry['reference_type'] . ':' . $entry['reference_id']
            : 'entry:' . spl_object_id((object) $entry) . ':' . md5(json_encode($entry));
    }

    protected function foldEntry(array $first, array $next): array
    {
        $isExplicit = fn (array $entry) => ($entry['metadata']['source'] ?? null) !== 'observer';

        // An explicit controller log ("posted", "printed") names the action
        // better than the observer's generic "updated"; a created record stays
        // "created" however many times it was saved afterwards.
        $base = $isExplicit($next) && ! $isExplicit($first) ? $next : $first;
        if ($first['event_type'] === 'created' && $base['event_type'] === 'updated') {
            $base['event_type'] = 'created';
        }

        $base['old_values'] = ($first['old_values'] ?? []) + ($next['old_values'] ?? []) ?: null;
        $base['new_values'] = array_merge($first['new_values'] ?? [], $next['new_values'] ?? []) ?: null;
        $base['description'] = $isExplicit($base) ? $base['description'] : ($first['description'] ?? $next['description']);
        $base['created_at'] = $first['created_at'];

        return $base;
    }

    /**
     * Which entry heads the log: the controller's explicit log, else the
     * record the route is about, else the first record saved. Null when the
     * route names a record that none of the entries is about.
     */
    protected function primaryIndex(array $entries, ?string $routeName, ?Model $routeModel): ?int
    {
        foreach ($entries as $index => $entry) {
            if (($entry['metadata']['source'] ?? null) !== 'observer') {
                return $index;
            }
        }

        if ($routeModel) {
            $type = $this->referenceType($routeModel);
            $id = (string) $routeModel->getKey();

            foreach ($entries as $index => $entry) {
                if ($entry['reference_type'] === $type && $entry['reference_id'] === $id) {
                    return $index;
                }
            }

            return null;
        }

        $routeModule = $this->routeModule($routeName);
        if ($routeModule) {
            foreach ($entries as $index => $entry) {
                if ($entry['module'] === $routeModule) {
                    return $index;
                }
            }
        }

        $details = config('activity_log.observer.detail_models', []);
        foreach ($entries as $index => $entry) {
            if (! in_array($entry['metadata']['model'] ?? null, $details, true)) {
                return $index;
            }
        }

        return 0;
    }

    /** "items.store" → "item", "chart-of-accounts.update" → "account". */
    protected function routeModule(?string $routeName): ?string
    {
        if (! $routeName) {
            return null;
        }

        $resource = Str::before($routeName, '.');
        $aliases = config('activity_log.route_modules', []);

        return $aliases[$resource] ?? Str::singular(str_replace('-', '_', $resource));
    }

    protected function routeEvent(?string $routeName): string
    {
        return match (Str::afterLast((string) $routeName, '.')) {
            'store' => 'created',
            'destroy' => 'deleted',
            'restore' => 'restored',
            default => 'updated',
        };
    }

    protected function buildPayload(array $data): array
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

        return $payload;
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
