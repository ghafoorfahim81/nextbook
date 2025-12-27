<?php

namespace App\Policies;

use App\Models\User;

abstract class BasePolicy
{
    /**
     * Check if the user has the given permission.
     */
    protected function hasPermission(User $user, string $permission): bool
    {
        return $user->can($permission);
    }

    /**
     * Enforce branch-based access if the model is branch aware.
     *
     * If the model does not have a branch_id attribute, branch checks are skipped.
     */
    protected function sameBranch(User $user, mixed $model): bool
    {
        // If the model is not branch-scoped, allow through here (permission still required separately)
        if (!isset($model->branch_id)) {
            return true;
        }

        if ($user->branch_id === null || $model->branch_id === null) {
            return false;
        }

        return (string) $user->branch_id === (string) $model->branch_id;
    }
}


