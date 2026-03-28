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

    /**
     * Resolve the permission prefixes that apply to the current ledger context.
     *
     * We support both the historical generic `ledgers.*` permissions and the
     * seeded `customers.*` / `suppliers.*` permissions that the UI actually uses.
     *
     * @return array<int, string>
     */
    protected function permissionPrefixes(?Ledger $ledger = null): array
    {
        $type = $ledger?->type?->value ?? $ledger?->type ?? request()->input('type');
        $routeName = request()->route()?->getName() ?? '';

        if ($type === 'customer' || str_contains($routeName, 'customers.')) {
            return ['customers', 'ledgers'];
        }

        if ($type === 'supplier' || str_contains($routeName, 'suppliers.')) {
            return ['suppliers', 'ledgers'];
        }

        return ['ledgers', 'customers', 'suppliers'];
    }

    protected function canFor(User $user, string $action, ?Ledger $ledger = null): bool
    {
        foreach ($this->permissionPrefixes($ledger) as $prefix) {
            if ($this->hasPermission($user, "{$prefix}.{$action}")) {
                return true;
            }
        }

        return false;
    }

    public function viewAny(User $user): bool
    {
        return $this->canFor($user, 'view_any');
    }

    public function view(User $user, Ledger $ledger): bool
    {
        return $this->canFor($user, 'view', $ledger)
            && $this->sameBranch($user, $ledger);
    }

    public function create(User $user): bool
    {
        return $this->canFor($user, 'create');
    }

    public function update(User $user, Ledger $ledger): bool
    {
        return $this->canFor($user, 'update', $ledger)
            && $this->sameBranch($user, $ledger);
    }

    public function delete(User $user, Ledger $ledger): bool
    {
        return $this->canFor($user, 'delete', $ledger)
            && $this->sameBranch($user, $ledger);
    }
}

