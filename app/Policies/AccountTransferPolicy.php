<?php

namespace App\Policies;

use App\Models\AccountTransfer\AccountTransfer;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AccountTransferPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'account_transfers.view_any');
    }

    public function view(User $user, AccountTransfer $transfer): bool
    {
        return $this->hasPermission($user, 'account_transfers.view')
            && $this->sameBranch($user, $transfer);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'account_transfers.create');
    }

    public function update(User $user, AccountTransfer $transfer): bool
    {
        return $this->hasPermission($user, 'account_transfers.update')
            && $this->sameBranch($user, $transfer);
    }

    public function delete(User $user, AccountTransfer $transfer): bool
    {
        return $this->hasPermission($user, 'account_transfers.delete')
            && $this->sameBranch($user, $transfer);
    }
}


