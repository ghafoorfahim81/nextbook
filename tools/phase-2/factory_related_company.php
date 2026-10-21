<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * Factory defaults that build a related row in a row-level-security table
 * without saying which company it belongs to.
 *
 *     php tools/phase-2/factory_related_company.php
 *     php tools/phase-2/factory_related_company.php --all
 *
 * A default like `'item_id' => Item::factory()` looks harmless, but
 * ItemFactory, AccountFactory and LedgerFactory each fall back to a
 * `Company::factory()` of their own. So the parent ends up pointing at a row in
 * a company that exists for no other reason than that nobody said otherwise.
 *
 * Before step 10 this was merely incoherent fixture data and nothing noticed —
 * ItemFactory had been giving every test item three posting accounts in three
 * different companies for as long as the factory existed. Now the policies
 * refuse the insert, so the ones that are actually reached fail loudly.
 *
 * The ones that are *not* reached are what this script is for: they are latent,
 * and each becomes a failing test the moment someone widens the policies to
 * cover another table or writes a test that leans on the default. Fix them the
 * way ItemFactory was fixed — declare `company_id` first in the definition and
 * derive the related row from it, since Laravel expands a definition in order
 * and hands each closure the attributes already resolved.
 *
 * By default only tables that currently have a policy are reported. `--all`
 * widens it to every company-scoped model, which is the list to work through
 * before extending the policies.
 */

$all = in_array('--all', $argv, true);

/** Models whose tables carry a policy today (see the step 10 migration). */
$protected = [
    'Transaction', 'TransactionLine', 'Sale', 'Purchase',
    'Ledger', 'Account', 'Item', 'StockMovement', 'StockBalance',
];

/** Everything else company-scoped that a factory commonly reaches for. */
$alsoCompanyScoped = [
    'Brand', 'Category', 'Currency', 'UnitMeasure', 'Quantity', 'Size',
    'AccountType', 'Warehouse', 'Employee', 'ExpenseCategory', 'JournalClass',
    'Department', 'Designation', 'Shift', 'LeaveType', 'PaymentTerm',
];

$models = $all ? array_merge($protected, $alsoCompanyScoped) : $protected;

$hits = [];

foreach (phpFiles(repo('database/factories')) as $path) {
    $display = rel($path);

    // A factory referring to its own model is not building a related row.
    $own = basename($display, 'Factory.php');

    foreach (file($path, FILE_IGNORE_NEW_LINES) as $i => $line) {
        // A comment explaining the fixed form names the old one, so reading
        // comments would report every factory that documents its own fix.
        if (preg_match('#^\s*(//|\*|/\*|\#)#', $line)) {
            continue;
        }

        foreach ($models as $model) {
            if ($model === $own || ! preg_match('/\b' . $model . '::factory\(\)/', $line)) {
                continue;
            }

            // Naming a company on the same line is the fixed form.
            if (str_contains($line, 'company_id')) {
                continue;
            }

            $hits[] = sprintf('%s:%d  [%s]  %s', $display, $i + 1, $model, trim($line));
        }
    }
}

sort($hits);

printf(
    "RELATED ROW WITH NO COMPANY (%d)%s:\n",
    count($hits),
    $all ? ' — every company-scoped model' : ' — tables under a policy today'
);

foreach ($hits as $hit) {
    echo "  {$hit}\n";
}

if ($hits === []) {
    echo "  none\n";
}
