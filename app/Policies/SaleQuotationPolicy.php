<?php

namespace App\Policies;

use App\Models\Sale\SaleQuotation;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SaleQuotationPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'sale_quotations.view_any');
    }

    public function view(User $user, SaleQuotation $saleQuotation): bool
    {
        return $this->hasPermission($user, 'sale_quotations.view')
            && $this->sameBranch($user, $saleQuotation);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'sale_quotations.create');
    }

    public function update(User $user, SaleQuotation $saleQuotation): bool
    {
        return $this->hasPermission($user, 'sale_quotations.update')
            && $this->sameBranch($user, $saleQuotation);
    }

    public function delete(User $user, SaleQuotation $saleQuotation): bool
    {
        return $this->hasPermission($user, 'sale_quotations.delete')
            && $this->sameBranch($user, $saleQuotation);
    }
}
