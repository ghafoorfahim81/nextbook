<?php

namespace App\Policies;

use App\Models\Administration\Company;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CompanyPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'companies.view');
    }

    public function view(User $user, Company $company): bool
    {
        return $this->hasPermission($user, 'companies.view');
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'companies.create');
    }

    public function update(User $user, Company $company): bool
    {
        return $this->hasPermission($user, 'companies.update');
    }

    public function delete(User $user, Company $company): bool
    {
        return $this->hasPermission($user, 'companies.delete');
    }
}


