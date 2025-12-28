<?php

namespace App\Policies;

use App\Models\Ledger\Ledger;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Shared policy for customer and supplier ledgers.
 *
 * Permissions are named using the generic "ledgers.*" resource.
 */
class CustomerSupplierPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'ledgers.view_any');
    }

    public function view(User $user, Ledger $ledger): bool
    {
        return $this->hasPermission($user, 'ledgers.view')
            && $this->sameBranch($user, $ledger);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'ledgers.create');
    }

    public function update(User $user, Ledger $ledger): bool
    {
        return $this->hasPermission($user, 'ledgers.update')
            && $this->sameBranch($user, $ledger);
    }

    public function delete(User $user, Ledger $ledger): bool
    {
        return $this->hasPermission($user, 'ledgers.delete')
            && $this->sameBranch($user, $ledger);
    }
}


