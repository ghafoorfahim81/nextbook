<?php

namespace App\Policies;

use App\Models\Purchase\PurchaseReturn;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PurchaseReturnPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'purchase_returns.view_any');
    }

    public function view(User $user, PurchaseReturn $purchaseReturn): bool
    {
        return $this->hasPermission($user, 'purchase_returns.view')
            && $this->sameBranch($user, $purchaseReturn);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'purchase_returns.create');
    }

    public function update(User $user, PurchaseReturn $purchaseReturn): bool
    {
        return $this->hasPermission($user, 'purchase_returns.update')
            && $this->sameBranch($user, $purchaseReturn);
    }

    public function delete(User $user, PurchaseReturn $purchaseReturn): bool
    {
        return $this->hasPermission($user, 'purchase_returns.delete')
            && $this->sameBranch($user, $purchaseReturn);
    }
}
