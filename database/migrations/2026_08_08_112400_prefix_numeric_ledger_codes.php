<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Prefix existing numeric customer and supplier codes without changing their sequence.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE ledgers
            SET code = CASE
                WHEN type = 'customer' THEN 'CUST-' || code
                WHEN type = 'supplier' THEN 'SUP-' || code
            END
            WHERE type IN ('customer', 'supplier')
              AND code ~ '^[0-9]+$'
        SQL);
    }

    /**
     * This data migration is intentionally irreversible.
     */
    public function down(): void
    {
        //
    }
};
