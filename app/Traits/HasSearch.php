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
                        // Handle relationship column search. The relation path may itself be
                        // dotted (e.g. "transaction.currency.name"), so split on the LAST dot:
                        // everything before it is the (possibly nested) relation, the rest is
                        // the column — Eloquent's whereHas() accepts dotted relation paths.
                        $lastDot = strrpos($column, '.');
                        $relationship = substr($column, 0, $lastDot);
                        $relatedColumn = substr($column, $lastDot + 1);
                        $query->orWhereHas($relationship, function ($q) use ($relatedColumn, $searchTerm): void {
                            $q->where($relatedColumn, 'like', "%{$searchTerm}%");
                        });
                    } else {
                        // Direct column search
                        $query->orWhere($column, 'iLike', "%{$searchTerm}%");
                    }
                }
            });
        });
    }
}
