<?php

namespace App\Traits;

use App\Support\CompanyContext;

/**
 * Fills `branch_id` on create from the branch resolved for this request.
 *
 * The counterpart of HasCompany, one level down, and deliberately as strict:
 * it does not invent a branch when none is resolved. It used to fall back to
 * whichever branch came first in the table, or to a freshly made one — which
 * outside a request meant any company's branch, and HasCompany then filed the
 * row in that company's books. A row with no resolvable branch now fails on
 * the NOT NULL constraint instead, which is the loud outcome.
 *
 * The acting branch comes from CompanyContext, the same place BranchSpecific's
 * read scope answers from, so a row is written into the branch it will be
 * read back from.
 */
trait HasBranch
{
    public static function bootHasBranch()
    {
        static::creating(function ($model) {
            if (empty($model->branch_id)) {
                $model->branch_id = CompanyContext::branchId();
            }
        });
    }
}
