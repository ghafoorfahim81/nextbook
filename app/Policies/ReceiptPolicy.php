<?php

namespace App\Policies;

use App\Models\Receipt\Receipt;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReceiptPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'receipts.view');
    }

    public function view(User $user, Receipt $receipt): bool
    {
        return $this->hasPermission($user, 'receipts.view')
            && $this->sameBranch($user, $receipt);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'receipts.create');
    }

    public function update(User $user, Receipt $receipt): bool
    {
        return $this->hasPermission($user, 'receipts.update')
            && $this->sameBranch($user, $receipt);
    }

    public function delete(User $user, Receipt $receipt): bool
    {
        return $this->hasPermission($user, 'receipts.delete')
            && $this->sameBranch($user, $receipt);
    }
}


