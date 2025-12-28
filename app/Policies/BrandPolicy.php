<?php

namespace App\Policies;

use App\Models\Administration\Brand;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BrandPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'brands.view_any');
    }

    public function view(User $user, Brand $brand): bool
    {
        return $this->hasPermission($user, 'brands.view')
            && $this->sameBranch($user, $brand);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'brands.create');
    }

    public function update(User $user, Brand $brand): bool
    {
        return $this->hasPermission($user, 'brands.update')
            && $this->sameBranch($user, $brand);
    }

    public function delete(User $user, Brand $brand): bool
    {
        return $this->hasPermission($user, 'brands.delete')
            && $this->sameBranch($user, $brand);
    }
}


