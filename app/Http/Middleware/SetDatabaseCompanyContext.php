<?php

namespace App\Http\Middleware;

use App\Support\CompanyDatabaseContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Copies the company resolved for this request onto the database connection,
 * where the row level security policies can read it.
 *
 * Runs immediately after SetActiveCompany and deliberately reads the same
 * container binding rather than the user or the request: whatever the
 * application believes the active company is, that is what Postgres is told,
 * so the two can never disagree.
 *
 * On a request with no company — login, registration, the company creation
 * form — the setting is cleared rather than left alone. The policies compare
 * for equality, so a cleared setting means the connection can see none of the
 * protected tables, which is the right answer for a caller that has not yet
 * established which books it is looking at.
 */
class SetDatabaseCompanyContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $companyId = app()->bound('active_company_id')
            ? (string) app('active_company_id')
            : null;

        CompanyDatabaseContext::set($companyId ?: null);

        return $next($request);
    }
}
