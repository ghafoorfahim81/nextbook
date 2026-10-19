<?php

namespace App\Policies;

use App\Models\Sale\SaleReturn;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SaleReturnPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'sale_returns.view_any');
    }

    public function view(User $user, SaleReturn $saleReturn): bool
    {
        return $this->hasPermission($user, 'sale_returns.view')
            && $this->sameBranch($user, $saleReturn);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'sale_returns.create');
    }

    public function update(User $user, SaleReturn $saleReturn): bool
    {
        return $this->hasPermission($user, 'sale_returns.update')
            && $this->sameBranch($user, $saleReturn);
    }

    public function delete(User $user, SaleReturn $saleReturn): bool
    {
        return $this->hasPermission($user, 'sale_returns.delete')
            && $this->sameBranch($user, $saleReturn);
    }
}
