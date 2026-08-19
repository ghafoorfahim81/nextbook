<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Enums\TransactionStatus;
use App\Exceptions\Accounting\InvalidPostingException;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Administration\Branch;
use App\Models\Administration\Currency;
use App\Models\Inventory\StockMovement;
use App\Models\Transaction\Transaction;
use App\Support\Decimal;
use App\Support\TransactionStateMachine;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * The single posting boundary for the general ledger.
 *
 * Every financial event in the system — sales, purchases, receipts, payments,
 * expenses, drawings, capital contributions, openings, transfers, manual
 * journals, reversals — routes through post() or reverse(). Nothing else may
 * insert into transaction_lines.
 *
 * Each line carries its own currency and rate, plus the base-currency (AFN)
 * value of that line. That is what lets one voucher relieve a receivable at
 * its original booking rate while receiving cash at today's rate.
 *
 * `rate` is REQUIRED at this boundary. post() will default a line's rate to
 * the header rate when a caller omits it, but nothing downstream — not the
 * model, not a query, not a report — may fall back to the header rate. A
 * silent fallback is how an AR line quietly ends up carrying the payment-date
 * rate instead of the invoice-date rate.
 */
class TransactionService
{
    /** Amounts are stored at 4 decimal places. */
    private const AMOUNT_SCALE = 4;

    /** Rates are stored at 8 decimal places. */
    private const RATE_SCALE = 8;

    /** Tolerance when checking base against amount x rate. */
    private const RATE_TOLERANCE = '0.0001';

    private $dateConversionService;

    public function __construct(
        DateConversionService $dateConversionService,
        private StockService $stockService,
    ) {
        $this->dateConversionService = $dateConversionService;
    }

    /**
     * Core posting method.
     *
     * ONE transaction (voucher), MANY lines. The entry must balance within
     * every currency it touches AND in base currency.
     */
    public function post(array $header, array $lines): Transaction
    {
        return DB::transaction(function () use ($header, $lines) {

            $this->validateHeader($header);
            $this->validateLines($lines);

            $status = TransactionStatus::tryFrom($header['status'] ?? TransactionStatus::POSTED->value)
                ?? TransactionStatus::POSTED;

            $branchId = $this->resolveBranchId($header);
            $baseCurrencyId = $this->resolveBaseCurrencyId($branchId);

            // Normalise and validate everything BEFORE a single row is written,
            // so a rejected entry never leaves a half-posted voucher behind.
            $prepared = $this->prepareLines($lines, $header);
            $this->assertInvariants(
                $prepared,
                $baseCurrencyId,
                (bool) ($header['cross_currency'] ?? false)
            );

            $transaction = Transaction::create([
                'currency_id'       => $header['currency_id'],
                'base_currency_id'  => $baseCurrencyId,
                'rate'              => $header['rate'],
                'is_cross_currency' => (bool) ($header['cross_currency'] ?? false),
                'date'              => $header['date'],
                'voucher_number'    => $header['voucher_number'] ?? null,
                'reference_type'    => $header['reference_type'] ?? null,
                'reference_id'      => $header['reference_id'] ?? null,
                'remark'            => $header['remark'] ?? null,
                'status'            => $status->value,
                'reversal_of_id'    => $header['reversal_of_id'] ?? null,
                'posted_at'         => $status === TransactionStatus::POSTED ? now() : null,
                'posted_by'         => $status === TransactionStatus::POSTED ? Auth::id() : null,
                // A draft keeps its lines in the payload instead of the ledger,
                // so nothing reaches transaction_lines until it is posted.
                'posting_payload'   => $status === TransactionStatus::DRAFT
                    ? array_merge($header['posting_payload'] ?? [], ['lines' => $lines])
                    : ($header['posting_payload'] ?? null),
                'branch_id'         => $branchId,
                'created_by'        => Auth::id(),
            ]);

            if ($status === TransactionStatus::DRAFT) {
                return $transaction;
            }

            foreach ($prepared as $line) {
                $transaction->lines()->create([
                    'account_id'       => $line['account_id'],
                    'ledger_id'        => $line['ledger_id'],
                    'journal_class_id' => $line['journal_class_id'],
                    'currency_id'      => $line['currency_id'],
                    'rate'             => $line['rate'],
                    'debit'            => $line['debit'],
                    'credit'           => $line['credit'],
                    'base_debit'       => $line['base_debit'],
                    'base_credit'      => $line['base_credit'],
                    'remark'           => $line['remark'],
                    'remark_fa'        => $line['remark_fa'],
                    'remark_ps'        => $line['remark_ps'],
                    // No created_by/branch_id here: transaction_lines has
                    // neither column. Who posted the entry, and which branch it
                    // belongs to, are carried once on the voucher header.
                ]);
            }

            return $transaction;
        });
    }

    /**
     * Promote a draft voucher to posted.
     *
     * The lines come from the draft's stored posting_payload unless the caller
     * supplies a fresh set, and they go through the same normalisation and
     * invariant checks as post() — a draft is not exempt from balancing.
     */
    public function postDraft(Transaction $draft, array $lines = []): Transaction
    {
        return DB::transaction(function () use ($draft, $lines) {
            $from = TransactionStatus::tryFrom((string) $draft->status);

            if ($from !== TransactionStatus::DRAFT) {
                throw InvalidStatusTransitionException::for($from ?? TransactionStatus::POSTED, TransactionStatus::POSTED);
            }

            if (! TransactionStateMachine::canTransition($from, TransactionStatus::POSTED)) {
                throw InvalidStatusTransitionException::for($from, TransactionStatus::POSTED);
            }

            $lines = $lines ?: (array) data_get($draft->posting_payload, 'lines', []);
            $this->validateLines($lines);

            $baseCurrencyId = $draft->base_currency_id ?? $this->resolveBaseCurrencyId($draft->branch_id);

            $prepared = $this->prepareLines($lines, [
                'currency_id' => $draft->currency_id,
                'rate'        => $draft->rate,
            ]);

            $this->assertInvariants($prepared, $baseCurrencyId, (bool) $draft->is_cross_currency);

            foreach ($prepared as $line) {
                $draft->lines()->create([
                    'account_id'       => $line['account_id'],
                    'ledger_id'        => $line['ledger_id'],
                    'journal_class_id' => $line['journal_class_id'],
                    'currency_id'      => $line['currency_id'],
                    'rate'             => $line['rate'],
                    'debit'            => $line['debit'],
                    'credit'           => $line['credit'],
                    'base_debit'       => $line['base_debit'],
                    'base_credit'      => $line['base_credit'],
                    'remark'           => $line['remark'],
                    'remark_fa'        => $line['remark_fa'],
                    'remark_ps'        => $line['remark_ps'],
                    // No created_by/branch_id here: transaction_lines has
                    // neither column. Who posted the entry, and which branch it
                    // belongs to, are carried once on the voucher header.
                ]);
            }

            $draft->update([
                'status'     => TransactionStatus::POSTED->value,
                'posted_at'  => now(),
                'posted_by'  => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            return $draft->refresh();
        });
    }

    // ======================================================
    // NORMALISATION
    // ======================================================

    /**
     * Resolve every line to an explicit currency, rate and base value.
     *
     * A caller that omits currency_id/rate inherits the header's — that is the
     * single-currency voucher every existing caller posts today, and it keeps
     * their numbers byte-for-byte identical.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function prepareLines(array $lines, array $header): array
    {
        $prepared = [];

        foreach ($lines as $index => $line) {
            $currencyId = $line['currency_id'] ?? $header['currency_id'];
            $rate = $this->scale($line['rate'] ?? $header['rate'], self::RATE_SCALE);

            $debit = $this->scale($line['debit'] ?? 0, self::AMOUNT_SCALE);
            $credit = $this->scale($line['credit'] ?? 0, self::AMOUNT_SCALE);

            $prepared[] = [
                'index'            => $index,
                'account_id'       => $line['account_id'],
                'ledger_id'        => $line['ledger_id'] ?? null,
                'journal_class_id' => $line['journal_class_id'] ?? null,
                'currency_id'      => $currencyId,
                'rate'             => $rate,
                'debit'            => $debit,
                'credit'           => $credit,
                'base_debit'       => array_key_exists('base_debit', $line)
                    ? $this->scale($line['base_debit'], self::AMOUNT_SCALE)
                    : $this->toBase($debit, $rate),
                'base_credit'      => array_key_exists('base_credit', $line)
                    ? $this->scale($line['base_credit'], self::AMOUNT_SCALE)
                    : $this->toBase($credit, $rate),
                'remark'           => $line['remark'] ?? null,
                'remark_fa'        => $line['remark_fa'] ?? null,
                'remark_ps'        => $line['remark_ps'] ?? null,
            ];
        }

        return $prepared;
    }

    // ======================================================
    // INVARIANTS
    // ======================================================

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    protected function assertInvariants(
        array $lines,
        ?string $baseCurrencyId = null,
        bool $crossCurrency = false
    ): void {
        $this->assertSingleSide($lines);
        $this->assertRateConsistency($lines);
        $this->assertBalancedPerCurrency($lines, $baseCurrencyId, $crossCurrency);
        $this->assertBalancedInBase($lines);
    }

    /**
     * Invariant 3 — a line has debit OR credit, never both. Same for base.
     */
    protected function assertSingleSide(array $lines): void
    {
        $violations = [];

        foreach ($lines as $line) {
            $debitSet = $this->isPositive($line['debit']);
            $creditSet = $this->isPositive($line['credit']);

            if ($debitSet && $creditSet) {
                $violations[] = [
                    'line' => $line['index'],
                    'account_id' => $line['account_id'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                ];

                continue;
            }

            if (! $debitSet && ! $creditSet) {
                $violations[] = [
                    'line' => $line['index'],
                    'account_id' => $line['account_id'],
                    'reason' => 'line has neither debit nor credit',
                ];

                continue;
            }

            if ($this->isPositive($line['base_debit']) && $this->isPositive($line['base_credit'])) {
                $violations[] = [
                    'line' => $line['index'],
                    'account_id' => $line['account_id'],
                    'base_debit' => $line['base_debit'],
                    'base_credit' => $line['base_credit'],
                ];
            }
        }

        if ($violations !== []) {
            throw InvalidPostingException::make(
                'Each transaction line must have either debit or credit, never both.',
                $violations
            );
        }
    }

    /**
     * Invariant 4 — |base - (amount x rate)| <= 0.0001.
     */
    protected function assertRateConsistency(array $lines): void
    {
        $violations = [];

        foreach ($lines as $line) {
            foreach ([['debit', 'base_debit'], ['credit', 'base_credit']] as [$amountKey, $baseKey]) {
                $expected = $this->toBase($line[$amountKey], $line['rate']);
                $drift = $this->abs(bcsub($line[$baseKey], $expected, self::AMOUNT_SCALE));

                if (bccomp($drift, self::RATE_TOLERANCE, self::AMOUNT_SCALE) > 0) {
                    $violations[] = [
                        'line' => $line['index'],
                        'account_id' => $line['account_id'],
                        'amount' => $line[$amountKey],
                        'rate' => $line['rate'],
                        'expected_base' => $expected,
                        'given_base' => $line[$baseKey],
                    ];
                }
            }
        }

        if ($violations !== []) {
            throw InvalidPostingException::make(
                'Base amount does not match amount x rate.',
                $violations
            );
        }
    }

    /**
     * Invariant 1 — within each NON-BASE currency, debits equal credits.
     *
     * Base-currency (AFN) lines are the balancing plug of a multi-currency
     * entry and are exempt. A realised FX gain/loss has no document amount in
     * the foreign currency at all — it exists only in base — so it lands as a
     * one-sided AFN line:
     *
     *     Cash in Hand USD   USD  dr 200  @55   base 11,000
     *     Accounts Recv      USD  cr 200  @60   base 12,000
     *     FX Loss            AFN  dr 1,000 @1   base  1,000
     *
     * USD self-balances; AFN does not, and must not be forced to. The whole
     * entry still has to balance in base — assertBalancedInBase() is what
     * actually holds the ledger together here, and it is never relaxed.
     *
     * The exemption applies only when the entry genuinely spans currencies. A
     * voucher that touches nothing but AFN has no plug to be, so it is still
     * required to self-balance and an AFN-only entry posts exactly as before.
     *
     * A NON-base currency that does not close is a broken subledger, not an FX
     * difference, and no AFN plug may paper over it. The one exception is a
     * cross-currency settlement — a USD invoice paid with AFN cash — where the
     * dollars really are relieved by afghanis and no dollar counterpart exists.
     * That case must be declared by the caller via header['cross_currency'],
     * because it is a commercial decision the user made, not something the
     * numbers can be trusted to imply. Base balance still holds absolutely.
     */
    protected function assertBalancedPerCurrency(
        array $lines,
        ?string $baseCurrencyId = null,
        bool $crossCurrency = false
    ): void {
        $totals = [];

        foreach ($lines as $line) {
            $currencyId = $line['currency_id'];
            $totals[$currencyId] ??= ['debit' => '0', 'credit' => '0'];
            $totals[$currencyId]['debit'] = bcadd($totals[$currencyId]['debit'], $line['debit'], self::AMOUNT_SCALE);
            $totals[$currencyId]['credit'] = bcadd($totals[$currencyId]['credit'], $line['credit'], self::AMOUNT_SCALE);
        }

        $isMultiCurrency = count($totals) > 1;

        // The escape hatch only means anything on an entry that spans
        // currencies. Set on a single-currency voucher it is a caller bug, and
        // silently honouring it would disable the check on ordinary vouchers.
        if ($crossCurrency && ! $isMultiCurrency) {
            throw InvalidPostingException::make(
                'A cross-currency entry must touch more than one currency.',
                [['line' => 'header', 'currencies' => array_keys($totals)]]
            );
        }

        if ($crossCurrency) {
            return;
        }

        $violations = [];

        foreach ($totals as $currencyId => $total) {
            if ($isMultiCurrency && $baseCurrencyId !== null && $currencyId === $baseCurrencyId) {
                continue;
            }

            if (bccomp($total['debit'], $total['credit'], self::AMOUNT_SCALE) !== 0) {
                $violations[] = [
                    'line' => 'currency ' . $currencyId,
                    'total_debit' => $total['debit'],
                    'total_credit' => $total['credit'],
                    'difference' => bcsub($total['debit'], $total['credit'], self::AMOUNT_SCALE),
                ];
            }
        }

        if ($violations !== []) {
            throw InvalidPostingException::make(
                'Transaction is not balanced within every currency.',
                $violations
            );
        }
    }

    /**
     * Invariant 2 — the whole entry balances in base currency.
     *
     * Not implied by invariant 1, and now that invariant 1 exempts the base
     * currency it is the only thing standing between a settlement voucher and
     * an out-of-balance ledger. An FX line is exactly the case where the
     * foreign currencies each balance but the base totals would diverge
     * without it.
     */
    protected function assertBalancedInBase(array $lines): void
    {
        $debit = '0';
        $credit = '0';

        foreach ($lines as $line) {
            $debit = bcadd($debit, $line['base_debit'], self::AMOUNT_SCALE);
            $credit = bcadd($credit, $line['base_credit'], self::AMOUNT_SCALE);
        }

        if (bccomp($debit, $credit, self::AMOUNT_SCALE) !== 0) {
            throw InvalidPostingException::make(
                'Transaction is not balanced in base currency.',
                [[
                    'line' => 'base',
                    'total_base_debit' => $debit,
                    'total_base_credit' => $credit,
                    'difference' => bcsub($debit, $credit, self::AMOUNT_SCALE),
                ]]
            );
        }
    }

    // ======================================================
    // VALIDATION
    // ======================================================

    protected function validateHeader(array $header): void
    {
        validator($header, [
            'currency_id'    => 'required|exists:currencies,id',
            // A rate of 0 would value the whole voucher at nothing in base.
            'rate'           => 'required|numeric|gt:0',
            'date'           => 'required|date',
            'voucher_number' => 'nullable',
            'reference_type' => 'nullable|string',
            'reference_id'   => 'nullable|string',
            'remark'         => 'nullable|string',
            'status'         => 'nullable|string',
            // Opt-in relaxation of per-currency balance. See
            // assertBalancedPerCurrency() — never set this to work around an
            // entry you have not deliberately built as cross-currency.
            'cross_currency' => 'nullable|boolean',
        ])->validate();
    }

    protected function validateLines(array $lines): void
    {
        if (empty($lines)) {
            throw new Exception('Transaction must have at least one line');
        }

        validator(
            ['lines' => $lines],
            [
                'lines'                    => 'required|array|min:2',
                'lines.*.account_id'       => 'required|exists:accounts,id',
                'lines.*.ledger_id'        => 'nullable|exists:ledgers,id',
                'lines.*.currency_id'      => 'nullable|exists:currencies,id',
                'lines.*.rate'             => 'nullable|numeric|gt:0',
                'lines.*.debit'            => 'nullable|numeric|min:0',
                'lines.*.credit'           => 'nullable|numeric|min:0',
                'lines.*.base_debit'       => 'nullable|numeric|min:0',
                'lines.*.base_credit'      => 'nullable|numeric|min:0',
                'lines.*.journal_class_id' => 'nullable|exists:journal_classes,id',
                'lines.*.remark'           => 'nullable|string',
                'lines.*.remark_fa'        => 'nullable|string',
                'lines.*.remark_ps'        => 'nullable|string',
            ]
        )->validate();
    }

    // ======================================================
    // REVERSAL (AUDIT-SAFE)
    // ======================================================

    public function reverse(
        Transaction $original,
        ?string $reason = null,
        ?string $number = null,
        ?string $referenceType = null
    ): Transaction {
        return DB::transaction(function () use ($original, $reason, $number, $referenceType) {
            $from = TransactionStatus::tryFrom((string) $original->status);

            if ($from !== TransactionStatus::POSTED || ! TransactionStateMachine::canTransition($from, TransactionStatus::REVERSED)) {
                throw InvalidStatusTransitionException::for($from ?? TransactionStatus::DRAFT, TransactionStatus::REVERSED);
            }

            $original->loadMissing('lines');

            // The reversal reads back in the operator's language, naming the
            // document it undoes rather than just saying "Reversal".
            $reversalRemark = $this->getReversalRemark($original, $number, $referenceType);

            // Each line keeps its OWN currency and rate. Re-valuing a reversal
            // at today's rate would invent an FX difference that belongs to the
            // settlement workflow, not to an undo.
            $lines = $original->lines->map(fn ($line) => [
                'account_id'       => $line->account_id,
                'ledger_id'        => $line->ledger_id,
                'journal_class_id' => $line->journal_class_id,
                'currency_id'      => $line->currency_id,
                'rate'             => $line->rate,
                'debit'            => $line->credit,
                'credit'           => $line->debit,
                'base_debit'       => $line->base_credit,
                'base_credit'      => $line->base_debit,
                'remark'           => $reversalRemark['remark'],
                'remark_fa'        => $reversalRemark['remark_fa'] ?? null,
                'remark_ps'        => $reversalRemark['remark_ps'] ?? null,
            ])->all();

            $reversal = $this->post(
                header: [
                    'currency_id'    => $original->currency_id,
                    'rate'           => $original->rate,
                    'date'           => now()->toDateString(),
                    'voucher_number' => 'Reversal of ' . $original->voucher_number,
                    'reference_type' => 'reversal',
                    'reference_id'   => $original->id,
                    'remark'         => $reason ?? 'Reversal of transaction ' . $original->id,
                    'status'         => TransactionStatus::POSTED->value,
                    'reversal_of_id' => $original->id,
                    'branch_id'      => $original->branch_id,
                ],
                lines: $lines,
            );

            $this->voidStockMovementsFor($original);

            $original->update([
                'status'          => TransactionStatus::REVERSED->value,
                'reversed_at'     => now(),
                'reversal_reason' => $reason,
                'updated_by'      => Auth::id(),
            ]);

            return $reversal;
        });
    }

    /**
     * Undo the stock side of a reversed voucher.
     *
     * Reversing the ledger alone would leave the goods where the original
     * document put them, so each movement is posted back in the opposite
     * direction at its own recorded cost and the originals are voided.
     */
    private function voidStockMovementsFor(Transaction $original): void
    {
        $movements = StockMovement::query()
            ->where('reference_type', $original->reference_type)
            ->where('reference_id', $original->reference_id)
            ->get();

        foreach ($movements as $movement) {
            $this->stockService->post([
                'branch_id' => $movement->branch_id,
                'item_id' => $movement->item_id,
                'warehouse_id' => $movement->warehouse_id,
                'unit_measure_id' => $movement->unit_measure_id,
                'size_id' => $movement->size_id,
                'movement_type' => $movement->movement_type === StockMovementType::IN
                    ? StockMovementType::OUT->value
                    : StockMovementType::IN->value,
                'source' => $movement->source,
                'reference_type' => Transaction::class,
                'reference_id' => $movement->reference_id,
                'quantity' => (float) $movement->quantity,
                'unit_cost' => $movement->unit_cost,
                'unit_cost_override' => (float) $movement->unit_cost,
                'batch' => $movement->batch,
                'expire_date' => $movement->expire_date,
                'date' => now()->toDateString(),
                'status' => \App\Enums\StockStatus::VOIDED->value,
            ]);
        }

        foreach ($movements as $movement) {
            $movement->update(['status' => \App\Enums\StockStatus::VOIDED->value]);
        }
    }

    /**
     * Localised remark naming the document a reversal undoes.
     */
    private function getReversalRemark(Transaction $original, ?string $number = null, ?string $referenceType = null): array
    {
        $number = $number ?? ($original->number ?? $original->id);
        $referenceType = $referenceType ?? $original->reference_type;

        $map = [
            \App\Models\AccountTransfer\AccountTransfer::class => [
                'Reversal of account transfer #',
                'برگشتی انتقال حساب #',
                'د حساب لیږد بیرته راګرځول #',
            ],
            \App\Models\JournalEntry\JournalEntry::class => [
                'Reversal of journal entry #',
                'برگشتی ژورنال #',
                'د ژورنال داخله بیرته راګرځول #',
            ],
            \App\Models\Purchase\Purchase::class => [
                'Reversal of purchase #',
                'برگشتی خریداری #',
                'د پيرودنې بیرته راګرځول #',
            ],
            \App\Models\Sale\Sale::class => [
                'Reversal of sale #',
                'برگشتی فروش #',
                'د خرڅلاو بیرته راګرځول #',
            ],
            \App\Models\Sale\SaleReturn::class => [
                'Reversal of sale return #',
                'برگشتی مسترد فروش #',
                'د خرڅلاو بیرته راستنیدو بیرته راګرځول #',
            ],
            \App\Models\Purchase\PurchaseReturn::class => [
                'Reversal of purchase return #',
                'برگشتی مسترد خرید #',
                'د پیرودنې بیرته راستنیدو بیرته راګرځول #',
            ],
            \App\Models\Payment\Payment::class => [
                'Reversal of payment #',
                'برگشتی پرداخت #',
                'د تادیې بیرته راګرځول #',
            ],
            \App\Models\Receipt\Receipt::class => [
                'Reversal of receipt #',
                'برگشتی رسید #',
                'د رسید بیرته راګرځول #',
            ],
            \App\Models\Inventory\StockAdjustment::class => [
                'Reversal of stock adjustment #',
                'برگشتی تعدیل موجودی #',
                'د موجودۍ تعدیل بیرته راګرځول #',
            ],
            \App\Models\Expense\Expense::class => [
                'Reversal of expense #',
                'برگشتی هزینه #',
                'د هزینه بیرته راګرځول #',
            ],
            \App\Models\Owner\Drawing::class => [
                'Reversal of drawing #',
                'برگشتی برداشت #',
                'د اخیستنې بیرته راګرځول #',
            ],
        ];

        [$en, $fa, $ps] = $map[$referenceType] ?? [
            'Reversal of transaction #',
            'برگشت تراکنش #',
            'د تراکنش بیرته راګرځول #',
        ];

        return [
            'remark'    => $en . $number,
            'remark_fa' => $fa . $number,
            'remark_ps' => $ps . $number,
        ];
    }

    // ======================================================
    // HELPERS
    // ======================================================

    protected function resolveBranchId(array $header): ?string
    {
        return $header['branch_id']
            ?? Auth::user()?->branch_id
            ?? Branch::query()->value('id');
    }

    /**
     * The branch's functional currency.
     *
     * Currencies are per-branch (unique on branch_id + code), so each branch
     * owns its own AFN row — a global lookup would cross-link branches.
     */
    protected function resolveBaseCurrencyId(?string $branchId): ?string
    {
        if ($branchId === null) {
            return null;
        }

        return Currency::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('is_base_currency', true)
            ->value('id')
            ?? Currency::withoutGlobalScopes()
                ->where('branch_id', $branchId)
                ->where('code', 'AFN')
                ->value('id');
    }

    // ------------------------------------------------------
    // Arithmetic lives in App\Support\Decimal.
    //
    // SettlementService builds base amounts and hands them to post(), which
    // then checks them against amount x rate. If the two rounded differently
    // every settlement carrying an odd fraction would be rejected here, so both
    // go through one implementation.
    // ------------------------------------------------------

    protected function toBase(string $amount, string $rate): string
    {
        return Decimal::toBase($amount, $rate);
    }

    protected function roundHalfUp(string $value, int $scale): string
    {
        return Decimal::roundHalfUp($value, $scale);
    }

    protected function scale(mixed $value, int $scale): string
    {
        return Decimal::scale($value, $scale);
    }

    protected function isPositive(string $value): bool
    {
        return Decimal::isPositive($value);
    }

    protected function abs(string $value): string
    {
        return Decimal::abs($value);
    }
}
