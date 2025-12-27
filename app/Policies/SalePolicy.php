<?php

namespace App\Policies;

use App\Models\Sale\Sale;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SalePolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'sales.view');
    }

    public function view(User $user, Sale $sale): bool
    {
        return $this->hasPermission($user, 'sales.view')
            && $this->sameBranch($user, $sale);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'sales.create');
    }

    public function update(User $user, Sale $sale): bool
    {
        return $this->hasPermission($user, 'sales.update')
            && $this->sameBranch($user, $sale);
    }

    public function delete(User $user, Sale $sale): bool
    {
        return $this->hasPermission($user, 'sales.delete')
            && $this->sameBranch($user, $sale);
    }
}


