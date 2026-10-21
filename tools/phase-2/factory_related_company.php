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
 * refuse the insert, so the ones that are actually reached fail loudly. The
 * ones that are *not* reached are what this script is for: they are latent, and
 * each becomes a failing test the moment someone widens the policies or writes
 * a test that leans on the default.
 *
 * The fix is to declare `company_id` (or the branch it is derived from) before
 * the related key and build the related row in that company, since Laravel
 * expands a definition in order and hands each closure what has already
 * resolved. See ItemFactory or SaleFactory.
 *
 * By default only tables that carry a policy today are reported. `--all`
 * widens it to every company-scoped model, which is the list to work through
 * before extending the policies.
 *
 * ## Two things this deliberately does not report
 *
 *  - The fixed form, where the related row is built with a company. The check
 *    reads the whole array element rather than one line, because the fix puts
 *    the company on the line after the key.
 *  - A line factory building its own parent document — `SaleItemFactory` with
 *    `'sale_id' => Sale::factory()`. The line takes its company *from* that
 *    document, so a parent with a company of its own is correct, not a leak.
 *    These are recognised by the model appearing in the factory's own
 *    `company_id` derivation.
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
    $source = file_get_contents($path);
    $lines = file($path, FILE_IGNORE_NEW_LINES);

    // A factory referring to its own model is not building a related row.
    $own = basename($display, 'Factory.php');

    // The model this factory reads its own company off, if any. A reference to
    // that model is the parent document, and the parent owning the company is
    // the whole point.
    $parent = null;
    if (preg_match("/'company_id'\s*=>.*?(\w+)::withoutGlobalScopes/s", $source, $m)) {
        $parent = $m[1];
    }

    foreach ($lines as $i => $line) {
        // A comment explaining the fixed form names the old one, so reading
        // comments would report every factory that documents its own fix.
        if (preg_match('#^\s*(//|\*|/\*|\#)#', $line)) {
            continue;
        }

        foreach ($models as $model) {
            if ($model === $own || $model === $parent) {
                continue;
            }

            if (! preg_match('/\b' . $model . '::factory\(\)/', $line)) {
                continue;
            }

            // The fix spans several lines: the key and the `->create([...])`
            // that names the company sit on separate lines. Read the whole
            // array element — up to the next key at the *same* indentation, or
            // the end of the definition.
            //
            // Indentation is what makes this work. The fix nests its own
            // `'branch_id' =>` or `'company_id' =>` inside the element, and
            // stopping at any key at all would cut the element off just before
            // the line that proves it is fixed.
            $indent = strlen($line) - strlen(ltrim($line));
            $element = $line;

            for ($j = $i + 1; $j < count($lines) && $j <= $i + 12; $j++) {
                $next = $lines[$j];
                $nextIndent = strlen($next) - strlen(ltrim($next));

                $isSiblingKey = preg_match("/^\s*'\w+'\s*=>/", $next) && $nextIndent <= $indent;

                if ($isSiblingKey || preg_match('/^\s*\];/', $next)) {
                    break;
                }

                $element .= "\n" . $next;
            }

            // `branch_id` counts as well as `company_id`: a document's company
            // is derived from its branch — the composite foreign key enforces
            // that they agree — so building a related document in this one's
            // branch puts it in this one's company too.
            if (str_contains($element, 'company_id') || str_contains($element, 'branch_id')) {
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
