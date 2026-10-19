<?php

namespace App\Policies;

use App\Models\Purchase\PurchaseOrder;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PurchaseOrderPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'purchase_orders.view_any');
    }

    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->hasPermission($user, 'purchase_orders.view')
            && $this->sameBranch($user, $purchaseOrder);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'purchase_orders.create');
    }

    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->hasPermission($user, 'purchase_orders.update')
            && $this->sameBranch($user, $purchaseOrder);
    }

    public function delete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->hasPermission($user, 'purchase_orders.delete')
            && $this->sameBranch($user, $purchaseOrder);
    }
}
