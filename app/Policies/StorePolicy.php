<?php

namespace App\Policies;

use App\Models\Administration\Store;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class StorePolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'stores.view');
    }

    public function view(User $user, Store $store): bool
    {
        return $this->hasPermission($user, 'stores.view')
            && $this->sameBranch($user, $store);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'stores.create');
    }

    public function update(User $user, Store $store): bool
    {
        return $this->hasPermission($user, 'stores.update')
            && $this->sameBranch($user, $store);
    }

    public function delete(User $user, Store $store): bool
    {
        return $this->hasPermission($user, 'stores.delete')
            && $this->sameBranch($user, $store);
    }
}


