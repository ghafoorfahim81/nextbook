<?php

namespace App\Services\Hr;

use App\Enums\PayFrequency;
use App\Enums\TaxPeriod;
use App\Exceptions\Hr\PayrollException;
use App\Models\Hr\TaxBracket;
use App\Models\Hr\TaxBracketSet;
use App\Support\Decimal;
use Illuminate\Support\Carbon;

/**
 * Wage withholding tax.
 *
 * Two things make this correct rather than merely plausible:
 *
 *  1. **Effective dating.** The bracket set is resolved from the period END
 *     date, and the resolved set id is stamped on the payslip. Re-running an
 *     old period after a rate change therefore reproduces the tax that was
 *     actually withheld, instead of silently restating history.
 *
 *  2. **Annualisation.** A weekly-paid worker earning the same annual salary as
 *     a monthly-paid one must pay the same tax. Taxing a week's pay directly
 *     against a monthly table would put them inside the zero-rate band four
 *     times over. Non-monthly frequencies are scaled to a monthly equivalent,
 *     taxed, then scaled back.
 *
 * Arithmetic is bcmath throughout — a tax figure computed in floats and a
 * ledger line computed in decimals will eventually disagree by a hundredth,
 * and TransactionService rejects entries that do not balance.
 */
class WageTaxService
{
    /**
     * The bracket set in force for a period.
     *
     * Resolved from the period END, because that is when the liability
     * crystallises: a rate that changes mid-month applies to the whole month.
     */
    public function resolveSet(
        Carbon $periodEnd,
        TaxPeriod $period = TaxPeriod::Monthly,
        ?string $branchId = null,
    ): TaxBracketSet {
        $query = TaxBracketSet::query()
            ->when($branchId, fn ($q) => $q->withoutGlobalScopes()->where('branch_id', $branchId)->whereNull('deleted_at'))
            ->where('period', $period->value)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $periodEnd->toDateString())
            ->where(function ($q) use ($periodEnd) {
                $q->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $periodEnd->toDateString());
            })
            ->orderByDesc('effective_from');

        $set = $query->first();

        if (! $set) {
            // Deliberately fatal rather than defaulting to zero tax. A payroll
            // that silently withholds nothing is a compliance problem nobody
            // notices until the MoF does.
            throw PayrollException::make(
                'No wage tax brackets are configured for this period.',
                ['period_end' => $periodEnd->toDateString(), 'period' => $period->value]
            );
        }

        return $set;
    }

    /**
     * Tax on a monthly taxable income against a given set.
     *
     * @return array{tax: string, bracket_id: ?string, marginal_rate: string}
     */
    public function compute(string $taxableIncome, TaxBracketSet $set): array
    {
        $income = Decimal::amount($taxableIncome);

        // Negative taxable income (heavy unpaid leave against a small salary)
        // is not a refund — it is simply no tax.
        if (Decimal::cmp($income, '0') <= 0) {
            return ['tax' => '0.0000', 'bracket_id' => null, 'marginal_rate' => '0.0000'];
        }

        $brackets = $set->relationLoaded('brackets')
            ? $set->brackets
            : $set->brackets()->get();

        if ($brackets->isEmpty()) {
            throw PayrollException::make(
                'This wage tax bracket set has no bands.',
                ['tax_bracket_set_id' => $set->id]
            );
        }

        $incomeFloat = (float) $income;

        $bracket = $brackets->first(fn (TaxBracket $b) => $b->contains($incomeFloat));

        if (! $bracket) {
            // Only reachable if the bands leave a gap, which the form request
            // rejects — but a silent zero here would be worse than a failure.
            throw PayrollException::make(
                'No wage tax band covers this income; the bracket table has a gap.',
                ['tax_bracket_set_id' => $set->id, 'taxable_income' => $income]
            );
        }

        $excess = Decimal::sub($income, Decimal::amount($bracket->from_amount));
        $marginal = bcdiv(
            bcmul($excess, Decimal::amount($bracket->rate), Decimal::AMOUNT_SCALE + 2),
            '100',
            Decimal::AMOUNT_SCALE
        );

        $tax = Decimal::add(Decimal::amount($bracket->fixed_amount), $marginal);

        return [
            'tax' => $tax,
            'bracket_id' => (string) $bracket->id,
            'marginal_rate' => Decimal::amount($bracket->rate),
        ];
    }

    /**
     * Tax for one pay period, whatever its length.
     *
     * Monthly is taxed directly. Anything shorter is scaled up to its monthly
     * equivalent, taxed there, then scaled back down — so the annual burden is
     * the same regardless of how often someone is paid.
     */
    public function computeForPeriod(
        string $taxableIncome,
        PayFrequency $frequency,
        TaxBracketSet $set,
    ): array {
        if ($frequency === PayFrequency::Monthly) {
            return $this->compute($taxableIncome, $set);
        }

        $periods = Decimal::amount((string) $frequency->periodsPerMonth());
        $monthlyEquivalent = bcmul(Decimal::amount($taxableIncome), $periods, Decimal::AMOUNT_SCALE);

        $result = $this->compute($monthlyEquivalent, $set);

        $scaledBack = bcdiv($result['tax'], $periods, Decimal::AMOUNT_SCALE);

        return [
            'tax' => $scaledBack,
            'bracket_id' => $result['bracket_id'],
            'marginal_rate' => $result['marginal_rate'],
            'monthly_equivalent' => $monthlyEquivalent,
        ];
    }

    /**
     * Tax on an income against bracket rows that have NOT been saved yet.
     *
     * The bracket form needs to answer "what would someone on 50,000 pay under
     * these bands" while the user is still editing them. A rate table is easy
     * to get subtly wrong and hard to read back, so checking before committing
     * is worth more than checking after.
     *
     * Deliberately separate from compute(): that one resolves a persisted,
     * effective-dated set and is what payroll uses. This one takes the user's
     * working copy and is never a source of a posted figure.
     *
     * @param  array<int, array<string, mixed>>  $brackets
     * @return array{tax: string, marginal_rate: string}
     */
    public function computeAgainst(mixed $taxableIncome, array $brackets): array
    {
        $income = Decimal::amount($taxableIncome);

        if (Decimal::cmp($income, '0') <= 0 || $brackets === []) {
            return ['tax' => '0.0000', 'marginal_rate' => '0.0000'];
        }

        $incomeFloat = (float) $income;

        $match = null;

        foreach ($brackets as $bracket) {
            $from = (float) ($bracket['from_amount'] ?? 0);
            $to = ($bracket['to_amount'] ?? null) === null || $bracket['to_amount'] === ''
                ? null
                : (float) $bracket['to_amount'];

            if ($incomeFloat > $from && ($to === null || $incomeFloat <= $to)) {
                $match = $bracket;
                break;
            }
        }

        if (! $match) {
            return ['tax' => '0.0000', 'marginal_rate' => '0.0000'];
        }

        $excess = Decimal::sub($income, Decimal::amount($match['from_amount'] ?? 0));
        $marginal = bcdiv(
            bcmul($excess, Decimal::amount($match['rate'] ?? 0), Decimal::AMOUNT_SCALE + 2),
            '100',
            Decimal::AMOUNT_SCALE
        );

        return [
            'tax' => Decimal::add(Decimal::amount($match['fixed_amount'] ?? 0), $marginal),
            'marginal_rate' => Decimal::amount($match['rate'] ?? 0),
        ];
    }

    /**
     * Every band with its computed tax at the top of the band, for the settings
     * screen — so an admin can see what the table actually does before saving.
     *
     * @return array<int, array<string, mixed>>
     */
    public function preview(TaxBracketSet $set): array
    {
        return $set->brackets()->get()->map(function (TaxBracket $bracket) use ($set) {
            $ceiling = $bracket->to_amount !== null
                ? Decimal::amount($bracket->to_amount)
                : Decimal::add(Decimal::amount($bracket->from_amount), '50000.0000');

            return [
                'sequence' => $bracket->sequence,
                'from_amount' => (float) $bracket->from_amount,
                'to_amount' => $bracket->to_amount !== null ? (float) $bracket->to_amount : null,
                'rate' => (float) $bracket->rate,
                'fixed_amount' => (float) $bracket->fixed_amount,
                'tax_at_ceiling' => (float) $this->compute($ceiling, $set)['tax'],
            ];
        })->all();
    }
}
