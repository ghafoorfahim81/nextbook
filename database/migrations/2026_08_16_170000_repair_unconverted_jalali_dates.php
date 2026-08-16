<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Morilog\Jalali\Jalalian;

/**
 * One-off data repair.
 *
 * Two controller paths saved a Jalali date string straight into a Gregorian date
 * column instead of converting it first, so Postgres stored a literal year 14xx:
 *
 *   - Sale\SaleController@update converted into a local $date used for the
 *     transaction, but saved the sale row from $validated, which still held the
 *     raw Jalali value. The sale and its transaction ended up on different dates.
 *   - JournalEntry\JournalEntryController@store never converted at all, so both
 *     the entry and its transaction got the raw value.
 *
 * The effect on reporting: the P&L filters on transactions.date while the sales
 * report filters on sales.date, so the same sales landed inside one period and
 * outside the other. Both controllers are fixed; this migration repairs the rows
 * written before the fix.
 *
 * Only rows with a year below 1900 are touched — a Gregorian date can never look
 * like that, so the filter cannot misfire on good data.
 */
return new class extends Migration
{
    /**
     * Columns known to have received raw Jalali input. Verified against the full
     * information_schema date/timestamp column list; no others were affected.
     */
    private array $targets = [
        ['sales', 'date'],
        ['journal_entries', 'date'],
        ['transactions', 'date'],
    ];

    public function up(): void
    {
        foreach ($this->targets as [$table, $column]) {
            $this->convert($table, $column, fn (string $value) => Jalalian::fromFormat('Y-m-d', $value)->toCarbon()->toDateString());
        }
    }

    /**
     * Reverse the conversion so the migration can be rolled back cleanly. This
     * puts the corrupt values back — it exists for rollback symmetry, not because
     * the old state is desirable.
     */
    public function down(): void
    {
        $repaired = $this->repairedIds();

        foreach ($this->targets as [$table, $column]) {
            $ids = $repaired[$table] ?? [];
            if ($ids === []) {
                continue;
            }

            foreach (DB::table($table)->whereIn('id', $ids)->get(['id', $column]) as $row) {
                DB::table($table)->where('id', $row->id)->update([
                    $column => Jalalian::fromCarbon(\Carbon\Carbon::parse($row->{$column}))->format('Y-m-d'),
                ]);
            }
        }
    }

    private function convert(string $table, string $column, callable $transform): void
    {
        $rows = DB::table($table)
            ->whereNotNull($column)
            ->whereRaw("extract(year from {$column}) < 1900")
            ->get(['id', $column]);

        $touched = [];

        foreach ($rows as $row) {
            $raw = substr((string) $row->{$column}, 0, 10);

            try {
                $converted = $transform($raw);
            } catch (\Throwable $e) {
                // Leave anything we cannot parse alone rather than guessing at it.
                continue;
            }

            DB::table($table)->where('id', $row->id)->update([$column => $converted]);
            $touched[] = $row->id;
        }

        if ($touched !== []) {
            $this->rememberRepaired($table, $touched);
        }
    }

    private function rememberRepaired(string $table, array $ids): void
    {
        $path = storage_path('app/jalali-date-repair-applied.json');
        $existing = file_exists($path) ? (json_decode(file_get_contents($path), true) ?: []) : [];
        $existing[$table] = array_values(array_unique(array_merge($existing[$table] ?? [], $ids)));
        file_put_contents($path, json_encode($existing, JSON_PRETTY_PRINT));
    }

    private function repairedIds(): array
    {
        $path = storage_path('app/jalali-date-repair-applied.json');

        return file_exists($path) ? (json_decode(file_get_contents($path), true) ?: []) : [];
    }
};
