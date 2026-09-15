<?php

namespace App\Policies;

use App\Models\Inventory\DiscountRule;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DiscountRulePolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'discount_rules.view_any');
    }

    public function view(User $user, DiscountRule $discountRule): bool
    {
        return $this->hasPermission($user, 'discount_rules.view')
            && $this->sameBranch($user, $discountRule);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'discount_rules.create');
    }

    public function update(User $user, DiscountRule $discountRule): bool
    {
        return $this->hasPermission($user, 'discount_rules.update')
            && $this->sameBranch($user, $discountRule);
    }

    public function delete(User $user, DiscountRule $discountRule): bool
    {
        return $this->hasPermission($user, 'discount_rules.delete')
            && $this->sameBranch($user, $discountRule);
    }
}
