<?php

namespace App\Policies;

use App\Models\Accounting\CostCenter;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/** Cost centers belong to the company, not a branch: no branch check. */
class CostCenterPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'cost_centers.view_any');
    }

    public function view(User $user, CostCenter $costCenter): bool
    {
        return $this->hasPermission($user, 'cost_centers.view');
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'cost_centers.create');
    }

    public function update(User $user, CostCenter $costCenter): bool
    {
        return $this->hasPermission($user, 'cost_centers.update');
    }

    public function delete(User $user, CostCenter $costCenter): bool
    {
        return $this->hasPermission($user, 'cost_centers.delete');
    }
}
