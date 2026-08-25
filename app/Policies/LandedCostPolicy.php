<?php

namespace App\Policies;

use App\Models\Inventory\LandedCost;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class LandedCostPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'landed_costs.view_any');
    }

    public function view(User $user, LandedCost $landedCost): bool
    {
        return $this->hasPermission($user, 'landed_costs.view')
            && $this->sameBranch($user, $landedCost);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'landed_costs.create');
    }

    public function update(User $user, LandedCost $landedCost): bool
    {
        return $this->hasPermission($user, 'landed_costs.update')
            && $this->sameBranch($user, $landedCost);
    }

    public function delete(User $user, LandedCost $landedCost): bool
    {
        return $this->hasPermission($user, 'landed_costs.delete')
            && $this->sameBranch($user, $landedCost);
    }

    public function allocate(User $user, LandedCost $landedCost): bool
    {
        return $this->hasPermission($user, 'landed_costs.allocate')
            && $this->sameBranch($user, $landedCost);
    }

    public function post(User $user, LandedCost $landedCost): bool
    {
        return $this->hasPermission($user, 'landed_costs.post')
            && $this->sameBranch($user, $landedCost);
    }
}
