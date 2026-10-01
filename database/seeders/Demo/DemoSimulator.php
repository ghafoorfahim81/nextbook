<?php

namespace Database\Seeders\Demo;

use App\Models\Account\Account;
use App\Models\Account\AccountType;
use App\Models\AccountTransfer\AccountTransfer;
use App\Models\Administration\Brand;
use App\Models\Administration\Category;
use App\Models\Administration\Company;
use App\Models\Administration\Currency;
use App\Models\Administration\CustomerGroup;
use App\Models\Administration\LandedCostCategory;
use App\Models\Administration\UnitMeasure;
use App\Models\Administration\Warehouse;
use App\Models\Expense\Expense;
use App\Models\Expense\ExpenseCategory;
use App\Models\Inventory\DiscountRule;
use App\Models\Inventory\Item;
use App\Models\Inventory\LandedCost;
use App\Models\Inventory\StockAdjustment;
use App\Models\JournalEntry\JournalEntry;
use App\Models\Ledger\Ledger;
use App\Models\Payment\Payment;
use App\Models\Purchase\Purchase;
use App\Models\Purchase\PurchaseItem;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Purchase\PurchaseQuotation;
use App\Models\Purchase\PurchaseReturn;
use App\Models\Receipt\Receipt;
use App\Models\Role;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleItem;
use App\Models\Sale\SaleOrder;
use App\Models\Sale\SaleQuotation;
use App\Models\Sale\SaleReturn;
use App\Models\Transaction\Transaction;
use App\Models\User;
use App\Services\Accounting\SettlementService;
use App\Services\DiscountRuleResolver;
use App\Support\BranchContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;
use Throwable;

/**
 * Simulates two years of a Kabul supermarket by posting every document through
 * the same routes the UI uses, in date order.
 *
 * The simulator keeps a shadow of what it has done — stock per item, cash per
 * account, which invoices are meant to stay open — only to decide what to do
 * next (never sell what is not on the shelf, never pay from an empty till). The
 * books themselves are written exclusively by the application.
 */
final class DemoSimulator
{
    public const VOID_RATE = 0.04;

    // Partly-paid bills and the last few weeks' invoices are open too, so the
    // share deliberately left unpaid is set below the ~15% target.
    private const KEEP_OPEN_RATE = 0.09;

    private DemoRandom $random;

    private DemoPeople $people;

    private DemoCalendar $calendar;

    private DemoClient $client;

    private string $branchId;

    private string $warehouseId;

    private User $owner;

    /** @var array<string, User> */
    private array $users = [];

    /** @var array<string, array<int, User>> ability pools, keyed by model class + ability */
    private array $pools = [];

    /** @var array<string, string> code => currency id */
    private array $currency = [];

    /** @var array<string, string> */
    private array $units = [];

    /** @var array<string, string> slug => account id */
    private array $accounts = [];

    /**
     * Cash and bank accounts, each used in one currency only.
     *
     * @var array<string, array{id: string, currency: string, balance: float, name: string}>
     */
    private array $cash = [];

    /** @var array<int, array<string, mixed>> */
    private array $items = [];

    /** @var array<string, array<int, float>> cumulative item weights per season key */
    private array $itemCdf = [];

    /** @var array<int, array<string, mixed>> */
    private array $customers = [];

    private string $cashCustomerId;

    /** @var array<int, array<string, mixed>> */
    private array $suppliers = [];

    /** @var array<string, string> */
    private array $groups = [];

    /** @var array<string, string> */
    private array $categoryIds = [];

    /** @var array<string, string> expense category name => [id, account id] */
    private array $expenseCategories = [];

    /** @var array<string, string> */
    private array $landedCategories = [];

    /** Transactions of invoices that are meant to still be open today. @var array<string, true> */
    private array $keepOpen = [];

    /** Documents that were voided or must not be touched again. @var array<string, true> */
    private array $untouchable = [];

    /** @var array<int, array<string, mixed>> recent sales, for returns */
    private array $recentSales = [];

    /** @var array<int, array<string, mixed>> recent purchases, for returns and landed costs */
    private array $recentPurchases = [];

    /** Ledgers with money outstanding. @var array<string, true> */
    private array $owingCustomers = [];

    /** @var array<string, true> */
    private array $owedSuppliers = [];

    /** @var array<int, array<int, array<string, mixed>>> day => events added while running */
    private array $deferred = [];

    /** @var array<string, int> */
    private array $numbers = [];

    private ?DiscountRuleResolver $discounts = null;

    /** @var array<string, array{ok: int, failed: int, seconds: float, voided: int}> */
    public array $stats = [];

    /** @var array<int, string> */
    public array $failures = [];

    /** @var array<string, float> */
    public array $phaseSeconds = [];

    private int $consecutiveFailures = 0;

    public function __construct(
        private Command $console,
        private Company $company,
        private float $scale,
        int $seed,
        CarbonImmutable $start,
        CarbonImmutable $end,
        private string $calendarType = "gregorian",
    ) {
        $this->random = new DemoRandom($seed);
        $this->people = new DemoPeople($this->random);
        $this->calendar = new DemoCalendar($start, $end, $this->random, $calendarType);
        $this->client = new DemoClient(app());
    }

    public function n(int $count): int
    {
        return max(1, (int) round($count * $this->scale));
    }

    // =====================================================================
    //  RUN
    // =====================================================================

    public function run(): void
    {
        $this->phase('آماده‌سازی و دیتای پایه', fn () => $this->prepare());
        $this->phase('اجناس و موجودی اولیه', fn () => $this->createItems());
        $this->phase('تأمین‌کنندگان', fn () => $this->createSuppliers());
        $this->phase('مشتریان (روز اول)', fn () => $this->createCustomers(initial: true));
        $this->phase('قوانین تخفیف', fn () => $this->createDiscountRules());
        $this->phase('شبیه‌سازی دو سال', fn () => $this->simulate());
    }

    private function phase(string $name, callable $work): void
    {
        $this->console->newLine();
        $this->console->info("▶ {$name}");
        $started = microtime(true);
        $work();
        $this->phaseSeconds[$name] = microtime(true) - $started;
        $this->console->line(sprintf('  ✓ %s در %s', $name, self::duration($this->phaseSeconds[$name])));
    }

    public static function duration(float $seconds): string
    {
        return $seconds >= 60
            ? sprintf('%d دقیقه و %d ثانیه', intdiv((int) $seconds, 60), (int) $seconds % 60)
            : sprintf('%.1f ثانیه', $seconds);
    }

    // =====================================================================
    //  POSTING
    // =====================================================================

    /**
     * One document, atomically: a savepoint around the request, so a refused
     * document leaves nothing behind and the run carries on.
     */
    private function attempt(string $type, callable $work): mixed
    {
        $this->stats[$type] ??= ['ok' => 0, 'failed' => 0, 'seconds' => 0.0, 'voided' => 0];
        $started = microtime(true);
        DB::beginTransaction();

        try {
            $result = $work();
            DB::commit();
            $this->stats[$type]['ok']++;
            $this->consecutiveFailures = 0;

            return $result;
        } catch (Throwable $e) {
            DB::rollBack();
            $this->stats[$type]['failed']++;
            $this->failures[] = "{$type}: " . mb_substr($e->getMessage(), 0, 600);
            $this->consecutiveFailures++;

            if ($this->consecutiveFailures >= 25) {
                throw new RuntimeException("25 failures in a row; last: {$e->getMessage()}", 0, $e);
            }

            return null;
        } finally {
            $this->stats[$type]['seconds'] += microtime(true) - $started;
        }
    }

    /**
     * Requests run in the order they are sent, so their clock must run forward
     * too: a document is never stamped earlier than one the system already saw.
     */
    private ?CarbonImmutable $clock = null;

    private function send(User $user, CarbonImmutable $at, string $route, array $data = [], array $params = [], string $method = 'POST', bool $json = false)
    {
        if ($this->clock !== null && $at->lte($this->clock)) {
            $at = $this->clock->addSeconds($this->random->int(1, 20));
        }
        $this->clock = $at;

        return $this->client->send($user, $at, $method, $route, $params, $data, $json);
    }

    private function next(string $key, string $modelClass): int
    {
        if (! isset($this->numbers[$key])) {
            $query = $modelClass::query()->withoutGlobalScopes();
            $this->numbers[$key] = (int) $query
                ->selectRaw("MAX(CASE WHEN number::text ~ '^[0-9]+$' THEN number::text::bigint END) as n")
                ->value('n');
        }

        return ++$this->numbers[$key];
    }

    /** Someone allowed to do $ability on $class, spread across the staff. */
    private function actor(string $class, string $ability = 'create'): User
    {
        $key = $class . '@' . $ability;

        if (! isset($this->pools[$key])) {
            $this->pools[$key] = array_values(array_filter(
                $this->users,
                fn (User $user) => Gate::forUser($user)->allows($ability, $class)
            ));

            if ($this->pools[$key] === []) {
                $this->pools[$key] = [$this->owner];
            }
        }

        return $this->random->pick($this->pools[$key]);
    }

    private function voidActor(object $model): User
    {
        $candidates = array_values(array_filter(
            $this->users,
            fn (User $user) => Gate::forUser($user)->allows('update', $model)
        ));

        return $candidates === [] ? $this->owner : $this->random->pick($candidates);
    }

    private function formDate(CarbonImmutable $at): string
    {
        return $this->calendar->formDate($at);
    }

    /** A Jalali date $days ahead that the forms will accept (see DemoCalendar::isBlocked()). */
    private function formDateAfter(CarbonImmutable $at, int $days): string
    {
        do {
            $date = $this->formDate($at->addDays($days++));
        } while (\Illuminate\Support\Facades\Validator::make(['d' => $date], ['d' => 'date'])->fails());

        return $date;
    }

    private function rate(string $code, int $day): float
    {
        return match ($code) {
            'USD' => round($this->calendar->usd[$day] * $this->random->between(0.998, 1.002), 2),
            'PKR' => $this->calendar->pkr[$day],
            default => 1.0,
        };
    }

    private function money(float $amount, string $code): float
    {
        return round($amount, 2);
    }

    // =====================================================================
    //  PREPARATION
    // =====================================================================

    private function prepare(): void
    {
        $this->owner = User::query()
            ->where('company_id', $this->company->id)
            ->whereHas('roles', fn ($q) => $q->where('slug', 'super-admin'))
            ->orderBy('created_at')
            ->firstOrFail();
        $this->branchId = (string) $this->owner->branch_id;
        app()->instance('active_branch_id', $this->branchId);
        $this->users[$this->owner->id] = $this->owner;

        $start = $this->calendar->at(0, 7 * 60);

        // Calendar the forms are filled in. A settings flag, changed the way
        // CompanyController does it (update + refresh the profile cache).
        $calendar = $this->company->calendar_type;
        if (($calendar instanceof \BackedEnum ? $calendar->value : $calendar) !== $this->calendarType) {
            $this->company->update(['calendar_type' => $this->calendarType]);
            \App\Jobs\RefreshCompanyProfileCache::dispatch($this->company->id);
        }

        $this->warehouseId = (string) (Warehouse::query()->withoutGlobalScopes()
            ->where('branch_id', $this->branchId)->orderByDesc('is_main')->orderBy('created_at')->value('id')
            ?? throw new RuntimeException('No warehouse in the branch.'));

        $this->loadAccounts();
        $this->ensureCurrencies($start);
        $this->ensureUnits($start);
        $this->createCategoriesAndBrands($start);
        $this->createUsers($start);
        $this->createAccounts($start);
        $this->createExpenseCategories($start);

        foreach (CustomerGroup::query()->get(['id', 'name_en']) as $group) {
            $this->groups[strtolower((string) $group->name_en)] = $group->id;
        }
        foreach (LandedCostCategory::query()->withoutGlobalScopes()->where('branch_id', $this->branchId)->get(['id', 'name']) as $category) {
            $this->landedCategories[$category->name] = $category->id;
        }

        $this->cashCustomerId = (string) Ledger::query()->where('code', 'CASH-CUST')->where('branch_id', $this->branchId)->value('id');
    }

    private function loadAccounts(): void
    {
        $this->accounts = Account::query()->withoutGlobalScopes()
            ->where('branch_id', $this->branchId)
            ->whereNull('deleted_at')
            ->pluck('id', 'slug')
            ->all();
    }

    private function ensureCurrencies(CarbonImmutable $at): void
    {
        $existing = Currency::query()->withoutGlobalScopes()->where('branch_id', $this->branchId)->pluck('id', 'code')->all();

        if (! isset($existing['PKR'])) {
            $list = Currency::currencyList()['PKR'];
            $this->attempt('currency', fn () => $this->send($this->owner, $at, 'currencies.store', [
                'currency_code' => 'PKR',
                'name' => $list['name'],
                'local_name' => $list['local_name'],
                'code' => 'PKR',
                'symbol' => $list['symbol'],
                'format' => $list['format'],
                'exchange_rate' => $this->calendar->pkr[0],
                'is_active' => true,
                'is_base_currency' => false,
                'flag' => $list['flag'],
            ]));
            $existing = Currency::query()->withoutGlobalScopes()->where('branch_id', $this->branchId)->pluck('id', 'code')->all();
        }

        foreach (['AFN', 'USD', 'PKR'] as $code) {
            $this->currency[$code] = $existing[$code] ?? throw new RuntimeException("Currency {$code} missing.");
        }

        $this->updateRates(0, $at);
    }

    private function updateRates(int $day, CarbonImmutable $at): void
    {
        $this->attempt('currency_rate_update', fn () => $this->send($this->owner, $at, 'currency-rate-updates.store', [
            // This controller parses the date with Carbon directly, so it takes Gregorian.
            'date' => $at->toDateString(),
            'updates' => [
                ['currency_id' => $this->currency['USD'], 'exchange_rate' => $this->calendar->usd[$day]],
                ['currency_id' => $this->currency['PKR'], 'exchange_rate' => $this->calendar->pkr[$day]],
            ],
        ]));
    }

    private function ensureUnits(CarbonImmutable $at): void
    {
        $byName = fn () => UnitMeasure::query()->withoutGlobalScopes()->where('branch_id', $this->branchId)->pluck('id', 'name')->all();
        $units = $byName();

        foreach ([['کارتن ۱۲', 12, 'ctn12'], ['کارتن ۲۴', 24, 'ctn24']] as [$name, $factor, $symbol]) {
            if (! isset($units[$name])) {
                $this->attempt('unit_measure', fn () => $this->send($this->owner, $at, 'unit-measures.store', [
                    'metric' => ['name' => 'شمارشی', 'unit' => 'دانه', 'symbol' => 'ea'],
                    'measure' => ['name' => $name, 'unit' => $factor, 'symbol' => $symbol],
                ]));
            }
        }

        // By symbol: the seeded Dari names mix Arabic and Persian letter forms.
        $bySymbol = UnitMeasure::query()->withoutGlobalScopes()->where('branch_id', $this->branchId)->pluck('id', 'symbol')->all();
        foreach (['ea' => 'ea', 'kg' => 'kg', 'L' => 'L', 'c12' => 'ctn12', 'c24' => 'ctn24'] as $key => $symbol) {
            $this->units[$key] = $bySymbol[$symbol] ?? throw new RuntimeException("Unit with symbol {$symbol} missing.");
        }
    }

    private function createCategoriesAndBrands(CarbonImmutable $at): void
    {
        $existing = Category::query()->withoutGlobalScopes()->where('branch_id', $this->branchId)->pluck('id', 'name')->all();

        foreach (DemoCatalog::CATEGORIES as $key => $name) {
            if (! isset($existing[$name])) {
                $this->attempt('category', fn () => $this->send($this->owner, $at, 'categories.store', ['name' => $name, 'local_name' => $name]));
            }
        }

        $existing = Category::query()->withoutGlobalScopes()->where('branch_id', $this->branchId)->pluck('id', 'name')->all();
        foreach (DemoCatalog::CATEGORIES as $key => $name) {
            $this->categoryIds[$key] = $existing[$name];
        }
    }

    private function createUsers(CarbonImmutable $at): void
    {
        $roles = Role::query()->pluck('id', 'slug')->all();
        $staff = [
            ['admin', 'مدیر عمومی'], ['admin', 'معاون مالی'], ['admin', 'مسئول گدام'],
            ['accountant', 'صندوق‌دار اول'], ['accountant', 'صندوق‌دار دوم'], ['accountant', 'صندوق‌دار سوم'],
            ['accountant', 'مسئول خرید'], ['accountant', 'محاسب'],
            ['clerk', 'بررس داخلی'], ['clerk', 'کارمند گدام'],
        ];

        foreach ($staff as $index => [$role, $title]) {
            $email = 'staff' . ($index + 1) . '@supermarket.demo';
            $user = User::query()->where('email', $email)->first();

            if (! $user) {
                $name = $this->people->person();
                $this->attempt('user', fn () => $this->send($this->owner, $at, 'users.store', [
                    'name' => "{$name} ({$title})",
                    'email' => $email,
                    'password' => 'Demo@12345',
                    'password_confirmation' => 'Demo@12345',
                    'branch_id' => $this->branchId,
                    'company_id' => $this->company->id,
                    'roles' => [$roles[$role]],
                ]));
                $user = User::query()->where('email', $email)->first();
            }

            if ($user) {
                $this->users[$user->id] = $user;
            }
        }
    }

    /**
     * Ten new accounts. Cash/bank ones carry an opening balance and are used in
     * one currency only (the system has no account currency; the name says it).
     */
    private function createAccounts(CarbonImmutable $at): void
    {
        $types = AccountType::query()->withoutGlobalScopes()->where('branch_id', $this->branchId)->pluck('id', 'slug')->all();
        $new = [
            ['afn_azizi', 'عزیزی بانک - افغانی', 'cash-or-bank', 'AFN', 3000000],
            ['usd_cash', 'صندوق دالر', 'cash-or-bank', 'USD', 25000],
            ['usd_azizi', 'عزیزی بانک - دالر', 'cash-or-bank', 'USD', 80000],
            ['usd_aib', 'افغانستان بین‌المللی بانک - دالر', 'cash-or-bank', 'USD', 60000],
            ['pkr_cash', 'صندوق کلدار', 'cash-or-bank', 'PKR', 900000],
            ['exp_packing', 'مصرف بسته‌بندی و پلاستیک', 'expense', null, 0],
            ['exp_guard', 'مصرف نگهبانی و امنیت', 'expense', null, 0],
            ['exp_hawala', 'مصرف کمیشن حواله', 'expense', null, 0],
            ['inc_shelf', 'عواید کرایهٔ قفسه و تبلیغ برندها', 'income', null, 0],
            ['inc_scrap', 'عواید فروش کارتن و ضایعات', 'income', null, 0],
        ];

        foreach ($new as $index => [$key, $name, $type, $code, $opening]) {
            $existing = Account::query()->withoutGlobalScopes()->where('branch_id', $this->branchId)->where('name', $name)->value('id');

            if (! $existing) {
                $payload = [
                    'name' => $name,
                    'local_name' => $name,
                    'number' => (string) (1100 + $index + 1),
                    'account_type_id' => $types[$type],
                    'is_active' => true,
                    'remark' => 'حساب جدید سوپرمارکت',
                ];

                if ($code !== null) {
                    $payload += [
                        'currency_id' => $this->currency[$code],
                        'rate' => $code === 'USD' ? $this->calendar->usd[0] : ($code === 'PKR' ? $this->calendar->pkr[0] : 1),
                        'amount' => $opening,
                        'transaction_type' => 'debit',
                    ];
                }

                $this->attempt('account', fn () => $this->send($this->owner, $at, 'chart-of-accounts.store', $payload));
                $existing = Account::query()->withoutGlobalScopes()->where('branch_id', $this->branchId)->where('name', $name)->value('id');
            }

            if ($existing) {
                $this->accounts[$key] = $existing;
                if ($code !== null) {
                    $this->cash[$key] = ['id' => $existing, 'currency' => $code, 'balance' => (float) $opening, 'name' => $name];
                }
            }
        }

        // The existing AFN tills get their opening through one opening journal,
        // since the account form only posts an opening when an account is created.
        $existingTills = [
            'afn_hand' => ['cash-in-hand', 600000],
            'afn_safe' => ['cash-in-safe', 2500000],
            'afn_bank' => ['cash-in-bank', 4000000],
            'afn_petty' => ['petty-cash', 50000],
        ];

        $lines = [];
        $total = 0;
        foreach ($existingTills as $key => [$slug, $amount]) {
            $this->cash[$key] = ['id' => $this->accounts[$slug], 'currency' => 'AFN', 'balance' => (float) $amount, 'name' => $slug];
            $lines[] = ['account_id' => $this->accounts[$slug], 'debit' => $amount, 'credit' => 0, 'remark' => 'بیلانس افتتاحیه'];
            $total += $amount;
        }
        $lines[] = ['account_id' => $this->accounts['opening-balance-equity'], 'debit' => 0, 'credit' => $total, 'remark' => 'بیلانس افتتاحیه صندوق‌ها و بانک'];

        $this->attempt('opening_journal', fn () => $this->send($this->owner, $at, 'journal-entries.store', [
            'number' => $this->next('journal', JournalEntry::class),
            'date' => $this->formDate($at),
            'currency_id' => $this->currency['AFN'],
            'rate' => 1,
            'remarks' => 'بیلانس افتتاحیه صندوق‌ها و حساب بانکی',
            'lines' => $lines,
        ]));
    }

    /** @var array<string, array{0: string, 1: int, 2: int, 3: float, 4: string}> name => [account key, min, max, weight, currency] */
    private const EXPENSE_CATEGORIES = [
        'کرایه دکان و گدام' => ['office-rent', 1500, 2500, 0, 'USD'],
        'معاش کارمندان' => ['permanent-staff-salary', 90000, 160000, 0, 'AFN'],
        'برق' => ['electricity-bill-genset-fuel', 15000, 45000, 0, 'AFN'],
        'انترنت' => ['internet-bill', 2500, 4000, 0, 'AFN'],
        'تیل جنراتور' => ['generator-expense', 3000, 12000, 6, 'AFN'],
        'معاش کارگران روزمزد' => ['temporary-staff-salary', 500, 3000, 10, 'AFN'],
        'ترانسپورت و باربری' => ['transportation-taxi-fare', 500, 5000, 20, 'AFN'],
        'تیلفون و کارت' => ['telephone-expense', 300, 1500, 5, 'AFN'],
        'آب' => ['utilities-expenses', 800, 3000, 3, 'AFN'],
        'صفایی و انتقال زباله' => ['cleaning-supplies-trash-removal', 500, 3000, 6, 'AFN'],
        'ترمیم و نگهداری' => ['building-repair-maintenance', 1000, 20000, 4, 'AFN'],
        'تبلیغات' => ['advertising-promotion', 2000, 30000, 3, 'AFN'],
        'قرطاسیه' => ['office-stationary', 300, 3000, 4, 'AFN'],
        'غذا و مهمانداری' => ['kitchen-food-refreshment', 300, 2500, 8, 'AFN'],
        'بسته‌بندی و پلاستیک' => ['exp_packing', 1000, 8000, 6, 'AFN'],
        'نگهبانی و امنیت' => ['exp_guard', 8000, 15000, 2, 'AFN'],
        'جواز و فیس‌های قانونی' => ['license-permit-legal-fee', 2000, 25000, 1, 'AFN'],
        'کمیشن حواله و بانک' => ['exp_hawala', 100, 2000, 4, 'AFN'],
        'مصارف موتر' => ['vehicle-expense', 1000, 8000, 5, 'AFN'],
        'بخاری و گرمایش' => ['heating-expense', 2000, 15000, 3, 'AFN'],
    ];

    private function createExpenseCategories(CarbonImmutable $at): void
    {
        foreach (self::EXPENSE_CATEGORIES as $name => $spec) {
            $id = ExpenseCategory::query()->withoutGlobalScopes()->where('branch_id', $this->branchId)->where('name', $name)->value('id');

            if (! $id) {
                $this->attempt('expense_category', fn () => $this->send($this->owner, $at, 'expense-categories.store', [
                    'name' => $name,
                    'remarks' => 'کتگوری مصرف سوپرمارکت',
                    'is_active' => true,
                ]));
                $id = ExpenseCategory::query()->withoutGlobalScopes()->where('branch_id', $this->branchId)->where('name', $name)->value('id');
            }

            if ($id) {
                $this->expenseCategories[$name] = $id;
            }
        }
    }

    // =====================================================================
    //  ITEMS
    // =====================================================================

    /** Brands that are real product brands; the rest are origins or places. */
    private function brandable(array $spec): bool
    {
        $notBrands = ['داخلی', 'پاکستانی', 'ایرانی', 'ترکی', 'چینایی', 'ازبکستانی', 'قزاقستانی', 'هندی', 'محلی', 'خانگی',
            'پلاستیکی', 'کندهار', 'شمالی', 'سمنگان', 'کاغذی', 'بادغیس', 'بدخشان', 'پکتیا', 'پروان', 'کابل', 'غزنی', 'پغمان',
            'هرات', 'قاینات', 'کوهی بدخشان', 'مزار', 'جلال‌آباد', 'هلمند', 'کابلی', 'قم', 'ید دار', 'تصفیه', 'سنگ', 'گواتمالا'];

        return ! in_array($spec['brand'], $notBrands, true);
    }

    private function createItems(): void
    {
        $at = $this->calendar->at(0, 8 * 60);
        $catalog = DemoCatalog::items($this->n(900), $this->random);

        // Brands first, one per distinct name.
        $brandIds = Brand::query()->withoutGlobalScopes()->where('branch_id', $this->branchId)->pluck('id', 'name')->all();
        $brands = [];
        foreach ($catalog as $spec) {
            if ($this->brandable($spec)) {
                $brands[$spec['brand']] = $spec['origin'];
            }
        }
        $bar = $this->console->getOutput()->createProgressBar(count($brands) + count($catalog));
        $bar->start();

        foreach ($brands as $name => $origin) {
            if (! isset($brandIds[$name])) {
                $this->attempt('brand', fn () => $this->send($this->owner, $at, 'brands.store', [
                    'name' => $name,
                    'country' => DemoCatalog::ORIGIN_LABELS[$origin] ?? null,
                    'industry' => 'مواد غذایی و مصرفی',
                ]));
            }
            $bar->advance();
        }
        $brandIds = Brand::query()->withoutGlobalScopes()->where('branch_id', $this->branchId)->pluck('id', 'name')->all();

        $this->estimateDemand($catalog);

        $admins = array_values(array_filter($this->users, fn (User $u) => Gate::forUser($u)->allows('create', Item::class)));

        foreach (array_chunk($catalog, 50) as $chunk) {
            DB::transaction(function () use ($chunk, $brandIds, $admins, $at, $bar) {
                foreach ($chunk as $spec) {
                    $spec = $this->finishItemSpec($spec);
                    $openingQty = $this->openingQuantity($spec);
                    $user = $admins === [] ? $this->owner : $this->random->pick($admins);

                    $created = $this->attempt('item', function () use ($spec, $brandIds, $openingQty, $user, $at) {
                        $this->send($user, $at->addSeconds($this->random->int(0, 3600)), 'items.store', [
                            'name' => $spec['name'],
                            'code' => $spec['code'],
                            'sku' => $spec['code'],
                            'barcode' => $spec['barcode'],
                            'item_type' => 'inventory_materials',
                            'unit_measure_id' => $this->units[$spec['unit']],
                            'category_id' => $this->categoryIds[$spec['category']],
                            'brand_id' => $brandIds[$spec['brand']] ?? null,
                            'default_warehouse_id' => $this->warehouseId,
                            'asset_account_id' => $this->accounts['inventory-stock'],
                            'income_account_id' => $this->accounts['product-income'],
                            'cost_account_id' => $this->accounts['cost-of-goods-sold'],
                            'purchase_price' => $spec['purchase_price'],
                            'sale_price' => $spec['sale_price'],
                            'minimum_stock' => max(1, round($spec['demand'] * 10)),
                            'reorder_quantity' => max(1, round($spec['demand'] * 45)),
                            'country_of_origin' => DemoCatalog::ORIGIN_LABELS[$spec['origin']] ?? null,
                            'manufacturer' => $this->brandable($spec) ? $spec['brand'] : null,
                            'is_batch_tracked' => false,
                            'is_expiry_tracked' => false,
                            'is_serial_tracked' => false,
                            'is_active' => true,
                            'is_stockable' => true,
                            'is_sellable' => true,
                            'is_purchasable' => true,
                            'show_in_pos' => true,
                            'openings' => [[
                                'quantity' => $openingQty,
                                'unit_price' => $spec['purchase_price'],
                                'warehouse_id' => $this->warehouseId,
                            ]],
                        ]);

                        return Item::query()->withoutGlobalScopes()->where('branch_id', $this->branchId)->where('code', $spec['code'])->value('id');
                    });

                    if ($created) {
                        $spec['id'] = $created;
                        $spec['stock'] = $openingQty;
                        $this->items[] = $spec;
                    }
                    $bar->advance();
                }
            });
        }

        $bar->finish();
        $this->console->newLine();
    }

    /**
     * Expected base quantity per day for each item, from the sale mix the
     * simulator will draw. Used to size openings and purchases.
     */
    private function estimateDemand(array &$catalog): void
    {
        $salesPerDay = $this->n(10000) / $this->calendar->dayCount();
        $totalPopularity = array_sum(array_column($catalog, 'popularity'));

        foreach ($catalog as &$spec) {
            $spec = $this->finishItemSpec($spec);
            $retail = 0.0;
            $wholesale = 0.0;
            for ($i = 0; $i < 20; $i++) {
                $retail += $this->lineQuantity($spec, false)[1];
                $wholesale += $this->lineQuantity($spec, true)[1];
            }
            $share = $spec['popularity'] / $totalPopularity;
            // ~65% retail baskets of ~3.5 lines, ~35% trade orders of ~8 lines.
            $spec['demand'] = $salesPerDay * $share * (0.65 * 3.5 * $retail / 20 + 0.35 * 8 * $wholesale / 20);
        }
        unset($spec);
    }

    private function finishItemSpec(array $spec): array
    {
        if (! isset($spec['purchase_unit'])) {
            $spec['purchase_unit'] = match ($spec['carton']) {
                12 => 'c12',
                24 => 'c24',
                default => $spec['unit'],
            };
            $spec['factor'] = in_array($spec['carton'], [12, 24], true) ? $spec['carton'] : 1;
            $spec['bulk'] = $spec['unit'] === 'ea' && $spec['purchase_price'] >= 800;
        }

        return $spec;
    }

    private function openingQuantity(array $spec): float
    {
        $days = $this->random->between(60, 100);
        $qty = $spec['demand'] * $days;

        if ($spec['factor'] > 1) {
            return max($spec['factor'], ceil($qty / $spec['factor']) * $spec['factor']);
        }

        return max($spec['unit'] === 'ea' ? 5 : 10, ceil($qty));
    }

    /**
     * Quantity for one sale line: [quantity in the selling unit, base quantity, unit key].
     *
     * @return array{0: float, 1: float, 2: string}
     */
    private function lineQuantity(array $spec, bool $wholesale): array
    {
        if ($wholesale && $spec['factor'] > 1) {
            $cartons = $this->random->pick([1, 1, 1, 2, 2, 3, 4, 5]);

            return [$cartons, $cartons * $spec['factor'], $spec['purchase_unit']];
        }

        if ($spec['unit'] !== 'ea') {
            $qty = $wholesale
                ? $this->random->pick([10, 10, 20, 25, 50])
                : $this->random->pick([0.5, 1, 1, 1, 2, 2, 3, 5]);

            return [$qty, $qty, $spec['unit']];
        }

        $qty = $wholesale
            ? ($spec['bulk'] ? $this->random->pick([2, 3, 5, 10]) : $this->random->pick([6, 10, 12, 20]))
            : ($spec['bulk'] ? 1 : $this->random->pick([1, 1, 1, 2, 2, 3, 4, 6]));

        return [$qty, $qty, 'ea'];
    }

    /** Weighted item draw for a day, favouring whatever is in season. */
    private function drawItem(array $seasons): int
    {
        $key = implode(',', $seasons);

        if (! isset($this->itemCdf[$key])) {
            $running = 0.0;
            $cdf = [];
            foreach ($this->items as $spec) {
                $boost = array_intersect($spec['seasons'], $seasons) === [] ? 1.0 : 2.2;
                $running += $spec['popularity'] * $boost;
                $cdf[] = $running;
            }
            $this->itemCdf[$key] = $cdf;
        }

        $cdf = $this->itemCdf[$key];
        $target = $this->random->float() * end($cdf);
        $lo = 0;
        $hi = count($cdf) - 1;
        while ($lo < $hi) {
            $mid = intdiv($lo + $hi, 2);
            if ($cdf[$mid] > $target) {
                $hi = $mid;
            } else {
                $lo = $mid + 1;
            }
        }

        return $lo;
    }

    private function unitPrice(float $afn, string $code, float $rate, float $qty): float
    {
        $price = $code === 'AFN' ? $afn : $afn / $rate;
        // Half quantities need a price with one decimal so the line total stays at two.
        $decimals = fmod($qty, 1.0) !== 0.0 ? 1 : 2;

        return max(round($price, $decimals), $code === 'AFN' ? 1 : 0.01);
    }

    // =====================================================================
    //  LEDGERS
    // =====================================================================

    private function createSuppliers(): void
    {
        $at = $this->calendar->at(0, 9 * 60);
        $count = $this->n(400);
        $weights = $this->random->paretoWeights($count);
        $origins = ['PK', 'IR', 'TR', 'AF', 'MIX'];
        $bar = $this->console->getOutput()->createProgressBar($count);
        $bar->start();

        foreach (array_chunk(range(0, $count - 1), 50) as $chunk) {
            DB::transaction(function () use ($chunk, $weights, $origins, $at, $bar) {
                foreach ($chunk as $i) {
                    $origin = $this->random->pick($origins);
                    // Importers trade in dollars; Pakistani houses sometimes in rupees;
                    // local producers in afghanis.
                    $currency = match ($origin) {
                        'IR', 'TR', 'MIX' => $this->random->chance(0.75) ? 'USD' : 'AFN',
                        'PK' => $this->random->weighted(['USD' => 55, 'PKR' => 20, 'AFN' => 25]),
                        default => $this->random->chance(0.9) ? 'AFN' : 'USD',
                    };
                    $address = $this->people->address(in_array($origin, ['PK'], true) && $this->random->chance(0.3) ? 'جلال‌آباد' : null);
                    $name = $this->people->company();
                    $openings = [];

                    if ($this->random->chance(0.4)) {
                        $openings[] = [
                            'currency_id' => $this->currency[$currency],
                            'rate' => $currency === 'AFN' ? 1 : ($currency === 'USD' ? $this->calendar->usd[0] : $this->calendar->pkr[0]),
                            'amount' => $this->money($this->afnTo($this->random->between(20000, 600000) * $weights[$i] * 8 + 15000, $currency, 0), $currency),
                            'remark' => 'بیلانس افتتاحیه تأمین‌کننده',
                        ];
                    }

                    $id = $this->attempt('supplier', function () use ($name, $address, $currency, $openings, $at) {
                        $this->send($this->actor(Ledger::class), $at->addSeconds($this->random->int(0, 3600)), 'suppliers.store', [
                            'name' => $name,
                            'code' => null, // the controller assigns the next code

                            'contact_person' => $this->people->person(),
                            'phone_no' => $this->people->phone(),
                            'address' => $address['address'],
                            'currency_id' => $this->currency[$currency],
                            'country_id' => $this->afghanistanId(),
                            'is_active' => true,
                            'openings' => $openings,
                        ]);

                        return Ledger::query()->where('type', 'supplier')->where('name', $name)->latest('created_at')->value('id');
                    });

                    if ($id) {
                        $this->suppliers[] = ['id' => $id, 'weight' => $weights[$i], 'currency' => $currency, 'origin' => $origin, 'items' => []];
                        if ($openings !== []) {
                            $this->owedSuppliers[$id] = true;
                        }
                    }
                    $bar->advance();
                }
            });
        }

        $bar->finish();
        $this->console->newLine();
        $this->assignSuppliers();
    }

    /** Each item gets a main supplier (brand-consistent) and a fallback. */
    private function assignSuppliers(): void
    {
        if ($this->suppliers === []) {
            throw new RuntimeException('No supplier could be created; see the failure log.');
        }

        $byOrigin = [];
        foreach ($this->suppliers as $index => $supplier) {
            $byOrigin[$supplier['origin']][$index] = $supplier['weight'];
        }

        $brandSupplier = [];
        foreach ($this->items as $itemIndex => $spec) {
            $pool = $byOrigin[in_array($spec['origin'], ['PK', 'IR', 'TR', 'AF'], true) ? $spec['origin'] : 'MIX'] ?? $byOrigin['MIX'] ?? [0 => 1];
            $brandKey = $spec['brand'] . '|' . $spec['origin'];
            $brandSupplier[$brandKey] ??= $this->random->weighted($pool);

            $main = $this->random->chance(0.75) ? $brandSupplier[$brandKey] : $this->random->weighted($pool + ($byOrigin['MIX'] ?? []));
            $this->items[$itemIndex]['supplier'] = $main;
            $this->suppliers[$main]['items'][] = $itemIndex;
        }
    }

    private ?string $afghanistan = null;

    private function afghanistanId(): ?string
    {
        return $this->afghanistan ??= DB::table('countries')->where('code', 'AF')->value('id');
    }

    private function afnTo(float $afn, string $code, int $day): float
    {
        return match ($code) {
            'USD' => $afn / $this->calendar->usd[$day],
            'PKR' => $afn / $this->calendar->pkr[$day],
            default => $afn,
        };
    }

    /** Customers: most exist from day one; the rest join over the two years. */
    private function createCustomers(bool $initial): void
    {
        $count = $this->n(1000);
        $weights = $this->random->paretoWeights($count);
        $initialCount = (int) round($count * 0.6);
        $withOpening = (int) round($count * 0.3);
        $joinDays = $this->calendar->distribute($count - $initialCount, $this->random, $this->calendar->officeWeights(), 1);

        for ($i = 0; $i < $count; $i++) {
            $trade = $this->random->chance(0.55);
            $group = $trade ? ($this->random->chance(0.85) ? 'wholesale' : 'vip') : 'retail';
            $this->customers[$i] = [
                'id' => null,
                'weight' => $weights[$i] * ($trade ? 1.6 : 0.6),
                'group' => $group,
                'currency' => (string) $this->random->weighted(['AFN' => 62, 'USD' => 34, 'PKR' => 4]),
                'credit' => $trade,
                'join' => $i < $initialCount ? 0 : $joinDays[$i - $initialCount],
                'opening' => $i < $withOpening,
            ];
        }

        $bar = $this->console->getOutput()->createProgressBar($initialCount);
        $bar->start();
        foreach (array_chunk(range(0, $initialCount - 1), 50) as $chunk) {
            DB::transaction(function () use ($chunk, $bar) {
                foreach ($chunk as $i) {
                    $this->createCustomer($i, $this->calendar->at(0, 10 * 60 + $this->random->int(0, 300)));
                    $bar->advance();
                }
            });
        }
        $bar->finish();
        $this->console->newLine();

        foreach ($this->customers as $i => $customer) {
            if ($customer['join'] > 0) {
                $this->deferred[$customer['join']][] = ['minute' => 7 * 60 + 30 + $this->random->int(0, 60), 'type' => 'customer', 'index' => $i];
            }
        }
    }

    private function createCustomer(int $index, CarbonImmutable $at): void
    {
        $customer = $this->customers[$index];
        $trade = $customer['group'] !== 'retail';
        $name = $trade ? $this->people->shop() . ' (' . $this->people->person() . ')' : $this->people->person();
        $address = $this->people->address();
        $code = $customer['currency'];
        $openings = [];

        if ($customer['opening']) {
            $openings[] = [
                'currency_id' => $this->currency[$code],
                'rate' => $code === 'AFN' ? 1 : ($code === 'USD' ? $this->calendar->usd[0] : $this->calendar->pkr[0]),
                'amount' => $this->money($this->afnTo($this->random->between(5000, 250000) * ($trade ? 1 : 0.2), $code, 0), $code),
                'remark' => 'بیلانس افتتاحیه مشتری',
            ];
        }

        $id = $this->attempt('customer', function () use ($name, $address, $code, $openings, $trade, $customer, $at) {
            $this->send($this->actor(Ledger::class), $at, 'customers.store', [
                'name' => $name,
                'code' => null, // the controller assigns the next code
                'contact_person' => $trade ? $this->people->person() : null,
                'phone_no' => $this->people->phone(),
                'address' => $address['address'],
                'currency_id' => $this->currency[$code],
                'group_id' => $this->groups[$customer['group']] ?? null,
                'country_id' => $this->afghanistanId(),
                'credit_limit' => $trade ? round($this->afnTo($this->random->between(100000, 1500000), $code, 0), -2) : null,
                'credit_limit_enabled' => false,
                'is_active' => true,
                'openings' => $openings,
            ]);

            return Ledger::query()->where('type', 'customer')->where('name', $name)->latest('created_at')->value('id');
        });

        if ($id) {
            $this->customers[$index]['id'] = $id;
            if ($openings !== []) {
                $this->owingCustomers[$id] = true;
            }
        }
    }

    // =====================================================================
    //  DISCOUNT RULES
    // =====================================================================

    private function createDiscountRules(): void
    {
        $at = $this->calendar->at(0, 12 * 60);
        $brandIds = Brand::query()->withoutGlobalScopes()->where('branch_id', $this->branchId)->pluck('id', 'name')->all();
        $j = fn (string $gregorian) => $this->formDate(CarbonImmutable::parse($gregorian));

        $rules = [
            ['name' => 'تخفیف عمده‌فروشی برنج و آرد ۳٪', 'scope' => 'category', 'scope_id' => $this->categoryIds['grain'],
                'discount_type' => 'percentage', 'value' => 3, 'customer_group_id' => $this->groups['wholesale'] ?? null, 'min_quantity' => 5],
            ['name' => 'تخفیف مشتریان ویژه ۵٪', 'scope' => 'all', 'discount_type' => 'percentage', 'value' => 5,
                'customer_group_id' => $this->groups['vip'] ?? null],
            ['name' => 'تخفیف رمضان ۲۰۲۵ – لبنیات', 'scope' => 'category', 'scope_id' => $this->categoryIds['dairy'],
                'discount_type' => 'percentage', 'value' => 7, 'starts_at' => $j('2025-02-25'), 'ends_at' => $j('2025-03-29'), 'is_priority' => true],
            ['name' => 'تخفیف نوروزی ۲۰۲۵ – خشکبار', 'scope' => 'category', 'scope_id' => $this->categoryIds['dried'],
                'discount_type' => 'percentage', 'value' => 8, 'starts_at' => $j('2025-03-01'), 'ends_at' => $j('2025-03-25'), 'is_priority' => true],
            ['name' => 'تخفیف برند الکوزی ۴٪', 'scope' => 'brand', 'scope_id' => $brandIds['الکوزی'] ?? ($brandIds['تپال'] ?? array_values($brandIds)[0] ?? null),
                'discount_type' => 'percentage', 'value' => 4, 'min_quantity' => 4],
        ];

        foreach (array_slice($rules, 0, 5) as $rule) {
            if (DiscountRule::query()->withoutGlobalScopes()->where('name', $rule['name'])->exists()) {
                continue;
            }
            $this->attempt('discount_rule', fn () => $this->send($this->actor(DiscountRule::class), $at, 'discount-rules.store', $rule + [
                'is_priority' => false,
                'is_active' => true,
                'show_on_invoice' => true,
            ]));
        }

        $this->discounts = new DiscountRuleResolver();
    }

    // =====================================================================
    //  SIMULATION
    // =====================================================================

    private function simulate(): void
    {
        $events = $this->schedule();
        $days = $this->calendar->dayCount();
        $bar = $this->console->getOutput()->createProgressBar($days);
        $bar->setFormat(" %current%/%max% روز [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %message%");
        $bar->setMessage('');
        $bar->start();

        for ($day = 0; $day < $days; $day++) {
            $today = array_merge($events[$day] ?? [], $this->deferred[$day] ?? []);
            unset($this->deferred[$day]);

            // Nothing can be saved on this date (see DemoCalendar::isBlocked()).
            if ($this->calendar->isBlocked($day)) {
                if ($day + 1 < $days) {
                    $this->deferred[$day + 1] = array_merge($this->deferred[$day + 1] ?? [], $today);
                }
                $bar->advance();
                continue;
            }

            usort($today, fn ($a, $b) => $a['minute'] <=> $b['minute']);

            DB::beginTransaction();
            try {
                $seasons = $this->calendar->seasons($this->calendar->days[$day]);
                foreach ($today as $event) {
                    $this->handle($event, $day, $seasons);
                }
                DB::commit();
            } catch (Throwable $e) {
                DB::rollBack();
                throw $e;
            }

            $bar->setMessage(sprintf('%s | فروش %d | خرید %d | خطا %d',
                $this->formDate($this->calendar->days[$day]),
                $this->stats['sale']['ok'] ?? 0,
                $this->stats['purchase']['ok'] ?? 0,
                count($this->failures)));
            $bar->advance();

            if ($day % 30 === 0) {
                gc_collect_cycles();
            }
        }

        $bar->finish();
        $this->console->newLine();
        \Carbon\Carbon::setTestNow();
        CarbonImmutable::setTestNow();
    }

    /**
     * Every scheduled event, bucketed by day. Documents that spawn from others
     * (order conversions, voids) are added while running.
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function schedule(): array
    {
        $trade = $this->calendar->weights;
        $office = $this->calendar->officeWeights();
        $orders = $this->n(100);
        $converted = (int) round($orders * 0.7);

        $plan = [
            'sale' => [$this->n(10000) - $converted, $trade, 0, [9 * 60, 20 * 60]],
            'purchase' => [$this->n(1000) - $converted, $office, 1, [7 * 60 + 30, 10 * 60]],
            'sale_quotation' => [$this->n(100), $office, 1, [9 * 60, 17 * 60]],
            'sale_order' => [$orders, $office, 1, [9 * 60, 17 * 60]],
            'purchase_quotation' => [$this->n(100), $office, 1, [9 * 60, 17 * 60]],
            'purchase_order' => [$orders, $office, 1, [8 * 60, 12 * 60]],
            'sale_return' => [$this->n(50), $trade, 5, [10 * 60, 19 * 60]],
            'purchase_return' => [$this->n(50), $office, 5, [10 * 60, 16 * 60]],
            'receipt' => [$this->n(2000), $trade, 7, [9 * 60, 18 * 60]],
            'payment' => [$this->n(1000), $office, 7, [10 * 60, 16 * 60]],
            'expense' => [$this->n(1000), $office, 1, [9 * 60, 17 * 60]],
            'journal' => [$this->n(1000), $office, 2, [15 * 60, 18 * 60]],
            'transfer' => [$this->n(500), $office, 1, [16 * 60, 20 * 60]],
            'adjustment' => [$this->n(600), $office, 2, [18 * 60, 20 * 60]],
            'landed_cost' => [$this->n(150), $office, 3, [11 * 60, 15 * 60]],
        ];

        $events = [];
        foreach ($plan as $type => [$count, $weights, $from, [$open, $close]]) {
            if ($count <= 0) {
                continue;
            }
            foreach ($this->calendar->distribute($count, $this->random, $weights, $from) as $day) {
                $events[$day][] = ['type' => $type, 'minute' => $this->random->int($open, $close)];
            }
        }

        // Weekly rate board, every Saturday morning.
        foreach ($this->calendar->days as $day => $date) {
            if ($day > 0 && $date->isSaturday()) {
                $events[$day][] = ['type' => 'rates', 'minute' => 7 * 60];
            }
        }

        return $events;
    }

    private function handle(array $event, int $day, array $seasons): void
    {
        $at = $this->calendar->at($day, $event['minute'])->addSeconds($this->random->int(0, 59));

        match ($event['type']) {
            'rates' => $this->updateRates($day, $at),
            'customer' => $this->createCustomer($event['index'], $at),
            'sale' => $this->sale($day, $at, $seasons),
            'convert_sale_order' => $this->convertSaleOrder($day, $at, $event),
            'purchase' => $this->purchase($day, $at),
            'convert_purchase_order' => $this->convertPurchaseOrder($day, $at, $event),
            'sale_quotation' => $this->quotation('sale', $day, $at, $seasons),
            'purchase_quotation' => $this->quotation('purchase', $day, $at, $seasons),
            'sale_order' => $this->saleOrder($day, $at, $seasons),
            'purchase_order' => $this->purchaseOrder($day, $at),
            'sale_return' => $this->saleReturn($day, $at),
            'purchase_return' => $this->purchaseReturn($day, $at),
            'receipt' => $this->settle('receipt', $day, $at),
            'payment' => $this->settle('payment', $day, $at),
            'expense' => $this->expense($day, $at),
            'journal' => $this->journal($day, $at),
            'transfer' => $this->transfer($day, $at),
            'adjustment' => $this->adjustment($day, $at, $seasons),
            'landed_cost' => $this->landedCost($day, $at),
        };
    }

    // ---------------------------------------------------------------- sales

    /** A customer for today: the walk-in cash customer, or a known one by weight. */
    private function pickCustomer(int $day, bool $tradeOnly = false): ?int
    {
        $weights = [];
        foreach ($this->customers as $i => $customer) {
            if ($customer['id'] && $customer['join'] <= $day && (! $tradeOnly || $customer['group'] !== 'retail')) {
                $weights[$i] = $customer['weight'];
            }
        }

        return $weights === [] ? null : (int) $this->random->weighted($weights);
    }

    /**
     * Build sale lines from what is on the shelf.
     *
     * @return array<int, array<string, mixed>>
     */
    private function saleLines(array $seasons, bool $wholesale, string $code, float $rate, ?string $customerId, CarbonImmutable $at, int $lineCount): array
    {
        $lines = [];
        $used = [];
        $attempts = 0;

        while (count($lines) < $lineCount && $attempts < $lineCount * 6) {
            $attempts++;
            $index = $this->drawItem($seasons);
            if (isset($used[$index])) {
                continue;
            }
            $spec = $this->items[$index];
            [$qty, $base, $unitKey] = $this->lineQuantity($spec, $wholesale);

            // Leave a little on the shelf; a line that would empty it is skipped.
            if ($spec['stock'] - $base < 0.0001) {
                continue;
            }

            $afn = $unitKey === $spec['purchase_unit'] && $spec['factor'] > 1
                ? $spec['sale_price'] * $spec['factor'] * 0.97
                : $spec['sale_price'];
            $price = $this->unitPrice($afn, $code, $rate, $qty);
            $discount = round((float) $this->discounts->discountFor(
                itemId: $spec['id'],
                quantity: (float) $qty,
                unitPrice: $price,
                ledgerId: $customerId,
                branchId: $this->branchId,
                onDate: $at->toDateString(),
            ), 2);

            $used[$index] = true;
            $lines[] = [
                'index' => $index,
                'base' => $base,
                'payload' => [
                    'item_id' => $spec['id'],
                    'quantity' => $qty,
                    'unit_measure_id' => $this->units[$unitKey],
                    'unit_price' => $price,
                    'item_discount' => $discount,
                    'free' => 0,
                    'tax' => 0,
                    'batch' => null,
                    'expire_date' => null,
                ],
            ];
        }

        return $lines;
    }

    private function sale(int $day, CarbonImmutable $at, array $seasons, ?array $fromOrder = null): void
    {
        $walkIn = $fromOrder === null && $this->random->chance(0.65);
        $customerIndex = $fromOrder['customer'] ?? ($walkIn ? null : $this->pickCustomer($day));
        if (! $walkIn && $customerIndex === null) {
            $walkIn = true;
        }

        $customer = $walkIn ? null : $this->customers[$customerIndex];
        $customerId = $walkIn ? $this->cashCustomerId : $customer['id'];
        $code = $walkIn ? (string) $this->random->weighted(['AFN' => 80, 'USD' => 17, 'PKR' => 3]) : $customer['currency'];
        $rate = $fromOrder['rate'] ?? $this->rate($code, $day);
        $wholesale = ! $walkIn && $customer['group'] !== 'retail';

        if ($fromOrder !== null) {
            $lines = [];
            foreach ($fromOrder['lines'] as $line) {
                if ($this->items[$line['index']]['stock'] - $line['base'] >= 0.0001) {
                    $lines[] = $line;
                }
            }
            if (count($lines) !== count($fromOrder['lines'])) {
                return; // not everything is in stock any more; the order stays open
            }
        } else {
            $lineCount = $wholesale ? $this->random->count(8, 3, 15) : $this->random->count(3.5, 1, 12);
            $lines = $this->saleLines($seasons, $wholesale, $code, $rate, $customerId, $at, $lineCount);
        }

        if ($lines === []) {
            return;
        }

        $gross = 0.0;
        $discount = 0.0;
        foreach ($lines as $line) {
            $gross += round($line['payload']['quantity'] * $line['payload']['unit_price'], 2);
            $discount += $line['payload']['item_discount'];
        }
        $total = round($gross - $discount, 2);

        // Walk-ins pay cash. Trade customers mostly buy on credit.
        $type = 'cash';
        if (! $walkIn) {
            $type = $customer['credit']
                ? (string) $this->random->weighted(['on_loan' => 60, 'credit' => 15, 'cash' => 25])
                : ($this->random->chance(0.15) ? 'on_loan' : 'cash');
        }

        $till = $this->tillFor($code, incoming: true);
        $number = $this->next('sale', Sale::class);
        $payload = [
            'number' => $number,
            'customer_id' => $customerId,
            'sale_order_id' => $fromOrder['id'] ?? null,
            'date' => $this->formDate($at),
            'currency_id' => $this->currency[$code],
            'rate' => $rate,
            'sale_type' => $type,
            'warehouse_id' => $this->warehouseId,
            'discount' => 0,
            'discount_type' => 'currency',
            'discount_total' => round($discount, 2),
            'transaction_total' => $total,
            'description' => $walkIn ? null : 'فروش به ' . ($wholesale ? 'مشتری عمده' : 'مشتری'),
            'item_list' => array_column($lines, 'payload'),
        ];

        $paid = 0.0;
        if ($type === 'cash') {
            $payload['bank_account_id'] = $this->cash[$till]['id'];
            $paid = $total;
        } elseif ($type === 'credit') {
            $paid = round($total * $this->random->between(0.2, 0.7), 2);
            $payload['payment'] = ['method' => 'cash', 'amount' => $paid, 'account_id' => $this->cash[$till]['id'], 'note' => 'پرداخت قسمی'];
        }

        $user = $this->actor(Sale::class);
        $sale = $this->attempt('sale', function () use ($user, $at, $payload, $number) {
            $this->send($user, $at, 'sales.store', $payload);

            return Sale::query()->withoutGlobalScopes()->where('number', $number)->where('branch_id', $this->branchId)->first();
        });

        if (! $sale) {
            return;
        }

        foreach ($lines as $line) {
            $this->items[$line['index']]['stock'] -= $line['base'];
        }
        $this->cash[$till]['balance'] += $paid;

        $transactionId = (string) Transaction::query()->where('reference_type', Sale::class)->where('reference_id', $sale->id)->value('id');
        $credit = $type !== 'cash';

        // Voids: only documents nothing else will ever hang off.
        if ($fromOrder === null && $this->random->chance(self::VOID_RATE)) {
            if ($this->void('sale', 'sales.reverse', ['sale' => $sale->id], $sale, $at)) {
                foreach ($lines as $line) {
                    $this->items[$line['index']]['stock'] += $line['base'];
                }
                $this->cash[$till]['balance'] -= $paid;

                return;
            }
        }

        if ($credit) {
            $this->owingCustomers[$customerId] = true;
            if ($this->random->chance(self::KEEP_OPEN_RATE)) {
                $this->keepOpen[$transactionId] = true;
            }
        }

        $this->remember($this->recentSales, [
            'id' => $sale->id,
            'day' => $day,
            'items' => SaleItem::query()->withoutGlobalScopes()->where('sale_id', $sale->id)->get(['id', 'item_id', 'quantity', 'unit_measure_id'])->all(),
            'returned' => [],
        ]);
    }

    private function remember(array &$list, array $entry, int $keep = 400): void
    {
        $list[] = $entry;
        if (count($list) > $keep) {
            array_shift($list);
        }
    }

    /**
     * Reverse a document right after it was posted (entered by mistake). Done
     * through the document's own reverse action so GL and stock are undone.
     */
    private function void(string $type, string $route, array $params, object $model, CarbonImmutable $at): bool
    {
        // Caught within minutes; the clock keeps later documents after it.
        $when = $at->addMinutes($this->random->int(2, 8));
        $user = $this->voidActor($model);
        $ok = $this->attempt("void_{$type}", fn () => $this->send($user, $when, $route, [
            'reason' => $this->random->pick(['ثبت اشتباه', 'مبلغ اشتباه وارد شده بود', 'تکراری ثبت شده', 'مشتری منصرف شد', 'طرف حساب اشتباه انتخاب شده']),
        ], $params) ?? true);

        if ($ok) {
            $this->stats[$type]['voided'] = ($this->stats[$type]['voided'] ?? 0) + 1;
            $this->untouchable[$model->id] = true;
        }

        return (bool) $ok;
    }

    /** Which till money goes into (or comes out of) for a currency. */
    private function tillFor(string $code, bool $incoming, float $needed = 0.0): string
    {
        $options = match ($code) {
            'USD' => ['usd_cash' => 60, 'usd_azizi' => 25, 'usd_aib' => 15],
            'PKR' => ['pkr_cash' => 100],
            default => $incoming ? ['afn_hand' => 80, 'afn_bank' => 12, 'afn_azizi' => 8] : ['afn_hand' => 40, 'afn_safe' => 30, 'afn_bank' => 15, 'afn_azizi' => 15],
        };

        if ($incoming) {
            return (string) $this->random->weighted($options);
        }

        // Paying out: the account with the most money that can cover it.
        $best = null;
        foreach (array_keys($options) as $key) {
            if ($best === null || $this->cash[$key]['balance'] > $this->cash[$best]['balance']) {
                $best = $key;
            }
        }

        return $best;
    }

    private function saleOrder(int $day, CarbonImmutable $at, array $seasons): void
    {
        $customerIndex = $this->pickCustomer($day, tradeOnly: true);
        if ($customerIndex === null) {
            return;
        }
        $customer = $this->customers[$customerIndex];
        $code = $customer['currency'];
        $rate = $this->rate($code, $day);
        $lines = $this->saleLines($seasons, true, $code, $rate, $customer['id'], $at, $this->random->count(6, 2, 12));
        if ($lines === []) {
            return;
        }

        $number = $this->next('sale_order', SaleOrder::class);
        $payload = [
            'number' => $number,
            'date' => $this->formDate($at),
            'delivery_date' => $this->formDateAfter($at, $this->random->int(1, 7)),
            'customer_id' => $customer['id'],
            'currency_id' => $this->currency[$code],
            'rate' => $rate,
            'warehouse_id' => $this->warehouseId,
            'discount' => 0,
            'discount_type' => 'currency',
            'note' => 'سفارش مشتری عمده',
            'item_list' => array_map(fn ($l) => [
                'item_id' => $l['payload']['item_id'],
                'quantity' => $l['payload']['quantity'],
                'unit_price' => $l['payload']['unit_price'],
                'unit_measure_id' => $l['payload']['unit_measure_id'],
                'discount' => $l['payload']['item_discount'],
                'free' => 0,
            ], $lines),
        ];

        $order = $this->attempt('sale_order', function () use ($payload, $at, $number) {
            $this->send($this->actor(SaleOrder::class), $at, 'sale-orders.store', $payload);
            $order = SaleOrder::query()->withoutGlobalScopes()->where('number', $number)->first();
            if ($order && $order->status === 'draft') {
                $this->send($this->actor(SaleOrder::class), $at->addMinutes(20), 'sale-orders.post', [], ['sale_order' => $order->id]);
            }

            return $order;
        });

        if ($order && $this->random->chance(0.7)) {
            $convertDay = $day + $this->random->int(1, 6);
            if ($convertDay < $this->calendar->dayCount()) {
                $this->deferred[$convertDay][] = [
                    'type' => 'convert_sale_order',
                    'minute' => $this->random->int(9 * 60, 16 * 60),
                    'order' => ['id' => $order->id, 'customer' => $customerIndex, 'rate' => $rate, 'lines' => $lines],
                ];
            }
        }
    }

    private function convertSaleOrder(int $day, CarbonImmutable $at, array $event): void
    {
        $this->sale($day, $at, [], $event['order']);
    }

    private function quotation(string $side, int $day, CarbonImmutable $at, array $seasons): void
    {
        if ($side === 'sale') {
            $customerIndex = $this->pickCustomer($day, tradeOnly: true);
            if ($customerIndex === null) {
                return;
            }
            $party = ['customer_id' => $this->customers[$customerIndex]['id']];
            $code = $this->customers[$customerIndex]['currency'];
            $model = SaleQuotation::class;
            $route = 'sale-quotations';
            $param = 'sale_quotation';
        } else {
            $supplier = $this->suppliers[(int) $this->random->weighted(array_column($this->suppliers, 'weight'))];
            $party = ['supplier_id' => $supplier['id']];
            $code = $supplier['currency'];
            $model = PurchaseQuotation::class;
            $route = 'purchase-quotations';
            $param = 'purchase_quotation';
        }

        $rate = $this->rate($code, $day);
        $lines = [];
        $count = $this->random->count(5, 1, 15);
        for ($i = 0; $i < $count; $i++) {
            $spec = $this->items[$this->drawItem($seasons)];
            [$qty, , $unitKey] = $side === 'sale' ? $this->lineQuantity($spec, true) : [$this->random->int(2, 20), 0, $spec['purchase_unit']];
            $afn = $side === 'sale' ? $spec['sale_price'] : $spec['purchase_price'];
            $afn *= $unitKey === $spec['purchase_unit'] ? $spec['factor'] : 1;
            $lines[$spec['id']] = [
                'item_id' => $spec['id'],
                'quantity' => $qty,
                'unit_price' => $this->unitPrice($afn, $code, $rate, $qty),
                'unit_measure_id' => $this->units[$unitKey],
                'discount' => 0,
                'free' => 0,
            ];
        }

        $number = $this->next($route, $model);
        $this->attempt(str_replace('-', '_', $side) . '_quotation', function () use ($model, $route, $param, $party, $code, $rate, $lines, $at, $number) {
            $user = $this->actor($model);
            $this->send($user, $at, "{$route}.store", $party + [
                'number' => $number,
                'date' => $this->formDate($at),
                'valid_until' => $this->formDateAfter($at, 15),
                'currency_id' => $this->currency[$code],
                'rate' => $rate,
                'warehouse_id' => $this->warehouseId,
                'discount' => 0,
                'discount_type' => 'currency',
                'note' => 'پیش‌فاکتور',
                'item_list' => array_values($lines),
            ]);

            // Most quotations are sent (posted); some stay as drafts.
            $quotation = $model::query()->withoutGlobalScopes()->where('number', $number)->first();
            if ($quotation && $quotation->status === 'draft' && $this->random->chance(0.75)) {
                $this->send($user, $at->addMinutes(30), "{$route}.post", [], [$param => $quotation->id]);
            }

            return true;
        });
    }

    // ------------------------------------------------------------ purchases

    /** The supplier whose shelves most need filling, biased toward the big ones. */
    private function pickSupplierForRestock(int $day): ?int
    {
        $weights = [];
        foreach ($this->suppliers as $index => $supplier) {
            $urgency = 0.0;
            foreach ($supplier['items'] as $itemIndex) {
                $spec = $this->items[$itemIndex];
                $cover = $spec['demand'] > 0 ? $spec['stock'] / $spec['demand'] : 999;
                if ($cover < 45) {
                    $urgency += (45 - $cover) * $spec['demand'] * $spec['purchase_price'];
                }
            }
            if ($urgency > 0) {
                $weights[$index] = $urgency * (0.3 + $supplier['weight']);
            }
        }

        if ($weights === []) {
            $candidates = array_filter($this->suppliers, fn ($s) => $s['items'] !== []);

            return $candidates === [] ? null : (int) $this->random->weighted(array_map(fn ($s) => $s['weight'], $candidates));
        }

        return (int) $this->random->weighted($weights);
    }

    /** @return array<int, array<string, mixed>> */
    private function restockLines(array $supplier, string $code, float $rate): array
    {
        $candidates = [];
        foreach ($supplier['items'] as $itemIndex) {
            $spec = $this->items[$itemIndex];
            $candidates[$itemIndex] = $spec['demand'] > 0 ? $spec['stock'] / $spec['demand'] : 999;
        }
        asort($candidates);

        $lines = [];
        foreach ($candidates as $itemIndex => $cover) {
            if (count($lines) >= 15 || ($cover >= 45 && count($lines) >= 1)) {
                break;
            }
            $spec = $this->items[$itemIndex];
            $target = $spec['demand'] * $this->random->between(60, 95);
            $need = max($target - $spec['stock'], $spec['demand'] * 20);
            $qty = $spec['factor'] > 1 ? max(1, (int) ceil($need / $spec['factor'])) : max($spec['unit'] === 'ea' ? 5 : 10, (int) ceil($need / 5) * 5);
            $base = $qty * $spec['factor'];
            $afn = $spec['purchase_price'] * $spec['factor'] * $this->random->between(0.97, 1.03);

            $lines[] = [
                'index' => $itemIndex,
                'base' => $base,
                'payload' => [
                    'item_id' => $spec['id'],
                    'quantity' => $qty,
                    'unit_measure_id' => $this->units[$spec['purchase_unit']],
                    'unit_price' => $this->unitPrice($afn, $code, $rate, $qty),
                    'item_discount' => 0,
                    'free' => 0,
                    'tax' => 0,
                    'batch' => null,
                    'expire_date' => null,
                ],
            ];
        }

        return $lines;
    }

    private function purchase(int $day, CarbonImmutable $at, ?array $fromOrder = null): void
    {
        $supplierIndex = $fromOrder['supplier'] ?? $this->pickSupplierForRestock($day);
        if ($supplierIndex === null) {
            return;
        }
        $supplier = $this->suppliers[$supplierIndex];
        $code = $supplier['currency'];
        $rate = $fromOrder['rate'] ?? $this->rate($code, $day);
        $lines = $fromOrder['lines'] ?? $this->restockLines($supplier, $code, $rate);
        if ($lines === []) {
            return;
        }

        // Some suppliers give a small discount on a line.
        if ($fromOrder === null && $this->random->chance(0.1)) {
            foreach ($lines as &$line) {
                if ($this->random->chance(0.5)) {
                    $line['payload']['item_discount'] = round($line['payload']['quantity'] * $line['payload']['unit_price'] * $this->random->between(0.01, 0.03), 2);
                }
            }
            unset($line);
        }

        $gross = 0.0;
        $discount = 0.0;
        foreach ($lines as $line) {
            $gross += round($line['payload']['quantity'] * $line['payload']['unit_price'], 2);
            $discount += $line['payload']['item_discount'];
        }
        $total = round($gross - $discount, 2);

        $type = (string) $this->random->weighted(['cash' => 30, 'on_loan' => 60, 'credit' => 10]);
        $till = $this->tillFor($code, incoming: false);
        $available = $this->cash[$till]['balance'];
        $paid = 0.0;

        if ($type === 'cash' && $available < $total * 1.05) {
            $type = 'on_loan';
        }
        if ($type === 'credit') {
            $paid = round(min($total * $this->random->between(0.2, 0.6), $available * 0.8), 2);
            if ($paid <= 0) {
                $type = 'on_loan';
                $paid = 0.0;
            }
        }

        $number = $this->next('purchase', Purchase::class);
        $payload = [
            'number' => $number,
            'supplier_id' => $supplier['id'],
            'purchase_order_id' => $fromOrder['id'] ?? null,
            'date' => $this->formDate($at),
            'currency_id' => $this->currency[$code],
            'rate' => $rate,
            'purchase_type' => $type,
            'warehouse_id' => $this->warehouseId,
            'discount' => 0,
            'discount_type' => 'currency',
            'discount_total' => round($discount, 2),
            'transaction_total' => $total,
            'description' => 'خرید از ' . ($code === 'AFN' ? 'بازار داخلی' : 'واردکننده'),
            'item_list' => array_column($lines, 'payload'),
        ];

        if ($type === 'cash') {
            $payload['bank_account_id'] = $this->cash[$till]['id'];
            $paid = $total;
        } elseif ($type === 'credit') {
            $payload['payment'] = ['method' => 'cash', 'amount' => $paid, 'account_id' => $this->cash[$till]['id'], 'note' => 'پیش‌پرداخت قسمی'];
        } else {
            $payload['payment'] = ['amount' => 0];
        }

        $purchase = $this->attempt('purchase', function () use ($payload, $at, $number) {
            $this->send($this->actor(Purchase::class), $at, 'purchases.store', $payload);

            return Purchase::query()->withoutGlobalScopes()->where('number', $number)->where('branch_id', $this->branchId)->latest('created_at')->first();
        });

        if (! $purchase) {
            return;
        }

        $this->cash[$till]['balance'] -= $paid;
        $transactionId = (string) Transaction::query()->where('reference_type', Purchase::class)->where('reference_id', $purchase->id)->value('id');

        // Voided purchases are reversed before any of their stock is sold.
        if ($fromOrder === null && $this->random->chance(self::VOID_RATE)) {
            if ($this->void('purchase', 'purchases.reverse', ['purchase' => $purchase->id], $purchase, $at)) {
                $this->cash[$till]['balance'] += $paid;

                return;
            }
        }

        foreach ($lines as $line) {
            $this->items[$line['index']]['stock'] += $line['base'];
        }

        if ($type !== 'cash') {
            $this->owedSuppliers[$supplier['id']] = true;
            if ($this->random->chance(self::KEEP_OPEN_RATE)) {
                $this->keepOpen[$transactionId] = true;
            }
        }

        $this->remember($this->recentPurchases, [
            'id' => $purchase->id,
            'day' => $day,
            'code' => $code,
            'total_afn' => $total * $rate,
            'lines' => $lines,
            'items' => PurchaseItem::query()->withoutGlobalScopes()->where('purchase_id', $purchase->id)->get(['id', 'item_id', 'quantity'])->all(),
            'returned' => [],
            'landed' => false,
        ], 200);
    }

    private function purchaseOrder(int $day, CarbonImmutable $at): void
    {
        $supplierIndex = $this->pickSupplierForRestock($day);
        if ($supplierIndex === null) {
            return;
        }
        $supplier = $this->suppliers[$supplierIndex];
        $code = $supplier['currency'];
        $rate = $this->rate($code, $day);
        $lines = $this->restockLines($supplier, $code, $rate);
        if ($lines === []) {
            return;
        }

        $number = $this->next('purchase_order', PurchaseOrder::class);
        $order = $this->attempt('purchase_order', function () use ($supplier, $code, $rate, $lines, $at, $number) {
            $user = $this->actor(PurchaseOrder::class);
            $this->send($user, $at, 'purchase-orders.store', [
                'number' => $number,
                'date' => $this->formDate($at),
                'delivery_date' => $this->formDateAfter($at, $this->random->int(3, 10)),
                'supplier_id' => $supplier['id'],
                'currency_id' => $this->currency[$code],
                'rate' => $rate,
                'warehouse_id' => $this->warehouseId,
                'discount' => 0,
                'discount_type' => 'currency',
                'note' => 'سفارش خرید',
                'item_list' => array_map(fn ($l) => [
                    'item_id' => $l['payload']['item_id'],
                    'quantity' => $l['payload']['quantity'],
                    'unit_price' => $l['payload']['unit_price'],
                    'unit_measure_id' => $l['payload']['unit_measure_id'],
                    'discount' => 0,
                    'free' => 0,
                ], $lines),
            ]);
            $order = PurchaseOrder::query()->withoutGlobalScopes()->where('number', $number)->first();
            if ($order && $order->status === 'draft') {
                $this->send($user, $at->addMinutes(20), 'purchase-orders.post', [], ['purchase_order' => $order->id]);
            }

            return $order;
        });

        if ($order && $this->random->chance(0.7)) {
            $convertDay = $day + $this->random->int(2, 10);
            if ($convertDay < $this->calendar->dayCount()) {
                $this->deferred[$convertDay][] = [
                    'type' => 'convert_purchase_order',
                    'minute' => $this->random->int(8 * 60, 11 * 60),
                    'order' => ['id' => $order->id, 'supplier' => $supplierIndex, 'rate' => $rate, 'lines' => $lines],
                ];
            }
        }
    }

    private function convertPurchaseOrder(int $day, CarbonImmutable $at, array $event): void
    {
        $this->purchase($day, $at, $event['order']);
    }

    // -------------------------------------------------------------- returns

    private function saleReturn(int $day, CarbonImmutable $at): void
    {
        $candidates = array_keys(array_filter(
            $this->recentSales,
            fn ($s) => $s['day'] <= $day - 1 && $s['day'] >= $day - 30 && ! isset($this->untouchable[$s['id']])
        ));
        if ($candidates === []) {
            return;
        }

        $key = $this->random->pick($candidates);
        $sale = $this->recentSales[$key];
        $lines = [];
        foreach ($this->random->shuffle($sale['items']) as $saleItem) {
            if (count($lines) >= $this->random->int(1, 2)) {
                break;
            }
            $left = (float) $saleItem->quantity - ($sale['returned'][$saleItem->id] ?? 0);
            $qty = $left >= 2 ? $this->random->int(1, (int) floor($left / 2)) : (floor($left) >= 1 ? 1 : 0);
            if ($qty <= 0) {
                continue;
            }
            $lines[] = ['sale_item_id' => $saleItem->id, 'quantity' => $qty, '_item' => $saleItem];
        }
        if ($lines === []) {
            return;
        }

        $number = $this->next('sale_return', SaleReturn::class);
        $ok = $this->attempt('sale_return', fn () => $this->send($this->actor(SaleReturn::class), $at, 'sale-returns.store', [
            'number' => $number,
            'sale_id' => $sale['id'],
            'date' => $this->formDate($at),
            'reason' => $this->random->pick(['damaged', 'expired', 'wrong_item', 'customer_changed_mind', 'defective']),
            'description' => 'برگشت جنس از مشتری',
            'item_list' => array_map(fn ($l) => ['sale_item_id' => $l['sale_item_id'], 'quantity' => $l['quantity']], $lines),
        ]));

        if ($ok) {
            foreach ($lines as $line) {
                $this->recentSales[$key]['returned'][$line['sale_item_id']] = ($this->recentSales[$key]['returned'][$line['sale_item_id']] ?? 0) + $line['quantity'];
                $index = $this->itemIndexById($line['_item']->item_id);
                if ($index !== null) {
                    $this->items[$index]['stock'] += $line['quantity'] * $this->unitFactor($line['_item']->unit_measure_id, $index);
                }
            }
            $this->untouchable[$sale['id']] = true; // never reverse a sale with a return
        }
    }

    private function purchaseReturn(int $day, CarbonImmutable $at): void
    {
        $candidates = array_keys(array_filter(
            $this->recentPurchases,
            fn ($p) => $p['day'] <= $day - 1 && $p['day'] >= $day - 7 && ! isset($this->untouchable[$p['id']])
        ));
        if ($candidates === []) {
            return;
        }

        $key = $this->random->pick($candidates);
        $purchase = $this->recentPurchases[$key];
        $lines = [];
        foreach ($this->random->shuffle($purchase['lines']) as $position => $line) {
            if (count($lines) >= $this->random->int(1, 2)) {
                break;
            }
            $spec = $this->items[$line['index']];
            $purchaseItem = collect($purchase['items'])->firstWhere('item_id', $spec['id']);
            if (! $purchaseItem) {
                continue;
            }
            $qty = max(1, (int) floor($line['payload']['quantity'] * $this->random->between(0.05, 0.25)));
            $base = $qty * $spec['factor'];
            if ($spec['stock'] - $base < $spec['demand'] * 5) {
                continue;
            }
            $lines[] = ['purchase_item_id' => $purchaseItem->id, 'quantity' => $qty, '_index' => $line['index'], '_base' => $base];
        }
        if ($lines === []) {
            return;
        }

        $number = $this->next('purchase_return', PurchaseReturn::class);
        $ok = $this->attempt('purchase_return', fn () => $this->send($this->actor(PurchaseReturn::class), $at, 'purchase-returns.store', [
            'number' => $number,
            'purchase_id' => $purchase['id'],
            'date' => $this->formDate($at),
            'reason' => $this->random->pick(['damaged', 'expired', 'quality_rejection', 'over_ordered', 'wrong_item']),
            'description' => 'برگشت جنس به تأمین‌کننده',
            'item_list' => array_map(fn ($l) => ['purchase_item_id' => $l['purchase_item_id'], 'quantity' => $l['quantity']], $lines),
        ]));

        if ($ok) {
            foreach ($lines as $line) {
                $this->items[$line['_index']]['stock'] -= $line['_base'];
            }
            $this->untouchable[$purchase['id']] = true;
        }
    }

    private ?array $itemIndex = null;

    private function itemIndexById(string $id): ?int
    {
        $this->itemIndex ??= array_flip(array_column($this->items, 'id'));

        return $this->itemIndex[$id] ?? null;
    }

    private function unitFactor(string $unitId, int $index): float
    {
        return $unitId === $this->units['c12'] ? 12 : ($unitId === $this->units['c24'] ? 24 : 1);
    }

    // ------------------------------------------------- receipts & payments

    /**
     * A receipt (money in from a customer) or a payment (money out to a supplier).
     *
     * ~60% name the bills they settle (fully or in part). The rest are "on
     * account": in this system that relieves the oldest open bills first and
     * parks any excess as an advance, so the amount is capped to stop before an
     * invoice that is meant to stay open — or, for a party that owes nothing,
     * it is a genuine prepayment.
     */
    private function settle(string $kind, int $day, CarbonImmutable $at): void
    {
        $isReceipt = $kind === 'receipt';
        $direction = $isReceipt ? SettlementService::DIRECTION_IN : SettlementService::DIRECTION_OUT;
        $parties = $isReceipt ? $this->owingCustomers : $this->owedSuppliers;
        $parties = array_diff_key($parties, [$this->cashCustomerId => true]);

        if ($parties === []) {
            return;
        }

        $weights = [];
        $currencyOf = [];
        foreach ($isReceipt ? $this->customers : $this->suppliers as $party) {
            if ($party['id'] && isset($parties[$party['id']])) {
                $weights[$party['id']] = $party['weight'] + 0.001;
                $currencyOf[$party['id']] = $party['currency'];
            }
        }
        if ($weights === []) {
            return;
        }

        $service = app(SettlementService::class);
        $cutoff = $this->calendar->days[max(0, $day - 5)]->toDateString();

        $mode = $this->random->chance(0.6) ? 'bill_by_bill' : 'on_account';
        $found = false;

        // Whoever comes in to pay has something this voucher can settle: named
        // bills for bill-by-bill, a clear run of old bills (or nothing owed in
        // their currency, i.e. an advance) for on-account.
        for ($try = 0; $try < 30 && $weights !== []; $try++) {
            $partyId = (string) $this->random->weighted($weights);
            unset($weights[$partyId]);
            $code = $currencyOf[$partyId];
            $allOpen = $service->openItems($partyId, null, $direction);
            $open = $allOpen->filter(fn ($i) => $i['currency_id'] === $this->currency[$code])->values();

            if ($allOpen->isEmpty()) {
                if ($isReceipt) {
                    unset($this->owingCustomers[$partyId]);
                } else {
                    unset($this->owedSuppliers[$partyId]);
                }
            }

            // Bills old enough to be paid, and not ones meant to stay open.
            $payable = $open->filter(fn ($i) => ! isset($this->keepOpen[$i['transaction_id']]) && substr((string) $i['date'], 0, 10) <= $cutoff)->values();

            // The FIFO run an on-account voucher would relieve, stopping before
            // the first bill meant to stay open.
            $prefix = 0.0;
            foreach ($open as $item) {
                if (isset($this->keepOpen[$item['transaction_id']])) {
                    break;
                }
                $prefix += (float) $item['remaining_amount'];
            }

            if ($mode === 'bill_by_bill' ? $payable->isNotEmpty() : ($open->isEmpty() || $prefix > 0)) {
                $found = true;
                break;
            }
        }

        if (! $found) {
            return;
        }

        $rate = $this->rate($code, $day);
        $allocations = [];
        $amount = 0.0;

        // Paying out on account needs the till to cover it; when it cannot, the
        // supplier is paid against named bills instead (in afghanis if need be).
        if (! $isReceipt && $mode === 'on_account' && $payable->isNotEmpty()
            && $this->cash[$this->tillFor($code, incoming: false)]['balance'] < $prefix) {
            $mode = 'bill_by_bill';
        }

        if ($mode === 'bill_by_bill') {
            $take = $payable->take($this->random->int(1, 4));
            foreach ($take as $position => $item) {
                $remaining = (float) $item['remaining_amount'];
                $apply = ($position === $take->count() - 1 && $this->random->chance(0.15))
                    ? round($remaining * $this->random->between(0.3, 0.9), 2)
                    : $remaining;
                if ($apply <= 0) {
                    continue;
                }
                $allocations[] = ['target_line_id' => $item['target_line_id'], 'amount' => $apply];
                $amount += $apply;
            }
        }

        if ($mode === 'on_account') {
            if ($open->isEmpty()) {
                // Owes nothing in this currency: an advance.
                $amount = $this->money($this->afnTo($this->random->between(5000, 60000), $code, $day), $code);
            } elseif ($prefix > 0) {
                $amount = round($prefix * $this->random->between(0.85, 1.0), 2);
            } else {
                return;
            }
        }

        $amount = round($amount, 2);
        if ($amount <= 0) {
            return;
        }

        // Paying out needs money in the till. A USD bill with no dollars on hand
        // is paid in afghanis at the day's rate (cross-currency settlement).
        $cashCode = $code;
        $appliedCash = null;
        $till = $this->tillFor($code, incoming: $isReceipt);

        if (! $isReceipt && $this->cash[$till]['balance'] < $amount) {
            if ($mode === 'bill_by_bill' && $code !== 'AFN') {
                $cashCode = 'AFN';
                $till = $this->tillFor('AFN', incoming: false);
                $appliedCash = round($amount * $rate * 1.003, 2);
                if ($this->cash[$till]['balance'] < $appliedCash) {
                    return;
                }
            } else {
                return;
            }
        } elseif ($isReceipt && $mode === 'bill_by_bill' && $code !== 'AFN' && $this->random->chance(0.1)) {
            // A dollar customer settling in afghanis.
            $cashCode = 'AFN';
            $till = $this->tillFor('AFN', incoming: true);
            $appliedCash = round($amount * $rate * 0.997, 2);
        }

        $cashAmount = $appliedCash ?? $amount;
        $model = $isReceipt ? Receipt::class : Payment::class;
        $number = $this->next($kind, $model);
        $payload = [
            'number' => $number,
            'date' => $this->formDate($at),
            'ledger_id' => $partyId,
            'payment_mode' => $mode,
            'amount' => $cashAmount,
            'bank_account_id' => $this->cash[$till]['id'],
            'currency_id' => $this->currency[$cashCode],
            'rate' => $cashCode === 'AFN' ? 1 : $rate,
            'narration' => $isReceipt
                ? ($mode === 'bill_by_bill' ? 'دریافت بابت بیل‌ها' : 'دریافت عمومی به حساب مشتری')
                : ($mode === 'bill_by_bill' ? 'پرداخت بابت بیل‌ها' : 'پرداخت عمومی به حساب تأمین‌کننده'),
        ];
        if ($allocations !== []) {
            $payload['allocations'] = $allocations;
        }
        if ($appliedCash !== null) {
            $payload['applied_cash_amount'] = $appliedCash;
        }
        if (str_contains($till, 'azizi') || str_contains($till, 'aib') || str_contains($till, 'bank')) {
            $payload['cheque_no'] = 'CHQ-' . $this->random->int(100000, 999999);
        }

        $document = $this->attempt($kind, function () use ($model, $kind, $payload, $at, $number) {
            $this->send($this->actor($model), $at, "{$kind}s.store", $payload);

            return $model::query()->withoutGlobalScopes()->where('number', $number)->where('branch_id', $this->branchId)->first();
        });

        if (! $document) {
            return;
        }

        $this->cash[$till]['balance'] += $isReceipt ? $cashAmount : -$cashAmount;

        // A cross-currency voucher cannot be reversed: TransactionService::reverse()
        // re-posts its lines without the cross-currency flag and the per-currency
        // balance check refuses it. Only same-currency vouchers are voided.
        if ($appliedCash === null && $this->random->chance(self::VOID_RATE * 1.05)) {
            if ($this->void($kind, "{$kind}s.reverse", [$kind => $document->id], $document, $at)) {
                $this->cash[$till]['balance'] -= $isReceipt ? $cashAmount : -$cashAmount;
            }
        }
    }

    // --------------------------------------------------------- back office

    private function expense(int $day, CarbonImmutable $at): void
    {
        // Rent, salaries, power and internet fall due around the start of each
        // Jalali month; everything else is drawn by how often it happens.
        $jalaliDay = (int) explode('-', $this->formDate($at))[2];
        if ($jalaliDay <= 3 && $this->random->chance(0.8)) {
            $name = $this->random->pick(['کرایه دکان و گدام', 'معاش کارمندان', 'برق', 'انترنت']);
        } else {
            $weights = [];
            foreach (self::EXPENSE_CATEGORIES as $category => $spec) {
                if ($spec[3] > 0) {
                    $weights[$category] = $spec[3] * ($category === 'بخاری و گرمایش' && ! in_array((int) $this->calendar->days[$day]->format('n'), [11, 12, 1, 2, 3], true) ? 0.1 : 1);
                }
            }
            $name = (string) $this->random->weighted($weights);
        }

        [$accountKey, $min, $max, , $code] = self::EXPENSE_CATEGORIES[$name];
        if (! isset($this->expenseCategories[$name], $this->accounts[$accountKey])) {
            return;
        }

        $total = round($this->random->between($min, $max), $code === 'USD' ? 0 : -1);
        $details = [];
        if ($name === 'معاش کارمندان') {
            $staff = $this->random->int(6, 12);
            $left = $total;
            for ($i = 1; $i <= $staff; $i++) {
                $share = $i === $staff ? $left : round($total / $staff * $this->random->between(0.7, 1.3), -1);
                $share = min($share, $left - ($staff - $i) * 100);
                $details[] = ['title' => 'معاش ' . $this->people->person(), 'amount' => max($share, 100)];
                $left -= max($share, 100);
            }
            $total = array_sum(array_column($details, 'amount'));
        } else {
            $details[] = ['title' => $name, 'amount' => $total];
        }

        $till = $this->tillFor($code, incoming: false);
        if ($this->cash[$till]['balance'] < $total) {
            return;
        }

        $number = $this->next('expense', Expense::class);
        $expense = $this->attempt('expense', function () use ($name, $accountKey, $till, $code, $day, $details, $at, $number) {
            $this->send($this->actor(Expense::class), $at, 'expenses.store', [
                'number' => (string) $number,
                'date' => $this->formDate($at),
                'remarks' => $name . ' - ' . $this->formDate($at),
                'category_id' => $this->expenseCategories[$name],
                'expense_account_id' => $this->accounts[$accountKey],
                'bank_account_id' => $this->cash[$till]['id'],
                'currency_id' => $this->currency[$code],
                'rate' => $this->rate($code, $day),
                'details' => $details,
            ]);

            return Expense::query()->withoutGlobalScopes()->where('number', (string) $number)->latest('created_at')->first();
        });

        if (! $expense) {
            return;
        }
        $this->cash[$till]['balance'] -= $total;

        if ($this->random->chance(self::VOID_RATE)) {
            if ($this->void('expense', 'expenses.reverse', ['expense' => $expense->id], $expense, $at)) {
                $this->cash[$till]['balance'] += $total;
            }
        }
    }

    private function transfer(int $day, CarbonImmutable $at): void
    {
        $routes = [
            ['afn_hand', 'afn_safe', 40], ['afn_safe', 'afn_azizi', 15], ['afn_hand', 'afn_bank', 15], ['afn_safe', 'afn_bank', 8],
            ['afn_bank', 'afn_petty', 6], ['afn_hand', 'afn_petty', 4], ['afn_azizi', 'afn_hand', 3], ['afn_bank', 'afn_hand', 3],
            ['usd_cash', 'usd_azizi', 12], ['usd_azizi', 'usd_aib', 4], ['usd_cash', 'usd_aib', 6], ['usd_aib', 'usd_cash', 3], ['usd_azizi', 'usd_cash', 4],
        ];
        $weights = [];
        foreach ($routes as $index => [$from, , $weight]) {
            if (isset($this->cash[$from]) && $this->cash[$from]['balance'] > 100) {
                $weights[$index] = $weight;
            }
        }
        if ($weights === []) {
            return;
        }

        [$from, $to] = $routes[(int) $this->random->weighted($weights)];
        $code = $this->cash[$from]['currency'];
        $amount = $this->cash[$from]['balance'] * $this->random->between(0.15, 0.6);
        $amount = $code === 'AFN' ? floor($amount / 1000) * 1000 : floor($amount / 100) * 100;
        if ($amount <= 0) {
            return;
        }

        $number = $this->next('transfer', AccountTransfer::class);
        $transfer = $this->attempt('transfer', function () use ($from, $to, $code, $amount, $day, $at, $number) {
            $this->send($this->actor(AccountTransfer::class), $at, 'account-transfers.store', [
                'number' => (string) $number,
                'date' => $this->formDate($at),
                'from_account_id' => $this->cash[$from]['id'],
                'to_account_id' => $this->cash[$to]['id'],
                'amount' => $amount,
                'currency_id' => $this->currency[$code],
                'rate' => $this->rate($code, $day),
                'remark' => 'انتقال از ' . $this->cash[$from]['name'] . ' به ' . $this->cash[$to]['name'],
            ]);

            return AccountTransfer::query()->withoutGlobalScopes()->where('number', (string) $number)->latest('created_at')->first();
        });

        if (! $transfer) {
            return;
        }
        $this->cash[$from]['balance'] -= $amount;
        $this->cash[$to]['balance'] += $amount;

        if ($this->random->chance(self::VOID_RATE)) {
            if ($this->void('transfer', 'account-transfers.reverse', ['accountTransfer' => $transfer->id], $transfer, $at)) {
                $this->cash[$from]['balance'] += $amount;
                $this->cash[$to]['balance'] -= $amount;
            }
        }
    }

    /**
     * Manual journals: depreciation, accruals, owner's money, prepaid rent,
     * fixed assets, sundry income. Never AR/AP or inventory — those belong to
     * their own documents. AFN or USD only: the journal form requires a rate of
     * at least 1, which rules the rupee out.
     */
    private function journal(int $day, CarbonImmutable $at): void
    {
        $a = $this->accounts;
        $code = $this->random->chance(0.2) ? 'USD' : 'AFN';
        $tillKey = $code === 'USD' ? $this->tillFor('USD', incoming: false) : $this->random->pick(['afn_safe', 'afn_bank', 'afn_hand']);
        $till = $this->cash[$tillKey]['id'];
        $k = $code === 'USD' ? 1 / $this->calendar->usd[$day] : 1.0;
        $amt = fn (float $min, float $max) => $code === 'USD' ? round($this->random->between($min, $max) * $k, 0) : round($this->random->between($min, $max), -2);
        $cashDelta = 0.0;

        $template = (string) $this->random->weighted([
            'depreciation' => 12, 'accrual' => 14, 'accrual_paid' => 10, 'bank_charge' => 10, 'owner_in' => 4, 'owner_out' => 8,
            'prepaid' => 5, 'amortize' => 8, 'asset' => 6, 'other_income' => 8, 'reclass' => 8, 'payroll' => 7,
        ]);

        switch ($template) {
            case 'depreciation':
                $v = $amt(8000, 40000);
                $lines = [[$a['depreciation-expense'], $v, 0, 'استهلاک ماهانه تجهیزات'], [$a['accumulated-depreciation'], 0, $v, 'استهلاک انباشته']];
                $remark = 'ثبت استهلاک ماهانه';
                break;
            case 'accrual':
                $v = $amt(5000, 50000);
                $expense = $this->random->pick(['utilities-expenses', 'electricity-bill-genset-fuel', 'generator-expense', 'misc-supplies-services']);
                $lines = [[$a[$expense], $v, 0, 'مصرف معوق'], [$a['other-accrued-expenses'], 0, $v, 'بدهی مصارف معوق']];
                $remark = 'ثبت مصارف تعهدی';
                break;
            case 'accrual_paid':
                $v = $amt(5000, 40000);
                $lines = [[$a['other-accrued-expenses'], $v, 0, 'تصفیه مصارف معوق'], [$till, 0, $v, 'پرداخت']];
                $remark = 'پرداخت مصارف معوق';
                $cashDelta = -$v;
                break;
            case 'bank_charge':
                $tillKey = $code === 'USD' ? $this->random->pick(['usd_azizi', 'usd_aib']) : $this->random->pick(['afn_bank', 'afn_azizi']);
                $till = $this->cash[$tillKey]['id'];
                $v = $amt(200, 3000);
                $lines = [[$a['bank-service-charges'], $v, 0, 'فیس خدمات بانکی'], [$till, 0, $v, 'کسر بانک']];
                $remark = 'کسر فیس بانکی';
                $cashDelta = -$v;
                break;
            case 'owner_in':
                $v = $amt(100000, 1500000);
                $lines = [[$till, $v, 0, 'آورده نقدی مالک'], [$a[$this->random->pick(['owner-1-contribution', 'owner-2-contribution'])], 0, $v, 'سرمایه‌گذاری مالک']];
                $remark = 'افزایش سرمایه توسط مالک';
                $cashDelta = $v;
                break;
            case 'owner_out':
                $v = $amt(20000, 150000);
                $lines = [[$a[$this->random->pick(['owner-1-draw', 'owner-2-draw'])], $v, 0, 'برداشت شخصی مالک'], [$till, 0, $v, 'برداشت از صندوق']];
                $remark = 'برداشت مالک';
                $cashDelta = -$v;
                break;
            case 'prepaid':
                $v = $amt(100000, 400000);
                $lines = [[$a['advances-prepaid-deposit'], $v, 0, 'پیش‌پرداخت کرایه/بیمه'], [$till, 0, $v, 'پرداخت']];
                $remark = 'پیش‌پرداخت کرایه و بیمه';
                $cashDelta = -$v;
                break;
            case 'amortize':
                $v = $amt(10000, 60000);
                $lines = [[$a[$this->random->pick(['office-rent', 'insurance-expense'])], $v, 0, 'مصرف ماهانه از پیش‌پرداخت'], [$a['advances-prepaid-deposit'], 0, $v, 'کاهش پیش‌پرداخت']];
                $remark = 'مستهلک ساختن پیش‌پرداخت';
                break;
            case 'asset':
                $v = $amt(15000, 250000);
                $asset = $this->random->pick(['furnitures-fixtures', 'machinery-equipments', 'computers-phone-items', 'kitchen-utensils-misc-tools']);
                $lines = [[$a[$asset], $v, 0, 'خرید دارایی ثابت (یخچال، قفسه، کمپیوتر)'], [$till, 0, $v, 'پرداخت']];
                $remark = 'خرید دارایی ثابت';
                $cashDelta = -$v;
                break;
            case 'other_income':
                $v = $amt(2000, 60000);
                $income = $this->random->pick(array_filter([$a['inc_shelf'] ?? null, $a['inc_scrap'] ?? null, $a['other-income']]));
                $lines = [[$till, $v, 0, 'دریافت'], [$income, 0, $v, 'عواید متفرقه']];
                $remark = 'ثبت عواید متفرقه';
                $cashDelta = $v;
                break;
            case 'reclass':
                $v = $amt(1000, 20000);
                $lines = [[$a['misc-supplies-services'], $v, 0, 'اصلاح کتگوری مصرف'], [$a['other-expenses'], 0, $v, 'اصلاح کتگوری مصرف']];
                $remark = 'اصلاح طبقه‌بندی مصارف';
                break;
            default: // payroll accrual with several debit lines
                $base = $amt(60000, 140000);
                $allow = $amt(5000, 20000);
                $over = $amt(2000, 10000);
                $lines = [
                    [$a['permanent-staff-salary'], $base, 0, 'معاش ماهانه'],
                    [$a['allowances-commissions'], $allow, 0, 'امتیازات و کمیشن'],
                    [$a['overtime-expense'], $over, 0, 'اضافه‌کاری'],
                    [$a['payroll-liabilities'], 0, $base + $allow + $over, 'معاش قابل پرداخت'],
                ];
                $remark = 'ثبت معاشات تعهدی ماه';
                break;
        }

        if ($cashDelta < 0 && $this->cash[$tillKey]['balance'] + $cashDelta < 0) {
            return;
        }

        $number = $this->next('journal', JournalEntry::class);
        $journal = $this->attempt('journal', function () use ($lines, $remark, $code, $day, $at, $number) {
            $this->send($this->actor(JournalEntry::class), $at, 'journal-entries.store', [
                'number' => $number,
                'date' => $this->formDate($at),
                'currency_id' => $this->currency[$code],
                'rate' => $code === 'USD' ? $this->rate('USD', $day) : 1,
                'remarks' => $remark,
                'lines' => array_map(fn ($l) => ['account_id' => $l[0], 'debit' => $l[1], 'credit' => $l[2], 'remark' => $l[3]], $lines),
            ]);

            return JournalEntry::query()->withoutGlobalScopes()->where('number', $number)->latest('created_at')->first();
        });

        if (! $journal) {
            return;
        }
        $this->cash[$tillKey]['balance'] += $cashDelta;

        if ($this->random->chance(self::VOID_RATE)) {
            if ($this->void('journal', 'journal-entries.reverse', ['journalEntry' => $journal->id], $journal, $at)) {
                $this->cash[$tillKey]['balance'] -= $cashDelta;
            }
        }
    }

    private function adjustment(int $day, CarbonImmutable $at, array $seasons): void
    {
        $reason = (string) $this->random->weighted([
            'damage' => 20, 'expiry' => 20, 'wastage' => 15, 'count_down' => 15, 'theft' => 4, 'internal_use' => 5,
            'count_up' => 10, 'found' => 5, 'surplus' => 6,
        ]);
        $isIn = in_array($reason, ['count_up', 'found', 'surplus'], true);
        $lines = [];
        $used = [];

        for ($i = 0, $count = $this->random->count(2, 1, 6); $i < $count * 4 && count($lines) < $count; $i++) {
            $index = $this->drawItem($seasons);
            if (isset($used[$index])) {
                continue;
            }
            $spec = $this->items[$index];
            $qty = $spec['unit'] === 'ea' ? $this->random->int(1, 6) : $this->random->pick([0.5, 1, 2, 3]);
            if (! $isIn && $spec['stock'] - $qty < $spec['demand'] * 3) {
                continue;
            }
            $used[$index] = true;
            $lines[] = ['index' => $index, 'qty' => $qty, 'payload' => [
                'item_id' => $spec['id'],
                'unit_measure_id' => $this->units[$spec['unit']],
                'quantity' => $qty,
                'unit_cost' => $isIn ? $spec['purchase_price'] : null,
            ]];
        }
        if ($lines === []) {
            return;
        }

        $notes = [
            'damage' => 'جنس در هنگام تخلیه آسیب دیده', 'expiry' => 'تاریخ انقضا گذشته', 'wastage' => 'ضایعات قفسه',
            'count_down' => 'کمبود در شمارش گدام', 'theft' => 'مفقود از قفسه', 'internal_use' => 'استفاده داخلی دکان',
            'count_up' => 'اضافه در شمارش گدام', 'found' => 'پیدا شده در گدام', 'surplus' => 'اضافه تحویلی',
        ][$reason];

        $ok = $this->attempt('adjustment', fn () => $this->send($this->actor(StockAdjustment::class), $at, 'stock-adjustments.store', [
            'date' => $this->formDate($at),
            'reason' => $reason,
            'warehouse_id' => $this->warehouseId,
            'notes' => $notes,
            'items' => array_column($lines, 'payload'),
        ]));

        if ($ok) {
            foreach ($lines as $line) {
                $this->items[$line['index']]['stock'] += $isIn ? $line['qty'] : -$line['qty'];
            }
        }
    }

    /** Freight, customs and handling on a purchase from the last ten days. */
    private function landedCost(int $day, CarbonImmutable $at): void
    {
        $candidates = array_keys(array_filter(
            $this->recentPurchases,
            fn ($p) => ! $p['landed'] && $p['day'] >= $day - 10 && $p['day'] <= $day && ! isset($this->untouchable[$p['id']])
        ));
        if ($candidates === []) {
            return;
        }

        // Imports carry the freight and customs.
        usort($candidates, fn ($x, $y) => ($this->recentPurchases[$y]['code'] !== 'AFN') <=> ($this->recentPurchases[$x]['code'] !== 'AFN'));
        $key = $candidates[$this->random->int(0, min(4, count($candidates) - 1))];
        $purchase = $this->recentPurchases[$key];

        $total = max(500, round($purchase['total_afn'] * $this->random->between(0.01, 0.04), -1));
        $till = $this->tillFor('AFN', incoming: false);
        if ($this->cash[$till]['balance'] < $total) {
            return;
        }

        $split = [
            'Freight & Transport' => $this->random->between(0.4, 0.7),
            'Customs & Government' => $purchase['code'] === 'AFN' ? 0 : $this->random->between(0.2, 0.5),
            'Handling & Port' => $this->random->between(0.05, 0.15),
        ];
        $sum = array_sum($split);
        $allocations = [];
        $left = $total;
        $names = array_keys(array_filter($split));
        foreach ($names as $position => $name) {
            if (! isset($this->landedCategories[$name])) {
                continue;
            }
            $amount = $position === count($names) - 1 ? $left : round($total * $split[$name] / $sum, 2);
            $left = round($left - $amount, 2);
            $allocations[] = ['landed_cost_category_id' => $this->landedCategories[$name], 'amount' => $amount];
        }

        $ok = $this->attempt('landed_cost', function () use ($purchase, $total, $till, $allocations, $at) {
            $user = $this->actor(LandedCost::class);
            $response = $this->send($user, $at, '/api/landed-costs', [
                // This controller stores the date as given, so it takes Gregorian.
                'date' => $at->toDateString(),
                'purchase_id' => $purchase['id'],
                'purchase_ids' => [$purchase['id']],
                'bank_account_id' => $this->cash[$till]['id'],
                'currency_id' => $this->currency['AFN'],
                'rate' => 1,
                'total_cost' => $total,
                'allocation_method' => 'by_value',
                'notes' => 'کرایه، گمرک و باربری خرید',
                'category_allocations' => $allocations,
                // The form loads the purchase and sends its lines as the rows to
                // allocate over; the controller does not derive them itself.
                'items' => array_values(array_filter(array_map(function ($line) use ($purchase) {
                    $purchaseItem = collect($purchase['items'])->firstWhere('item_id', $line['payload']['item_id']);

                    return $purchaseItem ? [
                        'purchase_item_id' => $purchaseItem->id,
                        'purchase_id' => $purchase['id'],
                        'item_id' => $line['payload']['item_id'],
                        'quantity' => $line['payload']['quantity'],
                        'unit_cost' => $line['payload']['unit_price'],
                        'warehouse_id' => $this->warehouseId,
                    ] : null;
                }, $purchase['lines']))),
            ], json: true);

            $id = $response->json('data.id');
            if (! $id) {
                throw new RuntimeException('Landed cost was not created.');
            }
            $this->send($user, $at->addMinutes(15), "/api/landed-costs/{$id}/post", [], json: true);

            return true;
        });

        if ($ok) {
            $this->recentPurchases[$key]['landed'] = true;
            $this->untouchable[$purchase['id']] = true;
            $this->cash[$till]['balance'] -= $total;
        }
    }

    public function branchId(): string
    {
        return $this->branchId;
    }
}
