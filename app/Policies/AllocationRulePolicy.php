<?php

namespace App\Policies;

use App\Models\Accounting\AllocationRule;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/** Allocation rules belong to the company, not a branch: no branch check. */
class AllocationRulePolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'allocation_rules.view_any');
    }

    public function view(User $user, AllocationRule $rule): bool
    {
        return $this->hasPermission($user, 'allocation_rules.view');
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'allocation_rules.create');
    }

    public function update(User $user, AllocationRule $rule): bool
    {
        return $this->hasPermission($user, 'allocation_rules.update');
    }

    public function delete(User $user, AllocationRule $rule): bool
    {
        return $this->hasPermission($user, 'allocation_rules.delete');
    }
}
