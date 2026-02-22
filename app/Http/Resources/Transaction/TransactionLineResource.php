<?php

namespace App\Http\Resources\Transaction;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Account\AccountResource;
use App\Http\Resources\JournalEntry\JournalClassResource;
use App\Http\Resources\Ledger\LedgerResource;
class TransactionLineResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'account' => $this->whenLoaded('account', fn () => new AccountResource($this->account)),
            'debit' => $this->debit,
            'credit' => $this->credit,
            'remark' => $this->remark,
            'ledger_id' => $this->ledger_id,
            'ledger' => $this->whenLoaded('ledger', fn () => new LedgerResource($this->ledger)),
            'journal_class_id' => $this->journal_class_id,
            'journal_class' => $this->whenLoaded('journalClass', fn () => new JournalClassResource($this->journalClass)),
        ];
    }
}
