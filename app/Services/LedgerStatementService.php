<?php

namespace App\Services;

use App\Enums\LedgerType;
use App\Enums\TransactionStatus;
use App\Models\Administration\Currency;
use App\Models\Ledger\Ledger;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds a party (customer / supplier) statement.
 *
 * The statement is deliberately grouped *per currency*: a ledger can carry an
 * opening balance and movements in several currencies at once (a customer may
 * owe 100 USD, 10,000 AFN and 1,200 EUR simultaneously), and those amounts must
 * never be added together into one running balance. Each currency therefore
 * gets its own opening balance, movement rows, running balance and closing
 * balance. A home-currency equivalent is reported separately, converted at each
 * transaction's own historical rate so it always ties back to the general ledger.
 */
class LedgerStatementService
{
    /** Aging bucket upper bounds, in days. The final bucket is open ended. */
    private const AGING_BUCKETS = [30, 60, 90];

    public function __construct(
        private readonly DateConversionService $dateConversionService,
    ) {
    }

    /**
     * @param  array{date_from?: ?string, date_to?: ?string, currency_id?: ?string}  $filters
     */
    public function build(Ledger $ledger, array $filters = []): array
    {
        $dateTo = $this->normalizeDate($filters['date_to'] ?? null) ?? Carbon::today()->toDateString();
        $dateFrom = $this->normalizeDate($filters['date_from'] ?? null);

        // A from-date after the to-date yields an empty window rather than a
        // Postgres error on the BETWEEN, so normalise it away up front.
        if ($dateFrom && $dateFrom > $dateTo) {
            $dateFrom = null;
        }

        // The key is optional per the signature, and an empty string from the
        // query string means "all currencies" just as a missing key does.
        $currencyId = ($filters['currency_id'] ?? null) ?: null;

        $broughtForward = $dateFrom
            ? $this->broughtForwardByCurrency($ledger, $dateFrom, $currencyId)
            : collect();

        $movements = $this->movementRows($ledger, $dateFrom, $dateTo, $currencyId);

        $sections = $this->buildSections($ledger, $broughtForward, $movements, $dateTo);

        return [
            'meta' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'date_from_display' => $dateFrom ? $this->dateConversionService->toDisplay($dateFrom) : null,
                'date_to_display' => $this->dateConversionService->toDisplay($dateTo),
                'currency_id' => $currencyId,
                'ledger_type' => $ledger->type instanceof LedgerType ? $ledger->type->value : (string) $ledger->type,
                'home_currency' => $this->homeCurrency(),
                'has_multiple_currencies' => $sections->count() > 1,
            ],
            'currencies' => $this->currencyOptions($ledger),
            'sections' => $sections->values()->all(),
            'totals' => [
                'currency_count' => $sections->count(),
                'home_equivalent' => round((float) $sections->sum('home_equivalent'), 2),
                'home_equivalent_nature' => $sections->sum('home_equivalent') >= 0 ? 'dr' : 'cr',
            ],
        ];
    }

    /**
     * The party's live position in each currency it actually holds one in.
     *
     * A ledger's currencies never net against each other, so the single home
     * total on the profile card hides the real picture: a customer carrying
     * 10,000 AFN and 200 USD owes two separate amounts, each settled in its own
     * currency. This returns one unfiltered row per currency the ledger has
     * moved in, base currency first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function balancesByCurrency(Ledger $ledger): array
    {
        $normalNature = $this->normalNature($ledger);

        return $this->baseQuery($ledger)
            ->groupBy('tl.currency_id', 'c.code', 'c.name', 'c.symbol', 'c.is_base_currency')
            ->selectRaw('tl.currency_id')
            ->selectRaw('c.code as currency_code')
            ->selectRaw('c.name as currency_name')
            ->selectRaw('c.symbol as currency_symbol')
            ->selectRaw('c.is_base_currency')
            ->selectRaw('COALESCE(SUM(tl.debit), 0) as debit')
            ->selectRaw('COALESCE(SUM(tl.credit), 0) as credit')
            ->selectRaw('COALESCE(SUM((tl.base_debit - tl.base_credit)), 0) as home_equivalent')
            ->get()
            ->map(function ($row) use ($normalNature) {
                $net = round((float) $row->debit - (float) $row->credit, 2);
                $nature = $net >= 0 ? 'dr' : 'cr';

                return [
                    'currency_id' => $row->currency_id,
                    'currency_code' => $row->currency_code,
                    'currency_name' => $row->currency_name,
                    'currency_symbol' => $row->currency_symbol,
                    'is_base_currency' => (bool) $row->is_base_currency,
                    'debit' => round((float) $row->debit, 2),
                    'credit' => round((float) $row->credit, 2),
                    'balance' => abs($net),
                    'net_balance' => $net,
                    'balance_nature' => $nature,
                    'balance_label' => $this->formatBalance($net),
                    'is_normal_balance' => $nature === $normalNature,
                    'home_equivalent' => round((float) $row->home_equivalent, 2),
                ];
            })
            // Zero rows are dropped: a currency that has been fully settled is
            // noise on a balance card, not information.
            ->reject(fn (array $row) => $row['balance'] === 0.0)
            ->sortBy(fn (array $row) => [$row['is_base_currency'] ? 0 : 1, $row['currency_code']])
            ->values()
            ->all();
    }

    /**
     * Every currency this ledger has ever transacted in, for the filter dropdown.
     */
    public function currencyOptions(Ledger $ledger): array
    {
        return $this->baseQuery($ledger)
            ->distinct()
            ->orderBy('c.code')
            ->get(['c.id as id', 'c.code as code', 'c.name as name', 'c.symbol as symbol'])
            ->map(fn ($row) => [
                'id' => $row->id,
                'code' => $row->code,
                'name' => $row->name,
                'symbol' => $row->symbol,
            ])
            ->all();
    }

    /**
     * Shared join skeleton: posted, non-deleted lines belonging to this ledger,
     * scoped to the ledger's own branch.
     */
    private function baseQuery(Ledger $ledger): \Illuminate\Database\Query\Builder
    {
        return DB::table('transaction_lines as tl')
            ->join('transactions as t', function ($join) use ($ledger) {
                $join->on('t.id', '=', 'tl.transaction_id')
                    ->where('t.branch_id', '=', $ledger->branch_id)
                    ->where('t.status', '=', TransactionStatus::POSTED->value)
                    ->whereNull('t.deleted_at');
            })
            ->join('currencies as c', 'c.id', '=', 'tl.currency_id')
            ->where('tl.ledger_id', '=', $ledger->id)
            ->whereNull('tl.deleted_at');
    }

    /**
     * Net position per currency for everything strictly before the window opens.
     */
    private function broughtForwardByCurrency(Ledger $ledger, string $dateFrom, ?string $currencyId): Collection
    {
        $query = $this->baseQuery($ledger)
            ->where('t.date', '<', $dateFrom)
            ->groupBy('tl.currency_id')
            ->selectRaw('tl.currency_id')
            ->selectRaw('COALESCE(SUM(tl.debit), 0) as debit')
            ->selectRaw('COALESCE(SUM(tl.credit), 0) as credit')
            ->selectRaw('COALESCE(SUM((tl.base_debit - tl.base_credit)), 0) as home_equivalent');

        if ($currencyId) {
            $query->where('tl.currency_id', '=', $currencyId);
        }

        return $query->get()->keyBy('currency_id');
    }

    /**
     * Individual statement lines inside the window, ordered so the running
     * balance accumulates chronologically within each currency.
     */
    private function movementRows(Ledger $ledger, ?string $dateFrom, string $dateTo, ?string $currencyId): Collection
    {
        $query = $this->baseQuery($ledger)
            ->where('t.date', '<=', $dateTo)
            ->orderBy('c.code')
            ->orderBy('t.date')
            ->orderBy('t.created_at')
            ->orderBy('t.id')
            ->orderBy('tl.id')
            ->selectRaw('tl.id as line_id')
            ->selectRaw('t.id as transaction_id')
            ->selectRaw('t.date')
            ->selectRaw('tl.rate')
            ->selectRaw('tl.currency_id')
            ->selectRaw('t.reference_type')
            ->selectRaw('t.reference_id')
            ->selectRaw("COALESCE(NULLIF(t.voucher_number, ''), '-') as voucher_number")
            ->selectRaw('c.code as currency_code')
            ->selectRaw('c.name as currency_name')
            ->selectRaw('c.symbol as currency_symbol')
            ->selectRaw('c.is_base_currency')
            ->selectRaw('COALESCE(tl.debit, 0) as debit')
            ->selectRaw('COALESCE(tl.credit, 0) as credit')
            ->selectRaw('COALESCE(tl.base_debit, 0) as base_debit')
            ->selectRaw('COALESCE(tl.base_credit, 0) as base_credit')
            ->selectRaw(
                sprintf(
                    "COALESCE(NULLIF(%s, ''), NULLIF(tl.remark, ''), NULLIF(t.remark, ''), '') as description",
                    $this->localisedRemarkColumn()
                )
            );

        if ($dateFrom) {
            $query->where('t.date', '>=', $dateFrom);
        }

        if ($currencyId) {
            $query->where('tl.currency_id', '=', $currencyId);
        }

        return $query->get();
    }

    /**
     * The remark column matching the active locale. Whitelisted rather than
     * interpolated from the locale directly so this can never become injection.
     */
    private function localisedRemarkColumn(): string
    {
        return match (app()->getLocale()) {
            'fa' => 'tl.remark_fa',
            'ps' => 'tl.remark_ps',
            default => 'tl.remark',
        };
    }

    /**
     * Fold brought-forward balances and movement rows into one section per currency.
     */
    private function buildSections(Ledger $ledger, Collection $broughtForward, Collection $movements, string $dateTo): Collection
    {
        $grouped = $movements->groupBy('currency_id');

        // Currencies with an opening balance but no movement in the window still
        // need a section, otherwise the balance simply vanishes from the statement.
        $currencyIds = $grouped->keys()
            ->merge($broughtForward->keys())
            ->unique()
            ->values();

        $currencyMeta = $this->currencyMeta($currencyIds->all());

        return $currencyIds
            ->map(function ($currencyId) use ($grouped, $broughtForward, $currencyMeta, $ledger, $dateTo) {
                $rows = $grouped->get($currencyId, collect());
                $opening = $broughtForward->get($currencyId);
                $meta = $currencyMeta->get($currencyId);

                $openingBalance = round((float) ($opening->debit ?? 0) - (float) ($opening->credit ?? 0), 2);
                $openingHome = round((float) ($opening->home_equivalent ?? 0), 2);

                $running = $openingBalance;
                $totalDebit = 0.0;
                $totalCredit = 0.0;
                $homeEquivalent = $openingHome;

                $statementRows = $rows->map(function ($row) use (&$running, &$totalDebit, &$totalCredit, &$homeEquivalent) {
                    $debit = round((float) $row->debit, 2);
                    $credit = round((float) $row->credit, 2);

                    $running = round($running + $debit - $credit, 2);
                    $totalDebit += $debit;
                    $totalCredit += $credit;
                    $homeEquivalent = round($homeEquivalent + ((float) $row->base_debit - (float) $row->base_credit), 2);

                    return [
                        'id' => $row->line_id,
                        'transaction_id' => $row->transaction_id,
                        'date' => $row->date,
                        'date_display' => $this->dateConversionService->toDisplay(
                            Carbon::parse($row->date)->toDateString()
                        ),
                        'voucher_number' => $row->voucher_number,
                        'document_type' => $this->referenceLabel($row->reference_type),
                        'reference_type' => $row->reference_type,
                        'reference_id' => $row->reference_id,
                        'description' => $row->description ?: '-',
                        'rate' => round((float) $row->rate, 4),
                        'debit' => $debit,
                        'credit' => $credit,
                        'balance' => $running,
                        'balance_label' => $this->formatBalance($running),
                    ];
                })->values()->all();

                $closing = $running;

                return [
                    'currency_id' => $currencyId,
                    'currency_code' => $meta->code ?? '',
                    'currency_name' => $meta->name ?? '',
                    'currency_symbol' => $meta->symbol ?? '',
                    'is_base_currency' => (bool) ($meta->is_base_currency ?? false),

                    'opening_balance' => $openingBalance,
                    'opening_label' => $this->formatBalance($openingBalance),

                    'rows' => $statementRows,

                    'total_debit' => round($totalDebit, 2),
                    'total_credit' => round($totalCredit, 2),

                    'closing_balance' => $closing,
                    'closing_label' => $this->formatBalance($closing),
                    'closing_nature' => $closing >= 0 ? 'dr' : 'cr',
                    'is_normal_balance' => ($closing >= 0 ? 'dr' : 'cr') === $this->normalNature($ledger),

                    'home_equivalent' => $homeEquivalent,

                    'aging' => $this->aging($ledger, $rows, $openingBalance, $dateTo),
                ];
            })
            ->sortBy(fn (array $section) => [$section['is_base_currency'] ? 0 : 1, $section['currency_code']])
            ->values();
    }

    /**
     * @param  array<int, string>  $currencyIds
     */
    private function currencyMeta(array $currencyIds): Collection
    {
        if ($currencyIds === []) {
            return collect();
        }

        return DB::table('currencies')
            ->whereIn('id', $currencyIds)
            ->get(['id', 'code', 'name', 'symbol', 'is_base_currency'])
            ->keyBy('id');
    }

    /**
     * Balance-forward FIFO aging.
     *
     * Charges (invoices for a customer, bills for a supplier) are laid out oldest
     * first and settlements are applied against them in order; whatever is left
     * unsettled is bucketed by the age of its own document. This needs no
     * invoice-level allocation table and matches how a balance-forward statement
     * is normally aged.
     */
    private function aging(Ledger $ledger, Collection $rows, float $openingBalance, string $dateTo): array
    {
        $isSupplier = $this->normalNature($ledger) === 'cr';
        $asOf = Carbon::parse($dateTo)->startOfDay();

        $charges = [];
        $settlements = 0.0;

        // The opening balance behaves as the oldest charge (or the oldest credit
        // when it sits on the wrong side of the account).
        $openingCharge = $isSupplier ? -$openingBalance : $openingBalance;
        if ($openingCharge > 0) {
            $charges[] = ['age' => null, 'amount' => round($openingCharge, 2)];
        } elseif ($openingCharge < 0) {
            $settlements += abs($openingCharge);
        }

        foreach ($rows as $row) {
            $debit = (float) $row->debit;
            $credit = (float) $row->credit;
            $charge = $isSupplier ? $credit - $debit : $debit - $credit;

            if ($charge > 0) {
                $charges[] = [
                    // Absolute diff: Carbon 3 returns a signed value by default,
                    // and rows are always on or before $asOf thanks to the filter.
                    'age' => (int) Carbon::parse($row->date)->startOfDay()->diffInDays($asOf, true),
                    'amount' => round($charge, 2),
                ];
            } elseif ($charge < 0) {
                $settlements += abs($charge);
            }
        }

        foreach ($charges as $index => $charge) {
            if ($settlements <= 0) {
                break;
            }

            $applied = min($settlements, $charge['amount']);
            $charges[$index]['amount'] = round($charge['amount'] - $applied, 2);
            $settlements = round($settlements - $applied, 2);
        }

        $buckets = ['current' => 0.0, 'days_30' => 0.0, 'days_60' => 0.0, 'days_90' => 0.0, 'days_90_plus' => 0.0];

        foreach ($charges as $charge) {
            if ($charge['amount'] <= 0) {
                continue;
            }

            // A null age is the brought-forward balance, which by definition
            // predates the window and is therefore treated as the oldest bucket.
            $age = $charge['age'] ?? PHP_INT_MAX;

            $key = match (true) {
                $age <= 0 => 'current',
                $age <= self::AGING_BUCKETS[0] => 'days_30',
                $age <= self::AGING_BUCKETS[1] => 'days_60',
                $age <= self::AGING_BUCKETS[2] => 'days_90',
                default => 'days_90_plus',
            };

            $buckets[$key] = round($buckets[$key] + $charge['amount'], 2);
        }

        $buckets['total'] = round(array_sum($buckets), 2);
        // Unapplied settlements mean the party is in credit beyond its charges.
        $buckets['unapplied_credit'] = round($settlements, 2);

        return $buckets;
    }

    /**
     * Customers sit debit-normal (they owe us); suppliers and employees sit
     * credit-normal (we owe them).
     */
    private function normalNature(Ledger $ledger): string
    {
        $type = $ledger->type instanceof LedgerType
            ? $ledger->type
            : LedgerType::tryFrom((string) $ledger->type);

        return $type?->isPayableParty() ? 'cr' : 'dr';
    }

    private function homeCurrency(): ?array
    {
        $currency = Currency::query()->where('is_base_currency', true)->first();

        if (! $currency) {
            return null;
        }

        return [
            'id' => $currency->id,
            'code' => $currency->code,
            'name' => $currency->name,
            'symbol' => $currency->symbol,
        ];
    }

    private function referenceLabel(?string $referenceType): string
    {
        if (! $referenceType) {
            return '-';
        }

        // reference_type is written inconsistently across the codebase: some
        // writers store a FQCN, others a bare slug. Normalise both.
        if (str_contains($referenceType, '\\')) {
            return str(class_basename($referenceType))->headline()->toString();
        }

        return str($referenceType)->headline()->toString();
    }

    private function formatBalance(mixed $value): string
    {
        $amount = round((float) ($value ?? 0), 2);

        if ($amount === 0.0) {
            return '0.00';
        }

        return number_format(abs($amount), 2, '.', '').' '.($amount > 0 ? 'Dr' : 'Cr');
    }

    private function normalizeDate(?string $date): ?string
    {
        if (! $date) {
            return null;
        }

        try {
            $gregorian = $this->dateConversionService->toGregorian($date);

            return $gregorian ? Carbon::parse($gregorian)->toDateString() : null;
        } catch (\Throwable) {
            // An unparseable filter degrades to "no bound" rather than a 500.
            return null;
        }
    }
}
