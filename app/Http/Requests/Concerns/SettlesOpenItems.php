<?php

namespace App\Http\Requests\Concerns;

use App\Enums\PaymentMode;

/**
 * Shared validation for the receipt and payment forms.
 *
 * The two forms are mirror images — customer/supplier, receivable/payable — so
 * their rules live in one place. Keeping them in step matters more than usual
 * here: a rule that exists on one side only is how the payable path quietly
 * grows different behaviour from the receivable path.
 */
trait SettlesOpenItems
{
    /**
     * @return array<string, array<int, string>>
     */
    protected function settlementRules(bool $sometimes = false): array
    {
        $prefix = $sometimes ? ['sometimes'] : [];

        return [
            // Allocations target JOURNAL LINES, not documents. An invoice, an
            // opening balance and a credit note are all just receivable lines,
            // so one shape covers every kind of claim with no special case.
            'allocations' => ['nullable', 'array'],
            'allocations.*.target_line_id' => ['required_with:allocations', 'string', 'exists:transaction_lines,id'],
            'allocations.*.amount' => ['required_with:allocations', 'numeric', 'gt:0'],

            // Cross-currency: how much of the cash goes to each currency the
            // claims are in. One voucher can settle several — a customer owing
            // both 10,000 AFN and $200 who hands over 15,000 AFN is paying
            // both — so the split is stated per currency rather than as one
            // number. Only currencies other than the cash's own need an entry;
            // the conversion between them is a commercial agreement, not
            // arithmetic, so the system will not infer it.
            'applied_cash' => ['nullable', 'array'],
            'applied_cash.*.currency_id' => ['required_with:applied_cash', 'exists:currencies,id'],
            'applied_cash.*.amount' => ['required_with:applied_cash', 'numeric', 'gt:0'],

            // Shorthand for the ordinary case of exactly one foreign currency.
            'applied_cash_amount' => array_merge($prefix, ['nullable', 'numeric', 'gt:0', 'lte:amount']),
        ];
    }

    protected function validateSettlementSelection($validator): void
    {
        $validator->after(function ($validator): void {
            $paymentMode = $this->input('payment_mode', PaymentMode::OnAccount->value);
            $allocations = (array) $this->input('allocations', []);

            if ($paymentMode === PaymentMode::BillByBill->value && empty($allocations)) {
                $validator->errors()->add('allocations', __('Please select at least one bill.'));
            }

            // The party is deliberately NOT restricted to one type. A customer
            // can be paid — refunding an overpayment, or settling with someone
            // who both buys and sells — and a supplier can pay you. What the
            // module fixes is the DIRECTION of the cash, not who is on the
            // other side; SettlementService relieves whichever column of their
            // account that direction implies.

            // Applied totals are deliberately NOT compared against the voucher
            // amount here. In a cross-currency settlement the two are in
            // different currencies and the comparison would be meaningless;
            // SettlementService checks each allocation against what is actually
            // still open on its own document, which is the check that matters.
        });
    }

    /** Which way this form moves cash. */
    abstract protected function settlementDirection(): string;
}
