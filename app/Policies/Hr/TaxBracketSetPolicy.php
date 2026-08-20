<?php

namespace App\Policies\Hr;

use App\Models\Hr\TaxBracketSet;
use App\Models\User;
use App\Policies\BasePolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

class TaxBracketSetPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'tax_bracket_sets.view_any');
    }

    public function view(User $user, TaxBracketSet $taxBracketSet): bool
    {
        return $this->hasPermission($user, 'tax_bracket_sets.view')
            && $this->sameBranch($user, $taxBracketSet);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'tax_bracket_sets.create');
    }

    public function update(User $user, TaxBracketSet $taxBracketSet): bool
    {
        return $this->hasPermission($user, 'tax_bracket_sets.update')
            && $this->sameBranch($user, $taxBracketSet);
    }

    public function delete(User $user, TaxBracketSet $taxBracketSet): bool
    {
        return $this->hasPermission($user, 'tax_bracket_sets.delete')
            && $this->sameBranch($user, $taxBracketSet);
    }
}
