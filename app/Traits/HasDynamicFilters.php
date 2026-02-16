<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Carbon\Carbon;

trait HasDynamicFilters
{
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        $allowed = $this->getAllowedDynamicFilters();
        $handlers = $this->getDynamicFilterHandlers();

        foreach ($filters as $field => $value) {

            if (is_null($value) || $value === '') {
                continue;
            }

            if (! $this->isAllowedDynamicFilter($field, $allowed)) {
                continue;
            }

            // Custom handler (per-model override)
            if (isset($handlers[$field]) && is_callable($handlers[$field])) {
                $handlers[$field]($query, $value, $filters);
                continue;
            }

            // Date range: <field>_from / <field>_to (supports relation paths too)
            if (str_ends_with($field, '_from') || str_ends_with($field, '_to')) {
                $op = str_ends_with($field, '_from') ? '>=' : '<=';
                $base = str_ends_with($field, '_from')
                    ? substr($field, 0, -5)
                    : substr($field, 0, -3);

                $this->applyDateComparison($query, $base, $op, $value);
                continue;
            }

            // Numeric range: <field>_min / <field>_max (supports relation paths too)
            if (str_ends_with($field, '_min') || str_ends_with($field, '_max')) {
                $op = str_ends_with($field, '_min') ? '>=' : '<=';
                $base = str_ends_with($field, '_min')
                    ? substr($field, 0, -4)
                    : substr($field, 0, -4);

                $this->applyNumericComparison($query, $base, $op, $value);
                continue;
            }

            // Relation filtering: relation.field
            if (str_contains($field, '.')) {
                [$relation, $relField] = $this->splitRelationAndField($field);

                $query->whereHas($relation, function ($q) use ($relField, $value) {
                    $this->applyFilterCondition($q, $relField, $value);
                });

                continue;
            }

            // Normal column
            $this->applyFilterCondition($query, $field, $value);
        }

        return $query;
    }

    protected function applyFilterCondition(Builder $query, string $field, $value): void
    {
        if (is_array($value)) {
            $query->whereIn($field, $value);
            return;
        }

        // Boolean
        if (in_array($value, ['true', 'false', true, false], true)) {
            $query->where($field, filter_var($value, FILTER_VALIDATE_BOOLEAN));
            return;
        }

        // Enum (string match)
        if (method_exists($this, 'getCasts') && Arr::has($this->getCasts(), $field)) {
            $query->where($field, $value);
            return;
        }

        // Date
        if ($this->isDateColumn($field)) {
            $query->whereDate($field, $value);
            return;
        }

        // Foreign keys & obvious identifiers: exact match
        if (
            str_ends_with($field, '_id')
            || $field === 'id'
            || in_array($field, ['created_by', 'updated_by', 'deleted_by'], true)
        ) {
            $query->where($field, $value);
            return;
        }

        // Default: string LIKE (gmail-style contains)
        $query->where($field, 'like', "%{$value}%");
    }

    protected function isDateColumn(string $field): bool
    {
        return in_array($field, [
            'date',
            'created_at',
            'updated_at',
        ]);
    }

    /**
     * @return array<int, string>
     */
    protected function getAllowedDynamicFilters(): array
    {
        // Prefer $allowedFilters on the model (already used in some models)
        $allowed = [];
        if (property_exists($this, 'allowedFilters') && is_array($this->allowedFilters)) {
            $allowed = $this->allowedFilters;
        }

        // Back-compat: allow $filterableAttributes (used by HasFilters trait pattern)
        if (empty($allowed) && property_exists($this, 'filterableAttributes') && is_array($this->filterableAttributes)) {
            $allowed = $this->filterableAttributes;
        }

        // Ensure "created_by" is always permitted when present
        if ((property_exists($this, 'fillable') && in_array('created_by', (array) $this->fillable, true))
            || (method_exists($this, 'getCasts') && Arr::has($this->getCasts(), 'created_by'))
        ) {
            $allowed[] = 'created_by';
        }

        // Also allow createdBy.name for gmail-style filtering by creator name (if relationship exists)
        if (method_exists($this, 'createdBy')) {
            $allowed[] = 'createdBy.name';
        }

        return array_values(array_unique(array_filter($allowed)));
    }

    protected function isAllowedDynamicFilter(string $field, array $allowed): bool
    {
        if (empty($allowed)) {
            // If model didn't define allowed filters, fail closed for safety.
            return false;
        }

        if (in_array($field, $allowed, true)) {
            return true;
        }

        // Allow range suffix variants when base field is whitelisted
        foreach (['_from', '_to', '_min', '_max'] as $suffix) {
            if (str_ends_with($field, $suffix)) {
                $base = substr($field, 0, -strlen($suffix));
                return in_array($base, $allowed, true);
            }
        }

        return false;
    }

    /**
     * @return array<string, callable(Builder, mixed, array): void>
     */
    protected function getDynamicFilterHandlers(): array
    {
        if (method_exists($this, 'dynamicFilterHandlers')) {
            $handlers = $this->dynamicFilterHandlers();
            return is_array($handlers) ? $handlers : [];
        }

        return [];
    }

    /**
     * Split "relation.path.field" into ["relation.path", "field"].
     *
     * @return array{0:string,1:string}
     */
    protected function splitRelationAndField(string $fieldPath): array
    {
        $pos = strrpos($fieldPath, '.');
        if ($pos === false) {
            return [$fieldPath, ''];
        }

        $relation = substr($fieldPath, 0, $pos);
        $field = substr($fieldPath, $pos + 1);
        return [$relation, $field];
    }

    protected function applyDateComparison(Builder $query, string $fieldPath, string $operator, mixed $value): void
    {
        try {
            $date = Carbon::parse($value);
        } catch (\Throwable $e) {
            return;
        }

        if (! str_contains($fieldPath, '.')) {
            $query->whereDate($fieldPath, $operator, $date);
            return;
        }

        [$relation, $column] = $this->splitRelationAndField($fieldPath);
        $query->whereHas($relation, function (Builder $q) use ($column, $operator, $date) {
            $q->whereDate($column, $operator, $date);
        });
    }

    protected function applyNumericComparison(Builder $query, string $fieldPath, string $operator, mixed $value): void
    {
        if (! is_numeric($value)) {
            return;
        }

        if (! str_contains($fieldPath, '.')) {
            $query->where($fieldPath, $operator, $value);
            return;
        }

        [$relation, $column] = $this->splitRelationAndField($fieldPath);
        $query->whereHas($relation, function (Builder $q) use ($column, $operator, $value) {
            $q->where($column, $operator, $value);
        });
    }
}