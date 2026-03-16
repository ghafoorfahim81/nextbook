<?php

namespace App\Services;

use App\Enums\LedgerType;
use App\Enums\StockStatus;
use App\Enums\TransactionStatus;
use App\Models\Administration\Branch;
use App\Models\Purchase\Purchase;
use App\Models\Sale\Sale;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public const REPORT_KEYS = [
        'trial_balance',
        'balance_sheet',
        'income_statement',
        'general_ledger',
        'customer_statement',
        'supplier_statement',
        'receipt_report',
        'payment_report',
        'cash_book',
        'sales_report',
        'purchase_report',
        'inventory_stock',
        'stock_movement',
        'low_stock',
        'inventory_valuation',
    ];

    public function __construct(
        private readonly DateConversionService $dateConversionService,
    ) {
    }

    public function getPageData(?Authenticatable $user = null, array $rawFilters = []): array
    {
        $filters = $this->normalizeFilters($rawFilters, $user);

        return [
            'filters' => $filters,
            'reportOptions' => $this->reportOptions(),
            'filterOptions' => $this->filterOptions($filters['branch_id']),
            'result' => $this->getReportData($filters),
        ];
    }

    public function getExportData(?Authenticatable $user = null, array $rawFilters = []): array
    {
        $filters = $this->normalizeFilters($rawFilters, $user);
        $filters['per_page'] = 50000;
        $filters['page'] = 1;

        $result = $this->getReportData($filters);

        if (($result['meta']['layout'] ?? null) === 'statement') {
            return $this->statementExportData($filters['report'], $result);
        }

        $rows = collect($result['rows'] ?? []);
        $headings = $rows->isNotEmpty()
            ? array_keys($rows->first())
            : $this->defaultExportHeadings($filters['report']);

        return [
            'filename' => $filters['report'].'-'.now()->format('Ymd-His').'.csv',
            'headings' => $headings,
            'rows' => $rows->map(fn ($row) => collect($row)->only($headings)->all())->all(),
        ];
    }

    public function getReportData(array $filters): array
    {
        return match ($filters['report']) {
            'trial_balance' => $this->getTrialBalance($filters),
            'balance_sheet' => $this->getBalanceSheet($filters),
            'income_statement' => $this->getIncomeStatement($filters),
            'general_ledger' => $this->getGeneralLedger($filters),
            'customer_statement' => $this->getCustomerStatement($filters),
            'supplier_statement' => $this->getSupplierStatement($filters),
            'receipt_report' => $this->getReceiptReport($filters),
            'payment_report' => $this->getPaymentReport($filters),
            'cash_book' => $this->getCashBook($filters),
            'sales_report' => $this->getSalesReport($filters),
            'purchase_report' => $this->getPurchaseReport($filters),
            'inventory_stock' => $this->getInventoryStock($filters),
            'stock_movement' => $this->getStockMovements($filters),
            'low_stock' => $this->getLowStock($filters),
            'inventory_valuation' => $this->getInventoryValuation($filters),
            default => $this->getTrialBalance($filters),
        };
    }

    public function getTrialBalance(array $filters): array
    {
        $baseQuery = DB::table('transaction_lines as tl')
            ->join('transactions as t', function ($join) use ($filters) {
                $join->on('t.id', '=', 'tl.transaction_id')
                    ->where('t.branch_id', '=', $filters['branch_id'])
                    ->where('t.status', '=', TransactionStatus::POSTED->value)
                    ->whereNull('t.deleted_at');
            })
            ->join('ledgers as l', function ($join) use ($filters) {
                $join->on('l.id', '=', 'tl.ledger_id')
                    ->where('l.branch_id', '=', $filters['branch_id'])
                    ->whereNull('l.deleted_at');
            })
            ->whereNull('tl.deleted_at');

        $this->applyDateFilter($baseQuery, 't.date', $filters);

        $query = (clone $baseQuery)
            ->groupBy('l.id', 'l.name')
            ->orderBy('l.name')
            ->selectRaw('l.id as ledger_id, l.name as ledger_name')
            ->selectRaw('COALESCE(SUM(tl.debit * t.rate), 0) as total_debit')
            ->selectRaw('COALESCE(SUM(tl.credit * t.rate), 0) as total_credit')
            ->selectRaw('COALESCE(SUM((tl.debit - tl.credit) * t.rate), 0) as balance');

        $totals = (clone $baseQuery)
            ->selectRaw('COALESCE(SUM(tl.debit * t.rate), 0) as total_debit')
            ->selectRaw('COALESCE(SUM(tl.credit * t.rate), 0) as total_credit')
            ->selectRaw('COALESCE(SUM((tl.debit - tl.credit) * t.rate), 0) as balance')
            ->first();

        return $this->paginateReport(
            $query,
            $filters,
            fn ($row) => [
                'ledger_id' => $row->ledger_id,
                'ledger_name' => $row->ledger_name,
                'total_debit' => $this->moneyValue($row->total_debit),
                'total_credit' => $this->moneyValue($row->total_credit),
                'balance' => $this->moneyValue($row->balance),
                'balance_label' => $this->formatBalance($row->balance),
            ],
            [
                'total_debit' => $this->moneyValue($totals?->total_debit),
                'total_credit' => $this->moneyValue($totals?->total_credit),
                'balance' => $this->moneyValue($totals?->balance),
                'balance_label' => $this->formatBalance($totals?->balance),
            ],
        );
    }

    public function getGeneralLedger(array $filters): array
    {
        if (! $filters['ledger_id']) {
            return $this->emptyResult('ledger_required');
        }

        $query = $this->ledgerStatementQuery($filters, $filters['ledger_id']);
        $summary = $this->ledgerStatementSummary($filters, $filters['ledger_id']);

        return $this->paginateReport(
            $query,
            $filters,
            fn ($row) => [
                'date' => $this->displayDate($row->date),
                'transaction_number' => $row->transaction_number,
                'reference_type' => $this->referenceLabel($row->reference_type),
                'description' => $row->description,
                'debit' => $this->moneyValue($row->debit),
                'credit' => $this->moneyValue($row->credit),
                'running_balance' => $this->moneyValue($row->running_balance),
                'running_balance_label' => $this->formatBalance($row->running_balance),
            ],
            $summary,
        );
    }

    public function getCustomerStatement(array $filters): array
    {
        if (! $filters['customer_id']) {
            return $this->emptyResult('customer_required');
        }

        $query = $this->ledgerStatementQuery($filters, $filters['customer_id'], LedgerType::CUSTOMER->value)
            ->addSelect(DB::raw('COALESCE(t.voucher_number, \'-\') as reference'));

        $summary = $this->ledgerStatementSummary($filters, $filters['customer_id'], LedgerType::CUSTOMER->value);

        return $this->paginateReport(
            $query,
            $filters,
            fn ($row) => [
                'date' => $this->displayDate($row->date),
                'reference' => $row->reference,
                'description' => $row->description,
                'debit' => $this->moneyValue($row->debit),
                'credit' => $this->moneyValue($row->credit),
                'running_balance' => $this->moneyValue($row->running_balance),
                'balance' => $this->formatBalance($row->running_balance),
            ],
            $summary,
        );
    }

    public function getSupplierStatement(array $filters): array
    {
        if (! $filters['supplier_id']) {
            return $this->emptyResult('supplier_required');
        }

        $query = $this->ledgerStatementQuery($filters, $filters['supplier_id'], LedgerType::SUPPLIER->value)
            ->addSelect(DB::raw('COALESCE(t.voucher_number, \'-\') as reference'));

        $summary = $this->ledgerStatementSummary($filters, $filters['supplier_id'], LedgerType::SUPPLIER->value);

        return $this->paginateReport(
            $query,
            $filters,
            fn ($row) => [
                'date' => $this->displayDate($row->date),
                'reference' => $row->reference,
                'description' => $row->description,
                'debit' => $this->moneyValue($row->debit),
                'credit' => $this->moneyValue($row->credit),
                'running_balance' => $this->moneyValue($row->running_balance),
                'balance' => $this->formatBalance($row->running_balance),
            ],
            $summary,
        );
    }

    public function getReceiptReport(array $filters): array
    {
        $query = DB::table('transaction_lines as tl')
            ->join('transactions as t', function ($join) use ($filters) {
                $join->on('t.id', '=', 'tl.transaction_id')
                    ->where('t.branch_id', '=', $filters['branch_id'])
                    ->where('t.status', '=', TransactionStatus::POSTED->value)
                    ->whereNull('t.deleted_at');
            })
            ->join('accounts as a', function ($join) use ($filters) {
                $join->on('a.id', '=', 'tl.account_id')
                    ->where('a.branch_id', '=', $filters['branch_id'])
                    ->whereNull('a.deleted_at');
            })
            ->join('account_types as at', 'at.id', '=', 'a.account_type_id')
            ->leftJoinSub($this->counterpartyNamesSubquery($filters), 'cp', fn ($join) => $join->on('cp.transaction_id', '=', 't.id'))
            ->whereNull('tl.deleted_at')
            ->where('at.slug', 'cash-or-bank')
            ->where('tl.debit', '>', 0)
            ->when($filters['account_id'], fn ($builder, $accountId) => $builder->where('a.id', $accountId));

        $this->applyDateFilter($query, 't.date', $filters);

        $summaryRow = (clone $query)
            ->selectRaw('COALESCE(SUM(tl.debit * t.rate), 0) as total_amount')
            ->first();

        $query
            ->orderByDesc('t.date')
            ->orderByDesc('t.created_at')
            ->orderByDesc('tl.id')
            ->selectRaw('t.date')
            ->selectRaw('COALESCE(t.voucher_number, \'-\') as transaction_number')
            ->selectRaw('COALESCE(cp.ledger_name, a.name) as ledger_name')
            ->selectRaw("COALESCE(NULLIF(tl.remark, ''), NULLIF(t.remark, ''), '') as description")
            ->selectRaw('COALESCE(tl.debit * t.rate, 0) as amount_received');

        return $this->paginateReport(
            $query,
            $filters,
            fn ($row) => [
                'date' => $this->displayDate($row->date),
                'transaction_number' => $row->transaction_number,
                'ledger_name' => $row->ledger_name,
                'description' => $row->description,
                'amount_received' => $this->moneyValue($row->amount_received),
            ],
            [
                'total_amount' => $this->moneyValue($summaryRow?->total_amount),
            ],
        );
    }

    public function getPaymentReport(array $filters): array
    {
        $query = DB::table('transaction_lines as tl')
            ->join('transactions as t', function ($join) use ($filters) {
                $join->on('t.id', '=', 'tl.transaction_id')
                    ->where('t.branch_id', '=', $filters['branch_id'])
                    ->where('t.status', '=', TransactionStatus::POSTED->value)
                    ->whereNull('t.deleted_at');
            })
            ->join('accounts as a', function ($join) use ($filters) {
                $join->on('a.id', '=', 'tl.account_id')
                    ->where('a.branch_id', '=', $filters['branch_id'])
                    ->whereNull('a.deleted_at');
            })
            ->join('account_types as at', 'at.id', '=', 'a.account_type_id')
            ->leftJoinSub($this->counterpartyNamesSubquery($filters), 'cp', fn ($join) => $join->on('cp.transaction_id', '=', 't.id'))
            ->whereNull('tl.deleted_at')
            ->where('at.slug', 'cash-or-bank')
            ->where('tl.credit', '>', 0)
            ->when($filters['account_id'], fn ($builder, $accountId) => $builder->where('a.id', $accountId));

        $this->applyDateFilter($query, 't.date', $filters);

        $summaryRow = (clone $query)
            ->selectRaw('COALESCE(SUM(tl.credit * t.rate), 0) as total_amount')
            ->first();

        $query
            ->orderByDesc('t.date')
            ->orderByDesc('t.created_at')
            ->orderByDesc('tl.id')
            ->selectRaw('t.date')
            ->selectRaw('COALESCE(t.voucher_number, \'-\') as transaction_number')
            ->selectRaw('COALESCE(cp.ledger_name, a.name) as ledger_name')
            ->selectRaw("COALESCE(NULLIF(tl.remark, ''), NULLIF(t.remark, ''), '') as description")
            ->selectRaw('COALESCE(tl.credit * t.rate, 0) as amount_paid');

        return $this->paginateReport(
            $query,
            $filters,
            fn ($row) => [
                'date' => $this->displayDate($row->date),
                'transaction_number' => $row->transaction_number,
                'ledger_name' => $row->ledger_name,
                'description' => $row->description,
                'amount_paid' => $this->moneyValue($row->amount_paid),
            ],
            [
                'total_amount' => $this->moneyValue($summaryRow?->total_amount),
            ],
        );
    }

    public function getCashBook(array $filters): array
    {
        if (! $filters['account_id']) {
            return $this->emptyResult('account_required');
        }

        $query = DB::table('transaction_lines as tl')
            ->join('transactions as t', function ($join) use ($filters) {
                $join->on('t.id', '=', 'tl.transaction_id')
                    ->where('t.branch_id', '=', $filters['branch_id'])
                    ->where('t.status', '=', TransactionStatus::POSTED->value)
                    ->whereNull('t.deleted_at');
            })
            ->join('accounts as a', function ($join) use ($filters) {
                $join->on('a.id', '=', 'tl.account_id')
                    ->where('a.branch_id', '=', $filters['branch_id'])
                    ->whereNull('a.deleted_at');
            })
            ->join('account_types as at', 'at.id', '=', 'a.account_type_id')
            ->whereNull('tl.deleted_at')
            ->where('at.slug', 'cash-or-bank')
            ->where('a.id', $filters['account_id']);

        $this->applyDateFilter($query, 't.date', $filters);

        $summaryRow = (clone $query)
            ->selectRaw('COALESCE(SUM(tl.debit * t.rate), 0) as total_debit')
            ->selectRaw('COALESCE(SUM(tl.credit * t.rate), 0) as total_credit')
            ->selectRaw('COALESCE(SUM((tl.debit - tl.credit) * t.rate), 0) as balance')
            ->first();

        $query
            ->orderBy('t.date')
            ->orderBy('t.created_at')
            ->orderBy('t.id')
            ->orderBy('tl.id')
            ->selectRaw('t.date')
            ->selectRaw('COALESCE(t.voucher_number, \'-\') as reference')
            ->selectRaw("COALESCE(NULLIF(tl.remark, ''), NULLIF(t.remark, ''), '') as description")
            ->selectRaw('COALESCE(tl.debit * t.rate, 0) as debit')
            ->selectRaw('COALESCE(tl.credit * t.rate, 0) as credit')
            ->selectRaw('SUM((tl.debit - tl.credit) * t.rate) OVER (ORDER BY t.date, t.created_at, t.id, tl.id) as running_balance');

        return $this->paginateReport(
            $query,
            $filters,
            fn ($row) => [
                'date' => $this->displayDate($row->date),
                'reference' => $row->reference,
                'description' => $row->description,
                'debit' => $this->moneyValue($row->debit),
                'credit' => $this->moneyValue($row->credit),
                'running_balance' => $this->moneyValue($row->running_balance),
                'running_balance_label' => $this->formatBalance($row->running_balance),
            ],
            [
                'total_debit' => $this->moneyValue($summaryRow?->total_debit),
                'total_credit' => $this->moneyValue($summaryRow?->total_credit),
                'balance' => $this->moneyValue($summaryRow?->balance),
                'balance_label' => $this->formatBalance($summaryRow?->balance),
            ],
        );
    }

    public function getSalesReport(array $filters): array
    {
        $query = DB::table('sales as s')
            ->join('transactions as t', function ($join) use ($filters) {
                $join->on('t.reference_id', '=', 's.id')
                    ->where('t.reference_type', '=', Sale::class)
                    ->where('t.branch_id', '=', $filters['branch_id'])
                    ->where('t.status', '=', TransactionStatus::POSTED->value)
                    ->whereNull('t.deleted_at');
            })
            ->join('sale_items as si', function ($join) use ($filters) {
                $join->on('si.sale_id', '=', 's.id')
                    ->where('si.branch_id', '=', $filters['branch_id'])
                    ->whereNull('si.deleted_at');
            })
            ->join('items as i', function ($join) use ($filters) {
                $join->on('i.id', '=', 'si.item_id')
                    ->where('i.branch_id', '=', $filters['branch_id'])
                    ->whereNull('i.deleted_at');
            })
            ->leftJoin('ledgers as l', function ($join) use ($filters) {
                $join->on('l.id', '=', 's.customer_id')
                    ->where('l.branch_id', '=', $filters['branch_id'])
                    ->whereNull('l.deleted_at');
            })
            ->where('s.branch_id', $filters['branch_id'])
            ->whereNull('s.deleted_at')
            ->when($filters['item_id'], fn ($builder, $itemId) => $builder->where('si.item_id', $itemId));

        $this->applyDateFilter($query, 's.date', $filters);

        $summaryRow = (clone $query)
            ->selectRaw('COALESCE(SUM(si.quantity), 0) as total_quantity')
            ->selectRaw('COALESCE(SUM(si.quantity * si.unit_price), 0) as total_amount')
            ->first();

        $query
            ->orderByDesc('s.date')
            ->orderByDesc('s.created_at')
            ->orderByDesc('si.id')
            ->selectRaw('s.date')
            ->selectRaw('s.number as sale_number')
            ->selectRaw('COALESCE(l.name, \'-\') as customer')
            ->selectRaw('i.name as item')
            ->selectRaw('si.quantity as quantity')
            ->selectRaw('si.unit_price as unit_price')
            ->selectRaw('COALESCE(si.quantity * si.unit_price, 0) as total_amount');

        return $this->paginateReport(
            $query,
            $filters,
            fn ($row) => [
                'date' => $this->displayDate($row->date),
                'sale_number' => $row->sale_number,
                'customer' => $row->customer,
                'item' => $row->item,
                'quantity' => $this->quantityValue($row->quantity),
                'unit_price' => $this->moneyValue($row->unit_price),
                'total_amount' => $this->moneyValue($row->total_amount),
            ],
            [
                'total_quantity' => $this->quantityValue($summaryRow?->total_quantity),
                'total_amount' => $this->moneyValue($summaryRow?->total_amount),
            ],
        );
    }

    public function getPurchaseReport(array $filters): array
    {
        $query = DB::table('purchases as p')
            ->join('transactions as t', function ($join) use ($filters) {
                $join->on('t.reference_id', '=', 'p.id')
                    ->where('t.reference_type', '=', Purchase::class)
                    ->where('t.branch_id', '=', $filters['branch_id'])
                    ->where('t.status', '=', TransactionStatus::POSTED->value)
                    ->whereNull('t.deleted_at');
            })
            ->join('purchase_items as pi', function ($join) use ($filters) {
                $join->on('pi.purchase_id', '=', 'p.id')
                    ->where('pi.branch_id', '=', $filters['branch_id'])
                    ->whereNull('pi.deleted_at');
            })
            ->join('items as i', function ($join) use ($filters) {
                $join->on('i.id', '=', 'pi.item_id')
                    ->where('i.branch_id', '=', $filters['branch_id'])
                    ->whereNull('i.deleted_at');
            })
            ->leftJoin('ledgers as l', function ($join) use ($filters) {
                $join->on('l.id', '=', 'p.supplier_id')
                    ->where('l.branch_id', '=', $filters['branch_id'])
                    ->whereNull('l.deleted_at');
            })
            ->where('p.branch_id', $filters['branch_id'])
            ->whereNull('p.deleted_at')
            ->when($filters['item_id'], fn ($builder, $itemId) => $builder->where('pi.item_id', $itemId));

        $this->applyDateFilter($query, 'p.date', $filters);

        $summaryRow = (clone $query)
            ->selectRaw('COALESCE(SUM(pi.quantity), 0) as total_quantity')
            ->selectRaw('COALESCE(SUM(pi.quantity * pi.unit_price), 0) as total_amount')
            ->first();

        $query
            ->orderByDesc('p.date')
            ->orderByDesc('p.created_at')
            ->orderByDesc('pi.id')
            ->selectRaw('p.date')
            ->selectRaw('p.number as purchase_number')
            ->selectRaw('COALESCE(l.name, \'-\') as supplier')
            ->selectRaw('i.name as item')
            ->selectRaw('pi.quantity as quantity')
            ->selectRaw('pi.unit_price as unit_price')
            ->selectRaw('COALESCE(pi.quantity * pi.unit_price, 0) as total_amount');

        return $this->paginateReport(
            $query,
            $filters,
            fn ($row) => [
                'date' => $this->displayDate($row->date),
                'purchase_number' => $row->purchase_number,
                'supplier' => $row->supplier,
                'item' => $row->item,
                'quantity' => $this->quantityValue($row->quantity),
                'unit_price' => $this->moneyValue($row->unit_price),
                'total_amount' => $this->moneyValue($row->total_amount),
            ],
            [
                'total_quantity' => $this->quantityValue($summaryRow?->total_quantity),
                'total_amount' => $this->moneyValue($summaryRow?->total_amount),
            ],
        );
    }

    public function getInventoryStock(array $filters): array
    {
        $query = $this->activeStockBalanceQuery($filters)
            ->join('items as i', function ($join) use ($filters) {
                $join->on('i.id', '=', 'sb.item_id')
                    ->where('i.branch_id', '=', $filters['branch_id'])
                    ->whereNull('i.deleted_at');
            })
            ->join('warehouses as w', function ($join) use ($filters) {
                $join->on('w.id', '=', 'sb.warehouse_id')
                    ->where('w.branch_id', '=', $filters['branch_id'])
                    ->whereNull('w.deleted_at');
            })
            ->when($filters['item_id'], fn ($builder, $itemId) => $builder->where('sb.item_id', $itemId));

        $summaryRow = (clone $query)
            ->selectRaw('COALESCE(SUM(sb.quantity), 0) as total_quantity')
            ->selectRaw('COALESCE(SUM(sb.quantity * COALESCE(sb.average_cost, 0)), 0) as total_value')
            ->first();

        $query
            ->groupBy('i.id', 'i.name', 'w.id', 'w.name')
            ->orderBy('i.name')
            ->orderBy('w.name')
            ->selectRaw('i.name as item')
            ->selectRaw('w.name as warehouse')
            ->selectRaw('COALESCE(SUM(sb.quantity), 0) as quantity')
            ->selectRaw('COALESCE(SUM(sb.quantity * COALESCE(sb.average_cost, 0)) / NULLIF(SUM(sb.quantity), 0), 0) as average_cost')
            ->selectRaw('COALESCE(SUM(sb.quantity * COALESCE(sb.average_cost, 0)), 0) as total_value');

        return $this->paginateReport(
            $query,
            $filters,
            fn ($row) => [
                'item' => $row->item,
                'warehouse' => $row->warehouse,
                'quantity' => $this->quantityValue($row->quantity),
                'average_cost' => $this->moneyValue($row->average_cost),
                'total_value' => $this->moneyValue($row->total_value),
            ],
            [
                'total_quantity' => $this->quantityValue($summaryRow?->total_quantity),
                'total_value' => $this->moneyValue($summaryRow?->total_value),
            ],
        );
    }

    public function getStockMovements(array $filters): array
    {
        $query = DB::table('stock_movements as sm')
            ->join('items as i', function ($join) use ($filters) {
                $join->on('i.id', '=', 'sm.item_id')
                    ->where('i.branch_id', '=', $filters['branch_id'])
                    ->whereNull('i.deleted_at');
            })
            ->join('warehouses as w', function ($join) use ($filters) {
                $join->on('w.id', '=', 'sm.warehouse_id')
                    ->where('w.branch_id', '=', $filters['branch_id'])
                    ->whereNull('w.deleted_at');
            })
            ->where('sm.branch_id', $filters['branch_id'])
            ->whereNull('sm.deleted_at')
            ->when($filters['item_id'], fn ($builder, $itemId) => $builder->where('sm.item_id', $itemId));

        $this->applyDateFilter($query, 'sm.date', $filters);

        $summaryRow = (clone $query)
            ->selectRaw('COALESCE(SUM(sm.quantity), 0) as total_quantity')
            ->first();

        $query
            ->orderByDesc('sm.date')
            ->orderByDesc('sm.created_at')
            ->selectRaw('sm.date')
            ->selectRaw('i.name as item')
            ->selectRaw('w.name as warehouse')
            ->selectRaw('sm.movement_type')
            ->selectRaw('sm.quantity')
            ->selectRaw('sm.unit_cost as unit_price')
            ->selectRaw('sm.source as source_type')
            ->selectRaw('sm.reference_type')
            ->selectRaw('sm.reference_id');

        return $this->paginateReport(
            $query,
            $filters,
            fn ($row) => [
                'date' => $this->displayDate($row->date),
                'item' => $row->item,
                'warehouse' => $row->warehouse,
                'movement_type' => $row->movement_type,
                'quantity' => $this->quantityValue($row->quantity),
                'unit_price' => $this->moneyValue($row->unit_price),
                'source_type' => $this->sourceLabel($row->source_type),
                'reference_type' => $this->referenceLabel($row->reference_type),
                'reference_id' => $row->reference_id,
            ],
            [
                'total_quantity' => $this->quantityValue($summaryRow?->total_quantity),
            ],
        );
    }

    public function getLowStock(array $filters): array
    {
        $query = $this->activeStockBalanceQuery($filters)
            ->join('items as i', function ($join) use ($filters) {
                $join->on('i.id', '=', 'sb.item_id')
                    ->where('i.branch_id', '=', $filters['branch_id'])
                    ->whereNull('i.deleted_at');
            })
            ->join('warehouses as w', function ($join) use ($filters) {
                $join->on('w.id', '=', 'sb.warehouse_id')
                    ->where('w.branch_id', '=', $filters['branch_id'])
                    ->whereNull('w.deleted_at');
            })
            ->when($filters['item_id'], fn ($builder, $itemId) => $builder->where('sb.item_id', $itemId))
            ->whereNotNull('i.minimum_stock')
            ->groupBy('i.id', 'i.name', 'w.id', 'w.name', 'i.minimum_stock')
            ->havingRaw('COALESCE(SUM(sb.quantity), 0) <= i.minimum_stock');

        $summaryRow = DB::query()
            ->fromSub(
                (clone $query)->selectRaw('1 as row_marker'),
                'low_stock_rows'
            )
            ->selectRaw('COUNT(*) as total_items')
            ->first();

        $query
            ->orderBy('i.name')
            ->orderBy('w.name')
            ->selectRaw('i.name as item')
            ->selectRaw('w.name as warehouse')
            ->selectRaw('COALESCE(SUM(sb.quantity), 0) as quantity')
            ->selectRaw('i.minimum_stock as reorder_level');

        return $this->paginateReport(
            $query,
            $filters,
            fn ($row) => [
                'item' => $row->item,
                'warehouse' => $row->warehouse,
                'quantity' => $this->quantityValue($row->quantity),
                'reorder_level' => $this->quantityValue($row->reorder_level),
            ],
            [
                'total_items' => (int) ($summaryRow?->total_items ?? 0),
            ],
        );
    }

    public function getInventoryValuation(array $filters): array
    {
        $query = $this->activeStockBalanceQuery($filters)
            ->join('items as i', function ($join) use ($filters) {
                $join->on('i.id', '=', 'sb.item_id')
                    ->where('i.branch_id', '=', $filters['branch_id'])
                    ->whereNull('i.deleted_at');
            })
            ->when($filters['item_id'], fn ($builder, $itemId) => $builder->where('sb.item_id', $itemId));

        $summaryRow = (clone $query)
            ->selectRaw('COALESCE(SUM(sb.quantity), 0) as total_quantity')
            ->selectRaw('COALESCE(SUM(sb.quantity * COALESCE(sb.average_cost, 0)), 0) as total_value')
            ->first();

        $query
            ->groupBy('i.id', 'i.name')
            ->orderBy('i.name')
            ->selectRaw('i.name as item')
            ->selectRaw('COALESCE(SUM(sb.quantity), 0) as quantity')
            ->selectRaw('COALESCE(SUM(sb.quantity * COALESCE(sb.average_cost, 0)) / NULLIF(SUM(sb.quantity), 0), 0) as average_cost')
            ->selectRaw('COALESCE(SUM(sb.quantity * COALESCE(sb.average_cost, 0)), 0) as total_value');

        return $this->paginateReport(
            $query,
            $filters,
            fn ($row) => [
                'item' => $row->item,
                'quantity' => $this->quantityValue($row->quantity),
                'average_cost' => $this->moneyValue($row->average_cost),
                'total_value' => $this->moneyValue($row->total_value),
            ],
            [
                'total_quantity' => $this->quantityValue($summaryRow?->total_quantity),
                'total_value' => $this->moneyValue($summaryRow?->total_value),
            ],
        );
    }

    public function getBalanceSheet(array $filters): array
    {
        $balances = $this->accountBalancesToDate($filters);

        $assets = $this->statementSectionRows($balances, [
            'cash-or-bank',
            'account-receivable',
            'other-current-asset',
            'fixed-asset',
        ], false);

        $liabilities = $this->statementSectionRows($balances, [
            'account-payable',
            'other-current-liability',
            'long-term-liability',
        ], true);

        $equity = $this->statementSectionRows($balances, ['equity'], true);

        $retainedEarnings = $this->yearToDateNetProfit($filters);
        if (abs($retainedEarnings) > 0.0001) {
            $equity[] = [
                'account_name' => 'Current Period Earnings',
                'balance' => $this->moneyValue($retainedEarnings),
            ];
        }

        $totalAssets = $this->moneyValue(collect($assets)->sum('balance'));
        $totalLiabilities = $this->moneyValue(collect($liabilities)->sum('balance'));
        $totalEquity = $this->moneyValue(collect($equity)->sum('balance'));

        return [
            'rows' => [],
            'pagination' => $this->singlePagePagination(),
            'summary' => [
                'total_assets' => $totalAssets,
                'total_liabilities' => $totalLiabilities,
                'total_equity' => $totalEquity,
                'equation_total' => $this->moneyValue($totalLiabilities + $totalEquity),
            ],
            'meta' => [
                'layout' => 'statement',
                'sections' => [
                    ['key' => 'assets', 'label' => 'Assets', 'rows' => $assets],
                    ['key' => 'liabilities', 'label' => 'Liabilities', 'rows' => $liabilities],
                    ['key' => 'equity', 'label' => 'Equity', 'rows' => $equity],
                ],
            ],
        ];
    }

    public function getIncomeStatement(array $filters): array
    {
        $balances = $this->accountBalancesForPeriod($filters);

        $revenue = $this->statementSectionRows($balances, ['income'], true);
        $costOfGoodsSold = $this->statementSectionRows($balances, ['cost-of-goods-sold'], false);
        $expenses = $this->statementSectionRows($balances, ['expense'], false);

        $totalRevenue = $this->moneyValue(collect($revenue)->sum('balance'));
        $totalCostOfGoodsSold = $this->moneyValue(collect($costOfGoodsSold)->sum('balance'));
        $grossProfit = $this->moneyValue($totalRevenue - $totalCostOfGoodsSold);
        $totalExpenses = $this->moneyValue(collect($expenses)->sum('balance'));
        $netProfit = $this->moneyValue($grossProfit - $totalExpenses);

        return [
            'rows' => [],
            'pagination' => $this->singlePagePagination(),
            'summary' => [
                'total_revenue' => $totalRevenue,
                'total_cost_of_goods_sold' => $totalCostOfGoodsSold,
                'gross_profit' => $grossProfit,
                'total_expenses' => $totalExpenses,
                'net_profit' => $netProfit,
            ],
            'meta' => [
                'layout' => 'statement',
                'sections' => [
                    ['key' => 'revenue', 'label' => 'Revenue', 'rows' => $revenue],
                    ['key' => 'cost_of_goods_sold', 'label' => 'Cost of Goods Sold', 'rows' => $costOfGoodsSold],
                    ['key' => 'expenses', 'label' => 'Expenses', 'rows' => $expenses],
                ],
            ],
        ];
    }

    protected function ledgerStatementQuery(array $filters, string $ledgerId, ?string $ledgerType = null): Builder
    {
        $query = DB::table('transaction_lines as tl')
            ->join('transactions as t', function ($join) use ($filters) {
                $join->on('t.id', '=', 'tl.transaction_id')
                    ->where('t.branch_id', '=', $filters['branch_id'])
                    ->where('t.status', '=', TransactionStatus::POSTED->value)
                    ->whereNull('t.deleted_at');
            })
            ->join('ledgers as l', function ($join) use ($filters, $ledgerId, $ledgerType) {
                $join->on('l.id', '=', 'tl.ledger_id')
                    ->where('l.branch_id', '=', $filters['branch_id'])
                    ->where('l.id', '=', $ledgerId)
                    ->whereNull('l.deleted_at');

                if ($ledgerType) {
                    $join->where('l.type', '=', $ledgerType);
                }
            })
            ->whereNull('tl.deleted_at');

        $this->applyDateFilter($query, 't.date', $filters);

        return $query
            ->orderBy('t.date')
            ->orderBy('t.created_at')
            ->orderBy('t.id')
            ->orderBy('tl.id')
            ->selectRaw('t.date')
            ->selectRaw('COALESCE(t.voucher_number, \'-\') as transaction_number')
            ->selectRaw('t.reference_type')
            ->selectRaw("COALESCE(NULLIF(tl.remark, ''), NULLIF(t.remark, ''), '') as description")
            ->selectRaw('COALESCE(tl.debit * t.rate, 0) as debit')
            ->selectRaw('COALESCE(tl.credit * t.rate, 0) as credit')
            ->selectRaw('SUM((tl.debit - tl.credit) * t.rate) OVER (ORDER BY t.date, t.created_at, t.id, tl.id) as running_balance');
    }

    protected function ledgerStatementSummary(array $filters, string $ledgerId, ?string $ledgerType = null): array
    {
        $query = DB::table('transaction_lines as tl')
            ->join('transactions as t', function ($join) use ($filters) {
                $join->on('t.id', '=', 'tl.transaction_id')
                    ->where('t.branch_id', '=', $filters['branch_id'])
                    ->where('t.status', '=', TransactionStatus::POSTED->value)
                    ->whereNull('t.deleted_at');
            })
            ->join('ledgers as l', function ($join) use ($filters, $ledgerId, $ledgerType) {
                $join->on('l.id', '=', 'tl.ledger_id')
                    ->where('l.branch_id', '=', $filters['branch_id'])
                    ->where('l.id', '=', $ledgerId)
                    ->whereNull('l.deleted_at');

                if ($ledgerType) {
                    $join->where('l.type', '=', $ledgerType);
                }
            })
            ->whereNull('tl.deleted_at');

        $this->applyDateFilter($query, 't.date', $filters);

        $row = $query
            ->selectRaw('COALESCE(SUM(tl.debit * t.rate), 0) as total_debit')
            ->selectRaw('COALESCE(SUM(tl.credit * t.rate), 0) as total_credit')
            ->selectRaw('COALESCE(SUM((tl.debit - tl.credit) * t.rate), 0) as balance')
            ->first();

        return [
            'total_debit' => $this->moneyValue($row?->total_debit),
            'total_credit' => $this->moneyValue($row?->total_credit),
            'balance' => $this->moneyValue($row?->balance),
            'balance_label' => $this->formatBalance($row?->balance),
        ];
    }

    protected function counterpartyNamesSubquery(array $filters): Builder
    {
        $query = DB::table('transaction_lines as tl2')
            ->join('transactions as t2', function ($join) use ($filters) {
                $join->on('t2.id', '=', 'tl2.transaction_id')
                    ->where('t2.branch_id', '=', $filters['branch_id'])
                    ->where('t2.status', '=', TransactionStatus::POSTED->value)
                    ->whereNull('t2.deleted_at');
            })
            ->join('accounts as a2', function ($join) use ($filters) {
                $join->on('a2.id', '=', 'tl2.account_id')
                    ->where('a2.branch_id', '=', $filters['branch_id'])
                    ->whereNull('a2.deleted_at');
            })
            ->join('account_types as at2', 'at2.id', '=', 'a2.account_type_id')
            ->leftJoin('ledgers as l2', function ($join) use ($filters) {
                $join->on('l2.id', '=', 'tl2.ledger_id')
                    ->where('l2.branch_id', '=', $filters['branch_id'])
                    ->whereNull('l2.deleted_at');
            })
            ->whereNull('tl2.deleted_at')
            ->where('at2.slug', '!=', 'cash-or-bank')
            ->groupBy('tl2.transaction_id')
            ->selectRaw("tl2.transaction_id, STRING_AGG(DISTINCT COALESCE(l2.name, a2.name), ', ') as ledger_name");

        $this->applyDateFilter($query, 't2.date', $filters);

        return $query;
    }

    protected function accountBalancesToDate(array $filters)
    {
        return $this->accountBalanceQuery($filters)
            ->whereDate('t.date', '<=', $filters['date_to'])
            ->get();
    }

    protected function accountBalancesForPeriod(array $filters)
    {
        return tap($this->accountBalanceQuery($filters), function ($query) use ($filters) {
            $this->applyDateFilter($query, 't.date', $filters);
        })->get();
    }

    protected function accountBalanceQuery(array $filters): Builder
    {
        return DB::table('transaction_lines as tl')
            ->join('transactions as t', function ($join) use ($filters) {
                $join->on('t.id', '=', 'tl.transaction_id')
                    ->where('t.branch_id', '=', $filters['branch_id'])
                    ->where('t.status', '=', TransactionStatus::POSTED->value)
                    ->whereNull('t.deleted_at');
            })
            ->join('accounts as a', function ($join) use ($filters) {
                $join->on('a.id', '=', 'tl.account_id')
                    ->where('a.branch_id', '=', $filters['branch_id'])
                    ->whereNull('a.deleted_at');
            })
            ->join('account_types as at', 'at.id', '=', 'a.account_type_id')
            ->whereNull('tl.deleted_at')
            ->groupBy('a.id', 'a.name', 'a.slug', 'at.slug')
            ->selectRaw('a.id as account_id')
            ->selectRaw('a.name as account_name')
            ->selectRaw('a.slug as account_slug')
            ->selectRaw('at.slug as account_type_slug')
            ->selectRaw('COALESCE(SUM((tl.debit - tl.credit) * t.rate), 0) as raw_balance');
    }

    protected function statementSectionRows($balances, array $typeSlugs, bool $reverseSign): array
    {
        return collect($balances)
            ->filter(fn ($row) => in_array($row->account_type_slug, $typeSlugs, true))
            ->map(function ($row) use ($reverseSign) {
                $balance = $reverseSign ? -1 * (float) $row->raw_balance : (float) $row->raw_balance;

                return [
                    'account_name' => $row->account_name,
                    'balance' => $this->moneyValue($balance),
                ];
            })
            ->filter(fn ($row) => abs($row['balance']) > 0.0001)
            ->values()
            ->all();
    }

    protected function yearToDateNetProfit(array $filters): float
    {
        $yearStart = Carbon::parse($filters['date_to'])->startOfYear()->toDateString();
        $yearFilters = array_merge($filters, [
            'date_from' => $yearStart,
        ]);

        $balances = $this->accountBalancesForPeriod($yearFilters);

        $revenue = collect($balances)
            ->filter(fn ($row) => $row->account_type_slug === 'income')
            ->sum(fn ($row) => -1 * (float) $row->raw_balance);

        $costOfGoodsSold = collect($balances)
            ->filter(fn ($row) => $row->account_type_slug === 'cost-of-goods-sold')
            ->sum(fn ($row) => (float) $row->raw_balance);

        $expenses = collect($balances)
            ->filter(fn ($row) => $row->account_type_slug === 'expense')
            ->sum(fn ($row) => (float) $row->raw_balance);

        return $this->moneyValue($revenue - $costOfGoodsSold - $expenses);
    }

    protected function activeStockBalanceQuery(array $filters): Builder
    {
        return DB::table('stock_balances as sb')
            ->where('sb.branch_id', $filters['branch_id'])
            ->whereNull('sb.deleted_at')
            ->whereNotIn('sb.status', [StockStatus::VOIDED->value, StockStatus::CANCELLED->value]);
    }

    protected function paginateReport(Builder $query, array $filters, callable $transformer, array $summary = [], array $meta = []): array
    {
        $paginator = $query->paginate(
            perPage: $filters['per_page'],
            columns: ['*'],
            pageName: 'page',
            page: $filters['page'],
        );

        $paginator->setCollection(
            $paginator->getCollection()->map($transformer)->values()
        );

        return [
            'rows' => $paginator->items(),
            'pagination' => $this->paginationArray($paginator),
            'summary' => $summary,
            'meta' => $meta,
        ];
    }

    protected function singlePagePagination(): array
    {
        return [
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => 1,
            'total' => 0,
            'from' => null,
            'to' => null,
            'has_more_pages' => false,
        ];
    }

    protected function statementExportData(string $reportKey, array $result): array
    {
        $rows = [];

        foreach (($result['meta']['sections'] ?? []) as $section) {
            $rows[] = [
                'section' => $section['label'],
                'account_name' => '',
                'balance' => '',
            ];

            foreach (($section['rows'] ?? []) as $row) {
                $rows[] = [
                    'section' => '',
                    'account_name' => $row['account_name'] ?? '',
                    'balance' => $row['balance'] ?? '',
                ];
            }

            $rows[] = [
                'section' => '',
                'account_name' => '',
                'balance' => '',
            ];
        }

        return [
            'filename' => $reportKey.'-'.now()->format('Ymd-His').'.csv',
            'headings' => ['section', 'account_name', 'balance'],
            'rows' => $rows,
        ];
    }

    protected function defaultExportHeadings(string $reportKey): array
    {
        return match ($reportKey) {
            'trial_balance' => ['ledger_id', 'ledger_name', 'total_debit', 'total_credit', 'balance', 'balance_label'],
            'general_ledger' => ['date', 'transaction_number', 'reference_type', 'description', 'debit', 'credit', 'running_balance', 'running_balance_label'],
            'customer_statement', 'supplier_statement' => ['date', 'reference', 'description', 'debit', 'credit', 'running_balance', 'balance'],
            'receipt_report' => ['date', 'transaction_number', 'ledger_name', 'description', 'amount_received'],
            'payment_report' => ['date', 'transaction_number', 'ledger_name', 'description', 'amount_paid'],
            'cash_book' => ['date', 'reference', 'description', 'debit', 'credit', 'running_balance', 'running_balance_label'],
            'sales_report' => ['date', 'sale_number', 'customer', 'item', 'quantity', 'unit_price', 'total_amount'],
            'purchase_report' => ['date', 'purchase_number', 'supplier', 'item', 'quantity', 'unit_price', 'total_amount'],
            'inventory_stock' => ['item', 'warehouse', 'quantity', 'average_cost', 'total_value'],
            'stock_movement' => ['date', 'item', 'warehouse', 'movement_type', 'quantity', 'unit_price', 'source_type', 'reference_type', 'reference_id'],
            'low_stock' => ['item', 'warehouse', 'quantity', 'reorder_level'],
            'inventory_valuation' => ['item', 'quantity', 'average_cost', 'total_value'],
            default => ['value'],
        };
    }

    protected function emptyResult(string $messageKey): array
    {
        return [
            'rows' => [],
            'pagination' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 25,
                'total' => 0,
                'from' => null,
                'to' => null,
                'has_more_pages' => false,
            ],
            'summary' => [],
            'meta' => [
                'requires_filter' => true,
                'message_key' => $messageKey,
            ],
        ];
    }

    protected function paginationArray(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'has_more_pages' => $paginator->hasMorePages(),
        ];
    }

    protected function normalizeFilters(array $filters, ?Authenticatable $user): array
    {
        $branchId = (string) ($filters['branch_id'] ?? $this->resolveBranchId($user));
        $today = Carbon::today()->toDateString();
        $defaultFrom = Carbon::today()->startOfMonth()->toDateString();

        $dateFrom = ! empty($filters['date_from'])
            ? $this->dateConversionService->toGregorian((string) $filters['date_from'])
            : $defaultFrom;

        $dateTo = ! empty($filters['date_to'])
            ? $this->dateConversionService->toGregorian((string) $filters['date_to'])
            : $today;

        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $requestedReport = (string) ($filters['report'] ?? 'trial_balance');
        $report = in_array($requestedReport, self::REPORT_KEYS, true)
            ? $requestedReport
            : 'trial_balance';

        $requestedPerPage = (int) ($filters['per_page'] ?? 25);
        $perPage = in_array($requestedPerPage, [15, 25, 50, 100], true)
            ? $requestedPerPage
            : 25;

        return [
            'report' => $report,
            'branch_id' => $branchId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'ledger_id' => $this->nullableString($filters['ledger_id'] ?? null),
            'customer_id' => $this->nullableString($filters['customer_id'] ?? null),
            'supplier_id' => $this->nullableString($filters['supplier_id'] ?? null),
            'item_id' => $this->nullableString($filters['item_id'] ?? null),
            'account_id' => $this->nullableString($filters['account_id'] ?? null),
            'per_page' => $perPage,
            'page' => max(1, (int) ($filters['page'] ?? 1)),
        ];
    }

    protected function filterOptions(string $branchId): array
    {
        return [
            'branches' => Branch::query()
                ->whereNull('deleted_at')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($branch) => ['id' => $branch->id, 'name' => $branch->name])
                ->all(),
            'ledgers' => DB::table('ledgers')
                ->where('branch_id', $branchId)
                ->whereNull('deleted_at')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($row) => ['id' => $row->id, 'name' => $row->name])
                ->all(),
            'customers' => DB::table('ledgers')
                ->where('branch_id', $branchId)
                ->where('type', LedgerType::CUSTOMER->value)
                ->whereNull('deleted_at')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($row) => ['id' => $row->id, 'name' => $row->name])
                ->all(),
            'suppliers' => DB::table('ledgers')
                ->where('branch_id', $branchId)
                ->where('type', LedgerType::SUPPLIER->value)
                ->whereNull('deleted_at')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($row) => ['id' => $row->id, 'name' => $row->name])
                ->all(),
            'items' => DB::table('items')
                ->where('branch_id', $branchId)
                ->whereNull('deleted_at')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($row) => ['id' => $row->id, 'name' => $row->name])
                ->all(),
            'cash_accounts' => DB::table('accounts as a')
                ->join('account_types as at', 'at.id', '=', 'a.account_type_id')
                ->where('a.branch_id', $branchId)
                ->where('at.slug', 'cash-or-bank')
                ->whereNull('a.deleted_at')
                ->orderBy('a.name')
                ->get(['a.id', 'a.name'])
                ->map(fn ($row) => ['id' => $row->id, 'name' => $row->name])
                ->all(),
        ];
    }

    protected function reportOptions(): array
    {
        return collect(self::REPORT_KEYS)
            ->map(fn ($key) => ['key' => $key])
            ->all();
    }

    protected function applyDateFilter(Builder $query, string $column, array $filters): void
    {
        $query->whereBetween($column, [$filters['date_from'], $filters['date_to']]);
    }

    protected function resolveBranchId(?Authenticatable $user): string
    {
        $branchId = $user?->branch_id ?? auth()->user()?->branch_id;

        if ($branchId) {
            return (string) $branchId;
        }

        return (string) Branch::query()->value('id');
    }

    protected function displayDate(?string $date): ?string
    {
        return $this->dateConversionService->toDisplay($date);
    }

    protected function moneyValue(mixed $value): float
    {
        return round((float) ($value ?? 0), 2);
    }

    protected function quantityValue(mixed $value): float
    {
        return round((float) ($value ?? 0), 2);
    }

    protected function formatBalance(mixed $value): string
    {
        $amount = round((float) ($value ?? 0), 2);

        if ($amount === 0.0) {
            return '0.00';
        }

        return number_format(abs($amount), 2, '.', '').' '.($amount >= 0 ? 'Dr' : 'Cr');
    }

    protected function referenceLabel(?string $referenceType): string
    {
        if (! $referenceType) {
            return '-';
        }

        if (str_contains($referenceType, '\\')) {
            return class_basename($referenceType);
        }

        return str($referenceType)->headline()->toString();
    }

    protected function sourceLabel(?string $sourceType): string
    {
        if (! $sourceType) {
            return '-';
        }

        return str($sourceType)->replace('_', ' ')->headline()->toString();
    }

    protected function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return filled($value) ? (string) $value : null;
    }
}

