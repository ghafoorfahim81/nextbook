<?php

namespace Database\Seeders\Performance;

use Carbon\Carbon;
use Database\Seeders\Account\AccountSeeder;
use Database\Seeders\Account\AccountTypeSeeder;
use Database\Seeders\Administration\BranchSeeder;
use Database\Seeders\Administration\CurrencySeeder;
use Database\Seeders\Administration\StoreSeeder;
use Database\Seeders\Administration\UnitMeasureSeeder;
use Database\Seeders\Administration\UserSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PerformanceLoadSeeder extends Seeder
{
    private const CUSTOMER_COUNT = 2000;
    private const SUPPLIER_COUNT = 900;
    private const CATEGORY_COUNT = 100;
    private const ITEM_COUNT = 5000;
    private const RECEIPT_COUNT = 10000;
    private const PAYMENT_COUNT = 12000;

    private const OPENING_QTY_MIN = 1000;
    private const OPENING_QTY_MAX = 20000;

    private const STORE_MAIN_NAME = 'Main Store';
    private const STORE_A_NAME = 'Store A';

    public function run(): void
    {
        DB::disableQueryLog();

        $this->ensurePrerequisites();

        $branchId = $this->getMainBranchId();
        $adminId = $this->getAdminUserId();

        // Prevent accidental duplicate runs that would violate unique constraints.
        if (DB::table('ledgers')->where('branch_id', $branchId)->where('name', 'Perf Customer 000001')->exists()) {
            $this->command?->warn('Performance data already exists (Perf Customer 000001 found). Skipping.');
            return;
        }

        $now = now();

        $currencyId = DB::table('currencies')
            ->where('branch_id', $branchId)
            ->where('is_base_currency', true)
            ->value('id')
            ?? DB::table('currencies')->where('branch_id', $branchId)->value('id');

        $unitMeasureId = DB::table('unit_measures')->where('branch_id', $branchId)->value('id')
            ?? DB::table('unit_measures')->value('id');

        $assetAccountId = DB::table('accounts')->where('branch_id', $branchId)->where('slug', 'inventory-stock')->value('id')
            ?? DB::table('accounts')->where('branch_id', $branchId)->value('id');
        $incomeAccountId = DB::table('accounts')->where('branch_id', $branchId)->where('slug', 'product-income')->value('id')
            ?? DB::table('accounts')->where('branch_id', $branchId)->value('id');
        $costAccountId = DB::table('accounts')->where('branch_id', $branchId)->where('slug', 'cost-of-goods-sold')->value('id')
            ?? DB::table('accounts')->where('branch_id', $branchId)->where('slug', 'direct-material-cost')->value('id')
            ?? DB::table('accounts')->where('branch_id', $branchId)->value('id');

        if (!$unitMeasureId || !$assetAccountId || !$incomeAccountId || !$costAccountId) {
            throw new \RuntimeException('Missing prerequisites: unit measure / accounts not found.');
        }

        $storeMainId = $this->ensureStore(self::STORE_MAIN_NAME, true, $branchId, $adminId, $now);
        $storeAId = $this->ensureStore(self::STORE_A_NAME, false, $branchId, $adminId, $now);

        $this->command?->info('Creating categories...');
        $categoryIds = $this->seedCategories(self::CATEGORY_COUNT, $branchId, $adminId, $now);

        $this->command?->info('Creating ledgers (customers/suppliers)...');
        [$customerIds, $supplierIds] = $this->seedLedgers(
            self::CUSTOMER_COUNT,
            self::SUPPLIER_COUNT,
            $branchId,
            $adminId,
            $currencyId,
            $now
        );

        $this->command?->info('Creating items...');
        $itemIds = $this->seedItems(
            self::ITEM_COUNT,
            $categoryIds,
            $branchId,
            $adminId,
            $unitMeasureId,
            $assetAccountId,
            $incomeAccountId,
            $costAccountId,
            $now
        );

        $this->command?->info('Creating stock openings in two stores...');
        $this->seedOpeningStocks(
            $itemIds,
            [$storeMainId, $storeAId],
            $branchId,
            $adminId,
            $unitMeasureId,
            $now
        );

        $this->command?->info('Creating receipts/payments...');
        $this->seedReceipts(self::RECEIPT_COUNT, $customerIds, $branchId, $adminId, $now);
        $this->seedPayments(self::PAYMENT_COUNT, $supplierIds, $branchId, $adminId, $now);

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
        $adminId = DB::table('users')->where('email', 'admin@nextbook.com')->value('id')
            ?? DB::table('users')->value('id');

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
    private function seedCategories(int $count, string $branchId, string $adminId, Carbon $now): array
    {
        $rows = [];
        $ids = [];

        for ($i = 1; $i <= $count; $i++) {
            $id = (string) Str::ulid();
            $ids[] = $id;

            $rows[] = [
                'id' => $id,
                'name' => sprintf('Perf Category %03d', $i),
                'remark' => 'Performance seed category',
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

        for ($i = 1; $i <= $customerCount; $i++) {
            $id = (string) Str::ulid();
            $customerIds[] = $id;

            $customers[] = [
                'id' => $id,
                'name' => sprintf('Perf Customer %06d', $i),
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

            $suppliers[] = [
                'id' => $id,
                'name' => sprintf('Perf Supplier %06d', $i),
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
     * @return array<int, string> item IDs
     */
    private function seedItems(
        int $count,
        array $categoryIds,
        string $branchId,
        string $adminId,
        string $unitMeasureId,
        string $assetAccountId,
        string $incomeAccountId,
        string $costAccountId,
        Carbon $now
    ): array {
        $rows = [];
        $ids = [];

        $categoryCount = count($categoryIds);
        if ($categoryCount === 0) {
            throw new \RuntimeException('No categories available for items.');
        }

        for ($i = 1; $i <= $count; $i++) {
            $id = (string) Str::ulid();
            $ids[] = $id;

            $categoryId = $categoryIds[($i - 1) % $categoryCount];

            $purchasePrice = random_int(10, 500);
            $cost = $purchasePrice + random_int(0, 50);
            $salePrice = $cost + random_int(10, 200);

            $rows[] = [
                'id' => $id,
                'name' => sprintf('Perf Item %06d', $i),
                'code' => sprintf('PERF-ITEM-%06d', $i),
                'item_type' => 'inventory_materials',
                'sku' => sprintf('SKU-%06d', $i),
                'generic_name' => null,
                'packing' => null,
                'barcode' => null,
                'unit_measure_id' => $unitMeasureId,
                'brand_id' => null,
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
                'fast_search' => sprintf('PERF-ITEM-%06d', $i),
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
     */
    private function seedOpeningStocks(
        array $itemIds,
        array $storeIds,
        string $branchId,
        string $adminId,
        string $unitMeasureId,
        Carbon $now
    ): void {
        $stocksChunk = [];
        $openingsChunk = [];
        $chunkSize = 1000;

        foreach ($itemIds as $itemId) {
            foreach ($storeIds as $storeId) {
                $stockId = (string) Str::ulid();
                $qty = random_int(self::OPENING_QTY_MIN, self::OPENING_QTY_MAX);
                $unitPrice = random_int(5, 500);

                $stocksChunk[] = [
                    'id' => $stockId,
                    'item_id' => $itemId,
                    'store_id' => $storeId,
                    'unit_measure_id' => $unitMeasureId,
                    'quantity' => $qty,
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

