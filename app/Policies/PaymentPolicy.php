<?php

namespace App\Policies;

use App\Models\Payment\Payment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PaymentPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'payments.view');
    }

    public function view(User $user, Payment $payment): bool
    {
        return $this->hasPermission($user, 'payments.view')
            && $this->sameBranch($user, $payment);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'payments.create');
    }

    public function update(User $user, Payment $payment): bool
    {
        return $this->hasPermission($user, 'payments.update')
            && $this->sameBranch($user, $payment);
    }

    public function delete(User $user, Payment $payment): bool
    {
        return $this->hasPermission($user, 'payments.delete')
            && $this->sameBranch($user, $payment);
    }
}


