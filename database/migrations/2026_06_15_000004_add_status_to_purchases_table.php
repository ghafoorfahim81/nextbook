<?php

use App\Enums\TransactionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->refreshStatusConstraint(TransactionStatus::values());
    }

    public function down(): void
    {
        $values = array_values(array_filter(
            TransactionStatus::values(),
            fn (string $value) => $value !== TransactionStatus::DRAFT->value
        ));

        $this->refreshStatusConstraint($values);
    }

    /**
     * @param array<int, string> $values
     */
    private function refreshStatusConstraint(array $values): void
    {
        $quotedValues = collect($values)
            ->map(fn (string $value) => "'" . str_replace("'", "''", $value) . "'")
            ->implode(', ');

        DB::statement('ALTER TABLE purchases DROP CONSTRAINT IF EXISTS purchases_status_check');
        DB::statement("ALTER TABLE purchases ADD CONSTRAINT purchases_status_check CHECK (status::text = ANY (ARRAY[{$quotedValues}]::text[]))");
    }
};
