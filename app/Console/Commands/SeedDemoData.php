<?php

namespace App\Console\Commands;

use App\Models\Administration\Company;
use App\Models\Sale\Sale;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\DemoSimulator;
use Database\Seeders\Demo\DemoVerifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fill a company with two years of realistic supermarket activity, for load
 * and performance testing.
 *
 * Every document goes through the same route, validation and controller the UI
 * uses (see DemoClient), so journals, stock movements, balances and settlements
 * are the ones the application itself produces.
 */
class SeedDemoData extends Command
{
    protected $signature = 'nextbook:seed-demo
        {--tenant= : Company id, English/Dari name or abbreviation}
        {--scale=1 : Volume multiplier; 0.1 = 10% of the full data set}
        {--seed=20240930 : Random seed; the same seed on the same starting data gives the same run}
        {--from= : First day, Gregorian Y-m-d (default: two years before --to)}
        {--to= : Last day, Gregorian Y-m-d (default: today)}
        {--calendar=gregorian : Calendar the company and the forms use: gregorian or jalali}
        {--verify-only : Only run the final checks}
        {--force : Run even if the company already has sales}';

    protected $description = 'Seed a period of realistic supermarket demo data through the application\'s own posting logic';

    public function handle(): int
    {
        ini_set('memory_limit', '-1');
        DB::disableQueryLog();
        // Internal requests need a session, but not one written to the database.
        // No debug error pages: a refused document is reported from the exception
        // object, and rendering the debug page costs seconds per failure.
        config(['session.driver' => 'array', 'app.debug' => false]);

        $company = $this->resolveCompany();
        if (! $company) {
            return self::FAILURE;
        }

        $scale = (float) $this->option('scale');
        if ($scale <= 0 || $scale > 5) {
            $this->error('--scale must be between 0 and 5.');

            return self::FAILURE;
        }

        if ($this->option('verify-only')) {
            $branchId = (string) DB::table('users')->where('company_id', $company->id)->orderBy('created_at')->value('branch_id');
            (new DemoVerifier($this, $branchId))->run();

            return self::SUCCESS;
        }

        if (Sale::query()->withoutGlobalScopes()->exists() && ! $this->option('force')) {
            $this->error('This database already has sales. Restore the backup first, or pass --force to add to it.');

            return self::FAILURE;
        }

        $end = $this->option('to') ? CarbonImmutable::parse($this->option('to'))->startOfDay() : CarbonImmutable::today();
        $start = $this->option('from') ? CarbonImmutable::parse($this->option('from'))->startOfDay() : $end->subYears(2);
        $calendar = (string) $this->option('calendar');

        if ($start->gte($end) || ! in_array($calendar, ['gregorian', 'jalali'], true)) {
            $this->error('--from must be before --to, and --calendar must be gregorian or jalali.');

            return self::FAILURE;
        }

        $this->info(sprintf('شرکت: %s | مقیاس: %s | seed: %s | بازه: %s تا %s',
            $company->name_fa ?: $company->name_en, $scale, $this->option('seed'), $start->toDateString(), $end->toDateString()));

        @unlink(storage_path('logs/demo-seed-failures.log'));
        $started = microtime(true);
        $simulator = new DemoSimulator($this, $company, $scale, (int) $this->option('seed'), $start, $end, $calendar);

        try {
            $simulator->run();
        } finally {
            \Carbon\Carbon::setTestNow();
            CarbonImmutable::setTestNow();
            $this->report($simulator, microtime(true) - $started);
        }

        $verifyStarted = microtime(true);
        (new DemoVerifier($this, $simulator->branchId()))->run();
        $this->line('  مدت بررسی نهایی: ' . DemoSimulator::duration(microtime(true) - $verifyStarted));

        return self::SUCCESS;
    }

    private function resolveCompany(): ?Company
    {
        $tenant = (string) $this->option('tenant');
        $companies = Company::query()->get();

        if ($tenant === '') {
            if ($companies->count() === 1) {
                return $companies->first();
            }
            $this->error('--tenant is required: ' . $companies->map(fn ($c) => "{$c->id} ({$c->name_en})")->implode(', '));

            return null;
        }

        $company = $companies->first(fn (Company $c) => in_array($tenant, [$c->id, $c->name_en, $c->name_fa, $c->abbreviation], true));
        if (! $company) {
            $this->error("No company matches '{$tenant}'.");
        }

        return $company;
    }

    private function report(DemoSimulator $simulator, float $seconds): void
    {
        $this->newLine();
        $this->info('مدت اجرای هر بخش');
        $rows = [];
        foreach ($simulator->phaseSeconds as $phase => $time) {
            $rows[] = [$phase, DemoSimulator::duration($time)];
        }
        $rows[] = ['مجموع', DemoSimulator::duration($seconds)];
        $this->table(['مرحله', 'مدت'], $rows);

        $rows = [];
        ksort($simulator->stats);
        foreach ($simulator->stats as $type => $stat) {
            $rows[] = [
                $type,
                number_format($stat['ok']),
                $stat['voided'] ? number_format($stat['voided']) : '—',
                $stat['failed'] ? number_format($stat['failed']) : '—',
                DemoSimulator::duration($stat['seconds']),
                $stat['ok'] ? sprintf('%.0f ms', 1000 * $stat['seconds'] / max(1, $stat['ok'] + $stat['failed'])) : '—',
            ];
        }
        $this->info('اسناد ثبت‌شده توسط seeder');
        $this->table(['نوع', 'ثبت‌شده', 'باطل‌شده', 'رد‌شده', 'مدت', 'میانگین هر سند'], $rows);

        if ($simulator->failures !== []) {
            $path = storage_path('logs/demo-seed-failures.log');
            file_put_contents($path, implode(PHP_EOL, $simulator->failures) . PHP_EOL);
            $this->warn(count($simulator->failures) . " سند توسط سیستم رد شد؛ جزئیات: {$path}");
            foreach (array_slice(array_count_values(array_map(fn ($f) => mb_substr($f, 0, 160), $simulator->failures)), 0, 8, true) as $message => $count) {
                $this->line("  ×{$count}  {$message}");
            }
        }
    }
}
