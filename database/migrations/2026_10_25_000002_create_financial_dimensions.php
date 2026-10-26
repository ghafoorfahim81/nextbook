<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7: financial dimensions.
 *
 * Cost centers, not more accounts. One "Rent" account with a cost center on
 * the line says where the cost belongs; a "Rent — Kabul", "Rent — Herat" account
 * per unit makes the chart of accounts unusable within a year.
 *
 *  - cost_centers: a tree per company ("Head office", "Marketing", …),
 *    independent of the branch tree. Chosen on expenses and journal lines.
 *  - allocation_rules + allocation_rule_shares: how a shared cost is split
 *    across branches. A rule's shares are dated (effective_from), so changing
 *    the split never rewrites a report for a date before the change.
 *  - transaction_lines.branch_id: a voucher has one branch on its header, but
 *    an allocated expense puts its cost in several. A line with a branch
 *    belongs to that branch; a line without one belongs to the voucher's.
 *  - transaction_lines.cost_center_id (journal entries post their lines
 *    straight to the ledger), expenses.cost_center_id / allocation_rule_id.
 *
 * The three new tables carry a company, so each gets the company isolation
 * policy here: the row level security migration only covered the tables that
 * existed when it ran.
 */
return new class extends Migration
{
    private const NEW_TABLES = ['cost_centers', 'allocation_rules', 'allocation_rule_shares'];

    public function up(): void
    {
        Schema::create('cost_centers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('company_id');
            $table->ulid('parent_id')->nullable();
            $table->string('code', 30)->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->ulid('created_by')->nullable();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'id']);
            $table->index(['company_id', 'parent_id']);
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            // A parent is one of the same company's cost centers.
            $table->foreign(['company_id', 'parent_id'])->references(['company_id', 'id'])->on('cost_centers');
        });

        Schema::create('allocation_rules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('company_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->ulid('created_by')->nullable();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'id']);
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });

        Schema::create('allocation_rule_shares', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('company_id');
            $table->ulid('allocation_rule_id');
            $table->ulid('branch_id');
            // The shares with the same date are one version of the rule; the
            // version used for an expense is the latest on or before its date.
            $table->date('effective_from');
            $table->decimal('percentage', 7, 4);
            $table->timestamps();

            $table->unique(['allocation_rule_id', 'effective_from', 'branch_id']);
            $table->index(['company_id', 'allocation_rule_id', 'effective_from']);
            $table->foreign(['company_id', 'allocation_rule_id'])->references(['company_id', 'id'])->on('allocation_rules')->cascadeOnDelete();
            $table->foreign(['company_id', 'branch_id'])->references(['company_id', 'id'])->on('branches');
        });

        Schema::table('transaction_lines', function (Blueprint $table) {
            $table->ulid('branch_id')->nullable();
            $table->ulid('cost_center_id')->nullable();

            $table->index(['company_id', 'branch_id']);
            $table->index(['company_id', 'cost_center_id']);
            $table->foreign(['company_id', 'branch_id'])->references(['company_id', 'id'])->on('branches');
            $table->foreign(['company_id', 'cost_center_id'])->references(['company_id', 'id'])->on('cost_centers');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->ulid('cost_center_id')->nullable();
            $table->ulid('allocation_rule_id')->nullable();
            $table->foreign(['company_id', 'cost_center_id'])->references(['company_id', 'id'])->on('cost_centers');
            $table->foreign(['company_id', 'allocation_rule_id'])->references(['company_id', 'id'])->on('allocation_rules');
        });

        foreach (self::NEW_TABLES as $table) {
            $this->installPolicy($table);
        }
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['company_id', 'cost_center_id']);
            $table->dropForeign(['company_id', 'allocation_rule_id']);
            $table->dropColumn(['cost_center_id', 'allocation_rule_id']);
        });

        Schema::table('transaction_lines', function (Blueprint $table) {
            $table->dropForeign(['company_id', 'branch_id']);
            $table->dropForeign(['company_id', 'cost_center_id']);
            $table->dropIndex(['company_id', 'branch_id']);
            $table->dropIndex(['company_id', 'cost_center_id']);
            $table->dropColumn(['branch_id', 'cost_center_id']);
        });

        Schema::dropIfExists('allocation_rule_shares');
        Schema::dropIfExists('allocation_rules');
        Schema::dropIfExists('cost_centers');
    }

    /** The same rule 2026_10_21_000001 installs on every company table. */
    private function installPolicy(string $table): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
        DB::statement("DROP POLICY IF EXISTS {$table}_company_isolation ON {$table}");
        DB::statement(
            "CREATE POLICY {$table}_company_isolation ON {$table}
                FOR ALL
                USING (company_id = current_setting('app.company_id', true)::char(26))
                WITH CHECK (company_id = current_setting('app.company_id', true)::char(26))"
        );
    }
};
