<?php

namespace App\Traits;

trait HasSearch
{
    /**
     * Scope for search functionality.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string|null  $searchTerm
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, $searchTerm)
    {
        return $query->when($searchTerm, function ($query) use ($searchTerm): void {
            $query->searchColumns($searchTerm, self::searchableColumns() ?? []);
        });
    }

    /**
     * Scope for search functionality across specified columns.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string|null  $searchTerm
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearchColumns($query, $searchTerm, array $searchableColumns)
    {
        return $query->when($searchTerm, function ($query) use ($searchTerm, $searchableColumns): void {
            $query->where(function ($query) use ($searchTerm, $searchableColumns): void {
                foreach ($searchableColumns as $column) {
                    if (str_contains($column, '.')) {
                        // Handle relationship column search
                        [$relationship, $relatedColumn] = explode('.', $column, 2);
                        $query->orWhereHas($relationship, function ($q) use ($relatedColumn, $searchTerm): void {
                            $q->where($relatedColumn, 'like', "%{$searchTerm}%");
                        });
                    } else {
                        // Direct column search
                        $query->orWhere($column, 'like', "%{$searchTerm}%");
                    }
                }
            });
        });
    }
}
