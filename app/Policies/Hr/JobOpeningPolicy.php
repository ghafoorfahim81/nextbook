<?php

namespace App\Policies\Hr;

use App\Models\Hr\JobOpening;
use App\Models\User;
use App\Policies\BasePolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

class JobOpeningPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'job_openings.view_any');
    }

    public function view(User $user, JobOpening $jobOpening): bool
    {
        return $this->hasPermission($user, 'job_openings.view')
            && $this->sameBranch($user, $jobOpening);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'job_openings.create');
    }

    public function update(User $user, JobOpening $jobOpening): bool
    {
        return $this->hasPermission($user, 'job_openings.update')
            && $this->sameBranch($user, $jobOpening);
    }

    public function delete(User $user, JobOpening $jobOpening): bool
    {
        return $this->hasPermission($user, 'job_openings.delete')
            && $this->sameBranch($user, $jobOpening);
    }
}
