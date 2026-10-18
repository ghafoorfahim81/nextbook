<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * Give every branch-scoped model the company traits alongside its branch ones.
 *
 * A model that carries both columns needs both scopes: CompanySpecific filters
 * to the readable companies, BranchSpecific narrows to the acting branch, and
 * HasCompany fills company_id on create the way HasBranch fills branch_id.
 *
 * Models that already use CompanySpecific — the master data converted in step 4
 * — are left alone.
 *
 *     php tools/phase-2/add_company_trait.php --dry
 */

$dry = in_array('--dry', $argv, true);
$report = [];

/**
 * User is deliberately excluded. CompanySpecific falls back to
 * `auth()->user()?->company_id` when no company is bound, and resolving the
 * authenticated user is itself a User query — on the guard's first resolution
 * that recurses. Users are also created before any company exists during
 * seeding. Access across companies is a later phase with its own tables.
 */
const EXCLUDE = ['app/Models/User.php'];

foreach (phpFiles(repo('app/Models')) as $path) {
    if (in_array(rel($path), EXCLUDE, true)) {
        $report[] = 'excluded ' . rel($path);
        continue;
    }

    $src = file_get_contents($path);
    $original = $src;

    $usesBranchScope = preg_match('/\buse\s[^;]*\bBranchSpecific\b/', $src);
    $usesBranchFill = preg_match('/\buse\s[^;]*\bHasBranch\b/', $src);

    if (! $usesBranchScope && ! $usesBranchFill) {
        continue;
    }
    if (preg_match('/\bCompanySpecific\b/', $src)) {
        $report[] = 'already  ' . rel($path);
        continue;
    }

    // Imports.
    if (str_contains($src, 'use App\Traits\BranchSpecific;')) {
        $src = str_replace(
            'use App\Traits\BranchSpecific;',
            "use App\Traits\BranchSpecific;\nuse App\Traits\CompanySpecific;",
            $src
        );
    }
    if (str_contains($src, 'use App\Traits\HasBranch;')) {
        $src = str_replace(
            'use App\Traits\HasBranch;',
            "use App\Traits\HasBranch;\nuse App\Traits\HasCompany;",
            $src
        );
    }

    // The `use ...;` line inside the class body. A namespace separator means
    // this is a top-level import instead — those were handled above, and
    // rewriting them produces a duplicate import of the same trait.
    $src = preg_replace_callback(
        '/^(\s*use\s+)([A-Za-z0-9_,\s\\\\]+?)(;\s*)$/m',
        function (array $m) use ($usesBranchScope, $usesBranchFill): string {
            $names = $m[2];
            if (str_contains($names, '\\')) {
                return $m[0];
            }
            if (! str_contains($names, 'BranchSpecific') && ! str_contains($names, 'HasBranch')) {
                return $m[0];
            }
            if (str_contains($names, 'CompanySpecific') || str_contains($names, 'HasCompany')) {
                return $m[0];
            }
            if ($usesBranchScope && str_contains($names, 'BranchSpecific')) {
                $names = preg_replace('/\bBranchSpecific\b/', 'CompanySpecific, BranchSpecific', $names, 1);
            }
            if ($usesBranchFill && str_contains($names, 'HasBranch')) {
                $names = preg_replace('/\bHasBranch\b/', 'HasCompany, HasBranch', $names, 1);
            }

            return $m[1] . $names . $m[3];
        },
        $src
    );

    // Mass assignment.
    if (preg_match('/protected \$fillable = \[/', $src) && ! preg_match('/[\'"]company_id[\'"]\s*,/', $src)) {
        $src = preg_replace(
            '/(protected \$fillable = \[\s*\n)/',
            "$1        'company_id',\n",
            $src,
            1
        );
    }

    if ($src !== $original) {
        if (! $dry) {
            file_put_contents($path, $src);
        }
        $report[] = 'changed  ' . rel($path);
    }
}

echo implode("\n", $report) . "\n\n" . count($report) . ' models' . ($dry ? ' (dry run)' : '') . "\n";
