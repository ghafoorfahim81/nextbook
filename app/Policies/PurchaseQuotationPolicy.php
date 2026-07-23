<?php

namespace App\Policies;

use App\Models\Purchase\PurchaseQuotation;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PurchaseQuotationPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'purchase_quotations.view_any');
    }

    public function view(User $user, PurchaseQuotation $purchaseQuotation): bool
    {
        return $this->hasPermission($user, 'purchase_quotations.view')
            && $this->sameBranch($user, $purchaseQuotation);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'purchase_quotations.create');
    }

    public function update(User $user, PurchaseQuotation $purchaseQuotation): bool
    {
        return $this->hasPermission($user, 'purchase_quotations.update')
            && $this->sameBranch($user, $purchaseQuotation);
    }

    public function delete(User $user, PurchaseQuotation $purchaseQuotation): bool
    {
        return $this->hasPermission($user, 'purchase_quotations.delete')
            && $this->sameBranch($user, $purchaseQuotation);
    }
}
