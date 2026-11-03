<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The invoices and payments the control center holds for a company, kept so the
 * "My subscription" page can show them. Display only: nothing is enforced from
 * them (the signed licence is), which is why they carry no signature.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_licenses', function (Blueprint $table) {
            $table->json('invoices')->nullable()->after('signature');
            $table->timestamp('invoices_fetched_at')->nullable()->after('fetched_at');
        });
    }

    public function down(): void
    {
        Schema::table('company_licenses', function (Blueprint $table) {
            $table->dropColumn(['invoices', 'invoices_fetched_at']);
        });
    }
};
