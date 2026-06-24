<?php

namespace App\Http\Resources\JournalEntry;

use App\Http\Resources\Transaction\TransactionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Account\AccountResource;
use App\Http\Resources\Ledger\LedgerResource;
use App\Http\Resources\Transaction\TransactionLineResource;
use App\Models\Account\Account;
use App\Models\JournalEntry\JournalClass;
use App\Models\Ledger\Ledger;

class JournalEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dateConversionService = app(\App\Services\DateConversionService::class);

        $lines = $this->resolveLines();
        $debitTotal = $lines->sum(fn ($line) => (float) ($line['debit'] ?? 0));
        $creditTotal = $lines->sum(fn ($line) => (float) ($line['credit'] ?? 0));

        return [
            'id' => $this->id,
            'number' => $this->number,
            'date' => $this->date ? $dateConversionService->toDisplay($this->date) : null,
            'remark' => $this->remark,
            'status' => $this->status,
            'amount' => $debitTotal > 0 ? $debitTotal : $creditTotal,
            'lines' => $lines->values(),
            'transaction' => new TransactionResource($this->whenLoaded('transaction')),
        ];
    }

    /**
     * Resolve the entry's lines from the posted transaction lines, falling back
     * to the draft's posting_payload when no lines have been posted yet.
     */
    protected function resolveLines(): \Illuminate\Support\Collection
    {
        $transaction = $this->relationLoaded('transaction') ? $this->transaction : null;
        $postedLines = $transaction && $transaction->relationLoaded('lines')
            ? $transaction->lines
            : collect();

        if ($postedLines->isNotEmpty()) {
            return collect(TransactionLineResource::collection($postedLines)->resolve());
        }

        return $this->resolvePayloadLines($transaction);
    }

    /**
     * Build display-ready lines from a draft transaction's posting_payload.
     */
    protected function resolvePayloadLines($transaction): \Illuminate\Support\Collection
    {
        $payloadLines = collect((array) data_get($transaction?->posting_payload, 'lines', []));

        if ($payloadLines->isEmpty()) {
            return collect();
        }

        $accounts = Account::whereIn('id', $payloadLines->pluck('account_id')->filter()->unique())->get()->keyBy('id');
        $ledgers = Ledger::whereIn('id', $payloadLines->pluck('ledger_id')->filter()->unique())->get()->keyBy('id');
        $journalClasses = JournalClass::whereIn('id', $payloadLines->pluck('journal_class_id')->filter()->unique())->get()->keyBy('id');

        $locale = app()->getLocale();

        return $payloadLines->map(function ($line) use ($accounts, $ledgers, $journalClasses, $locale) {
            $account = $accounts->get($line['account_id'] ?? null);
            $ledger = $ledgers->get($line['ledger_id'] ?? null);
            $journalClass = $journalClasses->get($line['journal_class_id'] ?? null);

            return [
                'id' => null,
                'account_id' => $line['account_id'] ?? null,
                'account' => $account ? (new AccountResource($account))->resolve() : null,
                'debit' => $line['debit'] ?? 0,
                'credit' => $line['credit'] ?? 0,
                'remark' => $line['remark_' . $locale] ?? $line['remark'] ?? '',
                'ledger_id' => $line['ledger_id'] ?? null,
                'ledger' => $ledger ? (new LedgerResource($ledger))->resolve() : null,
                'journal_class_id' => $line['journal_class_id'] ?? null,
                'journal_class' => $journalClass ? (new JournalClassResource($journalClass))->resolve() : null,
            ];
        });
    }
}
