<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('number')->nullable()->index()->after('id');
        });

        // One numbering series per company and document type. Partial, because
        // a soft-deleted document must not hold its number against a new one
        // and unique([..., 'deleted_at']) never fires in PostgreSQL.
        DB::statement('CREATE UNIQUE INDEX expenses_company_id_number_live_unique ON expenses (company_id, number) WHERE deleted_at IS NULL AND number IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS expenses_company_id_number_live_unique');

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('number');
        });
    }
};
