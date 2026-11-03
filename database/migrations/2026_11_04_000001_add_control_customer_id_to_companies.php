<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which customer of the control center a company was made for.
 *
 * The control center can create a company here (POST /api/control/provision).
 * A request for the same customer must never create a second company, even if
 * it is sent twice or at the same moment, so the customer id is unique on the
 * company itself and the database settles any race. Companies not made that
 * way leave it empty.
 *
 * `companies` has no company_id and no row level security policy, so it can be
 * looked up before any company is chosen, which is what this needs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->char('control_customer_id', 26)->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropUnique(['control_customer_id']);
            $table->dropColumn('control_customer_id');
        });
    }
};
