<?php

namespace App\Policies;

use App\Models\Accounting\FiscalYear;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Closing the books, reopening them, and posting into a closed month are three
 * separate authorities — see the permission list in RolePermissionSeeder. An
 * accountant closes; only an administrator reopens or overrides.
 */
class FiscalYearPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'financial_periods.view_any');
    }

    public function view(User $user, FiscalYear $fiscalYear): bool
    {
        return $this->hasPermission($user, 'financial_periods.view')
            && $this->sameBranch($user, $fiscalYear);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'financial_periods.create');
    }

    public function update(User $user, FiscalYear $fiscalYear): bool
    {
        return $this->hasPermission($user, 'financial_periods.update')
            && $this->sameBranch($user, $fiscalYear);
    }

    public function delete(User $user, FiscalYear $fiscalYear): bool
    {
        return $this->hasPermission($user, 'financial_periods.delete')
            && $this->sameBranch($user, $fiscalYear);
    }

    public function close(User $user, FiscalYear $fiscalYear): bool
    {
        return $this->hasPermission($user, 'financial_periods.close')
            && $this->sameBranch($user, $fiscalYear);
    }

    public function reopen(User $user, FiscalYear $fiscalYear): bool
    {
        return $this->hasPermission($user, 'financial_periods.reopen')
            && $this->sameBranch($user, $fiscalYear);
    }
}
