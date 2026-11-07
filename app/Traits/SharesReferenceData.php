<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * For reference tables that hold a list every company shares plus rows of its
 * own (customer groups, payment terms).
 *
 * ReferenceDataSeeder writes the shared list once with `company_id` null; a
 * company adds its own alongside. Reading therefore sees the shared rows and
 * the company's own — never another company's — while writing stays with the
 * company (HasCompany fills its id on create). The database's row level
 * security enforces the same rule; this scope keeps the owner connection
 * honest too.
 *
 * CompanySpecific is deliberately NOT used here: it filters by equality and
 * would hide the shared list from everyone.
 */
trait SharesReferenceData
{
    public static function bootSharesReferenceData(): void
    {
        static::addGlobalScope('sharedOrOwnCompany', function (Builder $builder) {
            $companyId = auth()->user()?->company_id;

            // No company context (console, queue): leave the query alone, as
            // CompanySpecific does.
            if (! $companyId) {
                return;
            }

            $table = (new static)->getTable();

            $builder->where(function (Builder $query) use ($table, $companyId) {
                $query->where("{$table}.company_id", $companyId)
                    ->orWhereNull("{$table}.company_id");
            });
        });
    }

    /** A row from the shared list: readable by everyone, changeable by no company. */
    public function isShared(): bool
    {
        return $this->company_id === null;
    }
}
