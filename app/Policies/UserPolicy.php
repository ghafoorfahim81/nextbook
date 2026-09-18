<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'users.view_any');
    }

    public function view(User $user, User $model): bool
    {
        return $this->hasPermission($user, 'users.view')
            && $this->sameCompany($user, $model);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'users.create');
    }

    public function update(User $user, User $model): bool
    {
        return $this->hasPermission($user, 'users.update')
            && $this->sameCompany($user, $model);
    }

    public function delete(User $user, User $model): bool
    {
        // Prevent deleting self even if permission exists; controller also guards this.
        if ($user->id === $model->id) {
            return false;
        }

        return $this->hasPermission($user, 'users.delete')
            && $this->sameCompany($user, $model);
    }

    /**
     * `authorizeResource` only covers the seven RESTful actions, so restore
     * and forceDelete were reachable by any authenticated user.
     *
     * Bringing a revoked login back is the same authority as revoking it, so
     * both reuse `users.delete`.
     */
    public function restore(User $user, User $model): bool
    {
        return $this->hasPermission($user, 'users.delete')
            && $this->sameCompany($user, $model);
    }

    public function forceDelete(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false;
        }

        return $this->hasPermission($user, 'users.delete')
            && $this->sameCompany($user, $model);
    }

    /**
     * Users are managed company-wide, so a company admin must not reach an
     * account belonging to another tenant through route-model binding.
     *
     * A user with no company yet (registered but never onboarded) is left
     * reachable: there is no tenant to protect, and locking it away would
     * make the row unmanageable.
     */
    private function sameCompany(User $user, User $model): bool
    {
        if ($model->company_id === null) {
            return true;
        }

        return (string) $user->company_id === (string) $model->company_id;
    }
}
