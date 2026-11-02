<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Makes a user the platform admin, or takes it away (phase 10).
 *
 * Deliberately a command and not a screen: it opens a panel that lists every
 * customer, so it is granted from the server, by someone who can run commands.
 *
 *   php artisan platform:admin owner@example.com
 *   php artisan platform:admin owner@example.com --revoke
 */
class MakePlatformAdmin extends Command
{
    protected $signature = 'platform:admin {email : The user\'s email} {--revoke : Take platform admin away instead}';

    protected $description = 'Grant or revoke platform admin, which opens the /platform panel';

    public function handle(): int
    {
        // users is read before any company is known, so it sits outside the
        // row level security and the command needs no company context.
        $user = User::query()->where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No user with that email.');

            return self::FAILURE;
        }

        $user->forceFill(['is_platform_admin' => ! $this->option('revoke')])->save();

        $this->info($this->option('revoke')
            ? "{$user->email} is no longer a platform admin."
            : "{$user->email} is a platform admin: the Platform menu is under Settings & Administration.");

        return self::SUCCESS;
    }
}
