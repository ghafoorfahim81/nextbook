<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * Give the branch-scoped factories a company_id derived from their branch.
 *
 * `'company_id' => Company::factory()` would be wrong here: it builds a second,
 * unrelated company, and the composite foreign key correctly rejects a branch
 * of company A on a document of company B. The company is read back off the
 * branch instead, as a closure so it resolves after branch_id does.
 *
 *     php tools/phase-2/factory_company.php --dry
 */

$dry = in_array('--dry', $argv, true);
$needle = "'branch_id' => Branch::factory(),";

$replacement = <<<'PHP'
'branch_id' => Branch::factory(),
            // Read off the branch rather than built separately: a document's
            // company and its branch's company have to agree, and the composite
            // foreign key enforces that.
            'company_id' => fn (array $attributes) => Branch::withoutGlobalScopes()
                ->whereKey($attributes['branch_id'])
                ->value('company_id'),
PHP;

$report = [];

foreach (phpFiles(repo('database/factories')) as $path) {
    $src = file_get_contents($path);

    if (! str_contains($src, $needle)) {
        continue;
    }
    if (str_contains($src, "'company_id'")) {
        $report[] = 'already  ' . rel($path);
        continue;
    }

    $src = str_replace($needle, $replacement, $src);

    if (! str_contains($src, 'use App\Models\Administration\Branch;')) {
        $src = preg_replace(
            '/^(use .*?;)$/m',
            "use App\Models\Administration\Branch;\n$1",
            $src,
            1
        );
    }

    if (! $dry) {
        file_put_contents($path, $src);
    }
    $report[] = 'changed  ' . rel($path);
}

echo implode("\n", $report) . "\n\n" . count($report) . ' factories' . ($dry ? ' (dry run)' : '') . "\n";
