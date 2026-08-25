<?php

namespace App\Policies\Hr;

use App\Models\Hr\EmployeeDocument;
use App\Models\User;
use App\Policies\BasePolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmployeeDocumentPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'employee_documents.view_any');
    }

    public function view(User $user, EmployeeDocument $document): bool
    {
        return $this->hasPermission($user, 'employee_documents.view')
            && $this->sameBranch($user, $document);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'employee_documents.create');
    }

    public function update(User $user, EmployeeDocument $document): bool
    {
        return $this->hasPermission($user, 'employee_documents.update')
            && $this->sameBranch($user, $document);
    }

    public function delete(User $user, EmployeeDocument $document): bool
    {
        return $this->hasPermission($user, 'employee_documents.delete')
            && $this->sameBranch($user, $document);
    }
}
