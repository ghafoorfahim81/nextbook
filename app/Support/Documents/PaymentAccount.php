<?php

namespace App\Support\Documents;

use App\Models\Account\Account;
use App\Models\Transaction\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * The cash or bank account a sale or purchase was settled through, for the
 * details page.
 *
 * Read in this order: the account the document names itself, then what the
 * form sent (kept on the voucher's payload since the totals moved to the
 * server), then — for documents written before that — the cash/bank line of
 * the voucher itself. A bill bought or sold wholly on loan has none.
 */
final class PaymentAccount
{
    /**
     * @param  string  $payloadKey  'sale' or 'purchase'.
     * @return array{id: string, name: string}|null
     */
    public static function for(?Transaction $transaction, ?string $accountId, string $payloadKey): ?array
    {
        $payload = (array) ($transaction?->posting_payload ?? []);

        $accountId = $accountId
            ?: data_get($payload, "{$payloadKey}.bank_account_id")
            ?: (((float) data_get($payload, "{$payloadKey}.payment.amount", 0)) > 0 ? data_get($payload, "{$payloadKey}.payment.account_id") : null);

        if (! $accountId && $transaction) {
            $accountId = DB::table('transaction_lines as tl')
                ->join('accounts as a', 'a.id', '=', 'tl.account_id')
                ->join('account_types as at', 'at.id', '=', 'a.account_type_id')
                ->where('tl.transaction_id', $transaction->id)
                ->whereNull('tl.deleted_at')
                ->where('at.slug', 'cash-or-bank')
                ->value('tl.account_id');
        }

        if (! $accountId) {
            return null;
        }

        $account = Account::query()->withoutGlobalScopes()->find($accountId, ['id', 'name', 'local_name']);

        if (! $account) {
            return null;
        }

        return [
            'id' => $account->id,
            'name' => app()->getLocale() === 'en' ? $account->name : ($account->local_name ?: $account->name),
        ];
    }
}
