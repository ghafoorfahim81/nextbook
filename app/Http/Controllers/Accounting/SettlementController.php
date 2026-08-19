<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Ledger\Ledger;
use App\Services\Accounting\PaymentStatusService;
use App\Services\Accounting\SettlementService;
use Illuminate\Http\Request;

/**
 * Read-only endpoints behind the receipt and payment forms.
 *
 * Everything here is preview: what is open, what an amount would settle, and
 * what exchange difference that would realise. Nothing posts. The form shows
 * the user the gain or loss BEFORE they save, because "why is there a 1,000
 * afghani expense on my receipt" is a question best answered while they can
 * still change the rate.
 */
class SettlementController extends Controller
{
    public function __construct(
        private readonly SettlementService $settlements,
        private readonly PaymentStatusService $statuses,
    ) {
    }

    /**
     * Open claims on a ledger, optionally narrowed to one currency.
     */
    public function openItems(Request $request)
    {
        $validated = $request->validate([
            'ledger_id' => ['required', 'exists:ledgers,id'],
            'currency_id' => ['nullable', 'exists:currencies,id'],
            // The voucher being edited. Its own applications are ignored, so
            // the documents it settled show up as open again with the amounts
            // it applied — otherwise editing a receipt presents an empty list.
            'exclude_transaction_id' => ['nullable', 'exists:transactions,id'],
            // Which way the calling module moves cash. It decides which column
            // of the party's account holds the claims: money in relieves their
            // debits, money out relieves their credits. Any party can appear on
            // either form — a customer being refunded, a supplier returning an
            // advance — so this is never inferred from the ledger type.
            'direction' => ['nullable', 'in:in,out'],
        ]);

        $ledger = Ledger::findOrFail($validated['ledger_id']);

        return response()->json([
            'data' => $this->settlements->openItems(
                $validated['ledger_id'],
                $validated['currency_id'] ?? null,
                $validated['direction'] ?? SettlementService::DIRECTION_IN,
                $validated['exclude_transaction_id'] ?? null
            ),
            'meta' => [
                'ledger_id' => $ledger->id,
                'ledger_name' => $ledger->name,
                'ledger_type' => (string) ($ledger->type?->value ?? $ledger->type),
                'balances' => $this->statuses->balancesForLedger($ledger->id),
            ],
        ]);
    }

    /**
     * What a voucher would settle, and the FX it would realise.
     *
     * Runs the same composition the save will, so what the form shows is what
     * gets posted — including the rounding. The payload mirrors the store
     * request on purpose: the form sends the voucher it is about to submit and
     * gets back the entry it would produce.
     */
    public function preview(Request $request)
    {
        $validated = $request->validate([
            'ledger_id' => ['required', 'exists:ledgers,id'],
            'cash_currency_id' => ['required', 'exists:currencies,id'],
            'cash_rate' => ['required', 'numeric', 'gt:0'],
            'cash_amount' => ['required', 'numeric', 'gt:0'],
            'allocations' => ['nullable', 'array'],
            'allocations.*.target_line_id' => ['required_with:allocations', 'string'],
            'allocations.*.amount' => ['required_with:allocations', 'numeric', 'gt:0'],
            // How much of the cash goes to each currency the claims are in.
            // Only needed for currencies other than the cash's own.
            'applied_cash' => ['nullable', 'array'],
            'applied_cash.*.currency_id' => ['required_with:applied_cash', 'exists:currencies,id'],
            'applied_cash.*.amount' => ['required_with:applied_cash', 'numeric', 'gte:0'],
            'exclude_transaction_id' => ['nullable', 'exists:transactions,id'],
            'direction' => ['nullable', 'in:in,out'],
            // 'fifo' asks the server to spread the cash oldest-first and return
            // the split it chose. The client used to do this itself and got it
            // wrong: it converted through the rates even when the claim was
            // already in the cash's currency, so 200 USD against a 200 USD
            // opening applied only 183.33.
            'strategy' => ['nullable', 'in:fifo,manual'],
        ]);

        $ledger = Ledger::findOrFail($validated['ledger_id']);
        $direction = $validated['direction'] ?? SettlementService::DIRECTION_IN;

        $allocations = $validated['allocations'] ?? [];
        $appliedCash = $validated['applied_cash'] ?? [];

        if (($validated['strategy'] ?? 'manual') === 'fifo') {
            $auto = $this->settlements->autoAllocate(
                $ledger->id,
                $direction,
                $validated['cash_currency_id'],
                $validated['cash_rate'],
                $validated['cash_amount'],
                $validated['exclude_transaction_id'] ?? null
            );

            $allocations = $auto['allocations'];
            $appliedCash = $auto['applied_cash'];
        }

        return response()->json([
            'data' => $this->settlements->preview(
                voucher: [
                    'ledger_id' => $ledger->id,
                    'direction' => $direction,
                    'cash_currency_id' => $validated['cash_currency_id'],
                    'cash_rate' => $validated['cash_rate'],
                    'cash_amount' => $validated['cash_amount'],
                    'cash_account_id' => $this->anyCashAccountId($ledger),
                    'applied_cash' => $appliedCash,
                    'exclude_transaction_id' => $validated['exclude_transaction_id'] ?? null,
                    'date' => now()->toDateString(),
                ],
                allocations: $allocations,
            ),
        ]);
    }

    /**
     * The preview builds real journal lines to get the rounding right, so it
     * needs an account for the cash line. Which one is irrelevant — nothing is
     * posted and the account never affects the arithmetic.
     */
    private function anyCashAccountId(Ledger $ledger): string
    {
        return (string) (\App\Support\BranchContext::glAccount('cash-in-hand', $ledger->branch_id)
            ?? \App\Models\Account\Account::withoutGlobalScopes()
                ->where('branch_id', $ledger->branch_id)
                ->value('id'));
    }
}
