<?php

namespace App\Policies;

use App\Models\Administration\Category;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CategoryPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'categories.view_any');
    }

    public function view(User $user, Category $category): bool
    {
        return $this->hasPermission($user, 'categories.view')
            && $this->sameBranch($user, $category);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'categories.create');
    }

    public function update(User $user, Category $category): bool
    {
        return $this->hasPermission($user, 'categories.update')
            && $this->sameBranch($user, $category);
    }

    public function delete(User $user, Category $category): bool
    {
        return $this->hasPermission($user, 'categories.delete')
            && $this->sameBranch($user, $category);
    }
}


