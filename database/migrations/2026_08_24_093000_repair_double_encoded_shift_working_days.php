<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Branch provisioning encoded `working_days` before handing it to the model,
     * whose `array` cast encoded it a second time. The jsonb column ended up
     * holding a JSON string ("[6,7,1,2,3,4]") rather than a list, so the cast
     * handed a string back and the shift listing blew up on array_map().
     *
     * Unwrap the string back into the array it was meant to be. Rows saved
     * through the normal create/edit form are already lists and are untouched.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE shifts
            SET working_days = (working_days #>> '{}')::jsonb
            WHERE jsonb_typeof(working_days) = 'string'
              AND working_days #>> '{}' ~ '^\s*\[[0-9,\s]*\]\s*$'
        SQL);
    }

    public function down(): void
    {
        // A data repair; re-breaking the rows is not a useful rollback.
    }
};
