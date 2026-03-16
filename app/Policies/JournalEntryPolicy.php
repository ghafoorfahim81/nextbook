<?php

namespace App\Policies;

use App\Models\JournalEntry\JournalEntry;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class JournalEntryPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'journal_entries.view_any');
    }

    public function view(User $user, JournalEntry $journalEntry): bool
    {
        return $this->hasPermission($user, 'journal_entries.view')
         && $this->sameBranch($user, $journalEntry);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'journal_entries.create');
    }

    public function update(User $user, JournalEntry $journalEntry): bool
    {
        return $this->hasPermission($user, 'journal_entries.update')
            && $this->sameBranch($user, $journalEntry);
    }

    public function delete(User $user, JournalEntry $journalEntry): bool
    {
        return $this->hasPermission($user, 'journal_entries.delete')
                && $this->sameBranch($user, $journalEntry);
    }
}


