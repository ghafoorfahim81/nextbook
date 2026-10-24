<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Contracts\PermissionsTeamResolver;

/**
 * Spatie "teams" are companies (phase 6): roles are assigned per company, so
 * a user can be an admin in one and a cashier in another.
 *
 * The team is the company the request acts in, read from CompanyContext — the
 * same answer the company scopes and the row level security policies get — so
 * permissions can never be checked against a different company than the data.
 * Code that has to act for another company (creating one, the access screen)
 * sets it explicitly with setPermissionsTeamId() and puts it back.
 */
class CompanyTeamResolver implements PermissionsTeamResolver
{
    protected int|string|null $teamId = null;

    protected bool $explicit = false;

    public function setPermissionsTeamId($id): void
    {
        if ($id instanceof Model) {
            $id = $id->getKey();
        }

        $this->teamId = $id;
        $this->explicit = $id !== null;
    }

    public function getPermissionsTeamId(): int|string|null
    {
        return $this->explicit ? $this->teamId : CompanyContext::companyId();
    }
}
