<?php

namespace Tests\Feature\Hr;

use App\Enums\PayFrequency;
use App\Enums\TaxPeriod;
use App\Exceptions\Hr\PayrollException;
use App\Models\Hr\TaxBracket;
use App\Models\Hr\TaxBracketSet;
use App\Services\Hr\WageTaxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * The Afghan monthly wage withholding table.
 *
 *   up to 5,000        0%
 *   5,001 – 12,500     2% above 5,000
 *   12,501 – 100,000   150 + 10% above 12,500
 *   above 100,000      8,900 + 20% above 100,000
 *
 * Every boundary is asserted, because a bracket table is exactly the kind of
 * thing that looks right and is wrong by one band — and the error only shows up
 * in someone's pay.
 */
class WageTaxFeatureTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    private TaxBracketSet $set;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ctx = $this->bootstrapErpContext();
        $this->set = $this->seedStatutorySet();
    }

    private function seedStatutorySet(string $effectiveFrom = '2005-01-01'): TaxBracketSet
    {
        $set = TaxBracketSet::create([
            'name' => 'Afghanistan Monthly Wage Tax',
            'jurisdiction' => 'AF',
            'period' => TaxPeriod::Monthly->value,
            'effective_from' => $effectiveFrom,
            'currency_id' => $this->ctx['currency']->id,
            'is_active' => true,
            'is_system' => true,
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
        ]);

        foreach (TaxBracketSet::defaultAfghanMonthlyBrackets() as $bracket) {
            TaxBracket::create(array_merge($bracket, [
                'tax_bracket_set_id' => $set->id,
                'branch_id' => $this->ctx['branch']->id,
                'created_by' => $this->ctx['user']->id,
            ]));
        }

        return $set->fresh();
    }

    private function tax(string|float $income): float
    {
        return (float) app(WageTaxService::class)->compute((string) $income, $this->set)['tax'];
    }

    /**
     * @return array<string, array{0: float, 1: float}>
     */
    public static function statutoryBoundaries(): array
    {
        return [
            'zero income' => [0, 0],
            'inside the exempt band' => [3000, 0],
            'exactly at the exempt ceiling' => [5000, 0],
            'one afghani into the 2% band' => [5001, 0.02],
            'mid 2% band' => [10000, 100],
            // 2% of 7,500 = 150, which is the next band's fixed amount.
            'exactly at the 2% ceiling' => [12500, 150],
            'one afghani into the 10% band' => [12501, 150.10],
            'mid 10% band' => [50000, 3900],
            // 150 + 10% of 87,500 = 8,900, which is the top band's fixed amount.
            'exactly at the 10% ceiling' => [100000, 8900],
            'one afghani into the 20% band' => [100001, 8900.20],
            'well into the top band' => [150000, 18900],
            'very high earner' => [500000, 88900],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('statutoryBoundaries')]
    public function test_it_matches_the_statutory_table(float $income, float $expected): void
    {
        $this->assertEqualsWithDelta($expected, $this->tax($income), 0.0001);
    }

    /**
     * The fixed amounts are cumulative tax at each floor. If they are right,
     * the table is continuous — no jump in tax for one extra afghani earned.
     */
    public function test_the_table_is_continuous_across_every_boundary(): void
    {
        foreach ([5000, 12500, 100000] as $boundary) {
            $below = $this->tax($boundary);
            $above = $this->tax($boundary + 1);

            $this->assertLessThan(
                1.0,
                $above - $below,
                "Tax jumps by more than one afghani crossing {$boundary}, so the fixed amounts do not line up."
            );
            $this->assertGreaterThanOrEqual($below, $above);
        }
    }

    /**
     * Earning more must never leave someone with less after tax.
     */
    public function test_effective_tax_never_exceeds_the_extra_earned(): void
    {
        $previousNet = 0.0;

        foreach (range(0, 200000, 2500) as $income) {
            $net = $income - $this->tax($income);

            $this->assertGreaterThanOrEqual(
                $previousNet - 0.0001,
                $net,
                "Net pay fell when gross rose to {$income}."
            );

            $previousNet = $net;
        }
    }

    public function test_negative_taxable_income_is_not_a_refund(): void
    {
        $this->assertSame(0.0, $this->tax(-5000));
    }

    /**
     * A weekly-paid worker must not sit inside the exempt band four times over.
     */
    public function test_weekly_pay_is_annualised_before_taxing(): void
    {
        $service = app(WageTaxService::class);

        // A weekly wage that annualises to well above the exempt threshold.
        $weekly = '5000';

        $direct = (float) $service->compute($weekly, $this->set)['tax'];
        $scaled = (float) $service->computeForPeriod($weekly, PayFrequency::Weekly, $this->set)['tax'];

        $this->assertSame(0.0, $direct, 'Taxed directly, a weekly wage of 5,000 falls in the exempt band.');
        $this->assertGreaterThan(0.0, $scaled, 'Annualised, the same wage is well above the exempt band.');
    }

    /**
     * The same annual salary should attract the same tax however often it is
     * paid — that is the whole point of annualising.
     */
    public function test_monthly_and_weekly_agree_on_the_same_annual_salary(): void
    {
        $service = app(WageTaxService::class);

        $monthly = '52000';
        $weekly = bcdiv($monthly, (string) PayFrequency::Weekly->periodsPerMonth(), 4);

        $monthlyTax = (float) $service->computeForPeriod($monthly, PayFrequency::Monthly, $this->set)['tax'];
        $weeklyTaxPerMonth = (float) $service->computeForPeriod($weekly, PayFrequency::Weekly, $this->set)['tax']
            * PayFrequency::Weekly->periodsPerMonth();

        $this->assertEqualsWithDelta($monthlyTax, $weeklyTaxPerMonth, 0.05);
    }

    public function test_it_resolves_the_set_in_force_for_the_period(): void
    {
        $service = app(WageTaxService::class);

        $resolved = $service->resolveSet(Carbon::parse('2026-08-31'), TaxPeriod::Monthly, $this->ctx['branch']->id);

        $this->assertSame($this->set->id, $resolved->id);
    }

    /**
     * Effective dating is what lets an old period be re-run without silently
     * restating history at today's rates.
     */
    public function test_an_older_period_resolves_the_older_set(): void
    {
        $newer = $this->seedStatutorySet('2026-01-01');
        $newer->update(['name' => 'Revised Table']);

        $service = app(WageTaxService::class);

        $old = $service->resolveSet(Carbon::parse('2025-06-30'), TaxPeriod::Monthly, $this->ctx['branch']->id);
        $new = $service->resolveSet(Carbon::parse('2026-06-30'), TaxPeriod::Monthly, $this->ctx['branch']->id);

        $this->assertSame($this->set->id, $old->id);
        $this->assertSame($newer->id, $new->id);
    }

    /**
     * A payroll that silently withholds nothing is a compliance problem nobody
     * notices until the ministry does, so a missing table must be fatal.
     */
    public function test_a_missing_bracket_set_throws_rather_than_taxing_zero(): void
    {
        TaxBracketSet::query()->delete();

        $this->expectException(PayrollException::class);

        app(WageTaxService::class)->resolveSet(
            Carbon::parse('2026-08-31'),
            TaxPeriod::Monthly,
            $this->ctx['branch']->id
        );
    }

    public function test_a_gap_in_the_table_throws_rather_than_taxing_zero(): void
    {
        // Remove the 2% band, leaving 5,000–12,500 uncovered.
        TaxBracket::query()->where('sequence', 2)->forceDelete();

        $this->expectException(PayrollException::class);

        app(WageTaxService::class)->compute('10000', $this->set->fresh());
    }

    public function test_branch_provisioning_seeds_a_usable_table(): void
    {
        // The bootstrap context creates its accounts directly, so provisioning
        // is exercised here on a fresh branch.
        $branch = \App\Models\Administration\Branch::factory()->create(['is_main' => false]);

        app(\App\Services\BranchProvisioningService::class)->provision($branch, $this->ctx['user']->id);

        $set = TaxBracketSet::withoutGlobalScopes()
            ->where('branch_id', $branch->id)
            ->first();

        $this->assertNotNull($set, 'Provisioning should seed a wage tax table.');
        $this->assertSame(4, $set->brackets()->count());

        $service = app(WageTaxService::class);
        $this->assertEqualsWithDelta(
            8900.0,
            (float) $service->compute('100000', $set)['tax'],
            0.0001
        );
    }

    public function test_the_preview_reports_tax_at_each_ceiling(): void
    {
        $preview = app(WageTaxService::class)->preview($this->set);

        $this->assertCount(4, $preview);
        $this->assertEqualsWithDelta(0.0, $preview[0]['tax_at_ceiling'], 0.0001);
        $this->assertEqualsWithDelta(150.0, $preview[1]['tax_at_ceiling'], 0.0001);
        $this->assertEqualsWithDelta(8900.0, $preview[2]['tax_at_ceiling'], 0.0001);
    }
}
