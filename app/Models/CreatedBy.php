<?php

namespace App\Models;

use Database\Factories\UserFactory;

class CreatedBy extends User
{
    /**
     * Reuse the user factory so existing tests can create audit users.
     */
    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
