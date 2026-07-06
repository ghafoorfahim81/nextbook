<?php

namespace App\Policies;

use App\Models\Sale\SaleOrder;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SaleOrderPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'sale_orders.view_any');
    }

    public function view(User $user, SaleOrder $saleOrder): bool
    {
        return $this->hasPermission($user, 'sale_orders.view')
            && $this->sameBranch($user, $saleOrder);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'sale_orders.create');
    }

    public function update(User $user, SaleOrder $saleOrder): bool
    {
        return $this->hasPermission($user, 'sale_orders.update')
            && $this->sameBranch($user, $saleOrder);
    }

    public function delete(User $user, SaleOrder $saleOrder): bool
    {
        return $this->hasPermission($user, 'sale_orders.delete')
            && $this->sameBranch($user, $saleOrder);
    }
}
