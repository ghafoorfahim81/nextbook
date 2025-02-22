<?php

namespace App\Traits;

trait HasFilters
{
    /**
     * Get the list of filterable attributes for the model.
     *
     * This method retrieves the array of attributes that are available for filtering,
     * defined in the model as `$filterableAttributes`.
     *
     * @return array The list of filterable attribute names.
     */
    public function getFilterableAttributes(): array
    {
        return self::$filterableAttributes ?? [];
    }

    /**
     * Scope for filtering by attributes and date.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  array  $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFilter($query, $filters)
    {
        return $query
            ->filterAttributes($filters, $this->getFilterableAttributes())
            ->relationshipFilter($filters, $this->getFilterableAttributes());
    }

    /**
     * Scope to apply filters based on specific attributes.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  array  $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFilterAttributes($query, $filters, array $filterableAttributes)
    {
        foreach ($filterableAttributes as $attribute) {
            if (isset($filters[$attribute])) {
                $value = $filters[$attribute];

                if ($attribute === 'distribution_channel') {
                    $query->whereJsonContains('distribution_channel', $value);

                    continue;
                }

                if (is_string($value) && str_contains($value, ',')) {
                    // Convert comma-separated string to array
                    $value = explode(',', $value);
                }

                if (is_array($value)) {
                    $query->whereIn($attribute, $value);
                } else {
                    $query->where($attribute, $value);
                }
            }
        }

        return $query;
    }

    /**
     * Scope to apply relationship filters based on specific relationship attributes.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  array  $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRelationshipFilter($query, $filters, array $filterableAttributes)
    {
        foreach ($filterableAttributes as $attribute) {
            if (str_contains((string) $attribute, '.')) {
                [$relationship, $column] = explode('.', (string) $attribute, 2);

                // Check if the base column (e.g., employee_id) exists in the filters
                $filterKey = $column; // e.g., employee_id
                if (isset($filters[$filterKey])) {
                    $value = $filters[$filterKey];

                    $query->whereHas($relationship, function ($q) use ($column, $value): void {
                        if (is_string($value) && str_contains($value, ',')) {
                            $value = explode(',', $value); // Convert comma-separated string to array
                        }

                        if (is_array($value)) {
                            $q->whereIn($column, $value);
                        } else {
                            $q->where($column, $value);
                        }
                    });
                }
            }
        }

        return $query;
    }

    /**
     * Scope to filter by date range.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  array  $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFilterByDate($query, $filters)
    {
        return $query->when(isset($filters['filter_date']), function ($query) use ($filters): void {
            foreach ($filters['filter_date'] as $column => $value) {
                $dates = explode(',', $value);
                [$start, $end] = $dates + [null, null];

                if (! $end) {
                    $query->whereDate($column, '=', $start);
                } else {
                    if ($start) {
                        $query->whereDate($column, '>=', $start);
                    }
                    $query->whereDate($column, '<=', $end);
                }
            }
        });
    }

    /**
     * Scope to filter based on soft delete status.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string|null  $softDeleted
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithSoftDeletes($query, $softDeleted = null)
    {
        return $query
            ->when($softDeleted === 'only', fn ($query) => $query->onlyTrashed())
            ->when($softDeleted === 'with', fn ($query) => $query->withTrashed());
    }
}
