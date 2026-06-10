<?php

namespace App\Http\Controllers\Sale;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sale\SaleStoreRequest;
use App\Http\Requests\Sale\SaleUpdateRequest;
use App\Http\Resources\Sale\SaleListResource;
use App\Http\Resources\Sale\SaleResource;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleItem;
use App\Services\BillAllocationService;
use App\Models\Ledger\Ledger;
use App\Models\Administration\Currency;
use App\Models\Administration\UnitMeasure;
use App\Models\Administration\Warehouse;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\TransactionService;
use App\Models\Account\Account;
use App\Services\StockService;
use App\Models\Transaction\Transaction;
use Mpdf\Mpdf;
use App\Enums\TransactionStatus;
use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Support\Preferences\InvoiceThemeOptions;
use App\Models\Sale\InvoiceFormat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use App\Models\Inventory\Item;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Services\DateConversionService;
use App\Services\ActivityLogService;
use App\Services\SpreadsheetExportService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Support\Str;

class SaleController extends Controller
{
    private $dateConversionService;
    public function __construct(DateConversionService $dateConversionService)
    {
        $this->authorizeResource(Sale::class, 'sale');
        $this->dateConversionService = $dateConversionService;
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortField = $request->input('sortField', 'id');
        $sortDirection = strtolower($request->input('sortDirection', 'desc')) === 'asc' ? 'asc' : 'desc';
        $filters = (array) $request->input('filters', []);
        $sortableFields = [
            'id' => 'sales.id',
            'number' => 'sales.number',
            'date' => 'sales.date',
            'type' => 'sales.type',
            'amount' => 'items_gross_total',
        ];
        $sortColumn = $sortableFields[$sortField] ?? 'sales.id';

        $itemGrossTotal = SaleItem::query()
            ->selectRaw('COALESCE(SUM(quantity * unit_price), 0)')
            ->whereColumn('sale_items.sale_id', 'sales.id')
            ->whereNull('sale_items.deleted_at');
        $itemDiscountTotal = SaleItem::query()
            ->selectRaw('COALESCE(SUM(discount), 0)')
            ->whereColumn('sale_items.sale_id', 'sales.id')
            ->whereNull('sale_items.deleted_at');
        $itemTaxTotal = SaleItem::query()
            ->selectRaw('COALESCE(SUM(tax), 0)')
            ->whereColumn('sale_items.sale_id', 'sales.id')
            ->whereNull('sale_items.deleted_at');

        $sales = Sale::query()
            ->select([
                'sales.id',
                'sales.number',
                'sales.customer_id',
                'sales.date',
                'sales.discount',
                'sales.discount_type',
                'sales.type',
                'sales.status',
                'sales.payment_status',
            ])
            ->selectSub($itemGrossTotal, 'items_gross_total')
            ->selectSub($itemDiscountTotal, 'items_discount_total')
            ->selectSub($itemTaxTotal, 'items_tax_total')
            ->with(['customer:id,name'])
            ->search($request->query('search'))
            ->filter($filters)
            ->orderBy($sortColumn, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
        return inertia('Sale/Sales/Index', [
            'sales' => SaleListResource::collection($sales),
            'filterOptions' => [
                'customers' => Ledger::query()->where('type', 'customer')->orderBy('name')->get(['id', 'name']),
                'currencies' => Currency::orderBy('code')->get(['id', 'code', 'name']),
                'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
                'types' => [
                    ['id' => 'cash', 'name' => 'Cash'],
                    ['id' => 'credit', 'name' => 'Credit'],
                ],
                'users' => User::query()->whereNull('deleted_at')->orderBy('name')->get(['id', 'name']),
            ],
            'filters' => [
                'search' => $request->query('search'),
                'perPage' => $perPage,
                'sortField' => $sortField,
                'sortDirection' => $sortDirection,
                'filters' => $filters,
            ],
        ]);
    }

    public function create(Request $request)
    {
        $saleNumber = Sale::max('number') ? Sale::max('number') + 1 : 1;
        $bankAccounts = new Account();
        $bankAccounts = $bankAccounts->getAccountsByAccountTypeSlug('cash-or-bank');

        return inertia('Sale/Sales/Create', [
            'saleNumber' => $saleNumber,
            'bankAccounts' => $bankAccounts,
        ]);
    }

    public function store(
        SaleStoreRequest $request,
        TransactionService $transactionService,
        StockService $stockService,
        ActivityLogService $activityLogService
    )
    {
        $validated = $request->validated();
        $sale = DB::transaction(function () use ($request, $transactionService, $stockService, $validated, $activityLogService) {
            $validated = $request->validated();

            $date = $validated['date'] ? $this->dateConversionService->toGregorian($validated['date']) : null;
            $validated['type'] = $validated['sale_type'] ?? 'cash';
            $validated['status'] = TransactionStatus::POSTED->value;

            $sale = Sale::create($validated);
            $totalDiscount = $request->input('discount_total', 0);
            // Build cost lookup before createMany so net_unit_cost captures the avg_cost
            // at the moment of this sale (before any stock deductions change state).
            [$itemModelsById, $averageCostsByItemId, $unitValuesById] = $this->buildSaleItemCostLookup($validated['item_list']);

            $validated['item_list'] = array_map(function ($item) use ($validated, $itemModelsById, $averageCostsByItemId, $unitValuesById) {
                $item['warehouse_id'] = $validated['warehouse_id'];
                $itemModel = $itemModelsById[$item['item_id']] ?? null;
                $avgCost = (float) ($averageCostsByItemId[$item['item_id']] ?? 0);
                $item['net_unit_cost'] = $itemModel ? $this->resolveUnitCost(
                    avgCost: $avgCost,
                    selectedUnitMeasureId: $item['unit_measure_id'],
                    itemUnitMeasureId: $itemModel->unit_measure_id,
                    unitValuesById: $unitValuesById,
                ) : 0.0;
                return $item;
            }, $validated['item_list']);

            $sale->items()->createMany($validated['item_list']);

            $lines = [];
            $glAccounts = Cache::get('gl_accounts');
            foreach ($validated['item_list'] as $item) {
                $quantity = (float) $item['quantity'];
                $unitPrice = (float) $item['unit_price'];

                // Assumption:
                // item_discount is the TOTAL discount for this line, not per-unit discount.
                $lineGrossTotal = $quantity * $unitPrice;
                $itemModel = $itemModelsById[$item['item_id']] ?? null;
                if (!$itemModel) {
                    throw (new \Illuminate\Database\Eloquent\ModelNotFoundException())->setModel(Item::class, [$item['item_id']]);
                }

                $unitCost = (float) $item['net_unit_cost'];
                $totalCost = $unitCost * $quantity;
                $stockService->post([
                    'item_id'              => $item['item_id'],
                    'movement_type'        => StockMovementType::OUT->value,
                    'unit_measure_id'      => $item['unit_measure_id'],
                    'quantity'             => $quantity,
                    'source'               => StockSourceType::SALE->value,
                    'unit_cost'            => $unitCost,
                    'unit_cost_override'   => $unitCost,
                    'status'               => StockStatus::POSTED->value,
                    'batch'                => $item['batch'] ?? null,
                    'date'                 => $validated['date'],
                    'expire_date'          => $item['expire_date'] ?? null,
                    'size_id'              => $validated['size_id'] ?? null,
                    'warehouse_id'         => $validated['warehouse_id'],
                    'branch_id'            => $sale->branch_id,
                    'reference_type'       => Sale::class,
                    'reference_id'         => $sale->id,
                ]);

                // Revenue at gross amount
                $lines[] = [
                    'account_id' => $itemModel->income_account_id,
                    'ledger_id'  => null,
                    'debit'      => 0,
                    'credit'     => $lineGrossTotal,
                    'remark'     => 'Sale income for item:' . $itemModel->name . '  #' . $sale->number,
                    'remark_fa' => 'عاید فروش '. ' '. $itemModel->name.' #'.$sale->number,
                    'remark_ps' => 'د'. ' '. $itemModel->name.' '.'خرڅلاو څخه عاید د#'.$sale->number,
                ];

                // Cost of goods sold
                $lines[] = [
                    'account_id' => $itemModel->cost_account_id,
                    'ledger_id'  => null,
                    'debit'      => $totalCost,
                    'credit'     => 0,
                    'remark'     => 'COGS for item: ' . $itemModel->name . ' #' . $sale->number,
                    'remark_fa' => 'هزینه محصول فروخته شد بابت '. ' '. $itemModel->name.' #'.$sale->number,
                    'remark_ps' => 'د'. ' '. $itemModel->name.' '.'د پلورل شوي توکو لګښت#'.$sale->number,
                ];

                // Inventory reduction
                $lines[] = [
                    'account_id' => $itemModel->asset_account_id,
                    'ledger_id'  => null,
                    'debit'      => 0,
                    'credit'     => $totalCost,
                    'remark'     => 'Inventory out for item: ' . $itemModel->name . ' #' . $sale->number,
                    'remark_fa'  => 'فروش جنس'. ' '. $itemModel->name.' #'.$sale->number,
                    'remark_ps'  => 'د'. ' '. $itemModel->name.' '.'د خرڅلاو #'.$sale->number,
                ];
            }

            // Sales discount line
            if ($totalDiscount > 0) {
                $lines[] = [
                    'account_id' => $glAccounts['discount-to-customer'], // must exist in your cache/accounts setup
                    'ledger_id'  => null,
                    'debit'      => $totalDiscount,
                    'credit'     => 0,
                    'remark'     => 'Sales discount for sale: #' . $sale->number,
                    'remark_fa' => 'تخفیف فروش'. ' '. $sale->number,
                    'remark_ps' => 'د'. ' '. $sale->number.' '.'تخفیف خرڅلاو د',
                ];
            }

            if ($validated['type'] === \App\Enums\SalePurchaseType::Cash->value) {
                $lines[] = [
                    'account_id' => $validated['bank_account_id'],
                    'ledger_id'  => null,
                    'debit'      => $validated['transaction_total'],
                    'credit'     => 0,
                    'remark'     => 'Cash received from sale #' . $sale->number,
                    'remark_fa'  => 'دریافت نقدی بابت فروش #' . $sale->number,
                    'remark_ps'  => 'د'. '#'. $sale->number.' '.'د نغدي اخیستلو په اړه فروش د',
                ];
            }

            if ($validated['type'] === \App\Enums\SalePurchaseType::OnLoan->value) {
                $lines[] = [
                    'account_id' => $glAccounts['account-receivable'],
                    'ledger_id'  => $validated['customer_id'],
                    'debit'      => $validated['transaction_total'],
                    'credit'     => 0,
                    'remark'     => 'Sale on loan for #' . $sale->number,
                    'remark_fa' => 'فروش قرضی بابت #' . $sale->number,
                    'remark_ps' => 'د'. '#'. $sale->number.' '.'د پور خرڅلاو د',
                ];
            }

            if ($validated['type'] === \App\Enums\SalePurchaseType::Credit->value) {
                $paidAmount = (float) ($validated['payment']['amount'] ?? 0);

                if ($paidAmount > 0) {
                    $lines[] = [
                        'account_id' => $validated['payment']['account_id'],
                        'ledger_id'  => null,
                        'debit'      => $paidAmount,
                        'credit'     => 0,
                        'remark'     => 'Partial payment for sale #' . $sale->number,
                        'remark_fa' => 'پرداخت جزئی برای فروش #' . $sale->number,
                        'remark_ps' => 'د'. '#'. $sale->number.' '.'جزوی تادیه خرڅلاو د',
                    ];

                    $remaining = $validated['transaction_total'] - $paidAmount;

                    if ($remaining > 0) {
                        $lines[] = [
                            'account_id' => $glAccounts['account-receivable'],
                            'ledger_id'  => $validated['customer_id'],
                            'debit'      => $remaining,
                            'credit'     => 0,
                            'remark'     => 'Remaining receivable for sale #' . $sale->number,
                            'remark_fa' => ' فروش قرض #' . $sale->number,
                            'remark_ps' => 'د'. '#'. $sale->number.' '.'د پور خرڅلاو د',
                        ];
                    }
                } else {
                    $lines[] = [
                        'account_id' => $glAccounts['account-receivable'],
                        'ledger_id'  => $validated['customer_id'],
                        'debit'      => $validated['transaction_total'],
                        'credit'     => 0,
                        'remark'     => 'Cash received from sale #' . $sale->number,
                        'remark_fa' => 'دریافت نقدی بابت فروش#' . $sale->number,
                        'remark_ps' => 'د'. '#'. $sale->number.' '.'د پور خرڅلاو د',
                    ];
                }
            }

            $transaction = $transactionService->post(
                header: [
                    'currency_id'    => $validated['currency_id'],
                    'rate'           => $validated['rate'],
                    'date'           => $validated['date'],
                    'voucher_number' => $sale->number,
                    'remark'         => 'Sale for sale number: ' . $sale->number,
                    'status'         => TransactionStatus::POSTED->value,
                    'reference_type' => Sale::class,
                    'reference_id'   => $sale->id,
                ],
                lines: $lines
            );

            app(BillAllocationService::class)->recalculateSalePaymentStatuses([$sale->id]);
            $activityLogService->logCreate(
                reference: $sale,
                module: 'sale',
                description: "Sale #{$sale->number} created and posted.",
                newValues: [
                    'number' => $sale->number,
                    'customer_id' => $sale->customer_id,
                    'date' => $sale->date?->toDateString(),
                    'status' => $sale->status,
                    'branch_id' => $sale->branch_id,
                    'warehouse_id' => $validated['warehouse_id'],
                    'currency_id' => $validated['currency_id'],
                    'item_count' => count($validated['item_list']),
                    'transaction_total' => (float) $validated['transaction_total'],
                ],
                metadata: [
                    'action' => 'sale_store',
                    'sale_type' => $validated['type'],
                    'transaction_id' => $transaction->id,
                ],
            );

            return $sale;
        });

        if ((bool) $request->create_and_new) {
            return redirect()->back()->with(
                'success',
                __('general.created_successfully', ['resource' => __('general.resource.sale')])
            );
        }

        $redirect = redirect()->route('sales.index')->with(
            'success',
            __('general.created_successfully', ['resource' => __('general.resource.sale')])
        );

        if ((bool) $request->create_and_print) {
            $redirect->with('print_url', route('sales.print', $sale));
        }

        return $redirect;
    }


    public function show(Request $request, Sale $sale)
    {
        $sale->load(['items.item', 'items.unitMeasure', 'customer', 'transaction.currency', 'createdBy', 'updatedBy']);

        $resource = new SaleResource($sale);

        if ($request->expectsJson()) {
            return response()->json(['data' => $resource]);
        }

        return inertia('Sale/Sales/Show', [
            'sale' => $resource,
        ]);
    }

    public function edit(Request $request, Sale $sale)
    {
        $bankAccounts = (new Account())->getAccountsByAccountTypeSlug('cash-or-bank');

        // Load only what the edit form needs.
        // transaction.lines is needed to find the payment line (account_id + debit).
        // We do NOT load lines.account or lines.ledger — those are not used by the edit form.
        // customer is loaded with minimal columns; the heavy `statement` accessor is not
        // triggered because we don't access it here.
        $sale->load([
            'items.item.unitMeasure',
            'items.unitMeasure',
            'items.warehouse',
            'customer:id,name,type',
            'transaction',
            'transaction.currency:id,code,symbol,is_base_currency,exchange_rate',
            'transaction.lines:id,transaction_id,account_id,ledger_id,debit,credit',
        ]);

        return inertia('Sale/Sales/Edit', [
            'sale' => new SaleResource($sale),
            'bankAccounts' => $bankAccounts,
        ]);
    }

    public function update(
        SaleUpdateRequest $request,
        Sale $sale,
        TransactionService $transactionService,
        StockService $stockService,
        ActivityLogService $activityLogService
    )
    {
        $beforeState = [
            'number' => $sale->number,
            'customer_id' => $sale->customer_id,
            'date' => $sale->date?->toDateString(),
            'status' => $sale->status,
            'branch_id' => $sale->branch_id,
            'warehouse_id' => $sale->warehouse_id,
            'currency_id' => $sale->transaction?->currency_id,
            'rate' => $sale->transaction?->rate,
            'item_count' => $sale->items()->count(),
            'transaction_total' => (float) ($sale->transaction_total ?? 0),
        ];

        $sale = DB::transaction(function () use ($request, $sale, $transactionService, $stockService, $activityLogService, $beforeState) {
            $validated = $request->validated();
            $validated['type'] = $validated['sale_type'] ?? $sale->type ?? 'cash';
            $validated['status'] = TransactionStatus::POSTED->value;

            $date = $validated['date'] ? $this->dateConversionService->toGregorian($validated['date']) : $sale->date;
            $affectedCombos = $sale->items()
                ->get(['item_id', 'warehouse_id', 'branch_id'])
                ->map(fn ($item) => [
                    'item_id' => $item->item_id,
                    'warehouse_id' => $item->warehouse_id,
                    'branch_id' => $item->branch_id ?? $sale->branch_id,
                ])
                ->all();

            // Capture item IDs from the OLD sale before merging new items in.
            $oldItemIds = collect($affectedCombos)->pluck('item_id')->unique()->values()->all();

            $validated['item_list'] = array_map(function ($item) use ($validated, $sale, &$affectedCombos) {
                $item['discount'] = $item['item_discount'] ?? 0;
                $item['warehouse_id'] = $validated['warehouse_id'];

                $affectedCombos[] = [
                    'item_id' => $item['item_id'],
                    'warehouse_id' => $validated['warehouse_id'],
                    'branch_id' => $sale->branch_id,
                ];

                return $item;
            }, $validated['item_list']);

            $sale->update($validated);

            // Snapshot the unit cost for each (item, unit) line BEFORE deleting anything.
            // Priority: net_unit_cost on the sale item (most reliable, set at sale time).
            // Fallback: unit_cost from the stock movement (covers historical data where
            // net_unit_cost was NULL). This ensures a sale update NEVER re-costs at the
            // current avg_cost — the original COGS is always preserved.
            $originalNetUnitCosts = $sale->items()
                ->whereNotNull('net_unit_cost')
                ->get(['item_id', 'unit_measure_id', 'net_unit_cost'])
                ->mapWithKeys(fn ($i) => [
                    $i->item_id . '_' . $i->unit_measure_id => (float) $i->net_unit_cost
                ])
                ->all();

            $originalMovementCosts = StockMovement::query()
                ->where('reference_type', Sale::class)
                ->where('reference_id', $sale->id)
                ->get(['item_id', 'unit_measure_id', 'unit_cost'])
                ->mapWithKeys(fn ($m) => [
                    $m->item_id . '_' . $m->unit_measure_id => (float) $m->unit_cost
                ])
                ->all();

            // Snapshot avg_cost and stock qty per item BEFORE any changes.
            // Used by the direct-formula avg recalculation for quantity-reduced items.
            $preUpdateAvgCosts = Item::whereIn('id', $oldItemIds)
                ->pluck('avg_cost', 'id')
                ->map(fn ($c) => (float) $c)
                ->all();

            $preUpdateStockQty = StockBalance::query()
                ->where('branch_id', $sale->branch_id)
                ->whereIn('item_id', $oldItemIds)
                ->groupBy('item_id')
                ->selectRaw('item_id, SUM(quantity) as total_qty')
                ->pluck('total_qty', 'item_id')
                ->map(fn ($q) => (float) $q)
                ->all();

            $oldSaleItemsByKey = $sale->items()
                ->get(['item_id', 'quantity', 'unit_measure_id'])
                ->mapWithKeys(fn ($i) => [$i->item_id . '_' . $i->unit_measure_id => (float) $i->quantity])
                ->all();

            $sale->items()->forceDelete();

            StockMovement::query()
                ->where('reference_type', Sale::class)
                ->where('reference_id', $sale->id)
                ->forceDelete();

            $this->rebuildStockStateForCombos($affectedCombos);

            // Build cost lookup after rebuild so avg_cost reflects post-rebuild state.
            [$itemModelsById, $averageCostsByItemId, $unitValuesById] = $this->buildSaleItemCostLookup($validated['item_list']);

            $validated['item_list'] = array_map(function ($item) use ($itemModelsById, $averageCostsByItemId, $unitValuesById, $originalNetUnitCosts, $originalMovementCosts) {
                $itemModel = $itemModelsById[$item['item_id']] ?? null;
                $key = $item['item_id'] . '_' . $item['unit_measure_id'];
                // 1. net_unit_cost on the sale item — most authoritative (set at sale creation).
                // 2. unit_cost from the stock movement — covers historical rows with NULL net_unit_cost.
                // 3. Current avg_cost — only for items newly added in this edit.
                $item['net_unit_cost'] = $originalNetUnitCosts[$key]
                    ?? $originalMovementCosts[$key]
                    ?? ($itemModel ? $this->resolveUnitCost(
                        avgCost: (float) ($averageCostsByItemId[$item['item_id']] ?? 0),
                        selectedUnitMeasureId: $item['unit_measure_id'],
                        itemUnitMeasureId: $itemModel->unit_measure_id,
                        unitValuesById: $unitValuesById,
                    ) : 0.0);
                return $item;
            }, $validated['item_list']);

            $sale->items()->createMany($validated['item_list']);

            $transaction = Transaction::query()
                ->where('reference_type', Sale::class)
                ->where('reference_id', $sale->id)
                ->first();

            if ($transaction) {
                $transaction->lines()->forceDelete();
                $transaction->forceDelete();
            }

            $lines = [];
            $totalDiscount = (float) $request->input('discount_total', 0);
            $glAccounts = Cache::get('gl_accounts');

            foreach ($validated['item_list'] as $item) {
                $quantity = (float) $item['quantity'];
                $unitPrice = (float) $item['unit_price'];
                $lineGrossTotal = $quantity * $unitPrice;

                $itemModel = $itemModelsById[$item['item_id']] ?? null;
                if (!$itemModel) {
                    throw (new \Illuminate\Database\Eloquent\ModelNotFoundException())->setModel(Item::class, [$item['item_id']]);
                }
                $unitCost = (float) $item['net_unit_cost'];
                $totalCost = $unitCost * $quantity;
                $stockService->post([
                    'item_id'              => $item['item_id'],
                    'movement_type'        => StockMovementType::OUT->value,
                    'unit_measure_id'      => $item['unit_measure_id'],
                    'quantity'             => $quantity,
                    'source'               => StockSourceType::SALE->value,
                    'unit_cost'            => $unitCost,
                    'unit_cost_override'   => $unitCost,
                    'status'               => StockStatus::POSTED->value,
                    'batch'                => $item['batch'] ?? null,
                    'date'                 => $date,
                    'expire_date'          => $item['expire_date'] ?? null,
                    'size_id'              => $validated['size_id'] ?? null,
                    'warehouse_id'         => $validated['warehouse_id'],
                    'branch_id'            => $sale->branch_id,
                    'reference_type'       => Sale::class,
                    'reference_id'         => $sale->id,
                ]);

                $lines[] = [
                    'account_id' => $itemModel->income_account_id,
                    'ledger_id'  => null,
                    'debit'      => 0,
                    'credit'     => $lineGrossTotal,
                    'remark'     => 'Sale income for item ' . $itemModel->name . '  #' . $sale->number,
                    'remark_fa' => 'عاید فروش '. ' '. $itemModel->name.' #'.$sale->number,
                    'remark_ps' => 'د'. ' '. $itemModel->name.' '.'خرڅلاو څخه عاید د#'.$sale->number,
                ];

                // Cost of goods sold
                $lines[] = [
                    'account_id' => $itemModel->cost_account_id,
                    'ledger_id'  => null,
                    'debit'      => $totalCost,
                    'credit'     => 0,
                    'remark'     => 'COGS for item ' . $itemModel->name . ' #' . $sale->number,
                    'remark_fa' => 'هزینه محصول فروخته شد بابت '. ' '. $itemModel->name.' #'.$sale->number,
                    'remark_ps' => 'د'. ' '. $itemModel->name.' '.'د پلورل شوي توکو لګښت#'.$sale->number,
                ];

                // Inventory reduction
                $lines[] = [
                    'account_id' => $itemModel->asset_account_id,
                    'ledger_id'  => null,
                    'debit'      => 0,
                    'credit'     => $totalCost,
                    'remark'     => 'Inventory out for item ' . $itemModel->name . ' #' . $sale->number,
                    'remark_fa'  => 'فروش جنس'. ' '. $itemModel->name.' #'.$sale->number,
                    'remark_ps'  => 'د'. ' '. $itemModel->name.' '.'د خرڅلاو #'.$sale->number,
                ];
            }

            // Recalculate avg_cost for items affected by qty changes:
            // - REMOVED: no OUTs left → replay is safe.
            // - REDUCED: units returned to stock → direct formula (avoids ULID date-tie issue).
            // - UNCHANGED / INCREASED: avg_cost stays the same (OUTs never affect avg).
            $newItemsByKey = collect($validated['item_list'])
                ->mapWithKeys(fn ($i) => [$i['item_id'] . '_' . $i['unit_measure_id'] => (float) $i['quantity']])
                ->all();
            $newItemIdSet = collect($validated['item_list'])->pluck('item_id')->flip()->all();

            foreach ($oldItemIds as $itemId) {
                if (!isset($newItemIdSet[$itemId])) {
                    // Item completely removed — replay is safe (no OUTs remain).
                    $this->recalculateAvgCostForItem($itemId);
                    continue;
                }

                // Find matching old/new qty by item+unit key.
                $reducedQty = 0.0;
                $returnCost = 0.0;
                foreach ($oldSaleItemsByKey as $key => $oldQty) {
                    if (!str_starts_with($key, $itemId . '_')) {
                        continue;
                    }
                    $newQty = $newItemsByKey[$key] ?? 0.0;
                    $diff = $oldQty - $newQty;
                    if ($diff > 0) {
                        $reducedQty += $diff;
                        $returnCost = $originalNetUnitCosts[$key] ?? $originalMovementCosts[$key] ?? 0.0;
                    }
                }

                if ($reducedQty <= 0) {
                    continue; // qty unchanged or increased — no avg change
                }

                // Direct WAC formula: returned units come back at historical cost.
                // stock_qty_before_return is the stock as it stood WITH the original sale in effect.
                $stockQtyBefore = $preUpdateStockQty[$itemId] ?? 0.0;
                $currentAvg    = $preUpdateAvgCosts[$itemId] ?? 0.0;
                $denominator   = $stockQtyBefore + $reducedQty;

                if ($denominator > 0 && $returnCost > 0) {
                    $newAvg = ($stockQtyBefore * $currentAvg + $reducedQty * $returnCost) / $denominator;
                    Item::where('id', $itemId)->update(['avg_cost' => $newAvg]);
                }
            }

            // Sales discount line
            if ($totalDiscount > 0) {
                $lines[] = [
                    'account_id' => $glAccounts['discount-to-customer'], // must exist in your cache/accounts setup
                    'ledger_id'  => null,
                    'debit'      => $totalDiscount,
                    'credit'     => 0,
                    'remark'     => 'Sales discount for sale #' . $sale->number,
                    'remark_fa' => 'تخفیف فروش'. ' '. $sale->number,
                    'remark_ps' => 'د'. ' '. $sale->number.' '.'تخفیف خرڅلاو د',
                ];
            }

            if ($validated['type'] === \App\Enums\SalePurchaseType::Cash->value) {
                $lines[] = [
                    'account_id' => $validated['bank_account_id'],
                    'ledger_id'  => null,
                    'debit'      => $validated['transaction_total'],
                    'credit'     => 0,
                    'remark'     => 'Cash received from sale: #' . $sale->number,
                    'remark_fa'  => ':دریافت نقدی بابت فروش #' . $sale->number,
                    'remark_ps'  => 'د'. '#'. $sale->number.' '.'د نغدي اخیستلو په اړه فروش: د',
                ];
            }

            if ($validated['type'] === \App\Enums\SalePurchaseType::OnLoan->value) {
                $lines[] = [
                    'account_id' => $glAccounts['account-receivable'],
                    'ledger_id'  => $validated['customer_id'],
                    'debit'      => $validated['transaction_total'],
                    'credit'     => 0,
                    'remark'     => 'Sale on loan for: #' . $sale->number,
                    'remark_fa' => ':فروش قرضی بابت #' . $sale->number,
                    'remark_ps' => 'د'. '#'. $sale->number.' '.'د پور خرڅلاو: د',
                ];
            }

            if ($validated['type'] === \App\Enums\SalePurchaseType::Credit->value) {
                $paidAmount = (float) ($validated['payment']['amount'] ?? 0);

                if ($paidAmount > 0) {
                    $lines[] = [
                        'account_id' => $validated['payment']['account_id'],
                        'ledger_id'  => null,
                        'debit'      => $paidAmount,
                        'credit'     => 0,
                        'remark'     => 'Partial payment for sale: #' . $sale->number,
                        'remark_fa' => ':پرداخت جزئی برای فروش #' . $sale->number,
                        'remark_ps' => 'د'. '#'. $sale->number.' '.'جزوی تادیه خرڅلاو: د',
                    ];

                    $remaining = $validated['transaction_total'] - $paidAmount;

                    if ($remaining > 0) {
                        $lines[] = [
                            'account_id' => $glAccounts['account-receivable'],
                            'ledger_id'  => $validated['customer_id'],
                            'debit'      => $remaining,
                            'credit'     => 0,
                            'remark'     => 'Remaining receivable for sale: #' . $sale->number,
                            'remark_fa' => ': فروش قرض #' . $sale->number,
                            'remark_ps' => 'د'. '#'. $sale->number.' '.'د پور خرڅلاو: د',
                        ];
                    }
                } else {
                    $lines[] = [
                        'account_id' => $glAccounts['account-receivable'],
                        'ledger_id'  => $validated['customer_id'],
                        'debit'      => $validated['transaction_total'],
                        'credit'     => 0,
                        'remark'     => 'Cash received from sale: #' . $sale->number,
                        'remark_fa' => ':دریافت نقدی بابت فروش#' . $sale->number,
                        'remark_ps' => 'د'. '#'. $sale->number.' '.'د پور خرڅلاو د',
                    ];
                }
            }

            $transaction = $transactionService->post(
                header: [
                    'currency_id'    => $validated['currency_id'],
                    'rate'           => $validated['rate'],
                    'date'           => $date,
                    'voucher_number' => $sale->number,
                    'remark'         => 'Sale for sale number: ' . $sale->number,
                    'status'         => TransactionStatus::POSTED->value,
                    'reference_type' => Sale::class,
                    'reference_id'   => $sale->id,
                ],
                lines: $lines
            );

            app(BillAllocationService::class)->recalculateSalePaymentStatuses([$sale->id]);
            $afterState = [
                'number' => $sale->number,
                'customer_id' => $sale->customer_id,
                'date' => $sale->date?->toDateString(),
                'status' => $sale->status,
                'branch_id' => $sale->branch_id,
                'warehouse_id' => $validated['warehouse_id'],
                'currency_id' => $validated['currency_id'],
                'rate' => (float) $validated['rate'],
                'item_count' => count($validated['item_list']),
                'transaction_total' => (float) $validated['transaction_total'],
            ];

            $activityLogService->logUpdate(
                reference: $sale,
                before: $beforeState,
                after: $afterState,
                module: 'sale',
                description: "Sale #{$sale->number} updated.",
                metadata: [
                    'action' => 'sale_update',
                    'sale_type' => $validated['type'],
                    'transaction_id' => $transaction->id,
                ],
            );

            return $sale;
        });

        $redirect = redirect()->route('sales.index')->with('success', __('general.updated_successfully', ['resource' => __('general.resource.sale')]));

        if ($request->boolean('save_and_print')) {
            $redirect->with('print_url', route('sales.print', $sale));
        }

        return $redirect;
    }

    private function rebuildStockStateForCombos(array $combos): void
    {
        $uniqueCombos = collect($combos)
            ->filter(fn ($combo) => !empty($combo['item_id']) && !empty($combo['warehouse_id']) && !empty($combo['branch_id']))
            ->unique(fn ($combo) => implode('|', [
                $combo['branch_id'],
                $combo['warehouse_id'],
                $combo['item_id'],
            ]))
            ->values();

        foreach ($uniqueCombos as $combo) {
            $this->rebuildStockStateForItemWarehouse(
                branchId: $combo['branch_id'],
                warehouseId: $combo['warehouse_id'],
                itemId: $combo['item_id'],
            );
        }

        // avg_cost is intentionally NOT recalculated here.
        // rebuildStockStateForCombos is called mid-transaction (after old OUT movements are
        // deleted, before new ones are posted), so any replay at this point would see only
        // IN movements and produce a wrong avg_cost for items that are still in the sale.
        // Callers (update / destroy) recalculate only for items that were actually removed.
    }

    private function recalculateAvgCostForItem(string $itemId): void
    {
        $item = Item::find($itemId);

        if (!$item) {
            return;
        }

        $movements = StockMovement::query()
            ->where('item_id', $itemId)
            ->orderBy('date')
            ->orderBy('id')
            ->get(['movement_type', 'quantity', 'unit_cost']);

        $avgCost = 0.0;
        $runningQty = 0.0;

        foreach ($movements as $movement) {
            $qty = (float) $movement->quantity;
            if ($movement->movement_type === StockMovementType::IN) {
                $cost = (float) $movement->unit_cost;
                if ($runningQty + $qty > 0) {
                    $avgCost = (($runningQty * $avgCost) + ($qty * $cost)) / ($runningQty + $qty);
                }
                $runningQty += $qty;
            } else {
                $runningQty = max(0.0, $runningQty - $qty);
            }
        }

        if ($runningQty > 0) {
            $item->avg_cost = $avgCost;
            $item->save();
        }
    }

    private function rebuildStockStateForItemWarehouse(string $branchId, string $warehouseId, string $itemId): void
    {
        /** @var Item|null $item */
        $item = Item::query()->find($itemId);

        if (!$item) {
            return;
        }

        $movements = StockMovement::query()
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->where('item_id', $itemId)
            ->orderBy('date')
            ->orderBy('id')
            ->get();
        /** @var \Illuminate\Database\Eloquent\Collection<int, StockMovement> $movements */

        StockBalance::query()
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->where('item_id', $itemId)
            ->forceDelete();

        if ($movements->isEmpty()) {
            return;
        }

        $balanceBuckets = [];
        $unitFactors = [];

        foreach ($movements as $movement) {
            $expireDate = $movement->expire_date ? Carbon::parse($movement->expire_date)->toDateString() : null;
            $bucketKey = implode('|', [
                $movement->batch ?? '',
                $expireDate ?? '',
            ]);
            $movementQuantityInItemUnit = $this->convertMovementQuantityToItemUnit($movement, $item, $unitFactors);

            if (!isset($balanceBuckets[$bucketKey])) {
                $balanceBuckets[$bucketKey] = [
                    'branch_id' => $branchId,
                    'item_id' => $itemId,
                    'warehouse_id' => $warehouseId,
                    'batch' => $movement->batch,
                    'expire_date' => $expireDate,
                    'status' => $this->stockStatusValue($movement->status),
                    'quantity' => 0,
                    'in_quantity' => 0,
                    'in_value' => 0,
                ];
            }

            if ($this->stockStatusValue($movement->status) === StockStatus::POSTED->value) {
                $balanceBuckets[$bucketKey]['status'] = StockStatus::POSTED->value;
            }

            if ($movement->movement_type === StockMovementType::IN) {
                $balanceBuckets[$bucketKey]['quantity'] += $movementQuantityInItemUnit;
                $balanceBuckets[$bucketKey]['in_quantity'] += $movementQuantityInItemUnit;
                $balanceBuckets[$bucketKey]['in_value'] += $movementQuantityInItemUnit * (float) $movement->unit_cost;
            } else {
                $balanceBuckets[$bucketKey]['quantity'] -= $movementQuantityInItemUnit;
            }
        }

        foreach ($balanceBuckets as $bucket) {
            if ($bucket['quantity'] <= 0) {
                continue;
            }

            StockBalance::create([
                'branch_id' => $bucket['branch_id'],
                'item_id' => $bucket['item_id'],
                'warehouse_id' => $bucket['warehouse_id'],
                'batch' => $bucket['batch'],
                'expire_date' => $bucket['expire_date'],
                'status' => $bucket['status'],
                'quantity' => $bucket['quantity'],
            ]);
        }

        $inMovements = $movements
            ->filter(fn (StockMovement $movement) => $movement->movement_type === StockMovementType::IN)
            ->values();

        foreach ($inMovements as $movement) {
            $movement->qty_remaining = (float) $movement->quantity;
            $movement->save();
        }

        $outMovements = $movements
            ->filter(fn (StockMovement $movement) => $movement->movement_type === StockMovementType::OUT)
            ->values();

        foreach ($outMovements as $outMovement) {
            $remaining = $this->convertMovementQuantityToItemUnit($outMovement, $item, $unitFactors);

            foreach ($inMovements as $inMovement) {
                if ($remaining <= 0) {
                    break;
                }

                if ($item->is_batch_tracked && ($inMovement->batch ?? null) !== ($outMovement->batch ?? null)) {
                    continue;
                }

                $inExpireDate = $inMovement->expire_date ? Carbon::parse($inMovement->expire_date)->toDateString() : null;
                $outExpireDate = $outMovement->expire_date ? Carbon::parse($outMovement->expire_date)->toDateString() : null;
                if ($outExpireDate && $inExpireDate !== $outExpireDate) {
                    continue;
                }

                $available = (float) ($inMovement->qty_remaining ?? 0);

                if ($available <= 0) {
                    continue;
                }

                $deduct = min($available, $remaining);
                $inMovement->qty_remaining = $available - $deduct;
                $inMovement->save();
                $remaining -= $deduct;
            }
        }
    }

    private function convertMovementQuantityToItemUnit(StockMovement $movement, Item $item, array &$unitFactors): float
    {
        if ($movement->unit_measure_id === $item->unit_measure_id) {
            return (float) $movement->quantity;
        }

        if (!array_key_exists($movement->unit_measure_id, $unitFactors)) {
            $movementUnit = \App\Models\Administration\UnitMeasure::query()->find($movement->unit_measure_id);
            $itemUnit = \App\Models\Administration\UnitMeasure::query()->find($item->unit_measure_id);

            if (!$movementUnit || !$itemUnit || (float) $itemUnit->unit === 0.0) {
                $unitFactors[$movement->unit_measure_id] = 1.0;
            } else {
                $unitFactors[$movement->unit_measure_id] = (float) $movementUnit->unit / (float) $itemUnit->unit;
            }
        }

        return (float) $movement->quantity * $unitFactors[$movement->unit_measure_id];
    }

    private function buildSaleItemCostLookup(array $items): array
    {
        $itemIds = collect($items)
            ->pluck('item_id')
            ->filter()
            ->unique()
            ->values();

        if ($itemIds->isEmpty()) {
            return [[], [], []];
        }

        $itemModelsById = Item::query()
            ->whereIn('id', $itemIds)
            ->get(['id', 'name', 'unit_measure_id', 'income_account_id', 'cost_account_id', 'asset_account_id'])
            ->keyBy('id')
            ->all();

        $averageCostsByItemId = Item::query()
            ->whereIn('id', $itemIds)
            ->pluck('avg_cost', 'id')
            ->map(fn ($value) => (float) $value)
            ->all();

        $itemUnitMeasureIds = collect($itemModelsById)
            ->pluck('unit_measure_id');
        $selectedUnitMeasureIds = collect($items)
            ->pluck('unit_measure_id')
            ->filter();
        $allUnitMeasureIds = $itemUnitMeasureIds
            ->merge($selectedUnitMeasureIds)
            ->unique()
            ->values();

        $unitValuesById = UnitMeasure::query()
            ->whereIn('id', $allUnitMeasureIds)
            ->pluck('unit', 'id')
            ->map(fn ($value) => (float) $value)
            ->all();

        return [$itemModelsById, $averageCostsByItemId, $unitValuesById];
    }

    private function resolveUnitCost(
        float $avgCost,
        string $selectedUnitMeasureId,
        string $itemUnitMeasureId,
        array $unitValuesById
    ): float {
        if ($selectedUnitMeasureId === $itemUnitMeasureId) {
            return $avgCost;
        }

        $selectedUnit = (float) ($unitValuesById[$selectedUnitMeasureId] ?? 0);
        $itemUnit = (float) ($unitValuesById[$itemUnitMeasureId] ?? 0);
        if ($itemUnit === 0.0) {
            return $avgCost;
        }

        return ($selectedUnit * $avgCost) / $itemUnit;
    }

    public function destroy(Request $request, Sale $sale, ActivityLogService $activityLogService)
    {
        DB::transaction(function () use ($sale, $activityLogService) {
              $oldValues = [
            'number' => $sale->number,
            'customer' => $sale->customer?->name,
            'date' => $sale->date?->toDateString(),
            'status' => $sale->status,
            'branch' => $sale->branch?->name,
            'warehouse' => $sale->warehouse()?->name,
            'currency' => $sale->transaction?->currency?->name,
            'rate' => $sale->transaction?->rate,
            'item_count' => $sale->items()->count(),
            'transaction_total' => (float) ($sale->transaction_total ?? 0),
        ];

            $affectedCombos = $sale->items()
                ->get(['item_id', 'warehouse_id', 'branch_id'])
                ->map(fn ($item) => [
                    'item_id' => $item->item_id,
                    'warehouse_id' => $item->warehouse_id,
                    'branch_id' => $item->branch_id ?? $sale->branch_id,
                ])
                ->all();

            $stockMovementCombos = StockMovement::query()
                ->where('reference_type', Sale::class)
                ->where('reference_id', $sale->id)
                ->get(['item_id', 'warehouse_id', 'branch_id'])
                ->map(fn ($movement) => [
                    'item_id' => $movement->item_id,
                    'warehouse_id' => $movement->warehouse_id,
                    'branch_id' => $movement->branch_id,
                ])
                ->all();

            $transaction = Transaction::query()
                ->where('reference_type', Sale::class)
                ->where('reference_id', $sale->id)
                ->first();

            if ($transaction) {
                $transaction->lines()->delete();
                $transaction->delete();
            }

            StockMovement::query()
                ->where('reference_type', Sale::class)
                ->where('reference_id', $sale->id)
                ->delete();

            $sale->items()->delete();
            $sale->delete();

            $this->rebuildStockStateForCombos([
                ...$affectedCombos,
                ...$stockMovementCombos,
            ]);

            // Recalculate avg_cost for all items in the deleted sale.
            // rebuildStockStateForCombos no longer handles this; and since the OUTs are
            // soft-deleted, the replay correctly excludes them, giving the right avg_cost.
            $deletedItemIds = collect([...$affectedCombos, ...$stockMovementCombos])
                ->pluck('item_id')
                ->unique();
            foreach ($deletedItemIds as $deletedItemId) {
                $this->recalculateAvgCostForItem($deletedItemId);
            }

             $activityLogService->logDelete(
            reference: $sale,
            module: 'sale',
            description: "Sale #{$sale->number} deleted.",
            oldValues: $oldValues,
            metadata: [
                'action' => 'sale_delete',
            ],
        );

        });

        return redirect()->route('sales.index')->with('success', __('general.sale_deleted_successfully'));
    }

    public function restore(Request $request, Sale $sale, ActivityLogService $activityLogService)
    {
        DB::transaction(function () use ($sale, $activityLogService) {
            $sale->restore();
            $sale->items()->withTrashed()->restore();

            StockMovement::withTrashed()
                ->where('reference_type', Sale::class)
                ->where('reference_id', $sale->id)
                ->restore();

            $transaction = Transaction::withTrashed()
                ->where('reference_type', Sale::class)
                ->where('reference_id', $sale->id)
                ->first();

            if ($transaction) {
                $transaction->restore();
                $transaction->lines()->withTrashed()->restore();
            }

            $affectedCombos = $sale->items()
                ->withTrashed()
                ->get(['item_id', 'warehouse_id', 'branch_id'])
                ->map(fn ($item) => [
                    'item_id' => $item->item_id,
                    'warehouse_id' => $item->warehouse_id,
                    'branch_id' => $item->branch_id ?? $sale->branch_id,
                ])
                ->all();

            $this->rebuildStockStateForCombos($affectedCombos);
            $activityLogService->logAction(
            eventType: 'restored',
            reference: $sale,
            module: 'sale',
            description: "Sale #{$sale->number} restored.",
            newValues: [
                'number' => $sale->number,
                'status' => $sale->status,
            ],
            metadata: [
                'action' => 'sale_restore',
            ],
        );

        });

        return redirect()->route('sales.index')->with('success', __('general.sale_restored_successfully'));
    }

    public function forceDelete(Request $request, Sale $sale)
    {
        app(\App\Services\DeletedRecordService::class)->forceDelete('sales', (string) $sale->id);

        return redirect()->route('sales.index')->with('success', __('general.permanently_deleted_successfully', ['resource' => __('general.resource.sale')]));
    }

    public function updateSaleStatus(Request $request, Sale $sale, ActivityLogService $activityLogService)
    {
        $oldStatus = $sale->status;
        $sale->update(['status' => $request->status]);

        $activityLogService->logAction(
            eventType: in_array($request->status, ['posted', 'unposted', 'approved', 'rejected', 'cancelled', 'completed'], true)
                ? $request->status
                : 'status_changed',
            reference: $sale,
            module: 'sale',
            description: "Sale #{$sale->number} status changed from {$oldStatus} to {$request->status}.",
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => $sale->status],
            metadata: [
                'action' => 'sale_status_update',
            ],
        );

        return back()->with('success', __('general.sale_status_updated_successfully'));
    }

    public function openBills(Request $request, BillAllocationService $billAllocationService)
    {
        $ledgerId = (string) $request->query('ledger_id', '');
        $excludeReceiptId = (string) $request->query('exclude_receipt_id', '');

        return response()->json([
            'data' => $ledgerId ? $billAllocationService->openSalesForCustomer($ledgerId, $excludeReceiptId ?: null) : [],
        ]);
    }

    public function print(Request $request, Sale $sale, ActivityLogService $activityLogService)
    {
        $company = auth()->user()?->company;
        $sale = $sale->load([
            'customer',
            'items.item',
            'items.unitMeasure',
            'items.warehouse',
            'transaction.currency',
            'transaction.lines',
        ]);

        $activityLogService->logAction(
            eventType: 'print',
            reference: $sale,
            module: 'sale',
            description: "Sale #{$sale->number} printed.",
            metadata: [
                'action' => 'sale_print',
            ],
        );

        $invoiceTheme = user_preference('sale.invoice_theme', InvoiceThemeOptions::DEFAULT);
        $customFormat = null;

        // If the selected theme is not a built-in format, treat it as a custom InvoiceFormat ULID
        if (!in_array($invoiceTheme, InvoiceThemeOptions::ids()) && $company) {
            $customFormat = InvoiceFormat::where('id', $invoiceTheme)
                ->where('company_id', $company->id)
                ->first();

            // Fall back to default built-in if the custom format doesn't exist
            if (!$customFormat) {
                $invoiceTheme = InvoiceThemeOptions::DEFAULT;
            }
        }

        return inertia('Sale/Sales/Print', [
            'invoice' => new SaleResource($sale),
            'company' => $company,
            'invoiceTheme' => $invoiceTheme,
            'customFormat' => $customFormat,
        ]);

    }

    public function exportDetail(Request $request, Sale $sale, SpreadsheetExportService $exporter): BinaryFileResponse
    {
        $this->authorize('view', $sale);

        $sale->load(['customer', 'items.item', 'items.unitMeasure', 'transaction.currency', 'createdBy', 'updatedBy']);

        $stockMovements = StockMovement::where('reference_id', $sale->id)
            ->where('reference_type', Sale::class)
            ->where('movement_type', StockMovementType::OUT)
            ->get(['item_id', 'unit_cost', 'quantity'])
            ->keyBy('item_id');

        $rtl = in_array(app()->getLocale(), ['fa', 'ps'], true);
        $company = $request->user()?->company;
        $companyName = match (app()->getLocale()) {
            'fa'    => $company?->name_fa ?: $company?->name_en ?: $company?->abbreviation ?: config('app.name'),
            'ps'    => $company?->name_pa ?: $company?->name_en ?: $company?->abbreviation ?: config('app.name'),
            default => $company?->name_en ?: $company?->abbreviation ?: $company?->name_fa ?: $company?->name_pa ?: config('app.name'),
        };
        $currencySymbol = $sale->transaction?->currency?->symbol ?? '';
        $t = fn (string $group, string $key, string $fallback = '') => $exporter->localeTranslation($group, $key, $fallback);

        $title = $t('sale', 'sale', 'Sale') . ' #' . $sale->number;

        $saleTotal = $sale->items->sum(function ($item) use ($sale) {
            $rowTotal      = (float) $item->quantity * (float) $item->unit_price;
            $itemDiscount  = (float) ($item->discount ?? 0);
            $saleDiscount  = $sale->discount_type === 'percentage'
                ? $rowTotal * ((float) $sale->discount / 100)
                : (float) ($sale->discount ?? 0);
            return $rowTotal - $itemDiscount - $saleDiscount;
        });

        $typeStr   = $sale->type   instanceof \BackedEnum ? $sale->type->value   : (string) ($sale->type   ?? '-');
        $statusStr = $sale->status instanceof \BackedEnum ? $sale->status->value : (string) ($sale->status ?? '-');

        $summaryFields = [
            ['label' => $t('general', 'date',       'Date'),       'value' => $sale->date?->format('Y-m-d') ?? '-'],
            ['label' => $t('general', 'customer',   'Customer'),   'value' => $sale->customer?->name ?? '-'],
            ['label' => $t('general', 'type',        'Type'),       'value' => ucfirst($typeStr)],
            ['label' => $t('general', 'status',      'Status'),     'value' => ucfirst($statusStr)],
            ['label' => $t('general', 'amount',      'Amount'),     'value' => trim($currencySymbol . ' ' . number_format($saleTotal, 2))],
            ['label' => $t('general', 'created_by',  'Created By'), 'value' => $sale->createdBy?->name ?? '-'],
            ['label' => $t('general', 'updated_by',  'Updated By'), 'value' => $sale->updatedBy?->name ?? '-'],
        ];

        $rows = $sale->items->map(function ($item) use ($stockMovements) {
            $movement  = $stockMovements->get($item->item_id);
            $unitCost  = $movement ? (float) $movement->unit_cost : 0.0;
            $qty       = (float) $item->quantity;
            $price     = (float) $item->unit_price;
            $discount  = (float) ($item->discount ?? 0);
            $free      = (float) ($item->free ?? 0);
            $tax       = (float) ($item->tax ?? 0);
            $subtotal  = ($qty * $price) - $discount + $tax;
            $lineCost  = $unitCost * $qty;
            $profit    = $subtotal - $lineCost;

            return [
                'item_name'         => $item->item?->name ?? '-',
                'item_code'         => $item->item?->code ?? '-',
                'batch'             => $item->batch ?? '-',
                'expire_date'       => $item->expire_date?->format('Y-m-d') ?? '-',
                'quantity'          => $qty,
                'unit_measure_name' => $item->unitMeasure?->name ?? '-',
                'unit_price'        => $price,
                'discount'          => $discount,
                'free'              => $free,
                'tax'               => $tax,
                'subtotal'          => $subtotal,
                'unit_cost'         => $unitCost,
                'line_profit'       => $profit,
            ];
        })->all();

        $columns = [
            ['key' => 'item_name',         'label' => $t('item',    'item',        'Item')],
            ['key' => 'item_code',         'label' => $t('item',    'code',        'Code')],
            ['key' => 'batch',             'label' => $t('general', 'batch',       'Batch')],
            ['key' => 'expire_date',       'label' => $t('general', 'expire_date', 'Expiry')],
            ['key' => 'quantity',          'label' => $t('general', 'qty',         'Qty'),      'type' => 'number', 'align' => 'right'],
            ['key' => 'unit_measure_name', 'label' => $t('general', 'unit',        'Unit')],
            ['key' => 'unit_price',        'label' => $t('general', 'price',       'Price'),    'type' => 'money',  'align' => 'right'],
            ['key' => 'discount',          'label' => $t('general', 'discount',    'Discount'), 'type' => 'money',  'align' => 'right'],
            ['key' => 'free',              'label' => $t('general', 'free',        'Free'),     'type' => 'number', 'align' => 'right'],
            ['key' => 'tax',               'label' => $t('general', 'tax',         'Tax'),      'type' => 'money',  'align' => 'right'],
            ['key' => 'subtotal',          'label' => $t('general', 'total',       'Total'),    'type' => 'money',  'align' => 'right'],
            ['key' => 'unit_cost',         'label' => $t('general', 'unit_cost',   'Unit Cost'),'type' => 'money',  'align' => 'right'],
            ['key' => 'line_profit',       'label' => $t('general', 'line_profit', 'Profit/Loss'),'type' => 'money','align' => 'right'],
        ];

        return $exporter->download([
            'filename'          => 'sale-' . $sale->number . '-' . now()->format('Ymd-His') . '.xlsx',
            'sheet_name'        => $title,
            'sheet_title'       => $title,
            'title'             => $title,
            'company_name'      => $companyName,
            'exported_on'       => now()->format('Y m d'),
            'rtl'               => $rtl,
            'include_row_number'=> true,
            'row_number_label'  => $t('report', 'columns.no', '#'),
            'summary_fields'    => $summaryFields,
            'columns'           => $columns,
            'rows'              => $rows,
        ]);
    }

    private function stockStatusValue(mixed $status): string
    {
        return $status instanceof StockStatus ? $status->value : (string) $status;
    }
}
