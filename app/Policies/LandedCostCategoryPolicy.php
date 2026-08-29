<?php

namespace App\Policies;

use App\Models\Administration\LandedCostCategory;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class LandedCostCategoryPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'landed_cost_categories.view_any');
    }

    public function view(User $user, LandedCostCategory $landedCostCategory): bool
    {
        return $this->hasPermission($user, 'landed_cost_categories.view')
            && $this->sameBranch($user, $landedCostCategory);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'landed_cost_categories.create');
    }

    public function update(User $user, LandedCostCategory $landedCostCategory): bool
    {
        return $this->hasPermission($user, 'landed_cost_categories.update')
            && $this->sameBranch($user, $landedCostCategory);
    }

    public function delete(User $user, LandedCostCategory $landedCostCategory): bool
    {
        return $this->hasPermission($user, 'landed_cost_categories.delete')
            && $this->sameBranch($user, $landedCostCategory);
    }
}
