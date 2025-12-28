<?php

namespace App\Policies;

use App\Models\Administration\UnitMeasure;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UnitMeasurePolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'unit_measures.view_any');
    }

    public function view(User $user, UnitMeasure $unitMeasure): bool
    {
        return $this->hasPermission($user, 'unit_measures.view')
            && $this->sameBranch($user, $unitMeasure);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'unit_measures.create');
    }

    public function update(User $user, UnitMeasure $unitMeasure): bool
    {
        return $this->hasPermission($user, 'unit_measures.update')
            && $this->sameBranch($user, $unitMeasure);
    }

    public function delete(User $user, UnitMeasure $unitMeasure): bool
    {
        return $this->hasPermission($user, 'unit_measures.delete')
            && $this->sameBranch($user, $unitMeasure);
    }
}


