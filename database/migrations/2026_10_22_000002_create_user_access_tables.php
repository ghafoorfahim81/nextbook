<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5: which companies, and which of their branches, a user may act in.
 *
 * Two levels, as the plan sets out: a user can belong to a company yet reach
 * only some of its branches — a Herat cashier sees Herat. Branch access is tied
 * to company access by a foreign key, so a branch grant cannot exist without
 * the company grant above it, and to the branch by the composite key on
 * (company_id, branch_id), so it cannot name another company's branch.
 *
 * A user's home company and branch (users.company_id / users.branch_id) count
 * as accessible without a row here; these tables hold the access granted on
 * top of that.
 *
 * Neither table is under row level security. SetActiveCompany reads them to
 * decide which company the request acts in, before any company is on the
 * connection — the same reason users and branches are exempt. They hold
 * membership, not books, and who may read or change them is decided in the
 * application (super-admins only).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_company_access', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('user_id');
            $table->ulid('company_id');
            $table->ulid('created_by')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'company_id']);
            $table->index('company_id');

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('user_branch_access', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('user_id');
            $table->ulid('company_id');
            $table->ulid('branch_id');
            $table->ulid('created_by')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'branch_id']);
            $table->index(['company_id', 'branch_id']);

            // Only a branch of the company the grant is filed under.
            $table->foreign(['company_id', 'branch_id'])
                ->references(['company_id', 'id'])->on('branches')
                ->cascadeOnDelete();

            // Only under a company the user has been given.
            $table->foreign(['user_id', 'company_id'])
                ->references(['user_id', 'company_id'])->on('user_company_access')
                ->cascadeOnDelete();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_branch_access');
        Schema::dropIfExists('user_company_access');
    }
};
