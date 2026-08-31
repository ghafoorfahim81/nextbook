<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wipe every transaction and opening, keeping only the setup a company needs to
 * start trading again: users and permissions, administration/reference data, the
 * chart of accounts, items, ledgers (customers/suppliers) and expense categories.
 *
 * The list below is a KEEP list, not a purge list. Anything in the schema that is
 * not named here is treated as transactional and truncated — so a table added
 * later is purged by default rather than being silently missed, and the only way
 * to preserve something new is to say so explicitly.
 *
 * All purged tables go in ONE `TRUNCATE` statement. Postgres then requires the
 * set to be closed under foreign keys: if a table we are keeping still points at
 * one we are wiping, the statement fails and names it, instead of leaving the
 * database half-emptied with dangling references.
 */
class ApplyChanges extends Command
{
    use ConfirmableTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'apply:changes
                            {--dry-run : List what would be truncated, and the row counts, without writing}
                            {--keep=* : Extra table(s) to preserve on top of the built-in keep list}
                            {--force : Run without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete all transactions and openings, keeping users, administration, items, ledgers, accounts and expense categories';

    /**
     * Tables that survive. Everything else in the public schema is truncated.
     *
     * @var array<int, string>
     */
    private const KEEP = [
        // --- Framework / infrastructure -------------------------------------
        'migrations',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'sessions',
        'password_reset_tokens',
        'password_histories',
        'personal_access_tokens',

        // --- Users, roles, permissions --------------------------------------
        'users',
        'roles',
        'permissions',
        'role_has_permissions',
        'model_has_roles',
        'model_has_permissions',

        // --- Administration / reference data --------------------------------
        'companies',
        'branches',
        'countries',
        'provinces',
        'currencies',
        'currency_rate_updates',
        'warehouses',
        'unit_measures',
        'quantities',
        'brands',
        'categories',
        'sizes',
        'customer_groups',
        'payment_terms',
        'invoice_formats',
        'financial_periods',

        // --- Chart of accounts, parties, catalogue --------------------------
        'accounts',
        'account_types',
        'journal_classes',
        'ledgers',
        'items',
        'owners',
        'expense_categories',

        // Attachments hang off ledgers and items as well as off vouchers, so the
        // table is kept and only the voucher-side rows are removed below.
        'attachments',

        // --- HR setup (HR *transactions* are purged) ------------------------
        'hr_settings',
        'departments',
        'designations',
        'employees',
        'employee_contracts',
        'employee_documents',
        'shifts',
        'holidays',
        'leave_types',
        'salary_components',
        'salary_structures',
        'salary_structure_lines',
        'tax_bracket_sets',
        'tax_brackets',
        'attendance_devices',
        'attendance_device_users',
        'job_openings',
        'job_applications',
        'interviews',
        'interview_panelists',
    ];

    /**
     * Morph values whose attachments survive — the kept parties and catalogue.
     *
     * Both the morph alias (AppServiceProvider enforces a morph map) and the FQCN
     * are listed, because rows written before the map was enforced still carry the
     * class name.
     *
     * @var array<int, string>
     */
    private const KEEP_ATTACHMENT_TYPES = [
        'ledger', 'App\Models\Ledger\Ledger',
        'item', 'App\Models\Inventory\Item',
        'account', 'App\Models\Account\Account',
        'user', 'App\Models\User',
        'employee', 'App\Models\Hr\Employee',
        'employee_contract', 'App\Models\Hr\EmployeeContract',
        'employee_document', 'App\Models\Hr\EmployeeDocument',
        'owner', 'App\Models\Owner\Owner',
    ];

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $dryRun = (bool) $this->option('dry-run');

        $keep = array_unique(array_merge(self::KEEP, (array) $this->option('keep')));
        $purge = $this->resolvePurgeTables($keep);

        if ($purge === []) {
            $this->info('Nothing to purge — every table is on the keep list.');

            return self::SUCCESS;
        }

        $counts = $this->rowCounts($purge);
        $total = array_sum($counts);

        $this->renderPlan($counts, $keep, $total);

        if ($dryRun) {
            $this->newLine();
            $this->warn('Dry run — nothing was written. Re-run without --dry-run to apply.');

            return self::SUCCESS;
        }

        // ConfirmableTrait: prompts here, and in production demands --force.
        if (! $this->confirmToProceed('This permanently deletes ' . number_format($total) . ' row(s) of transactional data')) {
            return self::FAILURE;
        }

        DB::transaction(function () use ($purge) {
            $this->truncate($purge);
            $this->cleanKeptTables();
        });

        Cache::flush();

        $this->newLine();
        $this->info('Done. ' . number_format($total) . ' row(s) removed across ' . count($purge) . ' table(s).');
        $this->line('Kept: users & permissions, administration, accounts, ledgers, items, expense categories.');

        return self::SUCCESS;
    }

    /**
     * Every table in the public schema that is not on the keep list.
     *
     * @param  array<int, string>  $keep
     * @return array<int, string>
     */
    private function resolvePurgeTables(array $keep): array
    {
        $tables = collect(DB::select(
            "select tablename from pg_tables where schemaname = current_schema() order by tablename"
        ))->pluck('tablename')->all();

        return array_values(array_diff($tables, $keep));
    }

    /**
     * @param  array<int, string>  $tables
     * @return array<string, int>
     */
    private function rowCounts(array $tables): array
    {
        $counts = [];

        foreach ($tables as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        arsort($counts);

        return $counts;
    }

    /**
     * @param  array<string, int>  $counts
     * @param  array<int, string>  $keep
     */
    private function renderPlan(array $counts, array $keep, int $total): void
    {
        $this->newLine();
        $this->line('<comment>Will be emptied:</comment>');
        $this->table(
            ['Table', 'Rows'],
            collect($counts)->map(fn ($count, $table) => [$table, number_format($count)])->values()->all()
        );

        $kept = array_values(array_intersect($keep, array_keys(
            collect(DB::select("select tablename from pg_tables where schemaname = current_schema()"))
                ->pluck('tablename', 'tablename')
                ->all()
        )));

        $this->line('<comment>Will be kept (' . count($kept) . ' tables):</comment> ' . implode(', ', $kept));
        $this->newLine();
        $this->line('Total rows to delete: <info>' . number_format($total) . '</info>');
    }

    /**
     * One statement, so Postgres validates the set is closed under foreign keys.
     *
     * RESTART IDENTITY resets the few sequence-backed tables; almost everything
     * here is ULID-keyed and unaffected.
     *
     * @param  array<int, string>  $tables
     */
    private function truncate(array $tables): void
    {
        $quoted = collect($tables)
            ->map(fn ($table) => '"' . str_replace('"', '""', $table) . '"')
            ->implode(', ');

        DB::statement("TRUNCATE TABLE {$quoted} RESTART IDENTITY");
    }

    /**
     * Repair the kept tables that carried figures derived from what was purged.
     */
    private function cleanKeptTables(): void
    {
        // Every stock layer is gone, so a running average cost is meaningless.
        if (Schema::hasColumn('items', 'avg_cost')) {
            $items = DB::table('items')->update(['avg_cost' => 0]);
            $this->line("  items.avg_cost reset on {$items} item(s).");
        }

        // Attachments whose owning voucher no longer exists.
        if (Schema::hasTable('attachments')) {
            $orphans = DB::table('attachments')
                ->whereNotIn('attachable_type', self::KEEP_ATTACHMENT_TYPES)
                ->delete();

            $this->line("  {$orphans} voucher attachment row(s) removed.");
        }
    }
}
