<?php

namespace App\Services\Accounting;

use App\Exceptions\Accounting\SettlementException;
use App\Models\Account\Account;
use App\Models\Account\AccountType;
use App\Models\Accounting\Settlement;
use App\Models\Administration\Currency;
use App\Models\Ledger\Ledger;
use App\Models\Transaction\Transaction;
use App\Services\TransactionService;
use App\Support\BranchContext;
use App\Support\Decimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Matching cash to claims, and recognising the exchange difference that falls
 * out of the match.
 *
 * The rule the whole service exists to enforce:
 *
 *   A receivable is relieved at the rate it was ORIGINALLY BOOKED at. Cash
 *   moves at TODAY's rate. The difference is FX gain or loss. The unpaid
 *   remainder KEEPS its original rate.
 *
 * Settlement targets JOURNAL LINES, not documents. A receivable is any
 * transaction_lines row with account_id = AR, a ledger_id, a currency and a
 * rate — which is equally true of a sales invoice, an opening balance, a credit
 * note and a manual journal debit. Because the claim is described entirely by
 * the line, opening balances settle through the same path as invoices with no
 * special case anywhere in this file.
 *
 * Receivables and payables are the same code. `side` picks the account slug and
 * flips which column holds the claim; everything downstream is symmetric.
 */
class SettlementService
{
    /**
     * Which way the cash moves. This is a property of the MODULE — a receipt
     * takes money in, a payment pays it out — and it is entirely independent of
     * what kind of party is on the other side.
     *
     * Conflating the two was a mistake: it meant a payment to a customer
     * inherited the receipt's direction and debited cash for money going out.
     * The party decides which ACCOUNT their balance lives in; the module
     * decides which way the money went. Both are needed, and neither implies
     * the other.
     */
    public const DIRECTION_IN = 'in';

    public const DIRECTION_OUT = 'out';

    public function __construct(
        private readonly TransactionService $transactions,
    ) {
    }

    // ======================================================
    // OPEN ITEMS
    // ======================================================

    /**
     * Claims on a ledger that still have something outstanding, oldest first.
     *
     * One query across every document type. There is no union of sales,
     * openings and journals here because there is nothing to union — they are
     * all rows in the same table, distinguished only by what posted them.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function openItems(
        string $ledgerId,
        ?string $currencyId = null,
        string $direction = self::DIRECTION_IN,
        ?string $excludeTransactionId = null
    ): Collection {
        $ledger = $this->resolveLedger($ledgerId);
        $claimColumn = $this->claimColumn($direction);
        $accountId = $this->partyAccountId($ledger, $ledger->branch_id);

        // When a voucher is being EDITED, its own applications must not count
        // as settled — otherwise the documents it paid look closed, the form
        // shows an empty list, and it reads as though the receipt settled
        // nothing. Its rows are still in the table at that point; the edit has
        // not been saved yet.
        $exclusion = $excludeTransactionId !== null ? ' AND s.transaction_id <> ?' : '';
        $bindings = $excludeTransactionId !== null ? [$excludeTransactionId] : [];

        $settled = 'COALESCE((SELECT SUM(s.amount_applied) FROM settlements s'
            . " WHERE s.target_line_id = tl.id AND s.deleted_at IS NULL{$exclusion}), 0)";

        $rows = DB::table('transaction_lines as tl')
            ->join('transactions as t', function ($join) {
                $join->on('t.id', '=', 'tl.transaction_id')->whereNull('t.deleted_at');
            })
            ->leftJoin('currencies as c', 'c.id', '=', 'tl.currency_id')
            // The subquery appears in both the SELECT and the WHERE, so its
            // binding has to be supplied to each of them.
            ->selectRaw(implode(', ', [
                'tl.id as target_line_id',
                'tl.transaction_id',
                'tl.currency_id',
                'tl.rate as target_rate',
                "tl.{$claimColumn} as original_amount",
                "{$settled} as settled_amount",
                't.date',
                't.voucher_number',
                't.reference_type',
                't.reference_id',
                't.remark',
                'c.code as currency_code',
                'c.symbol as currency_symbol',
            ]), $bindings)
            ->where('tl.ledger_id', $ledgerId)
            ->where('tl.account_id', $accountId)
            ->whereNull('tl.deleted_at')
            // A reversed voucher's claim is gone. Its reversal posts an opposite
            // line which is not a claim on this side, so it drops out too.
            ->where('t.status', 'posted')
            ->where("tl.{$claimColumn}", '>', 0)
            // A line that SETTLED something is not itself a claim. Once money
            // can move both ways, a receipt's AR credit sits in the same column
            // a refund would relieve — and it would offer every receipt back as
            // if the customer were owed it again. A settling line is always
            // fully consumed by its own settlements, so excluding it outright
            // is exact. Genuine credits — a credit note, an unapplied opening —
            // settle nothing and stay visible.
            ->whereRaw(
                'NOT EXISTS (SELECT 1 FROM settlements sx'
                . ' WHERE sx.settling_line_id = tl.id AND sx.deleted_at IS NULL)'
            )
            ->whereRaw("tl.{$claimColumn} - {$settled} > 0", $bindings)
            ->when($currencyId, fn ($query) => $query->where('tl.currency_id', $currencyId))
            ->orderBy('t.date')
            ->orderBy('t.created_at')
            ->orderBy('tl.created_at')
            ->get();

        $numbers = $this->documentNumbers($rows);
        $openings = $this->openingTransactionIds($rows);

        return collect($rows)->map(function ($row) use ($numbers, $openings) {
            $original = Decimal::amount($row->original_amount);
            $settledAmount = Decimal::amount($row->settled_amount);

            return [
                'target_line_id' => (string) $row->target_line_id,
                'transaction_id' => (string) $row->transaction_id,
                'currency_id' => (string) $row->currency_id,
                'currency_code' => $row->currency_code,
                'currency_symbol' => $row->currency_symbol,
                'date' => $row->date,
                'document_type' => $this->documentType($row->reference_type, $row->transaction_id, $openings),
                'document_number' => $numbers[(string) $row->reference_id]
                    ?? $row->voucher_number
                    ?? null,
                'remark' => $row->remark,
                'original_amount' => $original,
                'settled_amount' => $settledAmount,
                'remaining_amount' => Decimal::sub($original, $settledAmount),
                // The booking rate. This is what the remainder keeps, and what
                // any later settlement computes its FX against — never the rate
                // of the payment that partially settled it.
                'target_rate' => Decimal::rate($row->target_rate),
            ];
        })->values();
    }

    // ======================================================
    // ALLOCATION (PREVIEW)
    // ======================================================

    /**
     * Plan how an amount is spread across open items, without posting anything.
     *
     * `$strategy` is either 'fifo' — oldest claim first, the default — or an
     * explicit list of ['target_line_id' => ..., 'amount' => ...]. Manual
     * selection is not an optional extra: customers here routinely say "this is
     * for invoice 254", and silently applying their money to an older invoice
     * produces a subledger that disagrees with what they think they paid.
     *
     * @param  string|array<int, array<string, mixed>>  $strategy
     * @return array{allocations: array<int, array<string, mixed>>, applied: string, unapplied: string}
     */
    public function allocate(
        string $ledgerId,
        string $currencyId,
        mixed $amount,
        string|array $strategy = 'fifo',
        string $direction = self::DIRECTION_IN,
        ?string $excludeTransactionId = null
    ): array {
        $amount = Decimal::amount($amount);

        if (! Decimal::isPositive($amount)) {
            throw SettlementException::make('An allocation needs a positive amount.', [
                ['ledger_id' => $ledgerId, 'amount' => $amount],
            ]);
        }

        $open = $this->openItems($ledgerId, $currencyId, $direction, $excludeTransactionId)->keyBy('target_line_id');

        $allocations = is_array($strategy)
            ? $this->allocateManually($strategy, $open)
            : $this->allocateFifo($amount, $open);

        $applied = collect($allocations)->reduce(
            fn (string $carry, array $row) => Decimal::add($carry, $row['amount_applied']),
            '0'
        );

        if (Decimal::cmp($applied, $amount) > 0) {
            throw SettlementException::make('Allocations exceed the amount being settled.', [
                ['applied' => $applied, 'amount' => $amount],
            ]);
        }

        return [
            'allocations' => $allocations,
            'applied' => $applied,
            // Shown to the user rather than absorbed. Unapplied money becomes an
            // advance at posting time, and they should see that coming.
            'unapplied' => Decimal::sub($amount, $applied),
        ];
    }

    /**
     * @param  Collection<string, array<string, mixed>>  $open
     * @return array<int, array<string, mixed>>
     */
    private function allocateFifo(string $amount, Collection $open): array
    {
        $remaining = $amount;
        $allocations = [];

        foreach ($open as $item) {
            if (! Decimal::isPositive($remaining)) {
                break;
            }

            $apply = Decimal::cmp($remaining, $item['remaining_amount']) < 0
                ? $remaining
                : $item['remaining_amount'];

            $allocations[] = $this->allocationRow($item, $apply);
            $remaining = Decimal::sub($remaining, $apply);
        }

        return $allocations;
    }

    /**
     * @param  array<int, array<string, mixed>>  $selections
     * @param  Collection<string, array<string, mixed>>  $open
     * @return array<int, array<string, mixed>>
     */
    private function allocateManually(array $selections, Collection $open): array
    {
        $allocations = [];

        foreach ($selections as $index => $selection) {
            $lineId = (string) ($selection['target_line_id'] ?? '');
            $apply = Decimal::amount($selection['amount'] ?? 0);

            if (! Decimal::isPositive($apply)) {
                continue;
            }

            $item = $open->get($lineId);

            if (! $item) {
                throw SettlementException::make('That document is not open on this ledger.', [
                    ['index' => $index, 'target_line_id' => $lineId],
                ]);
            }

            if (Decimal::cmp($apply, $item['remaining_amount']) > 0) {
                throw SettlementException::make('Applied amount exceeds what is left on the document.', [
                    [
                        'index' => $index,
                        'target_line_id' => $lineId,
                        'applied' => $apply,
                        'remaining' => $item['remaining_amount'],
                    ],
                ]);
            }

            $allocations[] = $this->allocationRow($item, $apply);
        }

        return $allocations;
    }

    /**
     * What one voucher settled, named by the documents it relieved.
     *
     * The dialog this replaced showed the last six characters of a ULID, which
     * tells the user nothing. A claim's identity lives on the transaction that
     * posted it, so it is resolved the same way the open-items list does —
     * including recognising an opening balance for what it is.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function settlementsForVoucher(string $transactionId): Collection
    {
        $rows = DB::table('settlements as s')
            ->join('transaction_lines as tl', 'tl.id', '=', 's.target_line_id')
            ->join('transactions as t', 't.id', '=', 'tl.transaction_id')
            ->leftJoin('currencies as c', 'c.id', '=', 's.currency_id')
            ->where('s.transaction_id', $transactionId)
            ->whereNull('s.deleted_at')
            ->orderBy('t.date')
            ->orderBy('s.created_at')
            ->get([
                's.id',
                's.target_line_id',
                's.amount_applied',
                's.target_rate',
                's.settlement_rate',
                's.base_relieved',
                's.forex_amount',
                's.is_cross_currency',
                'tl.transaction_id',
                't.date',
                't.voucher_number',
                't.reference_type',
                't.reference_id',
                'c.code as currency_code',
            ]);

        $numbers = $this->documentNumbers($rows);
        $openings = $this->openingTransactionIds($rows);

        return $rows->map(fn ($row) => [
            'id' => (string) $row->id,
            'target_line_id' => (string) $row->target_line_id,
            'date' => $row->date,
            'document_type' => $this->documentType($row->reference_type, $row->transaction_id, $openings),
            'document_number' => $numbers[(string) $row->reference_id] ?? $row->voucher_number ?? null,
            'currency_code' => $row->currency_code,
            'amount_applied' => (float) $row->amount_applied,
            'target_rate' => (float) $row->target_rate,
            'settlement_rate' => (float) $row->settlement_rate,
            'base_relieved' => (float) $row->base_relieved,
            'forex_amount' => (float) $row->forex_amount,
            // Positive is a gain whichever way the money moved.
            'forex_kind' => $row->forex_amount < 0 ? 'loss' : ((float) $row->forex_amount > 0 ? 'gain' : 'none'),
            'is_cross_currency' => (bool) $row->is_cross_currency,
        ])->values();
    }

    /**
     * Spread an amount of cash across open claims, oldest first.
     *
     * The distinction that matters, and the one that was wrong:
     *
     *   Claim in the SAME currency as the cash — one unit settles one unit.
     *   200 USD received clears 200 USD of debt no matter what today's rate is.
     *   The rates are what produce the exchange difference; they have nothing
     *   to do with HOW MUCH is settled. Converting here (200 x 55 / 60 = 183.33)
     *   left the customer owing 16.67 USD they had already paid.
     *
     *   Claim in a DIFFERENT currency — a conversion is unavoidable, and it
     *   defaults to the claim's own booking rate, which realises no exchange
     *   difference. That is the honest default when nobody has stated an agreed
     *   rate; the user can then correct the per-currency cash split.
     *
     * @return array{allocations: array<int, array<string, mixed>>, applied_cash: array<int, array<string, mixed>>, unapplied: string}
     */
    public function autoAllocate(
        string $ledgerId,
        string $direction,
        string $cashCurrencyId,
        mixed $cashRate,
        mixed $cashAmount,
        ?string $excludeTransactionId = null
    ): array {
        $rate = Decimal::rate($cashRate);
        $remainingCash = Decimal::amount($cashAmount);

        $allocations = [];
        $cashByCurrency = [];

        foreach ($this->openItems($ledgerId, null, $direction, $excludeTransactionId) as $item) {
            if (! Decimal::isPositive($remainingCash)) {
                break;
            }

            $sameCurrency = $item['currency_id'] === $cashCurrencyId;

            // How much of THIS claim the remaining cash could cover.
            $affordable = $sameCurrency
                ? $remainingCash
                : Decimal::amount(bcdiv(
                    Decimal::toBase($remainingCash, $rate),
                    $item['target_rate'],
                    Decimal::AMOUNT_SCALE + 2
                ));

            // Is the cash the binding constraint, or the size of the claim?
            $cashBound = Decimal::cmp($affordable, $item['remaining_amount']) < 0;
            $apply = $cashBound ? $affordable : $item['remaining_amount'];

            if (! Decimal::isPositive($apply)) {
                continue;
            }

            $allocations[] = [
                'target_line_id' => $item['target_line_id'],
                'amount' => $apply,
            ];

            // When the cash ran out on this claim, ALL of it went here by
            // definition — take the remainder as-is rather than recomputing it
            // through the rate. Round-tripping leaves a fraction behind
            // (83.3333 x 60 = 4,999.998, not 5,000) which would then be
            // reported as an overpayment and posted to advances. The fraction
            // belongs in the FX line, where every other rounding remainder goes.
            $cashUsed = match (true) {
                $cashBound => $remainingCash,
                $sameCurrency => $apply,
                default => Decimal::amount(bcdiv(
                    Decimal::toBase($apply, $item['target_rate']),
                    $rate,
                    Decimal::AMOUNT_SCALE + 2
                )),
            };

            if (! $sameCurrency) {
                $cashByCurrency[$item['currency_id']] = Decimal::add(
                    $cashByCurrency[$item['currency_id']] ?? '0',
                    $cashUsed
                );
            }

            $remainingCash = Decimal::sub($remainingCash, $cashUsed);
        }

        return [
            'allocations' => $allocations,
            'applied_cash' => collect($cashByCurrency)
                ->map(fn (string $amount, string $currencyId) => [
                    'currency_id' => $currencyId,
                    'amount' => $amount,
                ])
                ->values()
                ->all(),
            'unapplied' => Decimal::max($remainingCash, '0'),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function allocationRow(array $item, string $apply): array
    {
        return [
            'target_line_id' => $item['target_line_id'],
            'currency_id' => $item['currency_id'],
            'document_type' => $item['document_type'],
            'document_number' => $item['document_number'],
            'date' => $item['date'],
            'target_rate' => $item['target_rate'],
            'amount_applied' => $apply,
            'base_relieved' => Decimal::toBase($apply, $item['target_rate']),
            'remaining_after' => Decimal::sub($item['remaining_amount'], $apply),
        ];
    }

    /**
     * Everything settle() would post, without posting it.
     *
     * Runs the SAME composition — same grouping, same rates, same line
     * building, same rounding — so the gain or loss the form shows is the one
     * that lands in the ledger. Computing the preview separately would give two
     * implementations of the rule that decides what hits the P&L, and the one
     * that disagreed would be the one the user was looking at.
     *
     * @param  array<string, mixed>  $voucher
     * @param  array<int, array<string, mixed>>  $allocations
     * @return array<string, mixed>
     */
    public function preview(array $voucher, array $allocations): array
    {
        $composed = $this->compose($voucher, $allocations, lock: false);

        $rows = [];

        foreach ($composed['groups'] as $group) {
            foreach ($group['plan'] as $row) {
                $rows[] = $row + [
                    'settlement_rate' => $group['rate'],
                    'cash_currency_id' => $composed['cash_currency_id'],
                    'is_cross_currency' => $group['is_cross_currency'],
                    // Positive is a gain, negative a loss, whichever way the
                    // money moved. Paying out below the rate a bill was booked
                    // at is a gain even though the arithmetic runs the other
                    // way round from a receipt.
                    'forex_amount' => $this->rowForex(
                        $row['amount_applied'],
                        $row['target_rate'],
                        $group['rate'],
                        $composed['direction']
                    ),
                ];
            }
        }

        return [
            'allocations' => $rows,
            'currencies' => array_values(array_map(fn (array $group) => [
                'currency_id' => $group['currency_id'],
                'applied' => $group['applied'],
                'cash_applied' => $group['cash'],
                'settlement_rate' => $group['rate'],
                'is_cross_currency' => $group['is_cross_currency'],
            ], $composed['groups'])),
            'cash_amount' => $composed['cash_amount'],
            'cash_applied' => Decimal::sub($composed['cash_amount'], $composed['excess']),
            'unapplied' => $composed['excess'],
            // Taken from the composed LINES, so it carries the same rounding
            // remainders the posted FX line will.
            'net_forex' => $composed['forex'],
            'is_cross_currency' => $composed['is_cross_currency'],
            'direction' => $composed['direction'],
            // Named after the party, so the form can warn where the leftover
            // is going before the user commits to it.
            'advance_account' => Decimal::isPositive($composed['excess'])
                ? ($this->partyAccountSlug($composed['ledger']) === 'account-payable'
                    ? 'supplier-advances'
                    : 'customer-advances')
                : null,
        ];
    }

    /**
     * The exchange result on one application. Positive is always a GAIN.
     *
     * Keyed on the DIRECTION of the cash, not on which control account the
     * party sits in — it mirrors the plug the entry produces, and the plug
     * depends on which side the cash landed. Paying out 11,000 to clear a
     * 12,000 debt is a gain; taking in 11,000 against a 12,000 claim is a loss.
     */
    private function rowForex(string $applied, string $targetRate, string $settlementRate, string $direction): string
    {
        $cashBase = Decimal::toBase($applied, $settlementRate);
        $baseRelieved = Decimal::toBase($applied, $targetRate);

        return $direction === self::DIRECTION_OUT
            ? Decimal::sub($baseRelieved, $cashBase)
            : Decimal::sub($cashBase, $baseRelieved);
    }

    // ======================================================
    // SETTLE
    // ======================================================

    /**
     * Post the voucher and record what it settled, in one database transaction.
     *
     * @param  array<string, mixed>  $voucher
     * @param  array<int, array<string, mixed>>  $allocations  target_line_id + amount
     */
    public function settle(array $voucher, array $allocations): Transaction
    {
        return DB::transaction(function () use ($voucher, $allocations) {
            $composed = $this->compose($voucher, $allocations, lock: true);

            $transaction = $this->transactions->post(
                header: [
                    'currency_id' => $composed['cash_currency_id'],
                    'rate' => $composed['cash_rate'],
                    'date' => $voucher['date'],
                    'voucher_number' => $voucher['voucher_number'] ?? null,
                    'reference_type' => $voucher['reference_type'] ?? null,
                    'reference_id' => $voucher['reference_id'] ?? null,
                    'remark' => $voucher['remark'] ?? null,
                    'status' => 'posted',
                    'branch_id' => $composed['branch_id'],
                    'cross_currency' => $composed['is_cross_currency'],
                ],
                lines: $composed['lines'],
            );

            $this->recordSettlements(
                transaction: $transaction,
                ledger: $composed['ledger'],
                direction: $composed['direction'],
                branchId: $composed['branch_id'],
                groups: $composed['groups'],
                postedForex: $composed['forex'],
            );

            return $transaction;
        });
    }

    /**
     * Work out everything about a settlement without committing to it.
     *
     * Shared by settle() and preview(); `$lock` is the only difference, because
     * a preview must not hold row locks while a user stares at a dialog.
     *
     * @param  array<string, mixed>  $voucher
     * @param  array<int, array<string, mixed>>  $allocations
     * @return array<string, mixed>
     */
    private function compose(array $voucher, array $allocations, bool $lock): array
    {
        $ledger = $this->resolveLedger((string) $voucher['ledger_id']);
        $direction = ($voucher["direction"] ?? null) === self::DIRECTION_OUT
            ? self::DIRECTION_OUT
            : self::DIRECTION_IN;
        $branchId = (string) ($voucher['branch_id'] ?? $ledger->branch_id);

        $cashAmount = Decimal::amount($voucher['cash_amount']);
        $cashRate = Decimal::rate($voucher['cash_rate']);
        $cashCurrencyId = (string) $voucher['cash_currency_id'];

        if (! Decimal::isPositive($cashAmount)) {
            throw SettlementException::make('A settlement voucher needs a positive amount.', [
                ['cash_amount' => $cashAmount],
            ]);
        }

        // On an edit the voucher's existing settlements are still present until
        // the controller replaces them, so they must not count against what is
        // open — otherwise re-saving an unchanged receipt fails as over-applied.
        $excludeTransactionId = $voucher['exclude_transaction_id'] ?? null;

        $plan = $this->validateAllocations(
            $ledger,
            $direction,
            $allocations === []
                ? $this->defaultAllocations($ledger, $direction, $cashCurrencyId, $cashAmount, $excludeTransactionId)
                : $allocations,
            $lock,
            $excludeTransactionId
        );

        $groups = $this->currencyGroups($plan, $voucher, $cashCurrencyId, $cashRate);

        $appliedCash = array_reduce(
            $groups,
            fn (string $carry, array $group) => Decimal::add($carry, $group['cash']),
            '0'
        );

        $excessCash = Decimal::sub($cashAmount, $appliedCash);

        if (Decimal::cmp($excessCash, '0') < 0) {
            throw SettlementException::make('Applied cash exceeds the amount received.', [
                ['cash_amount' => $cashAmount, 'applied_cash' => $appliedCash],
            ]);
        }

        $isCrossCurrency = (bool) array_filter($groups, fn (array $group) => $group['is_cross_currency']);

        $lines = $this->buildLines(
            ledger: $ledger,
            direction: $direction,
            branchId: $branchId,
            groups: $groups,
            cashAccountId: (string) $voucher['cash_account_id'],
            cashCurrencyId: $cashCurrencyId,
            cashRate: $cashRate,
            cashAmount: $cashAmount,
            excessCash: $excessCash,
            remarks: $this->remarks($voucher, $ledger),
        );

        return [
            'ledger' => $ledger,
            'direction' => $direction,
            'branch_id' => $branchId,
            'cash_currency_id' => $cashCurrencyId,
            'cash_rate' => $cashRate,
            'cash_amount' => $cashAmount,
            'groups' => $groups,
            'excess' => $excessCash,
            'is_cross_currency' => $isCrossCurrency,
            'lines' => $lines['lines'],
            'forex' => $lines['forex'],
        ];
    }

    /**
     * Split the plan by the currency of the claims, and work out what rate the
     * cash moved at for each.
     *
     * A customer who owes both 10,000 AFN and $200 and hands over 15,000 AFN is
     * settling BOTH, and refusing the voucher because it touches two currencies
     * would just push the user into raising two receipts for one payment. Each
     * currency gets its own settlement rate, which is what makes this work: the
     * rate is a property of the match, not of the voucher.
     *
     * For claims in the cash's own currency the rate is simply the voucher's.
     * For the rest the caller must say how much of the cash went to that
     * currency — the conversion the two parties agreed is a commercial
     * decision, and the day's published rate is routinely not it.
     *
     * @param  array<int, array<string, mixed>>  $plan
     * @param  array<string, mixed>  $voucher
     * @return array<string, array<string, mixed>>
     */
    private function currencyGroups(array $plan, array $voucher, string $cashCurrencyId, string $cashRate): array
    {
        $groups = [];

        foreach ($plan as $row) {
            $currencyId = (string) $row['currency_id'];

            $groups[$currencyId] ??= [
                'currency_id' => $currencyId,
                'plan' => [],
                'applied' => '0',
            ];

            $groups[$currencyId]['plan'][] = $row;
            $groups[$currencyId]['applied'] = Decimal::add(
                $groups[$currencyId]['applied'],
                $row['amount_applied']
            );
        }

        $stated = $this->statedCash($voucher, array_keys($groups), $cashCurrencyId);

        foreach ($groups as $currencyId => &$group) {
            $group['is_cross_currency'] = $currencyId !== $cashCurrencyId;

            if (! $group['is_cross_currency']) {
                // Same currency on both sides: the cash consumed is exactly the
                // amount applied, and the rate is the voucher's own.
                $group['cash'] = $group['applied'];
                $group['rate'] = $cashRate;

                continue;
            }

            if (! array_key_exists($currencyId, $stated)) {
                throw SettlementException::make(
                    'A cross-currency settlement must state how much cash is being applied.',
                    [['currency_id' => $currencyId, 'applied' => $group['applied']]]
                );
            }

            $group['cash'] = $stated[$currencyId];

            // rate = base value of the cash / units of claim it relieved.
            $group['rate'] = Decimal::isPositive($group['applied'])
                ? bcdiv(Decimal::toBase($group['cash'], $cashRate), $group['applied'], Decimal::RATE_SCALE)
                : $cashRate;
        }

        return $groups;
    }

    /**
     * Cash the caller has allocated to each non-cash currency.
     *
     * Accepts either a map — `applied_cash: [{currency_id, amount}, ...]` — or,
     * when exactly one foreign currency is involved, the scalar shorthand
     * `applied_cash_amount`. The shorthand covers the ordinary case of a USD
     * invoice paid in afghanis without making the caller build a map for it.
     *
     * @param  array<string, mixed>  $voucher
     * @param  array<int, string>  $currencyIds
     * @return array<string, string>
     */
    private function statedCash(array $voucher, array $currencyIds, string $cashCurrencyId): array
    {
        $stated = [];

        foreach ((array) ($voucher['applied_cash'] ?? []) as $entry) {
            $currencyId = (string) ($entry['currency_id'] ?? '');

            if ($currencyId !== '') {
                $stated[$currencyId] = Decimal::amount($entry['amount'] ?? 0);
            }
        }

        if ($stated !== [] || ! array_key_exists('applied_cash_amount', $voucher)) {
            return $stated;
        }

        $foreign = array_values(array_filter($currencyIds, fn ($id) => $id !== $cashCurrencyId));

        if (count($foreign) === 1) {
            $stated[$foreign[0]] = Decimal::amount($voucher['applied_cash_amount']);

            return $stated;
        }

        if ($foreign !== []) {
            throw SettlementException::make(
                'This voucher settles claims in several currencies, so the cash must be split per currency.',
                [['currencies' => $foreign]]
            );
        }

        return $stated;
    }

    /**
     * Re-read every target, optionally with its row locked.
     *
     * Two receipts posted at the same moment would otherwise both see the same
     * invoice as open and both settle it in full. The lock makes the second one
     * wait and then fail its remaining-amount check, which is the correct
     * outcome — over-applied cash is not something to reconcile later.
     *
     * A preview passes `$lock = false`: it must not hold row locks open while a
     * user reads a dialog, and it commits to nothing anyway.
     *
     * @param  array<int, array<string, mixed>>  $allocations
     * @return array<int, array<string, mixed>>
     */
    private function validateAllocations(
        Ledger $ledger,
        string $direction,
        array $allocations,
        bool $lock = true,
        ?string $excludeTransactionId = null
    ): array {
        $requested = collect($allocations)
            ->map(fn ($row) => [
                'target_line_id' => (string) ($row['target_line_id'] ?? ''),
                'amount' => Decimal::amount($row['amount'] ?? $row['amount_applied'] ?? 0),
            ])
            ->filter(fn (array $row) => $row['target_line_id'] !== '' && Decimal::isPositive($row['amount']))
            // The unique index refuses two rows for the same pair, so fold
            // repeats into one application rather than failing at insert time.
            ->groupBy('target_line_id')
            ->map(fn (Collection $rows, string $lineId) => [
                'target_line_id' => $lineId,
                'amount' => $rows->reduce(fn (string $carry, array $row) => Decimal::add($carry, $row['amount']), '0'),
            ])
            ->values();

        if ($requested->isEmpty()) {
            return [];
        }

        if ($lock) {
            DB::table('transaction_lines')
                ->whereIn('id', $requested->pluck('target_line_id')->all())
                ->lockForUpdate()
                ->get(['id']);
        }

        $open = $this->openItems($ledger->id, null, $direction, $excludeTransactionId)->keyBy('target_line_id');
        $plan = [];

        foreach ($requested as $row) {
            $item = $open->get($row['target_line_id']);

            if (! $item) {
                throw SettlementException::make('That document is no longer open on this ledger.', [
                    ['target_line_id' => $row['target_line_id'], 'ledger_id' => $ledger->id],
                ]);
            }

            if (Decimal::cmp($row['amount'], $item['remaining_amount']) > 0) {
                throw SettlementException::make('Applied amount exceeds what is left on the document.', [
                    [
                        'target_line_id' => $row['target_line_id'],
                        'applied' => $row['amount'],
                        'remaining' => $item['remaining_amount'],
                    ],
                ]);
            }

            $plan[] = $this->allocationRow($item, $row['amount']);
        }

        return $plan;
    }

    /**
     * What an on-account voucher settles when the user picked nothing.
     *
     * "Relieve what is open, park the excess" — money received against a
     * customer who owes something is paying that debt, and dumping the whole
     * receipt into Customer Advances while the invoice sits open would report
     * both a receivable and a liability for the same transaction.
     *
     * Restricted to open items in the CASH's own currency. Applying dollars to
     * an afghani invoice needs an agreed conversion rate, and inventing one on
     * the user's behalf is precisely what this service refuses to do — those
     * items stay open until someone states the rate.
     *
     * @return array<int, array<string, mixed>>
     */
    private function defaultAllocations(
        Ledger $ledger,
        string $direction,
        string $cashCurrencyId,
        string $cashAmount,
        ?string $excludeTransactionId = null
    ): array {
        $remaining = $cashAmount;
        $allocations = [];

        foreach ($this->openItems($ledger->id, $cashCurrencyId, $direction, $excludeTransactionId) as $item) {
            if (! Decimal::isPositive($remaining)) {
                break;
            }

            $apply = Decimal::cmp($remaining, $item['remaining_amount']) < 0
                ? $remaining
                : $item['remaining_amount'];

            $allocations[] = ['target_line_id' => $item['target_line_id'], 'amount' => $apply];
            $remaining = Decimal::sub($remaining, $apply);
        }

        return $allocations;
    }

    /**
     * Build the journal lines.
     *
     * One cash line. One AR/AP line per distinct (currency, booking rate) —
     * three invoices booked at three rates produce three lines, because
     * collapsing them would mean picking a single rate for the relief and that
     * choice is exactly the error this whole design avoids. One FX line for the
     * net, in base currency only.
     *
     * @param  array<string, array<string, mixed>>  $groups
     * @param  array<string, string>  $remarks
     * @return array{lines: array<int, array<string, mixed>>, forex: string}
     */
    private function buildLines(
        Ledger $ledger,
        string $direction,
        string $branchId,
        array $groups,
        string $cashAccountId,
        string $cashCurrencyId,
        string $cashRate,
        string $cashAmount,
        string $excessCash,
        array $remarks
    ): array {
        // The cash follows the direction; the party line is always its
        // opposite. Which control account that party line hits is a separate
        // question, answered by the ledger.
        $cashIn = $direction !== self::DIRECTION_OUT;
        $cashKey = $cashIn ? 'debit' : 'credit';
        $partyKey = $cashIn ? 'credit' : 'debit';

        $lines = [[
            'account_id' => $cashAccountId,
            'currency_id' => $cashCurrencyId,
            'rate' => $cashRate,
            $cashKey => $cashAmount,
            'remark' => $remarks['en'],
            'remark_fa' => $remarks['fa'],
            'remark_ps' => $remarks['ps'],
        ]];

        $partyAccountId = $this->partyAccountId($ledger, $branchId);

        foreach ($groups as $currencyId => $group) {
            // Group by rate, not by document. Two invoices booked at 60 relieve
            // as one line; the settlements rows keep the per-document detail.
            $byRate = [];

            foreach ($group['plan'] as $row) {
                $rate = $row['target_rate'];
                $byRate[$rate] = Decimal::add($byRate[$rate] ?? '0', $row['amount_applied']);
            }

            foreach ($byRate as $rate => $applied) {
                $lines[] = [
                    'account_id' => $partyAccountId,
                    'ledger_id' => $ledger->id,
                    'currency_id' => $currencyId,
                    'rate' => (string) $rate,
                    $partyKey => $applied,
                    'remark' => $remarks['en'],
                    'remark_fa' => $remarks['fa'],
                    'remark_ps' => $remarks['ps'],
                ];
            }
        }

        if (Decimal::isPositive($excessCash)) {
            // Overpayments are common here and rejecting them just pushes the
            // problem onto the user. Park the excess where it is visible.
            $lines[] = [
                'account_id' => $this->advanceAccountId($ledger, $branchId),
                'ledger_id' => $ledger->id,
                'currency_id' => $cashCurrencyId,
                'rate' => $cashRate,
                $partyKey => $excessCash,
                'remark' => $remarks['advance'],
                'remark_fa' => $remarks['advance_fa'],
                'remark_ps' => $remarks['advance_ps'],
            ];
        }

        $forex = $this->basePlug($lines);

        if (! Decimal::isZero($forex)) {
            // Zero-FX fast path: when the claim was booked at the rate the cash
            // moved at — every AFN voucher, and any foreign one where the rate
            // has not shifted — there is no line here at all, and the entry is
            // byte-identical to what the system posted before settlement existed.
            $lines[] = $this->forexLine($forex, $branchId, $remarks);
        }

        return ['lines' => $lines, 'forex' => $forex];
    }

    /**
     * How far the entry is out of balance in base currency, before the FX line.
     *
     * Taking the plug from the ROUNDED line totals rather than from a separate
     * sum of per-document differences is deliberate: it makes the FX line absorb
     * every rounding remainder. AR is then relieved by exactly what the
     * subledger says, to the last fraction, which is the number that has to tie.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function basePlug(array $lines): string
    {
        $debit = '0';
        $credit = '0';

        foreach ($lines as $line) {
            $debit = Decimal::add($debit, Decimal::toBase($line['debit'] ?? '0', $line['rate']));
            $credit = Decimal::add($credit, Decimal::toBase($line['credit'] ?? '0', $line['rate']));
        }

        return Decimal::sub($debit, $credit);
    }

    /**
     * The FX line is AFN-ONLY, with no document amount in the foreign currency.
     *
     * A realised exchange difference genuinely has no dollar value — writing
     * "0 USD" on it would be a statement about dollars that is not true, and it
     * would then have to be excluded by hand from every USD subledger report.
     *
     * @param  array<string, string>  $remarks
     * @return array<string, mixed>
     */
    private function forexLine(string $forex, string $branchId, array $remarks): array
    {
        $isGain = Decimal::isPositive($forex);
        $slug = $isGain ? 'fx-gain' : 'fx-loss';
        $amount = Decimal::abs($forex);

        $baseCurrency = $this->baseCurrency($branchId);

        return [
            'account_id' => $this->requireAccount($slug, $branchId),
            'currency_id' => $baseCurrency->id,
            'rate' => '1',
            // Debits exceed credits => more base came in than the claim was
            // worth => a gain, which is income and belongs on the credit side.
            $isGain ? 'credit' : 'debit' => $amount,
            'remark' => $remarks[$isGain ? 'gain' : 'loss'],
            'remark_fa' => $remarks[$isGain ? 'gain_fa' : 'loss_fa'],
            'remark_ps' => $remarks[$isGain ? 'gain_ps' : 'loss_ps'],
        ];
    }

    /**
     * Write one settlements row per target.
     *
     * @param  array<string, array<string, mixed>>  $groups
     */
    private function recordSettlements(
        Transaction $transaction,
        Ledger $ledger,
        string $direction,
        string $branchId,
        array $groups,
        string $postedForex
    ): void {
        if ($groups === []) {
            return;
        }

        $transaction->load('lines');
        $partyAccountId = $this->partyAccountId($ledger, $branchId);
        $cashIn = $direction !== self::DIRECTION_OUT;

        // Keyed by currency AND rate: one voucher can now relieve dollars at 60
        // and afghanis at 1 at the same time, and the two must not collide.
        $partyLines = $transaction->lines
            ->where('account_id', $partyAccountId)
            ->where('ledger_id', $ledger->id)
            ->filter(fn ($line) => $cashIn ? $line->credit > 0 : $line->debit > 0)
            ->keyBy(fn ($line) => $line->currency_id . '@' . Decimal::rate($line->rate));

        $rows = [];
        $forexTotal = '0';

        foreach ($groups as $currencyId => $group) {
            foreach ($group['plan'] as $row) {
                $forex = $this->rowForex($row['amount_applied'], $row['target_rate'], $group['rate'], $direction);
                $forexTotal = Decimal::add($forexTotal, $forex);

                $settlingLine = $partyLines->get($currencyId . '@' . Decimal::rate($row['target_rate']));

                if (! $settlingLine) {
                    // buildLines emits one party line per distinct currency and
                    // rate, so this cannot happen unless the two fall out of
                    // step. Fail loudly rather than write a settlement pointing
                    // at nothing.
                    throw SettlementException::make('No settling line was posted for this rate.', [
                        ['target_line_id' => $row['target_line_id'], 'target_rate' => $row['target_rate']],
                    ]);
                }

                $rows[] = [
                    'transaction_id' => $transaction->id,
                    'settling_line_id' => $settlingLine->id,
                    'target_line_id' => $row['target_line_id'],
                    'ledger_id' => $ledger->id,
                    'currency_id' => $row['currency_id'],
                    'amount_applied' => $row['amount_applied'],
                    'target_rate' => $row['target_rate'],
                    'settlement_rate' => $group['rate'],
                    'base_relieved' => $row['base_relieved'],
                    'forex_amount' => $forex,
                    'is_cross_currency' => $group['is_cross_currency'],
                    'branch_id' => $branchId,
                    'created_by' => Auth::id(),
                ];
            }
        }

        if ($rows === []) {
            return;
        }

        // The posted FX line carries every rounding remainder (see basePlug).
        // Push the same remainder onto the largest row so the settlements rows
        // add up to the line — a subledger that disagrees with the GL by a
        // hundredth is a reconciliation someone has to do by hand.
        $residual = Decimal::sub($postedForex, $forexTotal);

        if (! Decimal::isZero($residual)) {
            $largest = 0;

            foreach ($rows as $index => $row) {
                if (Decimal::cmp($row['amount_applied'], $rows[$largest]['amount_applied']) > 0) {
                    $largest = $index;
                }
            }

            $rows[$largest]['forex_amount'] = Decimal::add($rows[$largest]['forex_amount'], $residual);
        }

        foreach ($rows as $row) {
            Settlement::create($row);
        }
    }

    // ======================================================
    // ACCOUNT / LEDGER RESOLUTION
    // ======================================================

    private function resolveLedger(string $ledgerId): Ledger
    {
        $ledger = Ledger::withoutGlobalScopes()->find($ledgerId);

        if (! $ledger) {
            throw SettlementException::make('Unknown ledger.', [['ledger_id' => $ledgerId]]);
        }

        return $ledger;
    }

    /**
     * Which control account this party's balance lives in.
     *
     * A property of the LEDGER and nothing else. A customer's balance is in
     * Accounts Receivable whether you are taking money from them or refunding
     * them; a supplier's is in Accounts Payable either way.
     */
    private function partyAccountSlug(Ledger $ledger): string
    {
        $type = (string) ($ledger->type?->value ?? $ledger->type);

        return $type === 'supplier' ? 'account-payable' : 'account-receivable';
    }

    /**
     * Which column of the party's account holds the claims a voucher relieves.
     *
     * A property of the DIRECTION and nothing else, because the party line is
     * always posted opposite the cash:
     *
     *   money IN  -> party account credited -> it relieves party DEBITS
     *   money OUT -> party account debited  -> it relieves party CREDITS
     *
     * Which gives the four real cases, all from the same two rules:
     *
     *   receipt from customer  cash dr, AR cr  relieves AR debits  (invoices)
     *   payment to supplier    cash cr, AP dr  relieves AP credits (bills)
     *   payment to customer    cash cr, AR dr  relieves AR credits (their
     *                                          overpayments and credit notes)
     *   receipt from supplier  cash dr, AP cr  relieves AP debits  (advances
     *                                          you paid them, refunds)
     */
    private function claimColumn(string $direction): string
    {
        return $direction === self::DIRECTION_OUT ? 'credit' : 'debit';
    }

    private function partyAccountId(Ledger $ledger, string $branchId): string
    {
        return $this->requireAccount($this->partyAccountSlug($ledger), $branchId);
    }

    /**
     * Where unapplied money is parked.
     *
     * Named after the PARTY, posted according to the DIRECTION. Customer
     * Advances carrying a debit balance means money was refunded to a customer
     * beyond what they were owed — unusual, and legible as such, which is
     * better than inventing a fifth account for it.
     */
    private function advanceAccountId(Ledger $ledger, string $branchId): string
    {
        $slug = $this->partyAccountSlug($ledger) === 'account-payable'
            ? 'supplier-advances'
            : 'customer-advances';

        return BranchContext::glAccount($slug, $branchId)
            ?? $this->createAdvanceAccount($slug, $branchId);
    }

    /**
     * Accounts are resolved by SLUG, never by name — names are localised into
     * Dari and Pashto and a name lookup breaks the moment a user switches
     * language.
     */
    private function requireAccount(string $slug, string $branchId): string
    {
        $accountId = BranchContext::glAccount($slug, $branchId);

        if (! $accountId) {
            throw SettlementException::make(
                'This branch has no account for that slug. Run branch provisioning.',
                [['slug' => $slug, 'branch_id' => $branchId]]
            );
        }

        return $accountId;
    }

    /**
     * Advances are created on demand rather than required up front, so a branch
     * provisioned before this feature existed can still take an overpayment.
     */
    private function createAdvanceAccount(string $slug, string $branchId): string
    {
        $definition = collect(Account::defaultAccounts())->firstWhere('slug', $slug);

        if (! $definition) {
            throw SettlementException::make('No definition for advance account.', [['slug' => $slug]]);
        }

        $typeId = AccountType::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('slug', $definition['account_type_slug'])
            ->value('id');

        if (! $typeId) {
            throw SettlementException::make(
                'This branch has no account type for the advance account.',
                [['slug' => $slug, 'account_type_slug' => $definition['account_type_slug']]]
            );
        }

        $account = Account::withoutGlobalScopes()->create([
            'name' => $definition['name'],
            'local_name' => $definition['local_name'] ?? null,
            'number' => $definition['number'],
            'account_type_id' => $typeId,
            'parent_id' => Account::withoutGlobalScopes()
                ->where('branch_id', $branchId)
                ->where('slug', $definition['parent_slug'] ?? null)
                ->value('id'),
            'slug' => $slug,
            'branch_id' => $branchId,
            'remark' => $definition['remark'] ?? null,
            'is_main' => true,
            'is_active' => true,
            'created_by' => Auth::id(),
        ]);

        BranchContext::flush($branchId);

        return $account->id;
    }

    private function baseCurrency(string $branchId): Currency
    {
        $currency = BranchContext::homeCurrency($branchId);

        if (! $currency) {
            throw SettlementException::make('This branch has no base currency.', [['branch_id' => $branchId]]);
        }

        return $currency;
    }

    // ======================================================
    // HELPERS
    // ======================================================

    /**
     * How a claim is labelled in the open-items list.
     *
     * An opening balance posts with reference_type = Ledger, which on its own
     * renders as "Ledger" and tells the user nothing — they are looking for the
     * balance they entered when the customer was set up. The ledger_openings
     * table is what actually identifies one, so that is what is checked; the
     * reference type alone cannot distinguish an opening from any other
     * ledger-referenced entry.
     *
     * @param  array<string, true>  $openingTransactionIds
     */
    private function documentType(?string $referenceType, ?string $transactionId, array $openingTransactionIds = []): string
    {
        if ($transactionId !== null && isset($openingTransactionIds[$transactionId])) {
            return 'Opening Balance';
        }

        return $referenceType ? class_basename($referenceType) : 'Journal';
    }

    /**
     * Which of these transactions are opening balances.
     *
     * One query for the whole page rather than a lookup per row.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @return array<string, true>
     */
    private function openingTransactionIds(Collection $rows): array
    {
        $ids = $rows->pluck('transaction_id')->filter()->unique()->all();

        if ($ids === []) {
            return [];
        }

        return DB::table('ledger_openings')
            ->whereIn('transaction_id', $ids)
            ->whereNull('deleted_at')
            ->pluck('transaction_id')
            ->mapWithKeys(fn ($id) => [(string) $id => true])
            ->all();
    }

    /**
     * Resolve human document numbers in one query per referenced type.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @return array<string, string>
     */
    private function documentNumbers(Collection $rows): array
    {
        $tables = [
            \App\Models\Sale\Sale::class => 'sales',
            \App\Models\Purchase\Purchase::class => 'purchases',
            \App\Models\Receipt\Receipt::class => 'receipts',
            \App\Models\Payment\Payment::class => 'payments',
        ];

        $numbers = [];

        foreach ($rows->groupBy('reference_type') as $type => $group) {
            $table = $tables[$type] ?? null;

            if (! $table) {
                continue;
            }

            $ids = $group->pluck('reference_id')->filter()->unique()->all();

            foreach (DB::table($table)->whereIn('id', $ids)->get(['id', 'number']) as $record) {
                $numbers[(string) $record->id] = (string) $record->number;
            }
        }

        return $numbers;
    }

    /**
     * @param  array<string, mixed>  $voucher
     * @return array<string, string>
     */
    private function remarks(array $voucher, Ledger $ledger): array
    {
        $base = (string) ($voucher['remark'] ?? ('Settlement — ' . $ledger->name));

        return [
            'en' => $base,
            'fa' => (string) ($voucher['remark_fa'] ?? $base),
            'ps' => (string) ($voucher['remark_ps'] ?? $base),
            'advance' => 'Advance from ' . $ledger->name,
            'advance_fa' => 'پیش‌پرداخت از ' . $ledger->name,
            'advance_ps' => 'پیش‌پرداخت له ' . $ledger->name,
            'gain' => 'Exchange gain on ' . $base,
            'gain_fa' => 'سود تغیر ارز',
            'gain_ps' => 'د اسعارو ګټه',
            'loss' => 'Exchange loss on ' . $base,
            'loss_fa' => 'ضرر تغیر ارز',
            'loss_ps' => 'د اسعارو زیان',
        ];
    }
}
