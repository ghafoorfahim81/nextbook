<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a salary component say where its money is OWED, not just where it is
 * expensed.
 *
 * Two economic cases were being posted wrongly because there was nowhere to
 * record them:
 *
 *   1. A REMITTABLE deduction — pension, social security, union dues — is
 *      money withheld from the employee that the company must pass on to a
 *      third party. It was reducing salary expense, which both understated
 *      staff cost and left no record of the obligation.
 *
 *   2. An EMPLOYER CONTRIBUTION is an additional company cost on top of the
 *      gross. It was also reducing salary expense, so a 1,000 contribution
 *      moved the profit and loss 2,000 in the wrong direction.
 *
 * Both now need a liability account to credit; `account_id` continues to carry
 * the expense side.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::table('salary_components', function (Blueprint $table) {
            // Deduction: withheld from the employee and owed onward, rather
            // than simply reducing what the company spends.
            $table->boolean('is_remittable')->default(false)->after('affects_gross');

            $table->ulid('liability_account_id')->nullable()->index()->after('account_id');
        });

        Schema::table('salary_components', function (Blueprint $table) {
            $table->foreign('liability_account_id')
                ->references('id')->on('accounts')
                ->onDelete('set null');
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::table('salary_components', function (Blueprint $table) {
            $table->dropForeign(['liability_account_id']);
            $table->dropColumn(['is_remittable', 'liability_account_id']);
        });
    }
};
