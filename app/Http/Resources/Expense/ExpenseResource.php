<?php

namespace App\Http\Resources\Expense;

use App\Enums\TransactionStatus;
use App\Services\DateConversionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Account\AccountResource;
use App\Http\Resources\AttachmentResource;
class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dateConversionService = app(\App\Services\DateConversionService::class);

        return [
            'id' => $this->id,
            'number' => $this->number,
            'date' => $this->date ? $dateConversionService->toDisplay($this->date) : null,
            'remarks' => $this->remarks,
            'status' => $this->status ?? $this->transaction?->status,
            'status_label' => TransactionStatus::tryFrom((string) ($this->status ?? $this->transaction?->status))?->getLabel()
                ?? (string) ($this->status ?? $this->transaction?->status),
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn() => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
            // Phase 7: where the cost belongs, and each branch's part of a
            // shared one, as the ledger holds it.
            'cost_center_id' => $this->cost_center_id,
            'cost_center' => $this->costCenter?->only(['id', 'code', 'name']),
            'allocation_rule_id' => $this->allocation_rule_id,
            'allocation_rule' => $this->allocationRule?->only(['id', 'name']),
            'allocation' => $this->allocation_rule_id
                ? $this->transaction?->lines
                    ?->filter(fn ($line) => $line->debit > 0 && $line->branch_id)
                    ->map(fn ($line) => [
                        'branch_id' => $line->branch_id,
                        'branch' => \App\Models\Administration\Branch::withoutGlobalScopes()->whereKey($line->branch_id)->value('name'),
                        'amount' => $line->debit,
                    ])
                    ->values()
                : [],
            'currency_id' => $this->transaction?->currency_id,
            'currency' => $this->transaction?->currency,
            'bank_account_id' => $this->transaction?->lines?->last(fn ($line) => $line->credit > 0)?->account_id,
            'expense_account_id' => $this->transaction?->lines?->firstWhere(fn($line) => $line->debit > 0)?->account_id,
            'bank_account' => new AccountResource($this->transaction?->lines?->last(fn ($line) => $line->credit > 0)?->account),
            'expense_account' => new AccountResource($this->transaction?->lines?->firstWhere(fn($line) => $line->debit > 0)?->account),
            'rate' => $this->transaction?->rate,
            'attachment' => $this->attachment,
            'attachment_url' => $this->attachment ? asset('storage/' . $this->attachment) : null,
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'details' => ExpenseDetailResource::collection($this->whenLoaded('details')),
            'total' => $this->whenLoaded('details', fn() => $this->details->sum('amount')),
            'base_total' => $this->whenLoaded('details', fn() => $this->details->sum('amount') * ($this->rate ?? 1)),
        ];
    }
}

