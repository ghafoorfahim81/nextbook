<?php

namespace App\Traits;

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
            if (empty($model->company_id)) {
                $model->company_id = static::resolveCompanyId();
            }
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
