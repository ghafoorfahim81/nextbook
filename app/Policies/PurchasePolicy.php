<?php

namespace App\Policies;

use App\Models\Purchase\Purchase;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PurchasePolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'purchases.view_any');
    }

    public function view(User $user, Purchase $purchase): bool
    {
        return $this->hasPermission($user, 'purchases.view')
            && $this->sameBranch($user, $purchase);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'purchases.create');
    }

    public function update(User $user, Purchase $purchase): bool
    {
        return $this->hasPermission($user, 'purchases.update')
            && $this->sameBranch($user, $purchase);
    }

    public function delete(User $user, Purchase $purchase): bool
    {
        return $this->hasPermission($user, 'purchases.delete')
            && $this->sameBranch($user, $purchase);
    }
}


