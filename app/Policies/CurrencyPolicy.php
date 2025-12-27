<?php

namespace App\Policies;

use App\Models\Administration\Currency;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CurrencyPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'currencies.view');
    }

    public function view(User $user, Currency $currency): bool
    {
        return $this->hasPermission($user, 'currencies.view');
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'currencies.create');
    }

    public function update(User $user, Currency $currency): bool
    {
        return $this->hasPermission($user, 'currencies.update');
    }

    public function delete(User $user, Currency $currency): bool
    {
        return $this->hasPermission($user, 'currencies.delete');
    }
}


