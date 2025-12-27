<?php

namespace App\Policies;

use App\Models\Account\Account;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AccountPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'accounts.view');
    }

    public function view(User $user, Account $account): bool
    {
        return $this->hasPermission($user, 'accounts.view')
            && $this->sameBranch($user, $account);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'accounts.create');
    }

    public function update(User $user, Account $account): bool
    {
        return $this->hasPermission($user, 'accounts.update')
            && $this->sameBranch($user, $account);
    }

    public function delete(User $user, Account $account): bool
    {
        return $this->hasPermission($user, 'accounts.delete')
            && $this->sameBranch($user, $account);
    }
}


