<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait HasSorting
{
    /**
     * Scope for sorting results by a specified column and sortDirection.
     *
     * @param  Builder  $query
     * @param  string|null  $sortBy  The column name to sort by.
     */
    public function scopeSort($query, $sortBy = null): Builder
    {
        $sortBy ??= '-created_at';

        [$sortBy, $sortDirection] = $this->parseSortBy($sortBy);

        return $query->when($sortBy, function ($query) use ($sortBy, $sortDirection): void {
            if ($this->isSortingByRelatedModel($sortBy)) {
                $this->applyRelatedModelSorting($query, $sortBy, $sortDirection);
            } else {
                $this->applyDirectColumnSorting($query, $sortBy, $sortDirection);
            }
        });
    }

    /**
     * Check if sorting is by a related model column.
     */
    protected function isSortingByRelatedModel($sortBy): bool
    {
        return str_contains((string) $sortBy, '.');
    }

    /**
     * Apply sorting when sorting by a related model's column.
     */
    protected function applyRelatedModelSorting($query, $sortBy, $sortDirection): void
    {
        [$relation, $column] = explode('.', (string) $sortBy);

        $relationInstance = $this->{$relation}()->getRelated();
        $relationTable = $relationInstance->getTable();
        $relationAlias = "{$relationTable}_alias";
        $relationKey = $this->{$relation}()->getForeignKeyName();
        $ownerKey = $this->{$relation}()->getOwnerKeyName();
        $baseTable = $this->getTable();

        $query->join("{$relationTable} as {$relationAlias}", "{$relationAlias}.{$ownerKey}", '=', "{$baseTable}.{$relationKey}")
            ->orderBy("{$relationAlias}.{$column}", $sortDirection)
            ->select("{$baseTable}.*");
    }

    /**
     * Apply sorting when sorting by a direct model column.
     */
    protected function applyDirectColumnSorting($query, $sortBy, $sortDirection): void
    {
        $query->orderBy($sortBy, $sortDirection);
    }

    /**
     * Parses the sortBy string to determine the field and the sort direction.
     *
     * @param  string  $sortBy  The string representing the field to sort by,
     *                          optionally prefixed with '-' to indicate descending order.
     * @return array An array where the first element is the field name and the second element is the sort direction ('asc' or 'desc').
     */
    private function parseSortBy(string $sortBy): array
    {
        return [ltrim($sortBy, '-'), $sortBy[0] === '-' ? 'desc' : 'asc'];
    }
}
