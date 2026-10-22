<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * CompanyContext::glAccount / glAccounts / homeCurrency take a COMPANY now —
 * the chart of accounts and the currency set moved up a level. Any caller
 * still handing them a branch gets null back, with no error.
 *
 * Rewrites the obvious forms and reports anything it will not guess at.
 *
 *     php tools/phase-2/branchcontext_callers.php --dry
 */

$dry = in_array('--dry', $argv, true);

$simple = [
    '->branch_id)' => '->company_id)',
    '->branch_id,' => '->company_id,',
    '$branchId)' => '$companyId)',
    '$branchId,' => '$companyId,',
];

$changed = [];
$review = [];

foreach (phpFiles(repo('app')) as $path) {
    if (str_ends_with(str_replace('\\', '/', $path), 'Support/CompanyContext.php')) {
        continue;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    $dirty = false;

    foreach ($lines as $i => $line) {
        if (! preg_match('/CompanyContext::(glAccount|glAccounts|homeCurrency)\(/', $line)) {
            continue;
        }
        // No argument means "the acting company" — already correct.
        if (preg_match('/CompanyContext::(glAccount|glAccounts|homeCurrency)\(\)/', $line)) {
            continue;
        }
        if (! preg_match('/branch/i', $line)) {
            continue;
        }

        $new = $line;
        foreach ($simple as $from => $to) {
            $new = str_replace($from, $to, $new);
        }

        if ($new === $line) {
            $review[] = rel($path) . ':' . ($i + 1) . '  ' . trim($line);
            continue;
        }

        $lines[$i] = $new;
        $dirty = true;
        $changed[] = rel($path) . ':' . ($i + 1);
    }

    if ($dirty && ! $dry) {
        file_put_contents($path, implode("\n", $lines) . "\n");
    }
}

echo 'rewritten (' . count($changed) . "):\n  " . implode("\n  ", $changed) . "\n\n";
echo $review ? "NEEDS A LOOK:\n  " . implode("\n  ", $review) . "\n" : "nothing left to review\n";
