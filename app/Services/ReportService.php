<?php

namespace App\Services;

use App\Enums\LedgerType;
use App\Enums\StockMovementType;
use App\Enums\StockStatus;
use App\Enums\TransactionStatus;
use App\Models\Administration\Branch;
use App\Models\Purchase\Purchase;
use App\Models\Sale\Sale;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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
        'batch_wise_report',
        'expiry_wise_report',
        'zero_on_hand_report',
        'fast_moving_report',
        'slow_moving_report',
        'today_sale_purchase_closing_stock_report',
        'near_expiry_report',
        'maximum_stock_report',
        'group_summary_report',
        'day_book_report',
        'journal_book_report',
        'user_activity',
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
            return $this->statementExportData($filters['report'], $result, $user);
        }

        $rows = collect($result['rows'] ?? []);
        $headings = $rows->isNotEmpty()
            ? array_keys($rows->first())
            : $this->defaultExportHeadings($filters['report']);

        return [
            'filename' => $filters['report'] . '-' . now()->format('Ymd-His') . '.xlsx',
            'sheet_name' => $this->reportLabel($filters['report']),
            'sheet_title' => $this->reportLabel($filters['report']),
            'title' => $this->reportLabel($filters['report']),
            'company_name' => $this->exportCompanyName($user),
            'exported_on' => now()->format('Y m d'),
            'rtl' => in_array(app()->getLocale(), ['fa', 'ps'], true),
            'include_row_number' => true,
            'row_number_label' => $this->reportColumnLabel('no'),
            'columns' => collect($headings)
                ->map(fn ($key) => [
                    'key' => $key,
                    'label' => $this->reportColumnLabel($key),
                    'type' => $this->exportColumnType($key),
                ])
                ->values()
                ->all(),
            'rows' => $rows->map(fn ($row) => collect($row)->only($headings)->all())->all(),
        ];
    }    public function getReportData(array $filters): array
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
            'batch_wise_report' => $this->getBatchWiseReport($filters),
            'expiry_wise_report' => $this->getExpiryWiseReport($filters),
            'zero_on_hand_report' => $this->getZeroOnHandReport($filters),
            'fast_moving_report' => $this->getFastMovingReport($filters),
            'slow_moving_report' => $this->getSlowMovingReport($filters),
            'today_sale_purchase_closing_stock_report' => $this->getTodaySalePurchaseClosingStockReport($filters),
            'near_expiry_report' => $this->getNearExpiryReport($filters),
            'maximum_stock_report' => $this->getMaximumStockReport($filters),
            'group_summary_report' => $this->getGroupSummaryReport($filters),
            'day_book_report' => $this->getDayBookReport($filters),
            'journal_book_report' => $this->getJournalBookReport($filters),
            'user_activity' => $this->getUserActivity($filters),
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
            ->selectRaw('sm.id as id')
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
                'id' => $row->id,
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

    public function getBatchWiseReport(array $filters): array
    {
        $movementTotals = $this->stockMovementTotalsByBatchSubquery($filters['branch_id']);

        $query = $this->stockBatchTotalsQuery($filters)
            ->where('i.is_batch_tracked', true)
            ->leftJoinSub($movementTotals, 'sm_totals', function ($join) {
                $join->on('sm_totals.item_id', '=', 'sb.item_id')
                    ->whereRaw('COALESCE(sm_totals.batch, \'\') = COALESCE(sb.batch, \'\')')
                    ->whereRaw('COALESCE(sm_totals.expire_date::text, \'\') = COALESCE(sb.expire_date::text, \'\')');
            })
            ->where(function ($builder) {
                $builder->whereRaw('COALESCE(sb.quantity, 0) <> 0')
                    ->orWhereRaw('COALESCE(sm_totals.total_in, 0) <> 0')
                    ->orWhereRaw('COALESCE(sm_totals.total_out, 0) <> 0');
            })
            ->groupBy('i.id', 'i.code', 'i.name', 'sb.batch', 'sb.expire_date')
            ->selectRaw('i.code as item_code')
            ->selectRaw('i.name as item_name')
            ->selectRaw('sb.batch as batch_number')
            ->selectRaw('sb.expire_date')
            ->selectRaw('COALESCE(SUM(COALESCE(sm_totals.total_in, 0)), 0) as in_quantity')
            ->selectRaw('COALESCE(SUM(COALESCE(sm_totals.total_out, 0)), 0) as out_quantity')
            ->selectRaw('COALESCE(SUM(sb.quantity), 0) as on_hand')
            ->orderBy('i.code')
            ->orderBy('i.name')
            ->orderByRaw('CASE WHEN sb.batch IS NULL THEN 1 ELSE 0 END')
            ->orderBy('sb.batch')
            ->orderBy('sb.expire_date');

        $summaryRow = DB::query()
            ->fromSub(clone $query, 'batch_rows')
            ->selectRaw('COALESCE(SUM(in_quantity), 0) as total_in')
            ->selectRaw('COALESCE(SUM(out_quantity), 0) as total_out')
            ->selectRaw('COALESCE(SUM(on_hand), 0) as total_on_hand')
            ->first();

        return $this->paginateReport(
            $query,
            $filters,
            fn ($row) => [
                'item_code' => $row->item_code,
                'item_name' => $row->item_name,
                'batch_number' => $row->batch_number ?: '-',
                'expiry_date' => $row->expire_date ? $this->displayDate($row->expire_date) : '-',
                'in_quantity' => $this->quantityValue($row->in_quantity),
                'out_quantity' => $this->quantityValue($row->out_quantity),
                'on_hand' => $this->quantityValue($row->on_hand),
            ],
            [
                'total_in' => $this->quantityValue($summaryRow?->total_in),
                'total_out' => $this->quantityValue($summaryRow?->total_out),
                'total_on_hand' => $this->quantityValue($summaryRow?->total_on_hand),
            ],
            ['layout' => 'snapshot'],
        );
    }

    public function getExpiryWiseReport(array $filters): array
    {
        $movementTotals = $this->stockMovementTotalsByExpirySubquery($filters['branch_id']);

        $query = $this->stockExpiryTotalsQuery($filters)
            ->where('i.is_expiry_tracked', true)
            ->leftJoinSub($movementTotals, 'sm_totals', function ($join) {
                $join->on('sm_totals.item_id', '=', 'sb.item_id')
                    ->whereRaw('COALESCE(sm_totals.expire_date::text, \'\') = COALESCE(sb.expire_date::text, \'\')');
            })
            ->where(function ($builder) {
                $builder->whereRaw('COALESCE(sb.quantity, 0) <> 0')
                    ->orWhereRaw('COALESCE(sm_totals.total_in, 0) <> 0')
                    ->orWhereRaw('COALESCE(sm_totals.total_out, 0) <> 0');
            })
            ->groupBy('i.id', 'i.code', 'i.name', 'sb.expire_date')
            ->selectRaw('i.code as item_code')
            ->selectRaw('i.name as item_name')
            ->selectRaw('sb.expire_date')
            ->selectRaw('COALESCE(SUM(COALESCE(sm_totals.total_in, 0)), 0) as in_quantity')
            ->selectRaw('COALESCE(SUM(COALESCE(sm_totals.total_out, 0)), 0) as out_quantity')
            ->selectRaw('COALESCE(SUM(sb.quantity), 0) as on_hand')
            ->orderBy('i.code')
            ->orderBy('i.name')
            ->orderBy('sb.expire_date');

        $summaryRow = DB::query()
            ->fromSub(clone $query, 'expiry_rows')
            ->selectRaw('COALESCE(SUM(in_quantity), 0) as total_in')
            ->selectRaw('COALESCE(SUM(out_quantity), 0) as total_out')
            ->selectRaw('COALESCE(SUM(on_hand), 0) as total_on_hand')
            ->first();

        return $this->paginateReport(
            $query,
            $filters,
            fn ($row) => [
                'item_code' => $row->item_code,
                'item_name' => $row->item_name,
                'expiry_date' => $row->expire_date ? $this->displayDate($row->expire_date) : '-',
                'in_quantity' => $this->quantityValue($row->in_quantity),
                'out_quantity' => $this->quantityValue($row->out_quantity),
                'on_hand' => $this->quantityValue($row->on_hand),
            ],
            [
                'total_in' => $this->quantityValue($summaryRow?->total_in),
                'total_out' => $this->quantityValue($summaryRow?->total_out),
                'total_on_hand' => $this->quantityValue($summaryRow?->total_on_hand),
            ],
            ['layout' => 'snapshot'],
        );
    }

    public function getZeroOnHandReport(array $filters): array
    {
        $stockTotals = $this->stockTotalsByItemSubquery($filters['branch_id']);
        $movementTotals = $this->stockMovementTotalsByItemSubquery($filters['branch_id']);

        $query = DB::table('items as i')
            ->leftJoinSub($stockTotals, 'stock_totals', fn ($join) => $join->on('stock_totals.item_id', '=', 'i.id'))
            ->leftJoinSub($movementTotals, 'movement_totals', fn ($join) => $join->on('movement_totals.item_id', '=', 'i.id'))
            ->where('i.branch_id', $filters['branch_id'])
            ->whereNull('i.deleted_at')
            ->whereRaw('COALESCE(stock_totals.on_hand, 0) = 0')
            ->selectRaw('i.code as item_code')
            ->selectRaw('i.name as item_name')
            ->selectRaw('COALESCE(movement_totals.total_in, 0) as total_in')
            ->selectRaw('COALESCE(movement_totals.total_out, 0) as total_out')
            ->selectRaw('COALESCE(stock_totals.on_hand, 0) as on_hand')
            ->orderBy('i.code')
            ->orderBy('i.name');

        $summaryRow = DB::query()
            ->fromSub(clone $query, 'zero_rows')
            ->selectRaw('COUNT(*) as total_items')
            ->selectRaw('COALESCE(SUM(total_in), 0) as total_in')
            ->selectRaw('COALESCE(SUM(total_out), 0) as total_out')
            ->first();

        return $this->paginateReport(
            $query,
            $filters,
            fn ($row) => [
                'item_code' => $row->item_code,
                'item_name' => $row->item_name,
                'total_in' => $this->quantityValue($row->total_in),
                'total_out' => $this->quantityValue($row->total_out),
                'on_hand' => $this->quantityValue($row->on_hand),
            ],
            [
                'total_items' => (int) ($summaryRow?->total_items ?? 0),
                'total_in' => $this->quantityValue($summaryRow?->total_in),
                'total_out' => $this->quantityValue($summaryRow?->total_out),
            ],
            ['layout' => 'snapshot'],
        );
    }

    public function getFastMovingReport(array $filters): array
    {
        $saleTotals = $this->saleTotalsByItemSubquery($filters);
        $periodDays = $this->periodDays($filters);

        $query = DB::table('items as i')
            ->leftJoinSub($saleTotals, 'sale_totals', fn ($join) => $join->on('sale_totals.item_id', '=', 'i.id'))
            ->where('i.branch_id', $filters['branch_id'])
            ->whereNull('i.deleted_at')
            ->whereRaw('COALESCE(sale_totals.total_sold, 0) > 0')
            ->orderByDesc('sale_totals.total_sold')
            ->orderByDesc('sale_totals.sale_count')
            ->orderBy('i.code')
            ->selectRaw('i.code as item_code')
            ->selectRaw('i.name as item_name')
            ->selectRaw('COALESCE(sale_totals.total_sold, 0) as total_sold')
            ->selectRaw('COALESCE(sale_totals.sale_count, 0) as sale_count')
            ->selectRaw('COALESCE(sale_totals.total_sold, 0) / NULLIF(?, 0) as average_per_day', [$periodDays]);

        $summaryRow = DB::query()
            ->fromSub(clone $query, 'fast_moving_rows')
            ->selectRaw('COALESCE(SUM(total_sold), 0) as total_sold')
            ->selectRaw('COALESCE(SUM(sale_count), 0) as sale_count')
            ->first();

        return $this->paginateReport(
            $query,
            $filters,
            fn ($row) => [
                'item_code' => $row->item_code,
                'item_name' => $row->item_name,
                'total_sold' => $this->quantityValue($row->total_sold),
                'sale_count' => (int) $row->sale_count,
                'average_per_day' => $this->quantityValue($row->average_per_day),
            ],
            [
                'total_sold' => $this->quantityValue($summaryRow?->total_sold),
                'sale_count' => (int) ($summaryRow?->sale_count ?? 0),
                'average_per_day' => $this->quantityValue(($summaryRow?->total_sold ?? 0) / max($periodDays, 1)),
            ],
            ['layout' => 'snapshot'],
        );
    }

    public function getSlowMovingReport(array $filters): array
    {
        $saleTotals = $this->saleTotalsByItemSubquery($filters);
        $firstInDates = $this->stockFirstInDateSubquery($filters['branch_id']);
        $reportEnd = Carbon::parse($filters['date_to']);

        $query = DB::table('items as i')
            ->leftJoinSub($saleTotals, 'sale_totals', fn ($join) => $join->on('sale_totals.item_id', '=', 'i.id'))
            ->leftJoinSub($firstInDates, 'first_stock', fn ($join) => $join->on('first_stock.item_id', '=', 'i.id'))
            ->where('i.branch_id', $filters['branch_id'])
            ->whereNull('i.deleted_at')
            ->whereRaw('COALESCE(sale_totals.total_sold, 0) > 0')
            ->orderBy('sale_totals.total_sold')
            ->orderBy('sale_totals.sale_count')
            ->orderBy('i.code')
            ->selectRaw('i.code as item_code')
            ->selectRaw('i.name as item_name')
            ->selectRaw('COALESCE(sale_totals.total_sold, 0) as total_sold')
            ->selectRaw('COALESCE(sale_totals.sale_count, 0) as sale_count')
            ->selectRaw("COALESCE(first_stock.first_stock_in_date, i.created_at) as first_stock_in_date")
            ->selectRaw('GREATEST(1, COALESCE(DATE_PART(\'day\', ?::timestamp - COALESCE(first_stock.first_stock_in_date, i.created_at)::timestamp), 0)) as days_on_hand', [$reportEnd->toDateTimeString()])
            ->selectRaw('COALESCE(sale_totals.total_sold, 0) / NULLIF(GREATEST(1, COALESCE(DATE_PART(\'day\', ?::timestamp - COALESCE(first_stock.first_stock_in_date, i.created_at)::timestamp), 0)), 0) as turnover_rate', [$reportEnd->toDateTimeString()]);

        $summaryRow = DB::query()
            ->fromSub(clone $query, 'slow_moving_rows')
            ->selectRaw('COALESCE(SUM(total_sold), 0) as total_sold')
            ->selectRaw('COALESCE(SUM(sale_count), 0) as sale_count')
            ->first();

        return $this->paginateReport(
            $query,
            $filters,
            fn ($row) => [
                'item_code' => $row->item_code,
                'item_name' => $row->item_name,
                'total_sold' => $this->quantityValue($row->total_sold),
                'sale_count' => (int) $row->sale_count,
                'days_on_hand' => (int) $row->days_on_hand,
                'turnover_rate' => round((float) $row->turnover_rate, 4),
            ],
            [
                'total_sold' => $this->quantityValue($summaryRow?->total_sold),
                'sale_count' => (int) ($summaryRow?->sale_count ?? 0),
                'days_on_hand' => (int) max(1, Carbon::parse($filters['date_to'])->diffInDays(Carbon::parse($filters['date_from'])) + 1),
                'turnover_rate' => round((float) (($summaryRow?->total_sold ?? 0) / max(1, Carbon::parse($filters['date_to'])->diffInDays(Carbon::parse($filters['date_from'])) + 1)), 4),
            ],
            ['layout' => 'snapshot'],
        );
    }

    public function getTodaySalePurchaseClosingStockReport(array $filters): array
    {
        $today = Carbon::parse($filters['date_to'])->toDateString();
        $stockTotals = $this->stockTotalsByItemSubquery($filters['branch_id']);
        $todayMovements = $this->todayStockMovementTotalsByItemSubquery($filters['branch_id'], $today);

        $query = DB::table('items as i')
            ->leftJoinSub($stockTotals, 'stock_totals', fn ($join) => $join->on('stock_totals.item_id', '=', 'i.id'))
            ->leftJoinSub($todayMovements, 'today_totals', fn ($join) => $join->on('today_totals.item_id', '=', 'i.id'))
            ->where('i.branch_id', $filters['branch_id'])
            ->whereNull('i.deleted_at')
            ->whereRaw('COALESCE(stock_totals.on_hand, 0) <> 0 OR COALESCE(today_totals.purchase_today, 0) <> 0 OR COALESCE(today_totals.sale_today, 0) <> 0')
            ->orderBy('i.code')
            ->orderBy('i.name')
            ->selectRaw('i.code as item_code')
            ->selectRaw('i.name as item_name')
            ->selectRaw('COALESCE(stock_totals.on_hand, 0) as current_on_hand')
            ->selectRaw('COALESCE(today_totals.purchase_today, 0) as purchase_today')
            ->selectRaw('COALESCE(today_totals.sale_today, 0) as sale_today')
            ->selectRaw('COALESCE(stock_totals.on_hand, 0) - COALESCE(today_totals.purchase_today, 0) + COALESCE(today_totals.sale_today, 0) as opening_balance')
            ->selectRaw('COALESCE(stock_totals.on_hand, 0) as closing_balance');

        $summaryRow = DB::query()
            ->fromSub(clone $query, 'today_stock_rows')
            ->selectRaw('COALESCE(SUM(opening_balance), 0) as opening_balance')
            ->selectRaw('COALESCE(SUM(purchase_today), 0) as purchase_today')
            ->selectRaw('COALESCE(SUM(sale_today), 0) as sale_today')
            ->selectRaw('COALESCE(SUM(closing_balance), 0) as closing_balance')
            ->first();

        return $this->paginateReport(
            $query,
            $filters,
            fn ($row) => [
                'item_code' => $row->item_code,
                'item_name' => $row->item_name,
                'opening_balance' => $this->quantityValue($row->opening_balance),
                'purchase_today' => $this->quantityValue($row->purchase_today),
                'sale_today' => $this->quantityValue($row->sale_today),
                'closing_balance' => $this->quantityValue($row->closing_balance),
            ],
            [
                'opening_balance' => $this->quantityValue($summaryRow?->opening_balance),
                'purchase_today' => $this->quantityValue($summaryRow?->purchase_today),
                'sale_today' => $this->quantityValue($summaryRow?->sale_today),
                'closing_balance' => $this->quantityValue($summaryRow?->closing_balance),
            ],
            ['layout' => 'snapshot'],
        );
    }

    public function getNearExpiryReport(array $filters): array
    {
        $today = Carbon::parse($filters['date_from'])->toDateString();
        $cutoff = Carbon::parse($filters['date_from'])->addDays(30)->toDateString();

        $query = $this->stockBatchTotalsQuery($filters)
            ->whereNotNull('sb.expire_date')
            ->where('i.is_expiry_tracked', true)
            ->whereBetween('sb.expire_date', [$today, $cutoff])
            ->whereRaw('COALESCE(sb.quantity, 0) > 0')
            ->groupBy('i.id', 'i.code', 'i.name', 'sb.batch', 'sb.expire_date')
            ->orderBy('sb.expire_date')
            ->orderBy('i.code')
            ->selectRaw('i.code as item_code')
            ->selectRaw('i.name as item_name')
            ->selectRaw('sb.batch as batch_number')
            ->selectRaw('sb.expire_date')
            ->selectRaw('COALESCE(SUM(sb.quantity), 0) as on_hand');

        $summaryRow = DB::query()
            ->fromSub(clone $query, 'near_expiry_rows')
            ->selectRaw('COUNT(*) as total_items')
            ->selectRaw('COALESCE(SUM(on_hand), 0) as total_on_hand')
            ->first();

        return $this->paginateReport(
            $query,
            $filters,
            fn ($row) => [
                'item_code' => $row->item_code,
                'item_name' => $row->item_name,
                'batch_number' => $row->batch_number ?: '-',
                'expiry_date' => $row->expire_date ? $this->displayDate($row->expire_date) : '-',
                'on_hand' => $this->quantityValue($row->on_hand),
                'days_until_expiry' => $row->expire_date ? (int) Carbon::parse($today)->diffInDays(Carbon::parse($row->expire_date), false) : null,
            ],
            [
                'total_items' => (int) ($summaryRow?->total_items ?? 0),
                'total_on_hand' => $this->quantityValue($summaryRow?->total_on_hand),
            ],
            ['layout' => 'snapshot'],
        );
    }

    public function getMaximumStockReport(array $filters): array
    {
        $stockTotals = $this->stockTotalsByItemSubquery($filters['branch_id']);

        $query = DB::table('items as i')
            ->leftJoinSub($stockTotals, 'stock_totals', fn ($join) => $join->on('stock_totals.item_id', '=', 'i.id'))
            ->where('i.branch_id', $filters['branch_id'])
            ->whereNull('i.deleted_at')
            ->whereNotNull('i.maximum_stock')
            ->where('i.maximum_stock', '>', 0)
            ->whereRaw('COALESCE(stock_totals.on_hand, 0) > i.maximum_stock')
            ->orderByRaw('(COALESCE(stock_totals.on_hand, 0) - i.maximum_stock) DESC')
            ->orderBy('i.code')
            ->selectRaw('i.code as item_code')
            ->selectRaw('i.name as item_name')
            ->selectRaw('i.maximum_stock as max_stock_level')
            ->selectRaw('COALESCE(stock_totals.on_hand, 0) as on_hand')
            ->selectRaw('COALESCE(stock_totals.on_hand, 0) - i.maximum_stock as excess_quantity');

        $summaryRow = DB::query()
            ->fromSub(clone $query, 'maximum_stock_rows')
            ->selectRaw('COUNT(*) as total_items')
            ->selectRaw('COALESCE(SUM(excess_quantity), 0) as total_excess_quantity')
            ->first();

        return $this->paginateReport(
            $query,
            $filters,
            fn ($row) => [
                'item_code' => $row->item_code,
                'item_name' => $row->item_name,
                'max_stock_level' => $this->quantityValue($row->max_stock_level),
                'on_hand' => $this->quantityValue($row->on_hand),
                'excess_quantity' => $this->quantityValue($row->excess_quantity),
            ],
            [
                'total_items' => (int) ($summaryRow?->total_items ?? 0),
                'total_excess_quantity' => $this->quantityValue($summaryRow?->total_excess_quantity),
            ],
            ['layout' => 'snapshot'],
        );
    }

    public function getGroupSummaryReport(array $filters): array
    {
        $lifetimeTotals = $this->accountLifetimeTotalsSubquery($filters);
        $accountTypeNature = $this->accountTypeNatureExpression('at');

        $query = DB::table('accounts as a')
            ->join('account_types as at', function ($join) use ($filters) {
                $join->on('at.id', '=', 'a.account_type_id')
                    ->where('at.branch_id', '=', $filters['branch_id'])
                    ->whereNull('at.deleted_at');
            })
            ->leftJoinSub($lifetimeTotals, 'lifetime', fn ($join) => $join->on('lifetime.account_id', '=', 'a.id'))
            ->where('a.branch_id', $filters['branch_id'])
            ->whereNull('a.deleted_at')
            ->whereRaw("{$accountTypeNature} IN ('asset', 'liability', 'equity', 'income', 'expense')")
            ->orderByRaw($this->accountTypeNatureOrderExpression('at'))
            ->orderBy('at.name')
            ->orderBy('a.name')
            ->selectRaw('a.id as account_id')
            ->selectRaw('a.name as account_name')
            ->selectRaw('at.id as account_type_id')
            ->selectRaw('at.name as account_type')
            ->selectRaw("{$accountTypeNature} as account_type_nature")
            ->selectRaw('0 as opening_balance')
            ->selectRaw('COALESCE(lifetime.total_debit, 0) as debit')
            ->selectRaw('COALESCE(lifetime.total_credit, 0) as credit')
            ->selectRaw('COALESCE(lifetime.balance, 0) as closing_balance');

        $rows = $query->get();

        $sections = $rows
            ->groupBy('account_type_id')
            ->map(function ($sectionRows) {
                $section = $sectionRows->first();
                $rows = $sectionRows->map(fn ($row) => [
                    'account_name' => $row->account_name,
                    'opening_balance' => $this->moneyValue($row->opening_balance),
                    'debit' => $this->moneyValue($row->debit),
                    'credit' => $this->moneyValue($row->credit),
                    'closing_balance' => $this->moneyValue($row->closing_balance),
                ])->values()->all();

                $totals = [
                    'account_name' => 'Total '.$section->account_type,
                    'opening_balance' => $this->moneyValue($sectionRows->sum(fn ($row) => (float) $row->opening_balance)),
                    'debit' => $this->moneyValue($sectionRows->sum(fn ($row) => (float) $row->debit)),
                    'credit' => $this->moneyValue($sectionRows->sum(fn ($row) => (float) $row->credit)),
                    'closing_balance' => $this->moneyValue($sectionRows->sum(fn ($row) => (float) $row->closing_balance)),
                ];

                return [
                    'key' => $section->account_type_id,
                    'label' => $section->account_type,
                    'rows' => $rows,
                    'totals' => $totals,
                ];
            })
            ->values()
            ->all();

        $summary = [
            'opening_balance' => $this->moneyValue($rows->sum(fn ($row) => (float) $row->opening_balance)),
            'debit' => $this->moneyValue($rows->sum(fn ($row) => (float) $row->debit)),
            'credit' => $this->moneyValue($rows->sum(fn ($row) => (float) $row->credit)),
            'closing_balance' => $this->moneyValue($rows->sum(fn ($row) => (float) $row->closing_balance)),
        ];

        return [
            'rows' => $rows->map(fn ($row) => [
                'account_name' => $row->account_name,
                'opening_balance' => $this->moneyValue($row->opening_balance),
                'debit' => $this->moneyValue($row->debit),
                'credit' => $this->moneyValue($row->credit),
                'closing_balance' => $this->moneyValue($row->closing_balance),
            ])->values()->all(),
            'pagination' => $this->singlePagePagination(),
            'summary' => $summary,
            'meta' => [
                'layout' => 'group_summary',
                'sections' => $sections,
            ],
        ];
    }

    public function getDayBookReport(array $filters): array
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
            ->whereNull('tl.deleted_at');

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
            ->selectRaw('t.created_at as transaction_time')
            ->selectRaw('a.name as account_name')
            ->selectRaw('t.reference_type')
            ->selectRaw('COALESCE(t.voucher_number, t.reference_id::text, \'-\') as reference')
            ->selectRaw('COALESCE(tl.debit * t.rate, 0) as debit')
            ->selectRaw('COALESCE(tl.credit * t.rate, 0) as credit')
            ->selectRaw("COALESCE(NULLIF(tl.remark, ''), NULLIF(t.remark, ''), '') as narration");

        return $this->paginateReport(
            $query,
            $filters,
            fn ($row) => [
                'time' => Carbon::parse($row->transaction_time)->format('H:i'),
                'account_name' => $row->account_name,
                'transaction_type' => $this->referenceLabel($row->reference_type),
                'reference' => $row->reference,
                'debit' => $this->moneyValue($row->debit),
                'credit' => $this->moneyValue($row->credit),
                'narration' => $row->narration ?: '-',
            ],
            [
                'total_debit' => $this->moneyValue($summaryRow?->total_debit),
                'total_credit' => $this->moneyValue($summaryRow?->total_credit),
                'balance' => $this->moneyValue($summaryRow?->balance),
            ],
        );
    }

    public function getJournalBookReport(array $filters): array
    {
        $lifetimeTotals = $this->accountLifetimeTotalsSubquery($filters);
        $accountTypeNature = $this->accountTypeNatureExpression('at');

        $query = DB::table('account_types as at')
            ->join('accounts as a', function ($join) use ($filters) {
                $join->on('a.account_type_id', '=', 'at.id')
                    ->where('a.branch_id', '=', $filters['branch_id'])
                    ->whereNull('a.deleted_at');
            })
            ->leftJoinSub($lifetimeTotals, 'lifetime', fn ($join) => $join->on('lifetime.account_id', '=', 'a.id'))
            ->where('at.branch_id', $filters['branch_id'])
            ->whereNull('at.deleted_at')
            ->whereRaw("{$accountTypeNature} IN ('asset', 'liability', 'equity', 'income', 'expense')")
            ->groupBy('at.id', 'at.name', 'at.nature', 'at.slug')
            ->orderByRaw($this->accountTypeNatureOrderExpression('at'))
            ->orderBy('at.name')
            ->selectRaw('at.name as account_type')
            ->selectRaw('COALESCE(SUM(COALESCE(lifetime.total_debit, 0)), 0) as total_debit')
            ->selectRaw('COALESCE(SUM(COALESCE(lifetime.total_credit, 0)), 0) as total_credit')
            ->selectRaw('COALESCE(SUM(COALESCE(lifetime.balance, 0)), 0) as balance');

        $summaryRow = DB::query()
            ->fromSub(clone $query, 'journal_rows')
            ->selectRaw('COALESCE(SUM(total_debit), 0) as total_debit')
            ->selectRaw('COALESCE(SUM(total_credit), 0) as total_credit')
            ->selectRaw('COALESCE(SUM(balance), 0) as balance')
            ->first();

        return $this->paginateReport(
            $query,
            $filters,
            fn ($row) => [
                'account_type' => $row->account_type,
                'total_debit' => $this->moneyValue($row->total_debit),
                'total_credit' => $this->moneyValue($row->total_credit),
                'balance' => $this->moneyValue($row->balance),
            ],
            [
                'total_debit' => $this->moneyValue($summaryRow?->total_debit),
                'total_credit' => $this->moneyValue($summaryRow?->total_credit),
                'balance' => $this->moneyValue($summaryRow?->balance),
            ],
        );
    }

    protected function accountLifetimeTotalsSubquery(array $filters): Builder
    {
        return DB::table('transaction_lines as tl')
            ->join('transactions as t', function ($join) use ($filters) {
                $join->on('t.id', '=', 'tl.transaction_id')
                    ->where('t.branch_id', '=', $filters['branch_id'])
                    ->where('t.status', '=', TransactionStatus::POSTED->value)
                    ->whereNull('t.deleted_at');
            })
            ->whereNull('tl.deleted_at')
            ->groupBy('tl.account_id')
            ->selectRaw('tl.account_id')
            ->selectRaw('COALESCE(SUM(tl.debit * t.rate), 0) as total_debit')
            ->selectRaw('COALESCE(SUM(tl.credit * t.rate), 0) as total_credit')
            ->selectRaw('COALESCE(SUM((tl.debit - tl.credit) * t.rate), 0) as balance');
    }

    protected function accountTypeNatureExpression(string $alias = 'at'): string
    {
        return "COALESCE(NULLIF({$alias}.nature, ''), CASE
            WHEN {$alias}.slug IN ('cash-or-bank', 'account-receivable', 'other-current-asset', 'fixed-asset') THEN 'asset'
            WHEN {$alias}.slug IN ('account-payable', 'other-current-liability', 'long-term-liability') THEN 'liability'
            WHEN {$alias}.slug = 'equity' THEN 'equity'
            WHEN {$alias}.slug = 'income' THEN 'income'
            WHEN {$alias}.slug IN ('cost-of-goods-sold', 'expense') THEN 'expense'
            ELSE NULL
        END)";
    }

    protected function accountTypeNatureOrderExpression(string $alias = 'at'): string
    {
        $accountTypeNature = $this->accountTypeNatureExpression($alias);

        return "CASE {$accountTypeNature}
            WHEN 'asset' THEN 1
            WHEN 'liability' THEN 2
            WHEN 'equity' THEN 3
            WHEN 'income' THEN 4
            WHEN 'expense' THEN 5
            ELSE 6
        END";
    }

    protected function stockBatchTotalsQuery(array $filters): Builder
    {
        return DB::table('stock_balances as sb')
            ->join('items as i', function ($join) use ($filters) {
                $join->on('i.id', '=', 'sb.item_id')
                    ->where('i.branch_id', '=', $filters['branch_id'])
                    ->whereNull('i.deleted_at');
            })
            ->where('sb.branch_id', $filters['branch_id'])
            ->whereNull('sb.deleted_at')
            ->whereNotIn('sb.status', [StockStatus::VOIDED->value, StockStatus::CANCELLED->value]);
    }

    protected function stockExpiryTotalsQuery(array $filters): Builder
    {
        return $this->stockBatchTotalsQuery($filters)
            ->whereNotNull('sb.expire_date');
    }

    protected function stockTotalsByItemSubquery(string $branchId): Builder
    {
        return DB::table('stock_balances as sb')
            ->where('sb.branch_id', $branchId)
            ->whereNull('sb.deleted_at')
            ->whereNotIn('sb.status', [StockStatus::VOIDED->value, StockStatus::CANCELLED->value])
            ->groupBy('sb.item_id')
            ->selectRaw('sb.item_id')
            ->selectRaw('COALESCE(SUM(sb.quantity), 0) as on_hand');
    }

    protected function stockBatchMovementTotalsSubquery(string $branchId): Builder
    {
        return DB::table('stock_movements as sm')
            ->where('sm.branch_id', $branchId)
            ->whereNull('sm.deleted_at')
            ->whereNotIn('sm.status', [StockStatus::VOIDED->value, StockStatus::CANCELLED->value])
            ->groupBy('sm.item_id', 'sm.batch', 'sm.expire_date')
            ->selectRaw('sm.item_id')
            ->selectRaw('sm.batch')
            ->selectRaw('sm.expire_date')
            ->selectRaw("COALESCE(SUM(CASE WHEN sm.movement_type = 'in' THEN sm.quantity ELSE 0 END), 0) as total_in")
            ->selectRaw("COALESCE(SUM(CASE WHEN sm.movement_type = 'out' THEN sm.quantity ELSE 0 END), 0) as total_out");
    }

    protected function stockMovementTotalsByBatchSubquery(string $branchId): Builder
    {
        return $this->stockBatchMovementTotalsSubquery($branchId);
    }

    protected function stockMovementTotalsByExpirySubquery(string $branchId): Builder
    {
        return DB::table('stock_movements as sm')
            ->where('sm.branch_id', $branchId)
            ->whereNull('sm.deleted_at')
            ->whereNotIn('sm.status', [StockStatus::VOIDED->value, StockStatus::CANCELLED->value])
            ->whereNotNull('sm.expire_date')
            ->groupBy('sm.item_id', 'sm.expire_date')
            ->selectRaw('sm.item_id')
            ->selectRaw('sm.expire_date')
            ->selectRaw("COALESCE(SUM(CASE WHEN sm.movement_type = 'in' THEN sm.quantity ELSE 0 END), 0) as total_in")
            ->selectRaw("COALESCE(SUM(CASE WHEN sm.movement_type = 'out' THEN sm.quantity ELSE 0 END), 0) as total_out");
    }

    protected function stockMovementTotalsByItemSubquery(string $branchId): Builder
    {
        return DB::table('stock_movements as sm')
            ->where('sm.branch_id', $branchId)
            ->whereNull('sm.deleted_at')
            ->whereNotIn('sm.status', [StockStatus::VOIDED->value, StockStatus::CANCELLED->value])
            ->groupBy('sm.item_id')
            ->selectRaw('sm.item_id')
            ->selectRaw("COALESCE(SUM(CASE WHEN sm.movement_type = 'in' THEN sm.quantity ELSE 0 END), 0) as total_in")
            ->selectRaw("COALESCE(SUM(CASE WHEN sm.movement_type = 'out' THEN sm.quantity ELSE 0 END), 0) as total_out");
    }

    protected function stockFirstInDateSubquery(string $branchId): Builder
    {
        return DB::table('stock_movements as sm')
            ->where('sm.branch_id', $branchId)
            ->whereNull('sm.deleted_at')
            ->whereNotIn('sm.status', [StockStatus::VOIDED->value, StockStatus::CANCELLED->value])
            ->where('sm.movement_type', StockMovementType::IN->value)
            ->groupBy('sm.item_id')
            ->selectRaw('sm.item_id')
            ->selectRaw('MIN(sm.date) as first_stock_in_date');
    }

    protected function saleTotalsByItemSubquery(array $filters): Builder
    {
        return DB::table('sale_items as si')
            ->join('sales as s', function ($join) use ($filters) {
                $join->on('s.id', '=', 'si.sale_id')
                    ->where('s.branch_id', '=', $filters['branch_id'])
                    ->whereNull('s.deleted_at');
            })
            ->join('transactions as t', function ($join) use ($filters) {
                $join->on('t.reference_id', '=', 's.id')
                    ->where('t.branch_id', '=', $filters['branch_id'])
                    ->where('t.reference_type', '=', Sale::class)
                    ->where('t.status', '=', TransactionStatus::POSTED->value)
                    ->whereNull('t.deleted_at');
            })
            ->whereNull('si.deleted_at')
            ->when($filters['date_from'] ?? null, fn ($query) => $query->whereBetween('s.date', [$filters['date_from'], $filters['date_to']]))
            ->groupBy('si.item_id')
            ->selectRaw('si.item_id')
            ->selectRaw('COALESCE(SUM(si.quantity), 0) as total_sold')
            ->selectRaw('COUNT(DISTINCT si.sale_id) as sale_count');
    }

    protected function todayStockMovementTotalsByItemSubquery(string $branchId, string $date): Builder
    {
        return DB::table('stock_movements as sm')
            ->where('sm.branch_id', $branchId)
            ->whereNull('sm.deleted_at')
            ->whereNotIn('sm.status', [StockStatus::VOIDED->value, StockStatus::CANCELLED->value])
            ->whereDate('sm.date', $date)
            ->groupBy('sm.item_id')
            ->selectRaw('sm.item_id')
            ->selectRaw("COALESCE(SUM(CASE WHEN sm.source = 'purchase' AND sm.movement_type = 'in' THEN sm.quantity ELSE 0 END), 0) as purchase_today")
            ->selectRaw("COALESCE(SUM(CASE WHEN sm.source = 'sale' AND sm.movement_type = 'out' THEN sm.quantity ELSE 0 END), 0) as sale_today");
    }

    protected function accountOpeningBalancesSubquery(array $filters): Builder
    {
        return DB::table('transaction_lines as tl')
            ->join('transactions as t', function ($join) use ($filters) {
                $join->on('t.id', '=', 'tl.transaction_id')
                    ->where('t.branch_id', '=', $filters['branch_id'])
                    ->where('t.status', '=', TransactionStatus::POSTED->value)
                    ->whereNull('t.deleted_at');
            })
            ->whereNull('tl.deleted_at')
            ->whereDate('t.date', '<', $filters['date_from'])
            ->groupBy('tl.account_id')
            ->selectRaw('tl.account_id')
            ->selectRaw('COALESCE(SUM((tl.debit - tl.credit) * t.rate), 0) as opening_balance');
    }

    protected function accountPeriodTotalsSubquery(array $filters): Builder
    {
        return DB::table('transaction_lines as tl')
            ->join('transactions as t', function ($join) use ($filters) {
                $join->on('t.id', '=', 'tl.transaction_id')
                    ->where('t.branch_id', '=', $filters['branch_id'])
                    ->where('t.status', '=', TransactionStatus::POSTED->value)
                    ->whereNull('t.deleted_at');
            })
            ->whereNull('tl.deleted_at')
            ->whereBetween('t.date', [$filters['date_from'], $filters['date_to']])
            ->groupBy('tl.account_id')
            ->selectRaw('tl.account_id')
            ->selectRaw('COALESCE(SUM(tl.debit * t.rate), 0) as total_debit')
            ->selectRaw('COALESCE(SUM(tl.credit * t.rate), 0) as total_credit');
    }

    protected function periodDays(array $filters): int
    {
        return max(
            1,
            Carbon::parse($filters['date_from'])->startOfDay()->diffInDays(Carbon::parse($filters['date_to'])->startOfDay()) + 1,
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
                'account_name' => 'Current Period Profit / Loss',
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
                    ['key' => 'assets', 'label' => __('general.assets'), 'rows' => $assets],
                    ['key' => 'liabilities', 'label' => __('general.liabilities'), 'rows' => $liabilities],
                    ['key' => 'equity', 'label' => __('general.equity'), 'rows' => $equity],
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
                    ['key' => 'revenue', 'label' => __('general.revenue'), 'rows' => $revenue],
                    ['key' => 'cost_of_goods_sold', 'label' => __('general.cost_of_goods_sold'), 'rows' => $costOfGoodsSold],
                    ['key' => 'expenses', 'label' => __('general.expenses'), 'rows' => $expenses],
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

    protected function statementExportData(string $reportKey, array $result, ?Authenticatable $user = null): array
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
            'filename' => $reportKey . '-' . now()->format('Ymd-His') . '.xlsx',
            'sheet_name' => $this->reportLabel($reportKey),
            'sheet_title' => $this->reportLabel($reportKey),
            'title' => $this->reportLabel($reportKey),
            'company_name' => $this->exportCompanyName($user),
            'exported_on' => now()->format('Y m d'),
            'rtl' => in_array(app()->getLocale(), ['fa', 'ps'], true),
            'include_row_number' => true,
            'row_number_label' => $this->reportColumnLabel('no'),
            'columns' => [
                ['key' => 'section', 'label' => $this->reportColumnLabel('section'), 'type' => 'text'],
                ['key' => 'account_name', 'label' => $this->reportColumnLabel('account_name'), 'type' => 'text'],
                ['key' => 'balance', 'label' => $this->reportColumnLabel('balance'), 'type' => 'number', 'align' => 'right'],
            ],
            'rows' => $rows,
        ];
    }    protected function getUserActivity(array $filters): array
    {
        $totalUsers = $this->userActivityUsersBaseQuery($filters)->count('u.id');
        $userRoles = $this->userActivityUserRoles($filters);
        $roleDistribution = collect($userRoles)
            ->groupBy(fn ($row) => $row->role_label)
            ->map(fn ($rows, $role) => [
                'role' => $role,
                'count' => $rows->count(),
                'percent' => $totalUsers > 0 ? round(($rows->count() / $totalUsers) * 100, 1) : 0,
            ])
            ->sortByDesc('count')
            ->values()
            ->all();

        $loginEvents = $this->userActivityLoginEventsQuery($filters);
        $auditEvents = $this->userActivityAuditEventsQuery($filters);
        $allEvents = $this->userActivityEventsQuery($filters);
        $perUserActivity = $this->userActivityPerUserSubquery($filters);
        $lastLogin = $this->userActivityLastLoginSubquery($filters);

        $summaryRow = $allEvents
            ? (clone $allEvents)
                ->selectRaw('COUNT(*) as total_activities')
                ->selectRaw("SUM(CASE WHEN action_type = 'login' THEN 1 ELSE 0 END) as total_logins")
                ->selectRaw("SUM(CASE WHEN action_type = 'create' THEN 1 ELSE 0 END) as total_creates")
                ->selectRaw("SUM(CASE WHEN action_type = 'update' THEN 1 ELSE 0 END) as total_updates")
                ->selectRaw("SUM(CASE WHEN action_type = 'delete' THEN 1 ELSE 0 END) as total_deletes")
                ->first()
            : null;

        $activeUsers = $perUserActivity
            ? DB::query()->fromSub($perUserActivity, 'ua_users')->count()
            : 0;

        $topSourcesByUser = $auditEvents
            ? (clone $auditEvents)
                ->selectRaw('user_id')
                ->selectRaw('source_key')
                ->selectRaw('COUNT(*) as total')
                ->groupBy('user_id', 'source_key')
                ->orderBy('user_id')
                ->orderByDesc('total')
                ->get()
                ->groupBy('user_id')
                ->map(fn ($rows) => $rows
                    ->take(3)
                    ->map(fn ($row) => [
                        'key' => $row->source_key,
                        'count' => (int) $row->total,
                    ])
                    ->values()
                    ->all())
                ->all()
            : [];

        $topSourcesSummary = collect($topSourcesByUser)
            ->map(fn ($items) => collect($items)
                ->map(fn ($item) => Str::of($item['key'])->replace('_', ' ')->headline()->append(" ({$item['count']})")->toString())
                ->implode(', '))
            ->all();

        $topUsers = DB::query()
            ->fromSub($this->userActivityUsersBaseQuery($filters), 'u')
            ->leftJoinSub($perUserActivity, 'ua', fn ($join) => $join->on('ua.user_id', '=', 'u.id'))
            ->leftJoinSub($lastLogin, 'ul', fn ($join) => $join->on('ul.user_id', '=', 'u.id'))
            ->leftJoinSub($this->userActivityRoleLabelsSubquery(), 'ur', fn ($join) => $join->on('ur.user_id', '=', 'u.id'))
            ->selectRaw('u.id as user_id')
            ->selectRaw('u.name as user_name')
            ->selectRaw('u.email')
            ->selectRaw("COALESCE(ur.role_label, 'No role') as role")
            ->selectRaw('COALESCE(ua.total_activities, 0) as total_activities')
            ->selectRaw('COALESCE(ua.logins, 0) as total_logins')
            ->selectRaw('ul.last_login_at')
            ->orderByDesc(DB::raw('COALESCE(ua.total_activities, 0)'))
            ->orderBy('u.name')
            ->limit(5)
            ->get()
            ->filter(fn ($row) => (int) $row->total_activities > 0)
            ->map(fn ($row) => [
                'user_id' => $row->user_id,
                'user_name' => $row->user_name,
                'email' => $row->email,
                'role' => $row->role,
                'total_activities' => (int) $row->total_activities,
                'total_logins' => (int) $row->total_logins,
                'last_login' => $this->displayDateTime($row->last_login_at),
            ])
            ->values()
            ->all();

        $query = DB::query()
            ->fromSub($this->userActivityUsersBaseQuery($filters), 'u')
            ->leftJoinSub($this->userActivityRoleLabelsSubquery(), 'ur', fn ($join) => $join->on('ur.user_id', '=', 'u.id'))
            ->leftJoinSub($perUserActivity, 'ua', fn ($join) => $join->on('ua.user_id', '=', 'u.id'))
            ->leftJoinSub($lastLogin, 'ul', fn ($join) => $join->on('ul.user_id', '=', 'u.id'))
            ->selectRaw('u.id as user_id')
            ->selectRaw('u.name as user_name')
            ->selectRaw('u.email')
            ->selectRaw("COALESCE(ur.role_label, 'No role') as role")
            ->selectRaw('COALESCE(ua.total_activities, 0) as total_activities')
            ->selectRaw('COALESCE(ua.logins, 0) as logins')
            ->selectRaw('COALESCE(ua.creates, 0) as creates')
            ->selectRaw('COALESCE(ua.updates, 0) as updates')
            ->selectRaw('COALESCE(ua.deletes, 0) as deletes')
            ->selectRaw('ul.last_login_at')
            ->orderByDesc(DB::raw('COALESCE(ua.total_activities, 0)'))
            ->orderBy('u.name');

        $activeRate = $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100, 1) : 0;

        return $this->paginateReport(
            $query,
            $filters,
            function ($row) use ($topSourcesSummary) {
                return [
                    'user_id' => $row->user_id,
                    'user_name' => $row->user_name,
                    'email' => $row->email,
                    'role' => $row->role,
                    'total_activities' => (int) $row->total_activities,
                    'logins' => (int) $row->logins,
                    'creates' => (int) $row->creates,
                    'updates' => (int) $row->updates,
                    'deletes' => (int) $row->deletes,
                    'last_login' => $this->displayDateTime($row->last_login_at),
                    'top_sources' => $topSourcesSummary[$row->user_id] ?? '-',
                ];
            },
            [
                'total_users' => (int) $totalUsers,
                'active_users' => (int) $activeUsers,
                'active_rate' => $activeRate,
                'total_activities' => (int) ($summaryRow?->total_activities ?? 0),
                'total_logins' => (int) ($summaryRow?->total_logins ?? 0),
            ],
            [
                'layout' => 'user_activity',
                'activity_breakdown' => [
                    'login' => (int) ($summaryRow?->total_logins ?? 0),
                    'create' => (int) ($summaryRow?->total_creates ?? 0),
                    'update' => (int) ($summaryRow?->total_updates ?? 0),
                    'delete' => (int) ($summaryRow?->total_deletes ?? 0),
                ],
                'role_distribution' => $roleDistribution,
                'top_users' => $topUsers,
                'top_sources_by_user' => $topSourcesByUser,
                'range_label' => sprintf(
                    '%s - %s',
                    $this->displayDate($filters['date_from']) ?? $filters['date_from'],
                    $this->displayDate($filters['date_to']) ?? $filters['date_to'],
                ),
            ],
        );
    }

    protected function userActivityUsersBaseQuery(array $filters): Builder
    {
        return DB::table('users as u')
            ->where('u.branch_id', $filters['branch_id'])
            ->whereNull('u.deleted_at');
    }

    protected function userActivityRoleLabelsSubquery(): Builder
    {
        $userModelTypes = array_values(array_unique([
            (new User())->getMorphClass(),
            User::class,
        ]));

        return DB::table('model_has_roles as mhr')
            ->join('roles as r', function ($join) {
                $join->on('r.id', '=', 'mhr.role_id')
                    ->whereNull('r.deleted_at');
            })
            ->whereIn('mhr.model_type', $userModelTypes)
            ->groupBy('mhr.model_id')
            ->selectRaw('mhr.model_id as user_id')
            ->selectRaw("STRING_AGG(DISTINCT r.name, ', ' ORDER BY r.name) as role_label");
    }

    protected function userActivityUserRoles(array $filters)
    {
        return DB::query()
            ->fromSub($this->userActivityUsersBaseQuery($filters), 'u')
            ->leftJoinSub($this->userActivityRoleLabelsSubquery(), 'ur', fn ($join) => $join->on('ur.user_id', '=', 'u.id'))
            ->selectRaw("COALESCE(ur.role_label, 'No role') as role_label")
            ->get();
    }

    protected function userActivityLoginEventsQuery(array $filters): Builder
    {
        $fromTimestamp = Carbon::parse($filters['date_from'])->startOfDay()->timestamp;
        $toTimestamp = Carbon::parse($filters['date_to'])->endOfDay()->timestamp;

        return DB::table('sessions as s')
            ->join('users as u', function ($join) use ($filters) {
                $join->on('u.id', '=', 's.user_id')
                    ->where('u.branch_id', '=', $filters['branch_id'])
                    ->whereNull('u.deleted_at');
            })
            ->whereNotNull('s.user_id')
            ->whereBetween('s.last_activity', [$fromTimestamp, $toTimestamp])
            ->selectRaw('s.user_id')
            ->selectRaw("'login' as action_type")
            ->selectRaw("'sessions' as source_key")
            ->selectRaw('COALESCE(s.login_time, to_timestamp(s.last_activity)) as action_at');
    }

    protected function userActivityAuditEventsQuery(array $filters): ?Builder
    {
        $queries = collect($this->userActivityAuditedSources())
            ->flatMap(function (array $source) use ($filters) {
                $table = $source['table'];
                $sourceKey = $source['key'];

                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'branch_id')) {
                    return [];
                }

                $events = [];

                if (Schema::hasColumn($table, 'created_by') && Schema::hasColumn($table, 'created_at')) {
                    $events[] = $this->userActivityAuditEventSelect($table, $sourceKey, 'create', 'created_by', 'created_at', $filters);
                }

                if (Schema::hasColumn($table, 'updated_by') && Schema::hasColumn($table, 'updated_at')) {
                    $events[] = $this->userActivityAuditEventSelect($table, $sourceKey, 'update', 'updated_by', 'updated_at', $filters);
                }

                if (Schema::hasColumn($table, 'deleted_by') && Schema::hasColumn($table, 'deleted_at')) {
                    $events[] = $this->userActivityAuditEventSelect($table, $sourceKey, 'delete', 'deleted_by', 'deleted_at', $filters);
                }

                return $events;
            })
            ->values()
            ->all();

        return $this->unionSubqueries($queries, 'audit_events');
    }

    protected function userActivityAuditEventSelect(
        string $table,
        string $sourceKey,
        string $actionType,
        string $userColumn,
        string $timestampColumn,
        array $filters,
    ): Builder {
        $from = Carbon::parse($filters['date_from'])->startOfDay()->toDateTimeString();
        $to = Carbon::parse($filters['date_to'])->endOfDay()->toDateTimeString();

        return DB::table($table)
            ->where('branch_id', $filters['branch_id'])
            ->whereNotNull($userColumn)
            ->whereNotNull($timestampColumn)
            ->whereBetween($timestampColumn, [$from, $to])
            ->selectRaw("{$userColumn} as user_id")
            ->selectRaw("'{$actionType}' as action_type")
            ->selectRaw("'{$sourceKey}' as source_key")
            ->selectRaw("{$timestampColumn} as action_at");
    }

    protected function userActivityEventsQuery(array $filters): ?Builder
    {
        return $this->unionSubqueries(
            array_filter([
                $this->userActivityLoginEventsQuery($filters),
                $this->userActivityAuditEventsQuery($filters),
            ]),
            'user_activity_events',
        );
    }

    protected function userActivityPerUserSubquery(array $filters): ?Builder
    {
        $events = $this->userActivityEventsQuery($filters);

        if (! $events) {
            return null;
        }

        return DB::query()
            ->fromSub($events, 'ua')
            ->selectRaw('user_id')
            ->selectRaw('COUNT(*) as total_activities')
            ->selectRaw("SUM(CASE WHEN action_type = 'login' THEN 1 ELSE 0 END) as logins")
            ->selectRaw("SUM(CASE WHEN action_type = 'create' THEN 1 ELSE 0 END) as creates")
            ->selectRaw("SUM(CASE WHEN action_type = 'update' THEN 1 ELSE 0 END) as updates")
            ->selectRaw("SUM(CASE WHEN action_type = 'delete' THEN 1 ELSE 0 END) as deletes")
            ->groupBy('user_id');
    }

    protected function userActivityLastLoginSubquery(array $filters): Builder
    {
        return DB::query()
            ->fromSub($this->userActivityLoginEventsQuery($filters), 'ul')
            ->selectRaw('user_id')
            ->selectRaw('MAX(action_at) as last_login_at')
            ->groupBy('user_id');
    }

    protected function unionSubqueries(array $queries, string $alias): ?Builder
    {
        $queries = array_values(array_filter($queries));

        if ($queries === []) {
            return null;
        }

        $base = array_shift($queries);

        foreach ($queries as $query) {
            $base->unionAll($query);
        }

        return DB::query()->fromSub($base, $alias);
    }

    protected function userActivityAuditedSources(): array
    {
        return [
            ['table' => 'users', 'key' => 'users'],
            ['table' => 'accounts', 'key' => 'accounts'],
            ['table' => 'account_types', 'key' => 'account_types'],
            ['table' => 'brands', 'key' => 'brands'],
            ['table' => 'categories', 'key' => 'categories'],
            ['table' => 'currencies', 'key' => 'currencies'],
            ['table' => 'expense_categories', 'key' => 'expense_categories'],
            ['table' => 'expenses', 'key' => 'expenses'],
            ['table' => 'item_transfers', 'key' => 'item_transfers'],
            ['table' => 'items', 'key' => 'items'],
            ['table' => 'journal_classes', 'key' => 'journal_classes'],
            ['table' => 'journal_entries', 'key' => 'journal_entries'],
            ['table' => 'ledgers', 'key' => 'ledgers'],
            ['table' => 'owners', 'key' => 'owners'],
            ['table' => 'payments', 'key' => 'payments'],
            ['table' => 'purchases', 'key' => 'purchases'],
            ['table' => 'receipts', 'key' => 'receipts'],
            ['table' => 'sales', 'key' => 'sales'],
            ['table' => 'sizes', 'key' => 'sizes'],
            ['table' => 'unit_measures', 'key' => 'unit_measures'],
            ['table' => 'warehouses', 'key' => 'warehouses'],
        ];
    }

    protected function displayDateTime(mixed $value): string
    {
        if (! $value) {
            return '-';
        }

        $dateTime = Carbon::parse($value);
        $displayDate = $this->dateConversionService->toDisplay($dateTime->toDateString()) ?? $dateTime->toDateString();

        return $displayDate.' '.$dateTime->format('H:i');
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
            'batch_wise_report' => ['item_code', 'item_name', 'batch_number', 'expiry_date', 'in_quantity', 'out_quantity', 'on_hand'],
            'expiry_wise_report' => ['item_code', 'item_name', 'expiry_date', 'in_quantity', 'out_quantity', 'on_hand'],
            'zero_on_hand_report' => ['item_code', 'item_name', 'total_in', 'total_out', 'on_hand'],
            'fast_moving_report' => ['item_code', 'item_name', 'total_sold', 'sale_count', 'average_per_day'],
            'slow_moving_report' => ['item_code', 'item_name', 'total_sold', 'sale_count', 'days_on_hand', 'turnover_rate'],
            'today_sale_purchase_closing_stock_report' => ['item_code', 'item_name', 'opening_balance', 'purchase_today', 'sale_today', 'closing_balance'],
            'near_expiry_report' => ['item_code', 'item_name', 'batch_number', 'expiry_date', 'on_hand', 'days_until_expiry'],
            'maximum_stock_report' => ['item_code', 'item_name', 'max_stock_level', 'on_hand', 'excess_quantity'],
            'group_summary_report' => ['account_name', 'opening_balance', 'debit', 'credit', 'closing_balance'],
            'day_book_report' => ['time', 'account_name', 'transaction_type', 'reference', 'debit', 'credit', 'narration'],
            'journal_book_report' => ['account_type', 'total_debit', 'total_credit', 'balance'],
            'user_activity' => ['user_name', 'email', 'role', 'total_activities', 'logins', 'creates', 'updates', 'deletes', 'last_login', 'top_sources'],
            default => ['value'],
        };
    }

    protected function reportTranslations(): array
    {
        static $cache = [];

        $locale = app()->getLocale();

        if (! array_key_exists($locale, $cache)) {
            $path = resource_path("js/locales/{$locale}/report.json");

            if (! is_file($path)) {
                $path = resource_path('js/locales/en/report.json');
            }

            $cache[$locale] = json_decode((string) file_get_contents($path), true) ?: [];
        }

        return $cache[$locale];
    }

    protected function reportTranslation(string $key, ?string $fallback = null): string
    {
        $value = data_get($this->reportTranslations(), $key);

        if (filled($value)) {
            return (string) $value;
        }

        if (app()->getLocale() !== 'en') {
            $englishPath = resource_path('js/locales/en/report.json');
            $english = is_file($englishPath)
                ? json_decode((string) file_get_contents($englishPath), true) ?: []
                : [];
            $englishValue = data_get($english, $key);

            if (filled($englishValue)) {
                return (string) $englishValue;
            }
        }

        return (string) ($fallback ?? $key);
    }

    protected function reportLabel(string $reportKey): string
    {
        return $this->reportTranslation("reports.{$reportKey}.label", Str::headline($reportKey));
    }

    protected function reportColumnLabel(string $key): string
    {
        return $this->reportTranslation("columns.{$key}", Str::headline(str_replace('_', ' ', $key)));
    }

    protected function exportColumnType(string $key): string
    {
        $numericKeys = [
            'ledger_id',
            'total_debit',
            'total_credit',
            'balance',
            'debit',
            'credit',
            'running_balance',
            'amount_received',
            'amount_paid',
            'quantity',
            'unit_price',
            'total_amount',
            'average_cost',
            'total_value',
            'reorder_level',
            'total_quantity',
            'total_items',
            'total_assets',
            'total_liabilities',
            'total_equity',
            'equation_total',
            'total_revenue',
            'total_cost_of_goods_sold',
            'gross_profit',
            'total_expenses',
            'net_profit',
            'in_quantity',
            'out_quantity',
            'on_hand',
            'total_sold',
            'sale_count',
            'average_per_day',
            'days_on_hand',
            'turnover_rate',
            'opening_balance',
            'purchase_today',
            'sale_today',
            'closing_balance',
            'days_until_expiry',
            'max_stock_level',
            'excess_quantity',
            'balance',
        ];

        return in_array($key, $numericKeys, true) ? 'number' : 'text';
    }

    protected function exportCompanyName(?Authenticatable $user = null): string
    {
        $company = data_get($user, 'company');

        if (! $company) {
            return config('app.name');
        }

        return match (app()->getLocale()) {
            'fa' => $company->name_fa ?: $company->name_en ?: $company->abbreviation ?: config('app.name'),
            'ps' => $company->name_pa ?: $company->name_en ?: $company->abbreviation ?: config('app.name'),
            default => $company->name_en ?: $company->abbreviation ?: $company->name_fa ?: $company->name_pa ?: config('app.name'),
        };
    }    protected function emptyResult(string $messageKey): array
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

        $requestedReport = (string) ($filters['report'] ?? 'trial_balance');
        $report = in_array($requestedReport, self::REPORT_KEYS, true)
            ? $requestedReport
            : 'trial_balance';

        [$reportDefaultFrom, $reportDefaultTo] = match ($report) {
            'day_book_report', 'today_sale_purchase_closing_stock_report' => [$today, $today],
            'near_expiry_report' => [$today, Carbon::today()->addDays(30)->toDateString()],
            default => [$defaultFrom, $today],
        };

        $dateFrom = ! empty($filters['date_from'])
            ? $this->dateConversionService->toGregorian((string) $filters['date_from'])
            : $reportDefaultFrom;

        $dateTo = ! empty($filters['date_to'])
            ? $this->dateConversionService->toGregorian((string) $filters['date_to'])
            : $reportDefaultTo;

        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

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
                ->orderBy(app()->getLocale() === 'en' ? 'a.name' : 'a.local_name')
                ->get(['a.id', 'a.name', 'a.local_name'])
                ->map(fn ($row) => [
                    'id' => $row->id,
                    'name' => app()->getLocale() === 'en' ? $row->name : ($row->local_name ?? $row->name)
                ])
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
        $branchId = $user?->branch_id ?? Auth::user()?->branch_id;

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
