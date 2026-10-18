<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * Resolve the SQL aliases ReportService uses, so `a.branch_id` can be judged.
 *
 * The service builds its queries with the query builder and short aliases. An
 * alias bound to a company-scoped table has to move to company_id; one bound to
 * a document table keeps branch_id. Nothing about the line itself says which,
 * so the aliases are read out of the from/join clauses instead of guessed at.
 */
$path = repo('app/Services/ReportService.php');
$src = file_get_contents($path);

$map = [];
$patterns = [
    "/->(?:from|join|leftJoin|rightJoin|joinSub|fromSub)\(\s*'([a-z_]+) as ([a-z_0-9]+)'/",
    "/->(?:from|table)\(\s*'([a-z_]+)'\s*,\s*'([a-z_0-9]+)'/",
    "/DB::table\(\s*'([a-z_]+) as ([a-z_0-9]+)'/",
    "/DB::raw\(\s*'\(?\s*([a-z_]+)\s+as\s+([a-z_0-9]+)/",
];

foreach ($patterns as $re) {
    preg_match_all($re, $src, $m, PREG_SET_ORDER);
    foreach ($m as $hit) {
        $map[$hit[2]][$hit[1]] = ($map[$hit[2]][$hit[1]] ?? 0) + 1;
    }
}

// Raw SQL strings: "JOIN transactions t ON ..."
preg_match_all('/\b(?:FROM|JOIN)\s+([a-z_]+)\s+([a-z_0-9]+)\b/i', $src, $m, PREG_SET_ORDER);
foreach ($m as $hit) {
    $alias = strtolower($hit[2]);
    if (in_array($alias, ['on', 'as', 'where', 'left', 'inner', 'join'], true)) {
        continue;
    }
    $map[$alias][strtolower($hit[1])] = ($map[$alias][strtolower($hit[1])] ?? 0) + 1;
}

const COMPANY_SCOPED = [
    'account_types','accounts','items','item_variants','brands','categories',
    'unit_measures','quantities','sizes','currencies','ledgers','customer_groups',
    'payment_terms','discount_rules','expense_categories','journal_classes',
    'landed_cost_categories','financial_periods','owners','departments',
    'designations','shifts','leave_types','holidays','hr_settings',
    'salary_components','salary_structures','salary_structure_lines',
    'tax_bracket_sets','tax_brackets',
];

// Which aliases are actually used with branch_id?
preg_match_all("/['\"]([a-z_0-9]+)\.branch_id['\"]|\b([a-z_0-9]+)\.branch_id\b/", $src, $u, PREG_SET_ORDER);
$used = [];
foreach ($u as $hit) {
    $alias = $hit[1] ?: $hit[2];
    $used[$alias] = ($used[$alias] ?? 0) + 1;
}

ksort($used);
printf("%-8s %-7s %-34s %s\n", 'ALIAS', 'USES', 'RESOLVES TO', 'VERDICT');
foreach ($used as $alias => $count) {
    $tables = $map[$alias] ?? [];
    arsort($tables);
    $names = array_keys($tables);

    $verdict = 'UNKNOWN — read it';
    if ($names !== []) {
        $company = array_intersect($names, COMPANY_SCOPED);
        if (count($names) === 1) {
            $verdict = in_array($names[0], COMPANY_SCOPED, true)
                ? '-> company_id'
                : 'keep branch_id';
        } elseif ($company !== []) {
            $verdict = 'MIXED — read it';
        } else {
            $verdict = 'keep branch_id';
        }
    }

    printf("%-8s %-7d %-34s %s\n", $alias, $count, implode(',', array_slice($names, 0, 3)) ?: '?', $verdict);
}
