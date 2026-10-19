<?php

use App\Enums\TransactionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Puts item transfers on the same status vocabulary as every other document.
 *
 * They were drafted as "pending", posted as "completed" and reversed as
 * "cancelled" — three words for states the rest of the system calls draft,
 * posted and reversed. That meant the shared toolbar, the status badge and
 * every guard had to translate for this one module, and an operator reading a
 * transfer list saw a word they saw nowhere else.
 *
 * Idempotent: a fresh database is created with the new values already (the
 * create migration builds the column from TransactionStatus), so the updates
 * below match nothing and only the constraint is rewritten.
 */
return new class extends Migration
{
    /** The three states a transfer actually used, and what they are now. */
    private const RENAMES = [
        'pending' => 'draft',
        'completed' => 'posted',
        'cancelled' => 'reversed',
    ];

    public function up(): void
    {
        $this->rewrite(self::RENAMES, TransactionStatus::DRAFT->value, TransactionStatus::values());
    }

    public function down(): void
    {
        $this->rewrite(array_flip(self::RENAMES), 'pending', ['pending', 'completed', 'cancelled']);
    }

    /**
     * Postgres enforces an enum column with a CHECK constraint, and a row
     * cannot be moved to a value the constraint does not allow yet — so the
     * constraint comes off first and goes back on last.
     *
     * @param  array<string, string>  $renames
     * @param  list<string>  $allowed
     */
    private function rewrite(array $renames, string $default, array $allowed): void
    {
        DB::statement('ALTER TABLE item_transfers DROP CONSTRAINT IF EXISTS item_transfers_status_check');
        DB::statement('ALTER TABLE item_transfers ALTER COLUMN status DROP DEFAULT');

        foreach ($renames as $from => $to) {
            DB::table('item_transfers')->where('status', $from)->update(['status' => $to]);
        }

        $list = implode(', ', array_map(fn (string $value) => "'" . $value . "'", $allowed));

        DB::statement("ALTER TABLE item_transfers ALTER COLUMN status SET DEFAULT '{$default}'");
        DB::statement("ALTER TABLE item_transfers ADD CONSTRAINT item_transfers_status_check CHECK (status::text = ANY (ARRAY[{$list}]::text[]))");
    }
};
