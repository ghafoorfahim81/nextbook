<?php

namespace App\Http\Resources\JournalEntry;

use App\Http\Resources\Transaction\TransactionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Account\AccountResource;
use App\Http\Resources\Transaction\TransactionLineResource;
class JournalEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dateConversionService = app(\App\Services\DateConversionService::class);
        return [
            'id' => $this->id,
            'number' => $this->number,
            'date' => $this->date ? $dateConversionService->toDisplay($this->date) : null,
            'remark' => $this->remark,
            'status' => $this->status,
            'amount' => $this->transaction->lines->sum('debit')>0?$this->transaction->lines->sum('debit'):$this->transaction->lines->sum('credit'),
            'lines' => TransactionLineResource::collection($this->whenLoaded('transaction.lines')),
            'transaction' => new TransactionResource($this->whenLoaded('transaction')),
        ];
    }
}


