<?php

namespace App\Policies;

use App\Models\Accounting\ContraSettlement;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ContraSettlementPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'contra_settlements.view_any');
    }

    public function view(User $user, ContraSettlement $contraSettlement): bool
    {
        return $this->hasPermission($user, 'contra_settlements.view')
            && $this->sameBranch($user, $contraSettlement);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'contra_settlements.create');
    }

    public function update(User $user, ContraSettlement $contraSettlement): bool
    {
        return $this->hasPermission($user, 'contra_settlements.update')
            && $this->sameBranch($user, $contraSettlement);
    }

    public function delete(User $user, ContraSettlement $contraSettlement): bool
    {
        return $this->hasPermission($user, 'contra_settlements.delete')
            && $this->sameBranch($user, $contraSettlement);
    }
}
