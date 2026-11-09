<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Landed cost vouchers were saved with no reference number, a remark naming the
 * record's ULID, and line remarks in English only, so an account's statement
 * showed them untranslated with a blank reference. Only the labels change here;
 * no amount, account, date or status is touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        $vouchers = DB::table('transactions as t')
            ->join('landed_costs as lc', 'lc.id', '=', 't.reference_id')
            ->where('t.reference_type', 'landed_cost')
            ->get(['t.id', 't.posting_payload', 'lc.number']);

        foreach ($vouchers as $voucher) {
            $label = 'Landed cost #'.$voucher->number;

            $update = ['voucher_number' => $label, 'remark' => $label];

            // A draft keeps its lines in the payload until it is posted.
            $payload = json_decode((string) $voucher->posting_payload, true);

            if (is_array($payload) && isset($payload['lines'])) {
                $payload['lines'] = array_map(
                    fn (array $line) => array_merge($line, $this->remarks($voucher->number, (float) ($line['debit'] ?? 0) > 0)),
                    $payload['lines']
                );
                $update['posting_payload'] = json_encode($payload, JSON_UNESCAPED_UNICODE);
            }

            DB::table('transactions')->where('id', $voucher->id)->update($update);

            foreach ([true, false] as $isDebit) {
                DB::table('transaction_lines')
                    ->where('transaction_id', $voucher->id)
                    ->where($isDebit ? 'debit' : 'credit', '>', 0)
                    ->update($this->remarks($voucher->number, $isDebit));
            }

            // A reversal was numbered from the blank reference above.
            $reversalIds = DB::table('transactions')->where('reversal_of_id', $voucher->id)->pluck('id');

            DB::table('transactions')->whereIn('id', $reversalIds)->update(['voucher_number' => 'Reversal of '.$label]);
            DB::table('transaction_lines')->whereIn('transaction_id', $reversalIds)->update([
                'remark' => 'Reversal of landed cost #'.$voucher->number,
                'remark_fa' => 'ابطال مصرف جنس #'.$voucher->number,
                'remark_ps' => 'د اجناسو لګښت بیرته راګرځول #'.$voucher->number,
            ]);
        }
    }

    /** The old labels held nothing worth restoring. */
    public function down(): void
    {
    }

    private function remarks(mixed $number, bool $isDebit): array
    {
        return $isDebit
            ? [
                'remark' => "Landed cost #{$number} added to inventory",
                'remark_fa' => "مصرف جنس #{$number} به موجودی انبار اضافه شد",
                'remark_ps' => "د اجناسو لګښت #{$number} پر ګودام موجودۍ زیات شو",
            ]
            : [
                'remark' => "Landed cost #{$number} paid from account",
                'remark_fa' => "پرداخت مصرف جنس #{$number} از حساب",
                'remark_ps' => "د اجناسو لګښت #{$number} له حساب څخه ورکړل شو",
            ];
    }
};
