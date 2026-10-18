<?php

namespace App\Traits;

use App\Models\Administration\Branch;

/**
 * Fills `company_id` on create from the company resolved for this request.
 *
 * The counterpart of HasBranch, one level up, with one deliberate difference:
 * it does not invent a company when none is resolved. HasBranch falls back to
 * "any branch in the table, or a freshly made one", which is survivable for a
 * branch but not for a company — filing a document in whichever company
 * happened to be first is the silent misposting the architecture plan rules
 * out. A row with no resolvable company fails on the NOT NULL constraint
 * instead, which is the loud outcome.
 */
trait HasCompany
{
    public static function bootHasCompany()
    {
        static::creating(function ($model) {
            if (! empty($model->company_id)) {
                return;
            }

            // A row that already knows its branch knows its company: the branch
            // determines it, and the composite foreign key would reject any
            // other answer. This matters outside a request — jobs, console
            // commands, seeders and tests all create records with a branch but
            // no acting company — and is preferred over the request context
            // because it describes the row rather than the caller.
            $branchId = $model->branch_id ?? null;

            // HasBranch fills branch_id in a `creating` hook of its own, and
            // hooks fire in the order their traits booted — which puts this one
            // first on most models. So the acting branch is consulted directly
            // rather than waiting for the column to be populated.
            if (empty($branchId) && app()->bound('active_branch_id')) {
                $branchId = app('active_branch_id');
            }

            if (! empty($branchId)) {
                $model->company_id = Branch::withoutGlobalScopes()
                    ->whereKey($branchId)
                    ->value('company_id');

                if (! empty($model->company_id)) {
                    return;
                }
            }

            $model->company_id = static::resolveCompanyId();
        });
    }

    protected static function resolveCompanyId(): ?string
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
}
