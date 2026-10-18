<?php

namespace App\Traits;

/**
 * Fills `company_id` on a line from the document it belongs to.
 *
 * Line tables carry a company so a composite foreign key can pin their
 * references — a transaction line's account, a sale line's item — to the same
 * company as the line. They carry no branch, because the branch is a fact of
 * the parent document, so HasCompany's branch fallback has nothing to work
 * with.
 *
 * The parent is the single right answer regardless of who is posting or from
 * where, which is why this is preferred over the request context: a reversal
 * run from a queue worker files its lines in the same books as the original.
 */
trait HasCompanyFromParent
{
    /**
     * The relation holding the owning document. Override on models whose
     * parent is not `transaction`.
     */
    protected function companyParentRelation(): string
    {
        return 'transaction';
    }

    public static function bootHasCompanyFromParent()
    {
        static::creating(function ($model) {
            if (! empty($model->company_id)) {
                return;
            }

            $relation = $model->companyParentRelation();
            $parent = $model->{$relation};

            if ($parent && ! empty($parent->company_id)) {
                $model->company_id = $parent->company_id;
            }
        });
    }
}
