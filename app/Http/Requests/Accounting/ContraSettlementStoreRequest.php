<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A set-off names two ledgers and one amount.
 *
 * Both sides carry their own allocations because they relieve different
 * documents: the customer's invoices on one side, the supplier's bills on the
 * other. The amount is shared — that is the whole point of the document.
 */
class ContraSettlementStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'number' => ['required', 'integer', 'min:1'],
            'date' => ['required', 'date'],
            'customer_ledger_id' => ['required', 'exists:ledgers,id', 'different:supplier_ledger_id'],
            'supplier_ledger_id' => ['required', 'exists:ledgers,id'],
            // One currency for both sides. Offsetting a dollar receivable
            // against an afghani payable is a currency trade as much as a
            // set-off, and the rate it happens at is a commercial agreement
            // the system has no business inventing.
            'currency_id' => ['required', 'exists:currencies,id'],
            'rate' => ['required', 'numeric', 'gt:0'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'narration' => ['nullable', 'string'],

            // Allocations target JOURNAL LINES, not documents — an invoice, an
            // opening balance and a credit note are all just ledger lines.
            'customer_allocations' => ['nullable', 'array'],
            'customer_allocations.*.target_line_id' => ['required_with:customer_allocations', 'string', 'exists:transaction_lines,id'],
            'customer_allocations.*.amount' => ['required_with:customer_allocations', 'numeric', 'gt:0'],

            'supplier_allocations' => ['nullable', 'array'],
            'supplier_allocations.*.target_line_id' => ['required_with:supplier_allocations', 'string', 'exists:transaction_lines,id'],
            'supplier_allocations.*.amount' => ['required_with:supplier_allocations', 'numeric', 'gt:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_ledger_id.different' => __('general.contra_needs_two_accounts'),
        ];
    }
}
