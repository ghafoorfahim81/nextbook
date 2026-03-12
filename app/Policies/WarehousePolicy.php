<?php

namespace App\Policies;

use App\Models\Administration\Warehouse;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class WarehousePolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'warehouses.view_any');
    }

    public function view(User $user, Warehouse $warehouse): bool
    {
        return $this->hasPermission($user, 'warehouses.view')
            && $this->sameBranch($user, $warehouse);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'warehouses.create');
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return $this->hasPermission($user, 'warehouses.update')
            && $this->sameBranch($user, $warehouse);
    }

    public function delete(User $user, Warehouse $warehouse): bool
    {
        return $this->hasPermission($user, 'warehouses.delete')
            && $this->sameBranch($user, $warehouse);
    }
}

