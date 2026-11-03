<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The licence the control center issued to a company: which modules, which
 * limits, until when, and whether it is blocked.
 *
 * The control center decides, this table only remembers what it was told, so
 * the company keeps working when the control center cannot be reached. The
 * payload is stored with its signature and checked again when it is read; the
 * token that proves to the control center which company is calling is stored
 * encrypted.
 *
 * Unlike the older subscription tables this one is company-scoped and sits
 * behind row level security like the rest: a company can see only its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_licenses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('company_id', 26)->unique();
            $table->char('control_customer_id', 26)->nullable();
            $table->text('api_token')->nullable();
            $table->json('payload')->nullable();
            $table->text('signature')->nullable();
            $table->unsignedInteger('license_version')->default(0);
            $table->unsignedSmallInteger('grace_days')->default(7);
            $table->timestamp('fetched_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });

        $this->installPolicy('company_licenses');
    }

    public function down(): void
    {
        Schema::dropIfExists('company_licenses');
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
