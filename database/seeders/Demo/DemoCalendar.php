<?php

namespace Database\Seeders\Demo;

use Carbon\CarbonImmutable;
use Morilog\Jalali\Jalalian;

/**
 * The simulated two years: which days are busy, what season it is, and what
 * the dollar and rupee were worth on each day.
 */
final class DemoCalendar
{
    /** Ramadan and the two Eids inside the simulated window (Afghanistan dates). */
    private const RAMADAN = [['2025-03-01', '2025-03-29'], ['2026-02-18', '2026-03-19']];

    private const EID_FITR = ['2025-03-30', '2026-03-20'];

    private const EID_ADHA = ['2025-06-06', '2026-05-27'];

    /** @var array<int, CarbonImmutable> */
    public array $days = [];

    /** @var array<int, float> */
    public array $weights = [];

    /** @var array<int, float> AFN per USD */
    public array $usd = [];

    /** @var array<int, float> AFN per PKR */
    public array $pkr = [];

    public function __construct(public CarbonImmutable $start, public CarbonImmutable $end, DemoRandom $random)
    {
        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $this->days[] = $day;
        }

        $total = count($this->days);

        foreach ($this->days as $index => $day) {
            // Slow growth over the two years, on top of the calendar effects.
            $this->weights[$index] = $this->calendarFactor($day) * (0.9 + 0.2 * $index / max($total - 1, 1));

            // Days the application cannot record anything on (see isBlocked()).
            if ($this->isBlocked($index)) {
                $this->weights[$index] = 0.0;
            }
        }

        $this->buildRates($random);
    }

    public function dayCount(): int
    {
        return count($this->days);
    }

    public function calendarFactor(CarbonImmutable $day): float
    {
        $factor = 1.0;
        $date = $day->toDateString();

        if ($day->isFriday()) {
            $factor *= 0.4;
        }

        foreach (self::RAMADAN as [$from, $to]) {
            if ($date >= $from && $date <= $to) {
                $factor *= 1.35;
            }
        }

        foreach (self::EID_FITR as $eid) {
            $gap = CarbonImmutable::parse($eid)->diffInDays($day, false);

            if ($gap >= -10 && $gap < 0) {
                $factor *= 1.8;  // the last days before Eid
            } elseif ($gap >= 0 && $gap <= 2) {
                $factor *= 0.45; // shops half-closed over Eid itself
            }
        }

        foreach (self::EID_ADHA as $eid) {
            $gap = CarbonImmutable::parse($eid)->diffInDays($day, false);

            if ($gap >= -7 && $gap < 0) {
                $factor *= 1.6;
            } elseif ($gap >= 0 && $gap <= 3) {
                $factor *= 0.5;
            }
        }

        // Nowruz is 1 Hamal; the second half of Hoot is the run-up.
        $jalali = Jalalian::fromCarbon($day->toMutable());
        if ($jalali->getMonth() === 12 && $jalali->getDay() >= 15) {
            $factor *= 1.55;
        } elseif ($jalali->getMonth() === 1 && $jalali->getDay() <= 3) {
            $factor *= 0.6;
        }

        return $factor;
    }

    /** @return array<int, string> */
    public function seasons(CarbonImmutable $day): array
    {
        $tags = [];
        $date = $day->toDateString();

        foreach (self::RAMADAN as [$from, $to]) {
            if ($date >= $from && $date <= $to) {
                $tags[] = 'ramadan';
            }
        }

        foreach ([...self::EID_FITR, ...self::EID_ADHA] as $eid) {
            $gap = CarbonImmutable::parse($eid)->diffInDays($day, false);
            if ($gap >= -10 && $gap <= 0) {
                $tags[] = 'eid';
            }
        }

        $jalali = Jalalian::fromCarbon($day->toMutable());
        if (($jalali->getMonth() === 12 && $jalali->getDay() >= 10) || ($jalali->getMonth() === 1 && $jalali->getDay() <= 5)) {
            $tags[] = 'nowruz';
        }

        $month = (int) $day->format('n');
        if (in_array($month, [6, 7, 8], true)) {
            $tags[] = 'summer';
        }
        if (in_array($month, [12, 1, 2], true)) {
            $tags[] = 'winter';
        }

        return $tags;
    }

    /**
     * Spread $count events over the days in proportion to $weights (defaults
     * to the trading weights). Returns day indexes, sorted.
     *
     * @param  array<int, float>|null  $weights
     * @return array<int, int>
     */
    public function distribute(int $count, DemoRandom $random, ?array $weights = null, int $fromDay = 0): array
    {
        $weights ??= $this->weights;
        $weights = array_slice($weights, $fromDay, null, true);
        $total = array_sum($weights);
        $result = [];

        // Deterministic apportionment with a random remainder, so totals are exact.
        $remainders = [];
        foreach ($weights as $day => $weight) {
            $exact = $count * $weight / $total;
            $whole = (int) floor($exact);
            for ($i = 0; $i < $whole; $i++) {
                $result[] = $day;
            }
            $remainders[$day] = $exact - $whole;
        }

        while (count($result) < $count) {
            $day = $random->weighted($remainders);
            $result[] = $day;
            $remainders[$day] = 0;
            if (array_sum($remainders) <= 0) {
                $remainders = $weights;
            }
        }

        sort($result);

        return $result;
    }

    /** Weights for back-office work: steady, and quiet on Fridays. */
    public function officeWeights(): array
    {
        $weights = [];
        foreach ($this->days as $index => $day) {
            $weights[$index] = $this->isBlocked($index) ? 0.0 : ($day->isFriday() ? 0.25 : 1.0);
        }

        return $weights;
    }

    /** @var array<int, bool> */
    private array $blocked = [];

    /**
     * The document forms validate the date with Laravel's `date` rule, which
     * reads a Jalali "1404-02-30" as 30 February and refuses it. With the Jalali
     * calendar nothing can be saved on 30–31 Saur, 31 Saratan and 31 Sonbola, so
     * the simulated shop records nothing on those days either.
     */
    public function isBlocked(int $index): bool
    {
        return $this->blocked[$index] ??= \Illuminate\Support\Facades\Validator::make(
            ['date' => $this->jalali($this->days[$index])],
            ['date' => 'date']
        )->fails();
    }

    /** Jalali date as the date picker submits it. */
    public function jalali(CarbonImmutable $day): string
    {
        return Jalalian::fromCarbon($day->toMutable())->format('Y-m-d');
    }

    public function at(int $dayIndex, int $minuteOfDay): CarbonImmutable
    {
        return $this->days[$dayIndex]->startOfDay()->addMinutes($minuteOfDay);
    }

    /**
     * USD wanders between 65 and 75 AFN; the rupee follows the dollar at a
     * slowly drifting cross rate of ~278–285 PKR per USD.
     */
    private function buildRates(DemoRandom $random): void
    {
        $usd = 70.6;
        $cross = 278.0;

        foreach ($this->days as $index => $day) {
            $usd += $random->gaussian(0, 0.18) + (70.0 - $usd) * 0.01;
            $usd = max(65.2, min(74.8, $usd));
            $cross += $random->gaussian(0, 0.15) + (281.0 - $cross) * 0.01;
            $cross = max(276.0, min(286.0, $cross));

            $this->usd[$index] = round($usd, 2);
            // Two decimals: TransactionService rounds each line's base amount to
            // four places and then demands an exact base balance, so a rate with
            // more decimals makes valid invoices fail by 0.0001.
            $this->pkr[$index] = round($usd / $cross, 2);
        }
    }
}
