<?php

namespace Database\Seeders\Demo;

use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Deterministic random source for the demo seeder.
 *
 * A private engine rather than mt_rand(): the framework and the code under test
 * draw from the global generator too, so sharing it would make two runs with
 * the same seed diverge the moment any request took a different path.
 */
final class DemoRandom
{
    private Randomizer $randomizer;

    public function __construct(int $seed)
    {
        $this->randomizer = new Randomizer(new Mt19937($seed));
    }

    public function int(int $min, int $max): int
    {
        return $min >= $max ? $min : $this->randomizer->getInt($min, $max);
    }

    /** Uniform float in [0, 1). */
    public function float(): float
    {
        return $this->randomizer->getInt(0, PHP_INT_MAX - 1) / PHP_INT_MAX;
    }

    public function between(float $min, float $max): float
    {
        return $min + ($max - $min) * $this->float();
    }

    public function chance(float $probability): bool
    {
        return $this->float() < $probability;
    }

    /**
     * @template T
     * @param  array<int|string, T>  $items
     * @return T
     */
    public function pick(array $items): mixed
    {
        $values = array_values($items);

        return $values[$this->int(0, count($values) - 1)];
    }

    /**
     * Index of an entry drawn in proportion to its weight.
     *
     * @param  array<int|string, float|int>  $weights
     */
    public function weighted(array $weights): int|string
    {
        $total = array_sum($weights);
        $target = $this->float() * $total;
        $running = 0.0;

        foreach ($weights as $key => $weight) {
            $running += $weight;

            if ($target < $running) {
                return $key;
            }
        }

        return array_key_last($weights);
    }

    /**
     * @template T
     * @param  array<int, T>  $items
     * @return array<int, T>
     */
    public function shuffle(array $items): array
    {
        return $this->randomizer->shuffleArray(array_values($items));
    }

    /** Normal-ish sample (Box–Muller), for rate drift. */
    public function gaussian(float $mean = 0.0, float $sd = 1.0): float
    {
        $u = max($this->float(), 1e-12);
        $v = $this->float();

        return $mean + $sd * sqrt(-2 * log($u)) * cos(2 * M_PI * $v);
    }

    /** Geometric-ish count with the given mean, clamped. */
    public function count(float $mean, int $min, int $max): int
    {
        $p = 1 / max($mean - $min + 1, 1.0001);
        $n = $min;

        while ($n < $max && ! $this->chance($p)) {
            $n++;
        }

        return $n;
    }

    /**
     * Zipf weights: a handful of entries carry most of the volume. With the
     * default exponent the top 20% of ~1,000 entries carry roughly 80%.
     *
     * @return array<int, float>
     */
    public function paretoWeights(int $n, float $exponent = 1.0): array
    {
        $weights = [];

        for ($i = 1; $i <= $n; $i++) {
            $weights[] = 1 / ($i ** $exponent);
        }

        return $this->shuffle($weights);
    }
}
