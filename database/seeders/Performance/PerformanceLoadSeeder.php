<?php

namespace Database\Seeders\Performance;

use App\Models\Account\Account;
use App\Models\Account\AccountType;
use App\Models\Administration\Currency;
use App\Models\Administration\Quantity;
use App\Models\Administration\Size;
use App\Models\Administration\UnitMeasure;
use Carbon\Carbon;
use Database\Seeders\Account\AccountSeeder;
use Database\Seeders\Account\AccountTypeSeeder;
use Database\Seeders\Administration\BranchSeeder;
use Database\Seeders\Administration\CurrencySeeder;
use Database\Seeders\Administration\StoreSeeder;
use Database\Seeders\Administration\UnitMeasureSeeder;
use Database\Seeders\Administration\UserSeeder;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PerformanceLoadSeeder extends Seeder
{
    private const CUSTOMER_COUNT = 1000;
    private const SUPPLIER_COUNT = 500;
    private const BRAND_COUNT = 100;
    private const ITEM_COUNT = 1000;
    private const RECEIPT_COUNT = 10000;
    private const PAYMENT_COUNT = 12000;

    private const OPENING_QTY = 1000;

    private const STORE_MAIN_NAME = 'main store';
    private const STORE_2_NAME = 'store 2';
    private const SECOND_BRANCH_NAME = 'second branch';

    public function run(): void
    {
        DB::disableQueryLog();

        $this->ensurePrerequisites();
        $mainBranchId = $this->getMainBranchId();
        $adminId = $this->getAdminUserId();
        $now = now();

        $branchIds = [
            $mainBranchId,
            $this->ensureBranch(self::SECOND_BRANCH_NAME, false, $mainBranchId, $adminId, $now),
        ];

        foreach ($branchIds as $branchId) {
            if (DB::table('ledgers')->where('branch_id', $branchId)->where('code', 'CUST-000001')->exists()) {
                $this->command?->warn("Branch {$branchId} already seeded (CUST-000001 found). Skipping branch.");
                continue;
            }

            $this->command?->info("Preparing branch {$branchId}...");

            $storeMainId = $this->ensureStore(self::STORE_MAIN_NAME, true, $branchId, $adminId, $now);
            $store2Id = $this->ensureStore(self::STORE_2_NAME, false, $branchId, $adminId, $now);
            $storeCount = 2;

            $currencyId = DB::table('currencies')
                ->where('branch_id', $branchId)
                ->where('is_base_currency', true)
                ->value('id')
                ?? DB::table('currencies')->where('branch_id', $branchId)->value('id');

            $assetAccountId = DB::table('accounts')->where('branch_id', $branchId)->where('slug', 'inventory-stock')->value('id')
                ?? DB::table('accounts')->where('branch_id', $branchId)->value('id');
            $incomeAccountId = DB::table('accounts')->where('branch_id', $branchId)->where('slug', 'product-income')->value('id')
                ?? DB::table('accounts')->where('branch_id', $branchId)->value('id');
            $costAccountId = DB::table('accounts')->where('branch_id', $branchId)->where('slug', 'cost-of-goods-sold')->value('id')
                ?? DB::table('accounts')->where('branch_id', $branchId)->where('slug', 'direct-material-cost')->value('id')
                ?? DB::table('accounts')->where('branch_id', $branchId)->value('id');

            $unitMeasureIds = $this->resolveUnitMeasurePool($branchId, $adminId, $now);

            if (!$currencyId || !$assetAccountId || !$incomeAccountId || !$costAccountId || count($unitMeasureIds) === 0) {
                throw new \RuntimeException("Missing prerequisites for branch {$branchId}: currency/accounts/unit measures not found.");
            }

            $this->command?->info('Creating categories...');
            $categoryIds = $this->seedCategories($branchId, $adminId, $now);

            $this->command?->info('Creating brands...');
            $brandIds = $this->seedBrands(self::BRAND_COUNT, $branchId, $adminId, $now);

            $this->command?->info('Creating ledgers (customers/suppliers)...');
            [$customerIds, $supplierIds] = $this->seedLedgers(
                self::CUSTOMER_COUNT * $storeCount,
                self::SUPPLIER_COUNT * $storeCount,
                $branchId,
                $adminId,
                $currencyId,
                $now
            );

            $this->command?->info('Creating items...');
            $itemIds = $this->seedItems(
                self::ITEM_COUNT,
                $categoryIds,
                $brandIds,
                $branchId,
                $adminId,
                $unitMeasureIds,
                $assetAccountId,
                $incomeAccountId,
                $costAccountId,
                $now
            );

            $this->command?->info('Creating stock openings in two stores...');
            $this->seedOpeningStocks(
                $itemIds,
                [$storeMainId, $store2Id],
                $branchId,
                $adminId,
                $unitMeasureIds,
                $now
            );

            // Disabled per request for current load-test scenario.
            // $this->command?->info('Creating receipts/payments...');
            // $this->seedReceipts(self::RECEIPT_COUNT, $customerIds, $branchId, $adminId, $now);
            // $this->seedPayments(self::PAYMENT_COUNT, $supplierIds, $branchId, $adminId, $now);
        }

        $this->command?->info('PerformanceLoadSeeder finished.');
    }

    private function ensurePrerequisites(): void
    {
        // Branch + admin user
        if (!DB::table('branches')->exists()) {
            $this->call(BranchSeeder::class);
        }
        // Always run UserSeeder to ensure admin has branch_id
        $this->call(UserSeeder::class);

        // Core masters needed by items + stock
        if (!DB::table('currencies')->exists()) {
            $this->call(CurrencySeeder::class);
        }
        if (!DB::table('unit_measures')->exists()) {
            $this->call(UnitMeasureSeeder::class);
        }
        if (!DB::table('stores')->exists()) {
            $this->call(StoreSeeder::class);
        }

        // Accounts required by items (non-null FKs)
        if (!DB::table('account_types')->exists()) {
            $this->call(AccountTypeSeeder::class);
        }
        if (!DB::table('accounts')->exists()) {
            $this->call(AccountSeeder::class);
        }
    }

    private function ensureBranch(string $name, bool $isMain, string $parentId, string $createdBy, Carbon $now): string
    {
        $existingId = DB::table('branches')->where('name', $name)->value('id');
        if ($existingId) {
            $this->ensureBranchDefaultData((string) $existingId, $createdBy, $now);
            return (string) $existingId;
        }

        $id = (string) Str::ulid();
        DB::table('branches')->insert([
            'id' => $id,
            'name' => $name,
            'remark' => $name,
            'location' => $name,
            'is_main' => $isMain,
            'sub_domain' => 'second',
            'parent_id' => $parentId,
            'created_by' => $createdBy,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $this->ensureBranchDefaultData($id, $createdBy, $now);

        return $id;
    }

    private function ensureBranchDefaultData(string $branchId, string $adminId, Carbon $now): void
    {
        if (!DB::table('account_types')->where('branch_id', $branchId)->exists()) {
            $accountTypeRows = [];
            foreach (AccountType::defaultAccountTypes() as $accountType) {
                $accountTypeRows[] = [
                    'id' => (string) Str::ulid(),
                    'name' => $accountType['name'],
                    'nature' => $accountType['nature'] ?? null,
                    'slug' => $accountType['slug'],
                    'remark' => $accountType['remark'] ?? null,
                    'is_main' => $accountType['is_main'] ?? true,
                    'branch_id' => $branchId,
                    'created_by' => $adminId,
                    'updated_by' => null,
                    'deleted_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ];
            }
            $this->chunkInsert('account_types', $accountTypeRows, 200);
        }

        if (!DB::table('accounts')->where('branch_id', $branchId)->exists()) {
            $accountRows = [];
            foreach (Account::defaultAccounts() as $account) {
                $accountTypeId = DB::table('account_types')
                    ->where('branch_id', $branchId)
                    ->where('slug', $account['account_type_slug'])
                    ->value('id');

                if (!$accountTypeId) {
                    continue;
                }

                $accountRows[] = [
                    'id' => (string) Str::ulid(),
                    'name' => $account['name'],
                    'number' => $account['number'],
                    'account_type_id' => $accountTypeId,
                    'parent_id' => null,
                    'branch_id' => $branchId,
                    'slug' => $account['slug'] ?? null,
                    'remark' => $account['remark'] ?? null,
                    'is_active' => true,
                    'is_main' => $account['is_main'] ?? true,
                    'created_by' => $adminId,
                    'updated_by' => null,
                    'deleted_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ];
            }
            $this->chunkInsert('accounts', $accountRows, 300);
        }

        if (!DB::table('sizes')->where('branch_id', $branchId)->exists()) {
            $sizeRows = [];
            foreach (Size::defaultSizes() as $size) {
                $sizeRows[] = [
                    'id' => (string) Str::ulid(),
                    'name' => $size['name'],
                    'code' => $size['code'],
                    'is_active' => true,
                    'is_main' => true,
                    'branch_id' => $branchId,
                    'created_by' => $adminId,
                    'updated_by' => null,
                    'deleted_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ];
            }
            $this->chunkInsert('sizes', $sizeRows, 200);
        }

        if (!DB::table('currencies')->where('branch_id', $branchId)->exists()) {
            $currencyRows = [];
            foreach (Currency::defaultCurrencies() as $currency) {
                $currencyRows[] = [
                    'id' => (string) Str::ulid(),
                    'name' => $currency['name'],
                    'code' => $currency['code'],
                    'symbol' => $currency['symbol'],
                    'format' => $currency['format'],
                    'exchange_rate' => $currency['exchange_rate'],
                    'is_active' => $currency['is_active'],
                    'is_base_currency' => $currency['is_base_currency'] ?? false,
                    'flag' => $currency['flag'] ?? null,
                    'branch_id' => $branchId,
                    'created_by' => $adminId,
                    'updated_by' => null,
                    'deleted_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ];
            }
            $this->chunkInsert('currencies', $currencyRows, 50);
        }

        if (!DB::table('quantities')->where('branch_id', $branchId)->exists()) {
            $quantityRows = [];
            foreach (Quantity::defaultQuantity() as $quantity) {
                $quantityRows[] = [
                    'id' => (string) Str::ulid(),
                    'quantity' => $quantity['quantity'],
                    'unit' => $quantity['unit'],
                    'symbol' => $quantity['symbol'],
                    'slug' => $quantity['slug'],
                    'is_main' => $quantity['is_main'] ?? true,
                    'is_active' => true,
                    'branch_id' => $branchId,
                    'created_by' => $adminId,
                    'updated_by' => null,
                    'deleted_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ];
            }
            $this->chunkInsert('quantities', $quantityRows, 50);
        }

        if (!DB::table('unit_measures')->where('branch_id', $branchId)->exists()) {
            $rows = [];
            foreach (UnitMeasure::defaultUnitMeasures() as $unitMeasure) {
                $quantityId = DB::table('quantities')
                    ->where('branch_id', $branchId)
                    ->where('slug', $unitMeasure['quantity_slug'])
                    ->value('id');

                if (!$quantityId) {
                    continue;
                }

                $rows[] = [
                    'id' => (string) Str::ulid(),
                    'name' => $unitMeasure['name'],
                    'unit' => (string) $unitMeasure['unit'],
                    'symbol' => $unitMeasure['symbol'],
                    'value' => $unitMeasure['unit'],
                    'quantity_id' => $quantityId,
                    'is_main' => $unitMeasure['is_main'] ?? true,
                    'is_active' => true,
                    'branch_id' => $branchId,
                    'created_by' => $adminId,
                    'updated_by' => null,
                    'deleted_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ];
            }
            $this->chunkInsert('unit_measures', $rows, 100);
        }

        if (!DB::table('stores')->where('branch_id', $branchId)->where('is_main', true)->exists()) {
            $this->ensureStore(self::STORE_MAIN_NAME, true, $branchId, $adminId, $now);
        }
    }

    private function getMainBranchId(): string
    {
        $branchId = DB::table('branches')->where('is_main', true)->value('id')
            ?? DB::table('branches')->value('id');

        if (!$branchId) {
            throw new \RuntimeException('No branches found.');
        }

        return (string) $branchId;
    }

    private function getAdminUserId(): string
    {
        $adminId = User::withoutGlobalScopes()->where('email', 'admin@nextbook.com')->value('id');
           

        if (!$adminId) {
            throw new \RuntimeException('No users found.');
        }

        return (string) $adminId;
    }

    private function ensureStore(string $name, bool $isMain, string $branchId, string $adminId, Carbon $now): string
    {
        $existingId = DB::table('stores')
            ->where('branch_id', $branchId)
            ->where('name', $name)
            ->value('id');

        if ($existingId) {
            return (string) $existingId;
        }

        $id = (string) Str::ulid();

        DB::table('stores')->insert([
            'id' => $id,
            'name' => $name,
            'address' => $name,
            'is_main' => $isMain,
            'is_active' => true,
            'branch_id' => $branchId,
            'created_by' => $adminId,
            'updated_by' => null,
            'deleted_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        return $id;
    }

    /**
     * @return array<int, string> category IDs
     */
    private function seedCategories(string $branchId, string $adminId, Carbon $now): array
    {
        $rows = [];
        $ids = [];
        $categories = [
            'لبنیات',
            'نوشیدنی',
            'مواد شوینده',
            'حبوبات و غلات',
            'تنقلات',
        ];

        foreach ($categories as $name) {
            $id = (string) Str::ulid();
            $ids[] = $id;

            $rows[] = [
                'id' => $id,
                'name' => $name,
                'remark' => 'دسته بندی واقعی سوپرمارکت',
                'parent_id' => null,
                'is_active' => true,
                'branch_id' => $branchId,
                'created_by' => $adminId,
                'updated_by' => null,
                'deleted_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ];
        }

        $this->chunkInsert('categories', $rows, 500);

        return $ids;
    }

    /**
     * @return array<int, string> brand IDs
     */
    private function seedBrands(int $count, string $branchId, string $adminId, Carbon $now): array
    {
        $brandNames = [
            'گلستان', 'بهروز', 'مهرام', 'یک و یک', 'دلپذیر', 'کاله', 'پگاه', 'میهن', 'دامداران', 'هراز',
            'رامک', 'روزانه', 'نستله', 'چی توز', 'مزمز', 'شیرین عسل', 'درنا', 'آناتا', 'آیدین', 'شونیز',
            'فرمند', 'تبرک', 'خوشبخت', 'چین چین', 'برتر', 'سن ایچ', 'رانی', 'ایستک', 'زمزم', 'کوکاکولا',
            'پپسی', 'کاسل', 'سحرخیز', 'ترخینه', 'گلها', 'مصطفوی', 'تی تک', 'آپادا', 'شیررضا', 'پاک',
            'نان آوران', 'سه نان', 'دلوسه', 'هاشمی', 'برکت', 'عالیس', 'سولیکو', 'بیژن', 'پرسیل', 'سافتلن',
            'اکتیو', 'لطیفه', 'فامیلا', 'تاژ', 'گلنار', 'سپید', 'هوم کر', 'داو', 'کلینیک', 'نیوا',
            'صحت', 'داروگر', 'گلنوش', 'ماهگل', 'کمبو', 'پاپیا', 'تافته', 'چاپار', 'سپهر', 'پارس',
            'فیروز', 'سی گل', 'مای', 'شکوفه', 'بهنوش', 'عرقیات زهرا', 'جهان', 'عقاب', 'فرش',
            'دشت مرغاب', 'بیژن طلایی', 'زر ماکارون', 'تک ماکارون', 'مانا', 'جهان آرا', 'روژین',
            'هاینز', 'کنور', 'مجید', 'برگ سبز', 'طبیعت', 'نوین زعفران', 'قند پارس', 'گلپر',
            'خزر', 'پمینا', 'شیرین نوش', 'خوش طعم', 'کاپیتان', 'بی تا', 'پتی بور'
        ];

        if (count($brandNames) < $count) {
            throw new \RuntimeException('Insufficient brand names configured for requested brand count.');
        }

        $rows = [];
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $id = (string) Str::ulid();
            $ids[] = $id;
            $name = $brandNames[$i];

            $rows[] = [
                'id' => $id,
                'name' => $name,
                'legal_name' => "شرکت {$name}",
                'registration_number' => sprintf('%06d', $i + 1),
                'logo' => null,
                'email' => null,
                'phone' => null,
                'website' => null,
                'industry' => 'مواد غذایی',
                'type' => 'برند',
                'address' => null,
                'city' => null,
                'country' => 'افغانستان',
                'branch_id' => $branchId,
                'created_by' => $adminId,
                'updated_by' => null,
                'deleted_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ];
        }

        $this->chunkInsert('brands', $rows, 500);

        return $ids;
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, string>} customer IDs, supplier IDs
     */
    private function seedLedgers(
        int $customerCount,
        int $supplierCount,
        string $branchId,
        string $adminId,
        ?string $currencyId,
        Carbon $now
    ): array {
        $customers = [];
        $suppliers = [];
        $customerIds = [];
        $supplierIds = [];
        $customerFirstNames = [
            'علی', 'احمد', 'محمد', 'حسین', 'رضا', 'مهدی', 'امین', 'وحید', 'یاسر', 'نادر',
            'کامران', 'فرهاد', 'سعید', 'جواد', 'هادی', 'نعمت', 'صابر', 'شریف', 'فرید', 'حامد',
            'حمید', 'کریم', 'ناصر', 'داوود', 'تیمور', 'زاهد', 'فاروق', 'سلمان', 'بشیر', 'جمیل',
            'قدیر', 'نوید', 'پرویز', 'مجید', 'شهاب', 'یوسف', 'قدوس', 'عمر', 'رفیع', 'قاسم'
        ];
        $customerLastNames = [
            'احمدی', 'محمدی', 'حسینی', 'رحیمی', 'کریمی', 'جعفری', 'صادقی', 'نوری', 'هاشمی', 'عباسی',
            'کاظمی', 'خالقی', 'امینی', 'اکبری', 'قربانی', 'میرزایی', 'صفری', 'شریفی', 'فرهمند', 'یوسفی',
            'شاهی', 'خانزاده', 'وکیلی', 'قادری', 'حمیدی'
        ];
        $supplierPrefix = [
            'شرکت بازرگانی', 'شرکت پخش', 'موسسه تامین', 'فروشگاه عمده', 'گروه صنعتی',
            'شرکت تولیدی', 'مرکز توزیع', 'تجارت خانه', 'شرکت تعاونی', 'بنگاه مواد غذایی',
            'شرکت خدماتی', 'مجموعه اقتصادی', 'بخش فروش', 'دفتر تامینات', 'موسسه پخش سراسری',
            'شرکت صادراتی', 'شرکت وارداتی', 'کارخانه مواد خوراکی', 'مرکز خرید', 'شبکه توزیع',
            'شرکت راهبردی', 'شرکت خصوصی', 'شرکت توسعه', 'شرکت پیشگام', 'شرکت سپهر'
        ];
        $supplierNames = [
            'بهار', 'سبزینه', 'باران', 'سپیدار', 'نوین', 'آرمان', 'پارس', 'خاور', 'سروش', 'پرنیان',
            'مروارید', 'زرین', 'نیلوفر', 'یاقوت', 'مهر', 'آفتاب', 'دریا', 'کوهسار', 'یاس', 'نیکان'
        ];
        $customerFirstCount = count($customerFirstNames);
        $customerLastCount = count($customerLastNames);
        $supplierPrefixCount = count($supplierPrefix);
        $supplierNameCount = count($supplierNames);

        for ($i = 1; $i <= $customerCount; $i++) {
            $id = (string) Str::ulid();
            $customerIds[] = $id;
            $firstName = $customerFirstNames[($i - 1) % $customerFirstCount];
            $lastName = $customerLastNames[intdiv($i - 1, $customerFirstCount) % $customerLastCount];

            $customers[] = [
                'id' => $id,
                'name' => "{$firstName} {$lastName}",
                'code' => sprintf('CUST-%06d', $i),
                'address' => null,
                'contact_person' => null,
                'phone_no' => null,
                'email' => null,
                'currency_id' => $currencyId,
                'branch_id' => $branchId,
                'is_main' => false,
                'type' => 'customer',
                'is_active' => true,
                'created_by' => $adminId,
                'updated_by' => null,
                'deleted_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ];
        }

        for ($i = 1; $i <= $supplierCount; $i++) {
            $id = (string) Str::ulid();
            $supplierIds[] = $id;
            $prefix = $supplierPrefix[($i - 1) % $supplierPrefixCount];
            $name = $supplierNames[intdiv($i - 1, $supplierPrefixCount) % $supplierNameCount];

            $suppliers[] = [
                'id' => $id,
                'name' => "{$prefix} {$name}",
                'code' => sprintf('SUP-%06d', $i),
                'address' => null,
                'contact_person' => null,
                'phone_no' => null,
                'email' => null,
                'currency_id' => $currencyId,
                'branch_id' => $branchId,
                'is_main' => false,
                'type' => 'supplier',
                'is_active' => true,
                'created_by' => $adminId,
                'updated_by' => null,
                'deleted_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ];
        }

        $this->chunkInsert('ledgers', $customers, 1000);
        $this->chunkInsert('ledgers', $suppliers, 1000);

        return [$customerIds, $supplierIds];
    }

    /**
     * @param array<int, string> $categoryIds
     * @param array<int, string> $brandIds
     * @param array<int, string> $unitMeasureIds
     * @return array<int, string> item IDs
     */
    private function seedItems(
        int $count,
        array $categoryIds,
        array $brandIds,
        string $branchId,
        string $adminId,
        array $unitMeasureIds,
        string $assetAccountId,
        string $incomeAccountId,
        string $costAccountId,
        Carbon $now
    ): array {
        $rows = [];
        $ids = [];
        $baseItemNames = [
            'برنج', 'روغن مایع', 'روغن جامد', 'قند', 'شکر', 'آرد گندم', 'آرد جو', 'لپه', 'عدس', 'لوبیا قرمز',
            'لوبیا چیتی', 'نخود', 'ماش', 'چای سیاه', 'چای سبز', 'قهوه', 'نسکافه', 'نمک', 'فلفل سیاه', 'زردچوبه',
            'دارچین', 'هل', 'زعفران', 'رب گوجه', 'کنسرو نخود', 'کنسرو لوبیا', 'تن ماهی', 'ماکارونی', 'اسپاگتی', 'رشته آش',
            'شیر', 'ماست', 'دوغ', 'پنیر', 'کره', 'خامه', 'بیسکویت', 'کیک', 'ویفر', 'شکلات',
            'آب معدنی', 'نوشابه', 'دلستر', 'آبمیوه', 'سرکه سفید', 'سرکه سیب', 'آبلیمو', 'گلاب', 'عرق نعنا', 'عرق بیدمشک',
            'پودر لباسشویی', 'مایع ظرفشویی', 'مایع دستشویی', 'شامپو', 'صابون', 'خمیردندان', 'مسواک', 'دستمال کاغذی', 'پوشک', 'اسکاچ',
            'کیسه زباله', 'فویل آلومینیوم', 'سلفون', 'پودر ژله', 'نشاسته', 'پودر کاکائو', 'وانیل', 'جو دوسر', 'غلات صبحانه', 'عسل',
            'مربا توت فرنگی', 'مربا زردآلو', 'حلوا شکری', 'ارده', 'کشمش', 'خرما', 'بادام', 'پسته', 'گردو', 'تخمه آفتابگردان',
            'چیپس', 'پفک', 'ذرت بوداده', 'لواشک', 'آدامس', 'بستنی', 'یخ در بهشت', 'سس مایونیز', 'سس گوجه', 'سس فلفلی',
            'خیارشور', 'زیتون', 'زیتون پرورده', 'آبغوره', 'ادویه کاری', 'پودر سیر', 'پودر پیاز', 'سویا', 'نان تست', 'نان سوخاری'
        ];
        $itemQualifiers = ['ویژه', 'ممتاز', 'خانوادگی', 'اقتصادی', 'اعلا', 'تازه', 'طبیعی', 'خالص', 'کلاسیک', 'محلی'];
        $itemPackings = ['بسته 250 گرمی', 'بسته 500 گرمی', 'بسته 1 کیلویی', 'بسته 2 کیلویی', 'عددی'];

        $categoryCount = count($categoryIds);
        $brandCount = count($brandIds);
        $unitMeasureCount = count($unitMeasureIds);
        $baseNameCount = count($baseItemNames);
        $qualifierCount = count($itemQualifiers);
        $packingCount = count($itemPackings);

        if ($categoryCount === 0) {
            throw new \RuntimeException('No categories available for items.');
        }
        if ($brandCount === 0) {
            throw new \RuntimeException('No brands available for items.');
        }
        if ($unitMeasureCount === 0) {
            throw new \RuntimeException('No unit measures available for items.');
        }

        for ($i = 1; $i <= $count; $i++) {
            $id = (string) Str::ulid();
            $ids[] = $id;

            $baseName = $baseItemNames[($i - 1) % $baseNameCount];
            $qualifier = $itemQualifiers[intdiv($i - 1, $baseNameCount) % $qualifierCount];
            $packing = $itemPackings[intdiv($i - 1, ($baseNameCount * $qualifierCount)) % $packingCount];

            $categoryId = $categoryIds[random_int(0, $categoryCount - 1)];
            $brandId = $brandIds[random_int(0, $brandCount - 1)];
            $unitMeasureId = $unitMeasureIds[random_int(0, $unitMeasureCount - 1)];
            $purchasePrice = random_int(10, 500);
            $cost = $purchasePrice + random_int(0, 50);
            $salePrice = $cost + random_int(10, 200);

            $rows[] = [
                'id' => $id,
                'name' => "{$baseName} {$qualifier}",
                'code' => sprintf('ITEM-%06d', $i),
                'item_type' => 'inventory_materials',
                'sku' => sprintf('SKU-%06d', $i),
                'generic_name' => null,
                'packing' => null,
                'barcode' => null,
                'unit_measure_id' => $unitMeasureId,
                'brand_id' => $brandId,
                'category_id' => $categoryId,
                'cost_account_id' => $costAccountId,
                'income_account_id' => $incomeAccountId,
                'asset_account_id' => $assetAccountId,
                'minimum_stock' => 0,
                'maximum_stock' => 0,
                'colors' => json_encode([]),
                'size_id' => null,
                'purchase_price' => $purchasePrice,
                'cost' => $cost,
                'sale_price' => $salePrice,
                'rate_a' => null,
                'rate_b' => null,
                'rate_c' => null,
                'rack_no' => null,
                'fast_search' => sprintf('ITEM-%06d', $i),
                'branch_id' => $branchId,
                'created_by' => $adminId,
                'updated_by' => null,
                'deleted_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ];
        }

        $this->chunkInsert('items', $rows, 1000);

        return $ids;
    }

    /**
     * @param array<int, string> $itemIds
     * @param array<int, string> $storeIds
     * @param array<int, string> $unitMeasureIds
     */
    private function seedOpeningStocks(
        array $itemIds,
        array $storeIds,
        string $branchId,
        string $adminId,
        array $unitMeasureIds,
        Carbon $now
    ): void {
        $stocksChunk = [];
        $openingsChunk = [];
        $chunkSize = 1000;
        $unitMeasureCount = count($unitMeasureIds);
        if ($unitMeasureCount === 0) {
            throw new \RuntimeException('No unit measures available for stocks.');
        }

        foreach ($itemIds as $itemId) {
            foreach ($storeIds as $storeId) {
                $stockId = (string) Str::ulid();
                $unitMeasureId = $unitMeasureIds[random_int(0, $unitMeasureCount - 1)];
                $unitPrice = random_int(5, 500);

                $stocksChunk[] = [
                    'id' => $stockId,
                    'item_id' => $itemId,
                    'store_id' => $storeId,
                    'unit_measure_id' => $unitMeasureId,
                    'quantity' => self::OPENING_QTY,
                    'unit_price' => $unitPrice,
                    'free' => null,
                    'size_id' => null,
                    'batch' => null,
                    'discount' => null,
                    'tax' => null,
                    'date' => $now->toDateString(),
                    'expire_date' => null,
                    'branch_id' => $branchId,
                    'created_by' => $adminId,
                    'updated_by' => null,
                    'deleted_by' => null,
                    'source_type' => null,
                    'source_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ];

                $openingsChunk[] = [
                    'id' => (string) Str::ulid(),
                    'item_id' => $itemId,
                    'stock_id' => $stockId,
                    'branch_id' => $branchId,
                    'created_by' => $adminId,
                    'updated_by' => null,
                    'deleted_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ];

                if (count($stocksChunk) >= $chunkSize) {
                    DB::table('stocks')->insert($stocksChunk);
                    DB::table('stock_openings')->insert($openingsChunk);
                    $stocksChunk = [];
                    $openingsChunk = [];
                }
            }
        }

        if (!empty($stocksChunk)) {
            DB::table('stocks')->insert($stocksChunk);
            DB::table('stock_openings')->insert($openingsChunk);
        }
    }

    /**
     * @return array<int, string>
     */
    private function resolveUnitMeasurePool(string $branchId, string $adminId, Carbon $now): array
    {
        $pool = [];

        $kgId = $this->firstUnitMeasureId($branchId, ['kg']);
        if ($kgId) {
            $pool[] = $kgId;
        }

        $grId = $this->firstUnitMeasureId($branchId, ['gr', 'g']);
        if ($grId) {
            $pool[] = $grId;
        }

        $meterId = $this->firstUnitMeasureId($branchId, ['m']);
        if ($meterId) {
            $pool[] = $meterId;
        }

        $pieceId = $this->firstUnitMeasureId($branchId, ['ea', 'pc', 'piece']);
        if ($pieceId) {
            $pool[] = $pieceId;
        }

        $boxId = $this->firstUnitMeasureId($branchId, ['box', 'bx']);
        if (!$boxId) {
            $boxId = $this->createBoxUnitMeasure($branchId, $adminId, $now);
        }
        if ($boxId) {
            $pool[] = $boxId;
        }

        $pool = array_values(array_unique(array_filter($pool)));
        if (count($pool) > 0) {
            return $pool;
        }

        $fallback = DB::table('unit_measures')->where('branch_id', $branchId)->pluck('id')->all();
        return array_values(array_unique(array_map(static fn ($v) => (string) $v, $fallback)));
    }

    private function firstUnitMeasureId(string $branchId, array $symbols): ?string
    {
        $id = DB::table('unit_measures')
            ->where('branch_id', $branchId)
            ->whereIn('symbol', $symbols)
            ->value('id');

        return $id ? (string) $id : null;
    }

    private function createBoxUnitMeasure(string $branchId, string $adminId, Carbon $now): ?string
    {
        $quantityId = DB::table('quantities')
            ->where('branch_id', $branchId)
            ->where('slug', 'count')
            ->value('id');

        if (!$quantityId) {
            return null;
        }

        $id = (string) Str::ulid();
        DB::table('unit_measures')->insert([
            'id' => $id,
            'name' => 'بکس',
            'unit' => '1',
            'symbol' => 'box',
            'value' => 1,
            'quantity_id' => $quantityId,
            'is_main' => false,
            'is_active' => true,
            'branch_id' => $branchId,
            'created_by' => $adminId,
            'updated_by' => null,
            'deleted_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        return $id;
    }

    /**
     * @param array<int, string> $customerIds
     */
    private function seedReceipts(int $count, array $customerIds, string $branchId, string $adminId, Carbon $now): void
    {
        $rows = [];
        $chunkSize = 1000;
        $customerCount = count($customerIds);

        if ($customerCount === 0) {
            throw new \RuntimeException('No customers found for receipts.');
        }

        for ($i = 1; $i <= $count; $i++) {
            $rows[] = [
                'id' => (string) Str::ulid(),
                'number' => sprintf('RCPT-%06d', $i),
                'date' => $this->randomDateWithinDays(365),
                'ledger_id' => $customerIds[($i - 1) % $customerCount],
                'transaction_id' => null,
                'cheque_no' => null,
                'narration' => 'Performance seed receipt',
                'branch_id' => $branchId,
                'created_by' => $adminId,
                'updated_by' => null,
                'deleted_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ];

            if (count($rows) >= $chunkSize) {
                DB::table('receipts')->insert($rows);
                $rows = [];
            }
        }

        if (!empty($rows)) {
            DB::table('receipts')->insert($rows);
        }
    }

    /**
     * @param array<int, string> $supplierIds
     */
    private function seedPayments(int $count, array $supplierIds, string $branchId, string $adminId, Carbon $now): void
    {
        $rows = [];
        $chunkSize = 1000;
        $supplierCount = count($supplierIds);

        if ($supplierCount === 0) {
            throw new \RuntimeException('No suppliers found for payments.');
        }

        for ($i = 1; $i <= $count; $i++) {
            $rows[] = [
                'id' => (string) Str::ulid(),
                'number' => sprintf('PAY-%06d', $i),
                'date' => $this->randomDateWithinDays(365),
                'ledger_id' => $supplierIds[($i - 1) % $supplierCount],
                'transaction_id' => null,
                'cheque_no' => null,
                'narration' => 'Performance seed payment',
                'branch_id' => $branchId,
                'created_by' => $adminId,
                'updated_by' => null,
                'deleted_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ];

            if (count($rows) >= $chunkSize) {
                DB::table('payments')->insert($rows);
                $rows = [];
            }
        }

        if (!empty($rows)) {
            DB::table('payments')->insert($rows);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function chunkInsert(string $table, array $rows, int $chunkSize): void
    {
        if (empty($rows)) {
            return;
        }

        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    private function randomDateWithinDays(int $daysBack): string
    {
        $days = random_int(0, max(0, $daysBack));
        return now()->subDays($days)->toDateString();
    }
}

