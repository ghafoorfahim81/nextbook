<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Services\ActivityLogService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ModelActivityObserver
{
    protected static ?bool $activityLogTableExists = null;

    public function __construct(
        protected ActivityLogService $activityLogService
    ) {
    }

    public function created(Model $model): void
    {
        if ($this->shouldSkip($model)) {
            return;
        }

        $this->activityLogService->logCreate(
            reference: $model,
            module: $this->module($model),
            description: $this->description($model, 'created'),
            newValues: $this->snapshot($model),
            metadata: [
                'source' => 'observer',
                'model' => $model::class,
            ],
        );
    }

    public function updated(Model $model): void
    {
        if ($this->shouldSkip($model)) {
            return;
        }

        $changedKeys = array_keys(Arr::except($model->getChanges(), $this->excludedAttributes()));

        if ($changedKeys === []) {
            return;
        }

        $previous = $this->previousAttributes($model, $changedKeys);
        $current = Arr::only($this->normalizedAttributes($model), $changedKeys);

        $this->activityLogService->logUpdate(
            reference: $model,
            before: $previous,
            after: $current,
            module: $this->module($model),
            description: $this->description($model, 'updated'),
            metadata: [
                'source' => 'observer',
                'model' => $model::class,
            ],
            except: $this->excludedAttributes(),
        );
    }

    public function deleted(Model $model): void
    {
        if ($this->shouldSkip($model)) {
            return;
        }

        $this->activityLogService->logDelete(
            reference: $model,
            module: $this->module($model),
            description: $this->description($model, 'deleted'),
            oldValues: $this->snapshot($model),
            metadata: [
                'source' => 'observer',
                'model' => $model::class,
            ],
        );
    }

    public function restored(Model $model): void
    {
        if ($this->shouldSkip($model)) {
            return;
        }

        $this->activityLogService->logAction(
            eventType: 'restored',
            reference: $model,
            module: $this->module($model),
            description: $this->description($model, 'restored'),
            newValues: $this->snapshot($model),
            metadata: [
                'source' => 'observer',
                'model' => $model::class,
            ],
        );
    }

    protected function shouldSkip(Model $model): bool
    {
        if ($model instanceof ActivityLog) {
            return true;
        }

        if (! $this->observerEnabledFor($model::class)) {
            return true;
        }

        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return true;
        }

        if (self::$activityLogTableExists === null) {
            self::$activityLogTableExists = Schema::hasTable('activity_logs');
        }

        return ! self::$activityLogTableExists;
    }

    protected function observerEnabledFor(string $modelClass): bool
    {
        return in_array($modelClass, config('activity_log.observer.models', []), true);
    }

    protected function excludedAttributes(): array
    {
        return config('activity_log.observer.except_attributes', []);
    }

    protected function module(Model $model): string
    {
        return Str::snake(class_basename($this->activityLogService->referenceType($model)));
    }

    protected function description(Model $model, string $eventType): string
    {
        $module = Str::of($this->module($model))->replace('_', ' ')->headline();
        $label = $this->modelLabel($model);

        return trim("{$module} {$label} {$eventType}.");
    }

    protected function modelLabel(Model $model): string
    {
        foreach (['number', 'name', 'code', 'title', 'slug'] as $attribute) {
            $value = $model->getAttribute($attribute);

            if ($value !== null && $value !== '') {
                return $attribute === 'number' ? "#{$value}" : (string) $value;
            }
        }

        return "#{$model->getKey()}";
    }

    protected function snapshot(Model $model): ?array
    {
        return $this->activityLogService->snapshot(
            reference: $model,
            except: $this->excludedAttributes(),
        );
    }

    protected function previousAttributes(Model $model, array $changedKeys): array
    {
        $previous = method_exists($model, 'getPrevious')
            ? $model->getPrevious()
            : Arr::only($model->getOriginal(), $changedKeys);

        return Arr::only($this->normalizeArray($previous), $changedKeys);
    }

    protected function normalizedAttributes(Model $model): array
    {
        return $this->normalizeArray($model->attributesToArray());
    }

    protected function normalizeArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return $value;
    }
}
