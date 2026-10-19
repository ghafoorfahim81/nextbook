<?php

use App\Enums\TransactionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->refreshStatusConstraint('transactions', TransactionStatus::values());
    }

    public function down(): void
    {
        $values = array_values(array_filter(
            TransactionStatus::values(),
            fn (string $value) => $value !== TransactionStatus::DRAFT->value
        ));

        $this->refreshStatusConstraint('transactions', $values);
    }

    /**
     * PostgreSQL enum() columns are represented as varchar columns with a check constraint.
     *
     * @param array<int, string> $values
     */
    private function refreshStatusConstraint(string $table, array $values): void
    {
        $constraint = "{$table}_status_check";
        $quotedValues = collect($values)
            ->map(fn (string $value) => "'" . str_replace("'", "''", $value) . "'")
            ->implode(', ');

        DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$constraint}");
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$constraint} CHECK (status::text = ANY (ARRAY[{$quotedValues}]::text[]))");
    }
};
