<?php

namespace App\Policies\Hr;

use App\Models\Hr\JobApplication;
use App\Models\User;
use App\Policies\BasePolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

class JobApplicationPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'job_applications.view_any');
    }

    public function view(User $user, JobApplication $jobApplication): bool
    {
        return $this->hasPermission($user, 'job_applications.view')
            && $this->sameBranch($user, $jobApplication);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'job_applications.create');
    }

    public function update(User $user, JobApplication $jobApplication): bool
    {
        return $this->hasPermission($user, 'job_applications.update')
            && $this->sameBranch($user, $jobApplication);
    }

    public function delete(User $user, JobApplication $jobApplication): bool
    {
        return $this->hasPermission($user, 'job_applications.delete')
            && $this->sameBranch($user, $jobApplication);
    }
}
