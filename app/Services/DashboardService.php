<?php

namespace App\Services;

use App\Enums\StockStatus;
use App\Enums\TransactionStatus;
use App\Models\Administration\Branch;
use App\Models\Purchase\Purchase;
use App\Models\Sale\Sale;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function __construct(
        private readonly DateConversionService $dateConversionService,
    ) {
    }

    public function getDashboardData(?Authenticatable $user = null): array
    {
        $branchId = $this->resolveBranchId($user);
        $today = Carbon::today();
        $startDate = $today->copy()->subDays(29);
        $endDate = $today->copy();

        return [
            'meta' => [
                'branch_id' => $branchId,
                'generated_at' => now()->toIso8601String(),
                'today' => $today->toDateString(),
            ],
            'kpis' => $this->getKpis($branchId, $today),
            'sales_purchase_chart' => $this->getSalesPurchaseChart($branchId, $startDate, $endDate),
            'inventory_overview' => $this->getInventoryOverview($branchId, $today),
            'top_lists' => $this->getTopLists($branchId),
            'recent_activity' => $this->getRecentActivity($branchId),
            'alerts' => $this->getAlerts($branchId, $today),
        ];
    }

    protected function getKpis(string $branchId, Carbon $today): array
    {
        return [
            'cash_bank_balance' => $this->cashBankBalance($branchId),
            'accounts_receivable' => $this->ledgerBalanceTotal($branchId, 'customer', 'dr'),
            'accounts_payable' => $this->ledgerBalanceTotal($branchId, 'supplier', 'cr'),
            'today_sales_total' => $this->salesOrPurchaseTotalForDate($branchId, Sale::class, 'debit', ['cash-or-bank', 'account-receivable'], $today),
            'today_purchases_total' => $this->salesOrPurchaseTotalForDate($branchId, Purchase::class, 'credit', ['cash-or-bank', 'account-payable'], $today),
            'today_cash_received' => $this->cashMovementForDate($branchId, 'debit', $today),
            'today_cash_paid' => $this->cashMovementForDate($branchId, 'credit', $today),
        ];
    }

    protected function getSalesPurchaseChart(string $branchId, Carbon $startDate, Carbon $endDate): array
    {
        $sales = $this->salesOrPurchaseTotalsByDate(
            branchId: $branchId,
            referenceType: Sale::class,
            amountColumn: 'debit',
            accountTypeSlugs: ['cash-or-bank', 'account-receivable'],
            startDate: $startDate,
            endDate: $endDate,
        );

        $purchases = $this->salesOrPurchaseTotalsByDate(
            branchId: $branchId,
            referenceType: Purchase::class,
            amountColumn: 'credit',
            accountTypeSlugs: ['cash-or-bank', 'account-payable'],
            startDate: $startDate,
            endDate: $endDate,
        );

        $series = [];
        $cursor = $startDate->copy();
        while ($cursor->lte($endDate)) {
            $date = $cursor->toDateString();
            $series[] = [
                'date' => $date,
                'label' => $this->dateConversionService->toDisplay($date),
                'sales' => $this->moneyValue($sales[$date] ?? 0),
                'purchases' => $this->moneyValue($purchases[$date] ?? 0),
            ];
            $cursor->addDay();
        }

        return [
            'period' => [
                'from' => $startDate->toDateString(),
                'to' => $endDate->toDateString(),
            ],
            'series' => $series,
        ];
    }

    protected function getInventoryOverview(string $branchId, Carbon $today): array
    {
        $stockBalances = $this->activeStockBalances($branchId);
        $stockTotalsSubquery = $this->stockTotalsSubquery($branchId);

        $totalInventory = (array) $stockBalances
            ->selectRaw('COALESCE(SUM(quantity), 0) as total_quantity')
            ->selectRaw('COALESCE(SUM(quantity * COALESCE(average_cost, 0)), 0) as total_value')
            ->first();

        $lowStockItems = DB::table('items as i')
            ->leftJoinSub($stockTotalsSubquery, 'stock_totals', fn ($join) => $join->on('stock_totals.item_id', '=', 'i.id'))
            ->where('i.branch_id', $branchId)
            ->whereNull('i.deleted_at')
            ->whereNotNull('i.minimum_stock')
            ->where('i.minimum_stock', '>', 0)
            ->whereRaw('COALESCE(stock_totals.quantity, 0) < i.minimum_stock')
            ->count();

        $outOfStockItems = DB::table('items as i')
            ->leftJoinSub($stockTotalsSubquery, 'stock_totals', fn ($join) => $join->on('stock_totals.item_id', '=', 'i.id'))
            ->where('i.branch_id', $branchId)
            ->whereNull('i.deleted_at')
            ->whereRaw('COALESCE(stock_totals.quantity, 0) <= 0')
            ->count();

        $expiringBatches = (clone $stockBalances)
            ->where('quantity', '>', 0)
            ->whereBetween('expire_date', [$today->toDateString(), $today->copy()->addDays(30)->toDateString()])
            ->count();

        return [
            'total_inventory_quantity' => $this->quantityValue($totalInventory['total_quantity'] ?? 0),
            'total_inventory_value' => $this->moneyValue($totalInventory['total_value'] ?? 0),
            'low_stock_items' => $lowStockItems,
            'out_of_stock_items' => $outOfStockItems,
            'expiring_batches' => $expiringBatches,
        ];
    }

    protected function getTopLists(string $branchId): array
    {
        return [
            'customers_by_sales' => $this->topCustomersBySales($branchId),
            'suppliers_by_purchases' => $this->topSuppliersByPurchases($branchId),
            'receivable_balances' => $this->topLedgerBalances($branchId, 'customer', 'dr'),
            'payable_balances' => $this->topLedgerBalances($branchId, 'supplier', 'cr'),
        ];
    }

    protected function getRecentActivity(string $branchId): array
    {
        $saleTotals = $this->referenceTransactionTotalsSubquery(
            referenceType: Sale::class,
            amountColumn: 'debit',
            accountTypeSlugs: ['cash-or-bank', 'account-receivable'],
        );

        $purchaseTotals = $this->referenceTransactionTotalsSubquery(
            referenceType: Purchase::class,
            amountColumn: 'credit',
            accountTypeSlugs: ['cash-or-bank', 'account-payable'],
        );

        $recentSales = DB::table('sales as s')
            ->leftJoin('ledgers as l', function ($join) {
                $join->on('l.id', '=', 's.customer_id')
                    ->whereNull('l.deleted_at');
            })
            ->leftJoin('transactions as t', function ($join) use ($branchId) {
                $join->on('t.reference_id', '=', 's.id')
                    ->where('t.reference_type', '=', Sale::class)
                    ->where('t.branch_id', '=', $branchId)
                    ->whereNull('t.deleted_at');
            })
            ->leftJoinSub($saleTotals, 'totals', fn ($join) => $join->on('totals.transaction_id', '=', 't.id'))
            ->where('s.branch_id', $branchId)
            ->whereNull('s.deleted_at')
            ->orderByDesc('s.date')
            ->orderByDesc('s.created_at')
            ->limit(10)
            ->get([
                's.id',
                's.number',
                's.date',
                's.status',
                'l.name as customer_name',
                DB::raw('COALESCE(totals.total, 0) as amount'),
            ])
            ->map(fn ($row) => [
                'id' => $row->id,
                'number' => $row->number,
                'date' => $this->dateConversionService->toDisplay($row->date),
                'status' => $row->status,
                'party_name' => $row->customer_name,
                'amount' => $this->moneyValue($row->amount),
            ])
            ->values();

        $recentPurchases = DB::table('purchases as p')
            ->leftJoin('ledgers as l', function ($join) {
                $join->on('l.id', '=', 'p.supplier_id')
                    ->whereNull('l.deleted_at');
            })
            ->leftJoin('transactions as t', function ($join) use ($branchId) {
                $join->on('t.reference_id', '=', 'p.id')
                    ->where('t.reference_type', '=', Purchase::class)
                    ->where('t.branch_id', '=', $branchId)
                    ->whereNull('t.deleted_at');
            })
            ->leftJoinSub($purchaseTotals, 'totals', fn ($join) => $join->on('totals.transaction_id', '=', 't.id'))
            ->where('p.branch_id', $branchId)
            ->whereNull('p.deleted_at')
            ->orderByDesc('p.date')
            ->orderByDesc('p.created_at')
            ->limit(10)
            ->get([
                'p.id',
                'p.number',
                'p.date',
                'p.status',
                'l.name as supplier_name',
                DB::raw('COALESCE(totals.total, 0) as amount'),
            ])
            ->map(fn ($row) => [
                'id' => $row->id,
                'number' => $row->number,
                'date' => $this->dateConversionService->toDisplay($row->date),
                'status' => $row->status,
                'party_name' => $row->supplier_name,
                'amount' => $this->moneyValue($row->amount),
            ])
            ->values();

        $recentStockMovements = DB::table('stock_movements as sm')
            ->leftJoin('items as i', function ($join) {
                $join->on('i.id', '=', 'sm.item_id')
                    ->whereNull('i.deleted_at');
            })
            ->leftJoin('warehouses as w', function ($join) {
                $join->on('w.id', '=', 'sm.warehouse_id')
                    ->whereNull('w.deleted_at');
            })
            ->where('sm.branch_id', $branchId)
            ->whereNull('sm.deleted_at')
            ->orderByDesc('sm.date')
            ->orderByDesc('sm.created_at')
            ->limit(10)
            ->get([
                'sm.id',
                'sm.date',
                'sm.movement_type',
                'sm.status',
                'sm.quantity',
                'sm.unit_cost',
                'sm.batch',
                'sm.reference_type',
                'i.name as item_name',
                'w.name as warehouse_name',
            ])
            ->map(fn ($row) => [
                'id' => $row->id,
                'date' => $this->dateConversionService->toDisplay($row->date),
                'movement_type' => $row->movement_type,
                'status' => $row->status,
                'quantity' => $this->quantityValue($row->quantity),
                'unit_cost' => $this->moneyValue($row->unit_cost),
                'batch' => $row->batch,
                'item_name' => $row->item_name,
                'warehouse_name' => $row->warehouse_name,
                'reference_type' => class_basename((string) $row->reference_type),
            ])
            ->values();

        return [
            'sales' => $recentSales,
            'purchases' => $recentPurchases,
            'stock_movements' => $recentStockMovements,
        ];
    }

    protected function getAlerts(string $branchId, Carbon $today): array
    {
        $stockTotalsSubquery = $this->stockTotalsSubquery($branchId);

        $lowStockItems = DB::table('items as i')
            ->leftJoinSub($stockTotalsSubquery, 'stock_totals', fn ($join) => $join->on('stock_totals.item_id', '=', 'i.id'))
            ->where('i.branch_id', $branchId)
            ->whereNull('i.deleted_at')
            ->whereNotNull('i.minimum_stock')
            ->where('i.minimum_stock', '>', 0)
            ->whereRaw('COALESCE(stock_totals.quantity, 0) < i.minimum_stock')
            ->orderByRaw('(i.minimum_stock - COALESCE(stock_totals.quantity, 0)) DESC')
            ->limit(5)
            ->get([
                'i.id',
                'i.name',
                DB::raw('COALESCE(stock_totals.quantity, 0) as current_quantity'),
                'i.minimum_stock as reorder_level',
            ]);

        $expiringSoon = $this->activeStockBalances($branchId)
            ->leftJoin('items as i', function ($join) {
                $join->on('i.id', '=', 'stock_balances.item_id')
                    ->whereNull('i.deleted_at');
            })
            ->leftJoin('warehouses as w', function ($join) {
                $join->on('w.id', '=', 'stock_balances.warehouse_id')
                    ->whereNull('w.deleted_at');
            })
            ->where('stock_balances.quantity', '>', 0)
            ->whereBetween('stock_balances.expire_date', [$today->toDateString(), $today->copy()->addDays(30)->toDateString()])
            ->orderBy('stock_balances.expire_date')
            ->limit(5)
            ->get([
                'stock_balances.id',
                'stock_balances.expire_date',
                'stock_balances.batch',
                'stock_balances.quantity',
                'i.name as item_name',
                'w.name as warehouse_name',
            ]);

        $negativeStocks = $this->activeStockBalances($branchId)
            ->leftJoin('items as i', function ($join) {
                $join->on('i.id', '=', 'stock_balances.item_id')
                    ->whereNull('i.deleted_at');
            })
            ->leftJoin('warehouses as w', function ($join) {
                $join->on('w.id', '=', 'stock_balances.warehouse_id')
                    ->whereNull('w.deleted_at');
            })
            ->where('stock_balances.quantity', '<', 0)
            ->orderBy('stock_balances.quantity')
            ->limit(5)
            ->get([
                'stock_balances.id',
                'stock_balances.quantity',
                'stock_balances.batch',
                'i.name as item_name',
                'w.name as warehouse_name',
            ]);

        $unpostedTransactions = DB::table('transactions')
            ->where('branch_id', $branchId)
            ->whereNull('deleted_at')
            ->where('status', '!=', TransactionStatus::POSTED->value)
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get([
                'id',
                'voucher_number',
                'reference_type',
                'date',
                'status',
            ]);

        return [
            [
                'key' => 'low_stock',
                'label' => 'Items below reorder level',
                'count' => DB::table('items as i')
                    ->leftJoinSub($stockTotalsSubquery, 'stock_totals', fn ($join) => $join->on('stock_totals.item_id', '=', 'i.id'))
                    ->where('i.branch_id', $branchId)
                    ->whereNull('i.deleted_at')
                    ->whereNotNull('i.minimum_stock')
                    ->where('i.minimum_stock', '>', 0)
                    ->whereRaw('COALESCE(stock_totals.quantity, 0) < i.minimum_stock')
                    ->count(),
                'items' => $lowStockItems->map(fn ($row) => [
                    'id' => $row->id,
                    'title' => $row->name,
                    'meta' => 'Qty '.$this->trimNumber($row->current_quantity).' / Reorder '.$this->trimNumber($row->reorder_level),
                ])->values(),
            ],
            [
                'key' => 'expiring_soon',
                'label' => 'Items expiring within 30 days',
                'count' => $this->activeStockBalances($branchId)
                    ->where('quantity', '>', 0)
                    ->whereBetween('expire_date', [$today->toDateString(), $today->copy()->addDays(30)->toDateString()])
                    ->count(),
                'items' => $expiringSoon->map(fn ($row) => [
                    'id' => $row->id,
                    'title' => $row->item_name,
                    'meta' => collect([$row->batch ? 'Batch '.$row->batch : null, $row->warehouse_name, $this->dateConversionService->toDisplay($row->expire_date)])
                        ->filter()
                        ->implode(' | '),
                ])->values(),
            ],
            [
                'key' => 'negative_stock',
                'label' => 'Negative stock balances',
                'count' => $this->activeStockBalances($branchId)
                    ->where('quantity', '<', 0)
                    ->count(),
                'items' => $negativeStocks->map(fn ($row) => [
                    'id' => $row->id,
                    'title' => $row->item_name,
                    'meta' => collect([$row->warehouse_name, $row->batch ? 'Batch '.$row->batch : null, 'Qty '.$this->trimNumber($row->quantity)])
                        ->filter()
                        ->implode(' | '),
                ])->values(),
            ],
            [
                'key' => 'unposted_transactions',
                'label' => 'Transactions not posted',
                'count' => DB::table('transactions')
                    ->where('branch_id', $branchId)
                    ->whereNull('deleted_at')
                    ->where('status', '!=', TransactionStatus::POSTED->value)
                    ->count(),
                'items' => $unpostedTransactions->map(fn ($row) => [
                    'id' => $row->id,
                    'title' => $row->voucher_number ?: class_basename((string) $row->reference_type),
                    'meta' => collect([$row->status ?: 'unknown', $this->dateConversionService->toDisplay($row->date)])
                        ->filter()
                        ->implode(' | '),
                ])->values(),
            ],
        ];
    }

    protected function cashBankBalance(string $branchId): float
    {
        $row = $this->postedTransactionLines($branchId)
            ->join('accounts as a', function ($join) use ($branchId) {
                $join->on('a.id', '=', 'tl.account_id')
                    ->where('a.branch_id', '=', $branchId)
                    ->whereNull('a.deleted_at');
            })
            ->join('account_types as at', 'at.id', '=', 'a.account_type_id')
            ->where('at.slug', 'cash-or-bank')
            ->selectRaw('COALESCE(SUM((tl.debit - tl.credit) * t.rate), 0) as value')
            ->first();

        return $this->moneyValue($row?->value);
    }

    protected function ledgerBalanceTotal(string $branchId, string $ledgerType, string $nature): float
    {
        $row = DB::query()
            ->fromSub($this->postedLedgerBalances($branchId, $ledgerType), 'ledger_balances')
            ->selectRaw(
                $nature === 'dr'
                    ? 'COALESCE(SUM(CASE WHEN balance > 0 THEN balance ELSE 0 END), 0) as value'
                    : 'COALESCE(SUM(CASE WHEN balance < 0 THEN ABS(balance) ELSE 0 END), 0) as value'
            )
            ->first();

        return $this->moneyValue($row?->value);
    }

    protected function topLedgerBalances(string $branchId, string $ledgerType, string $nature): array
    {
        $rows = $this->postedLedgerBalances($branchId, $ledgerType)
            ->when(
                $nature === 'dr',
                fn ($query) => $query->havingRaw('SUM((COALESCE(tl.debit, 0) - COALESCE(tl.credit, 0)) * t.rate) > 0')
                    ->orderByDesc('balance'),
                fn ($query) => $query->havingRaw('SUM((COALESCE(tl.debit, 0) - COALESCE(tl.credit, 0)) * t.rate) < 0')
                    ->orderBy('balance')
            )
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->ledger_id,
                'name' => $row->ledger_name,
                'balance' => $this->moneyValue($nature === 'dr' ? $row->balance : abs((float) $row->balance)),
            ]);

        return $rows->values()->all();
    }

    protected function topCustomersBySales(string $branchId): array
    {
        $rows = DB::table('sales as s')
            ->join('ledgers as l', function ($join) use ($branchId) {
                $join->on('l.id', '=', 's.customer_id')
                    ->where('l.branch_id', '=', $branchId)
                    ->whereNull('l.deleted_at');
            })
            ->join('transactions as t', function ($join) use ($branchId) {
                $join->on('t.reference_id', '=', 's.id')
                    ->where('t.reference_type', '=', Sale::class)
                    ->where('t.branch_id', '=', $branchId)
                    ->whereNull('t.deleted_at');
            })
            ->join('transaction_lines as tl', function ($join) {
                $join->on('tl.transaction_id', '=', 't.id')
                    ->whereNull('tl.deleted_at');
            })
            ->join('accounts as a', function ($join) use ($branchId) {
                $join->on('a.id', '=', 'tl.account_id')
                    ->where('a.branch_id', '=', $branchId)
                    ->whereNull('a.deleted_at');
            })
            ->join('account_types as at', 'at.id', '=', 'a.account_type_id')
            ->where('s.branch_id', $branchId)
            ->whereNull('s.deleted_at')
            ->where('t.status', TransactionStatus::POSTED->value)
            ->whereIn('at.slug', ['cash-or-bank', 'account-receivable'])
            ->where('tl.debit', '>', 0)
            ->groupBy('s.customer_id', 'l.name')
            ->orderByDesc(DB::raw('SUM(tl.debit * t.rate)'))
            ->limit(5)
            ->get([
                's.customer_id as id',
                'l.name',
                DB::raw('COUNT(DISTINCT s.id) as sales_count'),
                DB::raw('COALESCE(SUM(tl.debit * t.rate), 0) as total'),
            ]);

        return $rows->map(fn ($row) => [
            'id' => $row->id,
            'name' => $row->name,
            'count' => (int) $row->sales_count,
            'total' => $this->moneyValue($row->total),
        ])->values()->all();
    }

    protected function topSuppliersByPurchases(string $branchId): array
    {
        $rows = DB::table('purchases as p')
            ->join('ledgers as l', function ($join) use ($branchId) {
                $join->on('l.id', '=', 'p.supplier_id')
                    ->where('l.branch_id', '=', $branchId)
                    ->whereNull('l.deleted_at');
            })
            ->join('transactions as t', function ($join) use ($branchId) {
                $join->on('t.reference_id', '=', 'p.id')
                    ->where('t.reference_type', '=', Purchase::class)
                    ->where('t.branch_id', '=', $branchId)
                    ->whereNull('t.deleted_at');
            })
            ->join('transaction_lines as tl', function ($join) {
                $join->on('tl.transaction_id', '=', 't.id')
                    ->whereNull('tl.deleted_at');
            })
            ->join('accounts as a', function ($join) use ($branchId) {
                $join->on('a.id', '=', 'tl.account_id')
                    ->where('a.branch_id', '=', $branchId)
                    ->whereNull('a.deleted_at');
            })
            ->join('account_types as at', 'at.id', '=', 'a.account_type_id')
            ->where('p.branch_id', $branchId)
            ->whereNull('p.deleted_at')
            ->where('t.status', TransactionStatus::POSTED->value)
            ->whereIn('at.slug', ['cash-or-bank', 'account-payable'])
            ->where('tl.credit', '>', 0)
            ->groupBy('p.supplier_id', 'l.name')
            ->orderByDesc(DB::raw('SUM(tl.credit * t.rate)'))
            ->limit(5)
            ->get([
                'p.supplier_id as id',
                'l.name',
                DB::raw('COUNT(DISTINCT p.id) as purchase_count'),
                DB::raw('COALESCE(SUM(tl.credit * t.rate), 0) as total'),
            ]);

        return $rows->map(fn ($row) => [
            'id' => $row->id,
            'name' => $row->name,
            'count' => (int) $row->purchase_count,
            'total' => $this->moneyValue($row->total),
        ])->values()->all();
    }

    protected function salesOrPurchaseTotalForDate(
        string $branchId,
        string $referenceType,
        string $amountColumn,
        array $accountTypeSlugs,
        Carbon $date,
    ): float {
        $row = $this->postedTransactionLines($branchId)
            ->join('accounts as a', function ($join) use ($branchId) {
                $join->on('a.id', '=', 'tl.account_id')
                    ->where('a.branch_id', '=', $branchId)
                    ->whereNull('a.deleted_at');
            })
            ->join('account_types as at', 'at.id', '=', 'a.account_type_id')
            ->where('t.reference_type', $referenceType)
            ->whereDate('t.date', $date->toDateString())
            ->whereIn('at.slug', $accountTypeSlugs)
            ->where("tl.{$amountColumn}", '>', 0)
            ->selectRaw("COALESCE(SUM(tl.{$amountColumn} * t.rate), 0) as value")
            ->first();

        return $this->moneyValue($row?->value);
    }

    protected function salesOrPurchaseTotalsByDate(
        string $branchId,
        string $referenceType,
        string $amountColumn,
        array $accountTypeSlugs,
        Carbon $startDate,
        Carbon $endDate,
    ): array {
        return $this->postedTransactionLines($branchId)
            ->join('accounts as a', function ($join) use ($branchId) {
                $join->on('a.id', '=', 'tl.account_id')
                    ->where('a.branch_id', '=', $branchId)
                    ->whereNull('a.deleted_at');
            })
            ->join('account_types as at', 'at.id', '=', 'a.account_type_id')
            ->where('t.reference_type', $referenceType)
            ->whereBetween('t.date', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereIn('at.slug', $accountTypeSlugs)
            ->where("tl.{$amountColumn}", '>', 0)
            ->groupBy(DB::raw('DATE(t.date)'))
            ->orderBy(DB::raw('DATE(t.date)'))
            ->get([
                DB::raw('DATE(t.date) as report_date'),
                DB::raw("COALESCE(SUM(tl.{$amountColumn} * t.rate), 0) as total"),
            ])
            ->mapWithKeys(fn ($row) => [$row->report_date => $this->moneyValue($row->total)])
            ->all();
    }

    protected function cashMovementForDate(string $branchId, string $amountColumn, Carbon $date): float
    {
        $row = $this->postedTransactionLines($branchId)
            ->join('accounts as a', function ($join) use ($branchId) {
                $join->on('a.id', '=', 'tl.account_id')
                    ->where('a.branch_id', '=', $branchId)
                    ->whereNull('a.deleted_at');
            })
            ->join('account_types as at', 'at.id', '=', 'a.account_type_id')
            ->joinSub($this->externalCashTransactionSubquery($branchId), 'cash_txn', fn ($join) => $join->on('cash_txn.transaction_id', '=', 't.id'))
            ->where('at.slug', 'cash-or-bank')
            ->whereDate('t.date', $date->toDateString())
            ->where("tl.{$amountColumn}", '>', 0)
            ->selectRaw("COALESCE(SUM(tl.{$amountColumn} * t.rate), 0) as value")
            ->first();

        return $this->moneyValue($row?->value);
    }

    protected function postedLedgerBalances(string $branchId, string $ledgerType)
    {
        return DB::table('transaction_lines as tl')
            ->join('transactions as t', function ($join) use ($branchId) {
                $join->on('t.id', '=', 'tl.transaction_id')
                    ->where('t.branch_id', '=', $branchId)
                    ->where('t.status', '=', TransactionStatus::POSTED->value)
                    ->whereNull('t.deleted_at');
            })
            ->join('ledgers as l', function ($join) use ($branchId, $ledgerType) {
                $join->on('l.id', '=', 'tl.ledger_id')
                    ->where('l.branch_id', '=', $branchId)
                    ->where('l.type', '=', $ledgerType)
                    ->whereNull('l.deleted_at');
            })
            ->whereNull('tl.deleted_at')
            ->groupBy('l.id', 'l.name')
            ->selectRaw('l.id as ledger_id, l.name as ledger_name')
            ->selectRaw('COALESCE(SUM((COALESCE(tl.debit, 0) - COALESCE(tl.credit, 0)) * t.rate), 0) as balance');
    }

    protected function referenceTransactionTotalsSubquery(string $referenceType, string $amountColumn, array $accountTypeSlugs)
    {
        return DB::table('transaction_lines as tl')
            ->join('transactions as t', function ($join) use ($referenceType) {
                $join->on('t.id', '=', 'tl.transaction_id')
                    ->where('t.reference_type', '=', $referenceType)
                    ->where('t.status', '=', TransactionStatus::POSTED->value)
                    ->whereNull('t.deleted_at');
            })
            ->join('accounts as a', function ($join) {
                $join->on('a.id', '=', 'tl.account_id')
                    ->whereNull('a.deleted_at');
            })
            ->join('account_types as at', 'at.id', '=', 'a.account_type_id')
            ->whereNull('tl.deleted_at')
            ->whereIn('at.slug', $accountTypeSlugs)
            ->where("tl.{$amountColumn}", '>', 0)
            ->groupBy('tl.transaction_id')
            ->selectRaw("tl.transaction_id, COALESCE(SUM(tl.{$amountColumn} * t.rate), 0) as total");
    }

    protected function activeStockBalances(string $branchId)
    {
        return DB::table('stock_balances')
            ->where('stock_balances.branch_id', $branchId)
            ->whereNull('stock_balances.deleted_at')
            ->whereNotIn('stock_balances.status', [StockStatus::VOIDED->value, StockStatus::CANCELLED->value]);
    }

    protected function stockTotalsSubquery(string $branchId)
    {
        return $this->activeStockBalances($branchId)
            ->groupBy('item_id')
            ->selectRaw('item_id, COALESCE(SUM(quantity), 0) as quantity');
    }

    protected function postedTransactionLines(string $branchId)
    {
        return DB::table('transactions as t')
            ->join('transaction_lines as tl', function ($join) {
                $join->on('tl.transaction_id', '=', 't.id')
                    ->whereNull('tl.deleted_at');
            })
            ->where('t.branch_id', $branchId)
            ->where('t.status', TransactionStatus::POSTED->value)
            ->whereNull('t.deleted_at');
    }

    protected function externalCashTransactionSubquery(string $branchId)
    {
        return DB::table('transaction_lines as tl')
            ->join('transactions as t', function ($join) use ($branchId) {
                $join->on('t.id', '=', 'tl.transaction_id')
                    ->where('t.branch_id', '=', $branchId)
                    ->where('t.status', '=', TransactionStatus::POSTED->value)
                    ->whereNull('t.deleted_at');
            })
            ->join('accounts as a', function ($join) use ($branchId) {
                $join->on('a.id', '=', 'tl.account_id')
                    ->where('a.branch_id', '=', $branchId)
                    ->whereNull('a.deleted_at');
            })
            ->join('account_types as at', 'at.id', '=', 'a.account_type_id')
            ->whereNull('tl.deleted_at')
            ->where('at.slug', 'cash-or-bank')
            ->groupBy('tl.transaction_id')
            ->havingRaw('COUNT(*) = 1')
            ->selectRaw('tl.transaction_id');
    }

    protected function resolveBranchId(?Authenticatable $user): string
    {
        $branchId = $user?->branch_id ?? auth()->user()?->branch_id;

        if ($branchId) {
            return (string) $branchId;
        }

        return (string) Branch::query()->value('id');
    }

    protected function moneyValue(mixed $value): float
    {
        return round((float) ($value ?? 0), 2);
    }

    protected function quantityValue(mixed $value): float
    {
        return round((float) ($value ?? 0), 2);
    }

    protected function trimNumber(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) ($value ?? 0), 2, '.', ''), '0'), '.');
    }
}
