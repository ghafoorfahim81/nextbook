<?php

namespace App\Policies;

use App\Models\Account\AccountType;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AccountTypePolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'account_types.view');
    }

    public function view(User $user, AccountType $accountType): bool
    {
        return $this->hasPermission($user, 'account_types.view')
            && $this->sameBranch($user, $accountType);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'account_types.create');
    }

    public function update(User $user, AccountType $accountType): bool
    {
        return $this->hasPermission($user, 'account_types.update')
            && $this->sameBranch($user, $accountType);
    }

    public function delete(User $user, AccountType $accountType): bool
    {
        return $this->hasPermission($user, 'account_types.delete')
            && $this->sameBranch($user, $accountType);
    }
}


