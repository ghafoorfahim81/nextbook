<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * Scopes a model to the companies the current request may read.
 *
 * The counterpart of BranchSpecific, one level up. Where a model carries both
 * columns the two traits compose: company first, then branch.
 *
 * The scope is `whereIn` over an array rather than `where` over a single id,
 * even though that array holds exactly one company today. A user who belongs
 * to several companies is a later phase, and widening a `where` into a
 * `whereIn` across every model afterwards is the kind of change that gets
 * missed in one place and silently leaks.
 */
trait CompanySpecific
{
    public static function bootCompanySpecific()
    {
        static::addGlobalScope('companySpecific', function (Builder $builder) {
            $companyIds = static::accessibleCompanyIds();

            // Outside a web request (console command, queue worker,
            // unauthenticated request) there is no company to scope to. Leaving
            // the query alone matches how BranchSpecific behaves; both are
            // listed in the architecture plan as debt to revisit once jobs
            // carry their own context.
            if ($companyIds === []) {
                return;
            }

            $builder->whereIn(static::getCompanyColumn(), $companyIds);
        });
    }

    /**
     * Companies the current request may read, or an empty array when there is
     * no company context.
     *
     * @return array<int, string>
     */
    protected static function accessibleCompanyIds(): array
    {
        if (app()->bound('accessible_company_ids')) {
            $ids = array_filter((array) app('accessible_company_ids'));

            if ($ids !== []) {
                return array_values(array_map('strval', $ids));
            }
        }

        $companyId = auth()->user()?->company_id;

        return $companyId ? [(string) $companyId] : [];
    }

    /**
     * The single company new records are written in.
     *
     * Deliberately not derived from `accessibleCompanyIds()`: reading may span
     * companies, writing never does, and picking "the first readable company"
     * would quietly file a document in the wrong set of books.
     */
    public static function activeCompanyId(): ?string
    {
        if (app()->bound('active_company_id')) {
            $companyId = app('active_company_id');

            if ($companyId) {
                return (string) $companyId;
            }
        }

        $companyId = auth()->user()?->company_id;

        return $companyId ? (string) $companyId : null;
    }

    protected static function getCompanyColumn(): string
    {
        return (new static)->getTable().'.company_id';
    }
}
