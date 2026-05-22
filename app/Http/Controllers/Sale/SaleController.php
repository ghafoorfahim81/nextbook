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
        StockService $stockService,
        ActivityLogService $activityLogService
    )
    {
        $sale = DB::transaction(function () use ($request, $activityLogService) {
            $validated = $request->validated();

            $validated['type']   = $validated['sale_type'] ?? 'cash';
            $validated['status'] = TransactionStatus::DRAFT->value;
            $validated['date']   = $validated['date'] ? $this->dateConversionService->toGregorian($validated['date']) : null;

            $validated['currency_id']               = $validated['currency_id'] ?? null;
            $validated['rate']                       = $validated['rate'] ?? 1;
            $validated['bank_account_id']            = $validated['bank_account_id'] ?? null;

            $initialReceiptAmount    = (float) ($validated['payment']['amount'] ?? 0);
            $initialReceiptAccountId = $validated['payment']['account_id'] ?? null;
            $validated['initial_receipt_amount']     = $initialReceiptAmount;
            $validated['initial_receipt_account_id'] = $initialReceiptAccountId;

            $sale = Sale::create($validated);

            $validated['item_list'] = array_map(function ($item) use ($validated) {
                $item['discount']    = $item['item_discount'] ?? 0;
                $item['warehouse_id'] = $validated['warehouse_id'];
                return $item;
            }, $validated['item_list']);

            $sale->items()->createMany($validated['item_list']);

            $activityLogService->logCreate(
                reference: $sale,
                module: 'sale',
                description: "Sale #{$sale->number} created as draft.",
                newValues: [
                    'number'            => $sale->number,
                    'customer_id'       => $sale->customer_id,
                    'date'              => $sale->date?->toDateString(),
                    'status'            => TransactionStatus::DRAFT->value,
                    'branch_id'         => $sale->branch_id,
                    'warehouse_id'      => $validated['warehouse_id'],
                    'currency_id'       => $validated['currency_id'],
                    'item_count'        => count($validated['item_list']),
                    'transaction_total' => (float) ($validated['transaction_total'] ?? 0),
                ],
                metadata: ['action' => 'sale_store'],
            );

            return $sale;
        });

        if ((bool) $request->create_and_new) {
            return redirect()->back()->with(
                'success',
                __('general.created_successfully', ['resource' => __('general.resource.sale')])
            );
        }

        return redirect()->route('sales.show', $sale->id)->with(
            'success',
            __('general.created_successfully', ['resource' => __('general.resource.sale')])
        );
    }

    public function post(
        Sale $sale,
        TransactionService $transactionService,
        StockService $stockService,
        ActivityLogService $activityLogService
    ) {
        $this->authorize('update', $sale);

        $statusValue = $sale->status instanceof \BackedEnum
            ? $sale->status->value
            : (string) $sale->status;

        if ($statusValue !== TransactionStatus::DRAFT->value) {
            return back()->with('error', __('general.only_draft_can_be_posted'));
        }

        DB::transaction(function () use ($sale, $transactionService, $stockService) {
            $sale->load('items.item');
            $glAccounts  = Cache::get('gl_accounts');
            $lines       = [];
            $typeValue   = $sale->type instanceof \BackedEnum ? $sale->type->value : (string) $sale->type;
            $date        = $sale->date?->toDateString();

            [$itemModelsById, $averageCostsByItemId, $unitValuesById] = $this->buildSaleItemCostLookup(
                $sale->items->map(fn ($i) => ['item_id' => $i->item_id, 'unit_measure_id' => $i->unit_measure_id])->all()
            );

            foreach ($sale->items as $item) {
                $quantity       = (float) $item->quantity;
                $unitPrice      = (float) $item->unit_price;
                $lineGrossTotal = $quantity * $unitPrice;

                $itemModel = $itemModelsById[$item->item_id] ?? null;
                if (!$itemModel) {
                    throw (new \Illuminate\Database\Eloquent\ModelNotFoundException())->setModel(Item::class, [$item->item_id]);
                }

                $avgCost  = (float) ($averageCostsByItemId[$item->item_id] ?? 0);
                $unitCost = $this->resolveUnitCost(
                    avgCost: $avgCost,
                    selectedUnitMeasureId: $item->unit_measure_id,
                    itemUnitMeasureId: $itemModel->unit_measure_id,
                    unitValuesById: $unitValuesById,
                );
                $totalCost = $unitCost * $quantity;

                $stockService->post([
                    'item_id'         => $item->item_id,
                    'movement_type'   => StockMovementType::OUT->value,
                    'unit_measure_id' => $item->unit_measure_id,
                    'quantity'        => $quantity,
                    'source'          => StockSourceType::SALE->value,
                    'unit_cost'       => $unitCost,
                    'status'          => StockStatus::POSTED->value,
                    'batch'           => $item->batch ?? null,
                    'date'            => $date,
                    'expire_date'     => $item->expire_date ?? null,
                    'size_id'         => $sale->size_id ?? null,
                    'warehouse_id'    => $item->warehouse_id ?? $sale->warehouse_id,
                    'branch_id'       => $sale->branch_id,
                    'reference_type'  => Sale::class,
                    'reference_id'    => $sale->id,
                ]);

                $lines[] = [
                    'account_id' => $itemModel->income_account_id,
                    'ledger_id'  => null,
                    'debit'      => 0,
                    'credit'     => $lineGrossTotal,
                    'remark'     => 'Sale income for item: ' . $itemModel->name . ' #' . $sale->number,
                    'remark_fa'  => 'عاید فروش ' . $itemModel->name . ' #' . $sale->number,
                    'remark_ps'  => 'د ' . $itemModel->name . ' خرڅلاو څخه عاید د#' . $sale->number,
                ];
                $lines[] = [
                    'account_id' => $itemModel->cost_account_id,
                    'ledger_id'  => null,
                    'debit'      => $totalCost,
                    'credit'     => 0,
                    'remark'     => 'COGS for item: ' . $itemModel->name . ' #' . $sale->number,
                    'remark_fa'  => 'هزینه محصول فروخته شد ' . $itemModel->name . ' #' . $sale->number,
                    'remark_ps'  => 'د ' . $itemModel->name . ' د پلورل شوي توکو لګښت#' . $sale->number,
                ];
                $lines[] = [
                    'account_id' => $itemModel->asset_account_id,
                    'ledger_id'  => null,
                    'debit'      => 0,
                    'credit'     => $totalCost,
                    'remark'     => 'Inventory out for item: ' . $itemModel->name . ' #' . $sale->number,
                    'remark_fa'  => 'فروش جنس ' . $itemModel->name . ' #' . $sale->number,
                    'remark_ps'  => 'د ' . $itemModel->name . ' د خرڅلاو #' . $sale->number,
                ];
            }

            $transactionTotal = $sale->items->sum(function ($item) use ($sale) {
                $rowTotal     = (float) $item->quantity * (float) $item->unit_price;
                $itemDiscount = (float) ($item->discount ?? 0);
                return $rowTotal - $itemDiscount;
            });
            $discountTotal = 0;
            if ($sale->discount > 0) {
                $discountTotal = $sale->discount_type === 'percentage'
                    ? $transactionTotal * ($sale->discount / 100)
                    : (float) $sale->discount;
                $transactionTotal -= $discountTotal;
            }

            if ($discountTotal > 0) {
                $lines[] = [
                    'account_id' => $glAccounts['discount-to-customer'],
                    'ledger_id'  => null,
                    'debit'      => $discountTotal,
                    'credit'     => 0,
                    'remark'     => 'Sales discount for sale #' . $sale->number,
                    'remark_fa'  => 'تخفیف فروش ' . $sale->number,
                    'remark_ps'  => 'د ' . $sale->number . ' تخفیف خرڅلاو د',
                ];
            }

            if ($typeValue === \App\Enums\SalePurchaseType::Cash->value) {
                $lines[] = [
                    'account_id' => $sale->bank_account_id,
                    'ledger_id'  => null,
                    'debit'      => $transactionTotal,
                    'credit'     => 0,
                    'remark'     => 'Cash received from sale #' . $sale->number,
                    'remark_fa'  => 'دریافت نقدی بابت فروش #' . $sale->number,
                    'remark_ps'  => 'د #' . $sale->number . ' د نغدي اخیستلو',
                ];
            } elseif ($typeValue === \App\Enums\SalePurchaseType::OnLoan->value) {
                $lines[] = [
                    'account_id' => $glAccounts['account-receivable'],
                    'ledger_id'  => $sale->customer_id,
                    'debit'      => $transactionTotal,
                    'credit'     => 0,
                    'remark'     => 'Sale on loan #' . $sale->number,
                    'remark_fa'  => 'فروش قرضی بابت #' . $sale->number,
                    'remark_ps'  => 'د #' . $sale->number . ' د پور خرڅلاو',
                ];
            } elseif ($typeValue === \App\Enums\SalePurchaseType::Credit->value) {
                $initialAmount    = (float) ($sale->initial_receipt_amount ?? 0);
                $initialAccountId = $sale->initial_receipt_account_id;

                if ($initialAmount > 0 && $initialAccountId) {
                    $lines[] = [
                        'account_id' => $initialAccountId,
                        'ledger_id'  => null,
                        'debit'      => $initialAmount,
                        'credit'     => 0,
                        'remark'     => 'Partial receipt for sale #' . $sale->number,
                        'remark_fa'  => 'دریافت جزئی بابت فروش #' . $sale->number,
                        'remark_ps'  => 'د #' . $sale->number . ' جزوی دریافت',
                    ];
                    $lines[] = [
                        'account_id' => $glAccounts['account-receivable'],
                        'ledger_id'  => $sale->customer_id,
                        'debit'      => $transactionTotal - $initialAmount,
                        'credit'     => 0,
                        'remark'     => 'Remaining receivable for sale #' . $sale->number,
                        'remark_fa'  => 'فروش قرض #' . $sale->number,
                        'remark_ps'  => 'د #' . $sale->number . ' د پور خرڅلاو',
                    ];
                } else {
                    $lines[] = [
                        'account_id' => $glAccounts['account-receivable'],
                        'ledger_id'  => $sale->customer_id,
                        'debit'      => $transactionTotal,
                        'credit'     => 0,
                        'remark'     => 'Sale on credit #' . $sale->number,
                        'remark_fa'  => 'فروش قرضی بابت #' . $sale->number,
                        'remark_ps'  => 'د #' . $sale->number . ' د پور خرڅلاو',
                    ];
                }
            }

            $transactionService->post(
                header: [
                    'currency_id'    => $sale->currency_id,
                    'rate'           => $sale->rate ?? 1,
                    'date'           => $date,
                    'voucher_number' => $sale->number,
                    'remark'         => 'Sale #' . $sale->number,
                    'status'         => TransactionStatus::POSTED->value,
                    'reference_type' => Sale::class,
                    'reference_id'   => $sale->id,
                ],
                lines: $lines
            );

            $sale->update([
                'status'    => TransactionStatus::POSTED->value,
                'posted_at' => now(),
                'posted_by' => \Illuminate\Support\Facades\Auth::id(),
            ]);

            app(BillAllocationService::class)->recalculateSalePaymentStatuses([$sale->id]);
        });

        $activityLogService->logAction(
            eventType: 'posted',
            reference: $sale,
            module: 'sale',
            description: "Sale #{$sale->number} posted.",
            newValues: ['status' => TransactionStatus::POSTED->value],
            metadata: ['action' => 'sale_post'],
        );

        return back()->with('success', __('general.posted_successfully', ['resource' => __('general.resource.sale')]));
    }

    public function reverse(
        \Illuminate\Http\Request $request,
        Sale $sale,
        TransactionService $transactionService,
        ActivityLogService $activityLogService
    ) {
        $this->authorize('update', $sale);

        $statusValue = $sale->status instanceof \BackedEnum
            ? $sale->status->value
            : (string) $sale->status;

        if ($statusValue !== TransactionStatus::POSTED->value) {
            return back()->with('error', __('general.only_posted_can_be_reversed'));
        }

        DB::transaction(function () use ($sale, $transactionService, $request) {
            $transaction = Transaction::query()
                ->where('reference_type', Sale::class)
                ->where('reference_id', $sale->id)
                ->firstOrFail();

            $transactionService->reverse($transaction, $request->input('reason'));

            $affectedCombos = StockMovement::query()
                ->where('reference_type', Sale::class)
                ->where('reference_id', $sale->id)
                ->get(['item_id', 'warehouse_id', 'branch_id'])
                ->map(fn ($m) => [
                    'item_id'      => $m->item_id,
                    'warehouse_id' => $m->warehouse_id,
                    'branch_id'    => $m->branch_id,
                ])->all();

            StockMovement::query()
                ->where('reference_type', Sale::class)
                ->where('reference_id', $sale->id)
                ->update(['status' => StockStatus::VOIDED->value]);

            $this->rebuildStockStateForCombos($affectedCombos);

            $sale->update([
                'status'      => TransactionStatus::REVERSED->value,
                'reversed_at' => now(),
            ]);

            app(BillAllocationService::class)->recalculateSalePaymentStatuses([$sale->id]);
        });

        $activityLogService->logAction(
            eventType: 'reversed',
            reference: $sale,
            module: 'sale',
            description: "Sale #{$sale->number} reversed.",
            newValues: ['status' => TransactionStatus::REVERSED->value],
            metadata: ['action' => 'sale_reverse'],
        );

        return back()->with('success', __('general.reversed_successfully', ['resource' => __('general.resource.sale')]));
    }


    public function show(Request $request, Sale $sale)
    {
        $sale->load(['items.item', 'items.unitMeasure', 'customer', 'transaction.currency', 'createdBy', 'updatedBy']);

        $stockMovements = StockMovement::where('reference_id', $sale->id)
            ->where('reference_type', Sale::class)
            ->where('movement_type', StockMovementType::OUT)
            ->get(['item_id', 'unit_cost', 'quantity'])
            ->keyBy('item_id');

        $resource = (new SaleResource($sale))->additional(['stockMovements' => $stockMovements]);

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

        // Pre-load all stock movements for this sale in one query and attach to the resource
        // so SaleItemResource doesn't fire N individual queries.
        $stockMovements = StockMovement::where('reference_id', $sale->id)
            ->where('reference_type', Sale::class)
            ->where('movement_type', StockMovementType::OUT)
            ->get(['item_id', 'unit_cost', 'quantity'])
            ->keyBy('item_id');

        return inertia('Sale/Sales/Edit', [
            'sale' => (new SaleResource($sale))->additional(['stockMovements' => $stockMovements]),
            'bankAccounts' => $bankAccounts,
        ]);
    }

    public function update(
        SaleUpdateRequest $request,
        Sale $sale,
        ActivityLogService $activityLogService
    )
    {
        $statusValue = $sale->status instanceof \BackedEnum ? $sale->status->value : (string) $sale->status;
        if ($statusValue !== TransactionStatus::DRAFT->value) {
            return back()->with('error', __('general.only_draft_can_be_edited'));
        }

        $beforeState = [
            'number'            => $sale->number,
            'customer_id'       => $sale->customer_id,
            'date'              => $sale->date?->toDateString(),
            'status'            => $sale->status,
            'branch_id'         => $sale->branch_id,
            'warehouse_id'      => $sale->warehouse_id,
            'currency_id'       => $sale->currency_id,
            'rate'              => $sale->rate,
            'item_count'        => $sale->items()->count(),
        ];

        $sale = DB::transaction(function () use ($request, $sale, $activityLogService, $beforeState) {
            $validated = $request->validated();
            $validated['type']   = $validated['sale_type'] ?? $sale->type ?? 'cash';
            $validated['status'] = TransactionStatus::DRAFT->value;
            $validated['date']   = $validated['date'] ? $this->dateConversionService->toGregorian($validated['date']) : $sale->date;

            $validated['bank_account_id']            = $validated['bank_account_id'] ?? $sale->bank_account_id;
            $validated['currency_id']                = $validated['currency_id'] ?? $sale->currency_id;
            $validated['rate']                       = $validated['rate'] ?? $sale->rate ?? 1;
            $validated['initial_receipt_amount']     = (float) ($validated['payment']['amount'] ?? $sale->initial_receipt_amount ?? 0);
            $validated['initial_receipt_account_id'] = $validated['payment']['account_id'] ?? $sale->initial_receipt_account_id;

            $validated['item_list'] = array_map(function ($item) use ($validated) {
                $item['discount']     = $item['item_discount'] ?? 0;
                $item['warehouse_id'] = $validated['warehouse_id'];
                return $item;
            }, $validated['item_list']);

            $sale->update($validated);
            $sale->items()->forceDelete();
            $sale->items()->createMany($validated['item_list']);

            $afterState = [
                'number'            => $sale->number,
                'customer_id'       => $sale->customer_id,
                'date'              => $sale->date?->toDateString(),
                'status'            => $sale->status,
                'branch_id'         => $sale->branch_id,
                'warehouse_id'      => $validated['warehouse_id'],
                'currency_id'       => $validated['currency_id'],
                'rate'              => (float) $validated['rate'],
                'item_count'        => count($validated['item_list']),
                'transaction_total' => (float) ($validated['transaction_total'] ?? 0),
            ];

            $activityLogService->logUpdate(
                reference: $sale,
                before: $beforeState,
                after: $afterState,
                module: 'sale',
                description: "Sale #{$sale->number} updated.",
                metadata: ['action' => 'sale_update'],
            );

            return $sale;
        });

        return redirect()->route('sales.show', $sale->id)->with('success', __('general.updated_successfully', ['resource' => __('general.resource.sale')]));
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
        $statusValue = $sale->status instanceof \BackedEnum ? $sale->status->value : (string) $sale->status;
        if ($statusValue !== TransactionStatus::DRAFT->value) {
            return back()->with('error', __('general.only_draft_can_be_deleted'));
        }

        DB::transaction(function () use ($sale, $activityLogService) {
              $oldValues = [
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

        // dd('hiiii');
        // $sale->load([
        //     'items.item',
        //     'items.unitMeasure',
        //     'customer',
        //     'transaction.currency',
        //     'stockOuts.store',
        // ]);

        // $company = auth()->user()?->company;

        // $html = view('sales.print', [
        //     'sale' => $sale,
        //     'company' => $company,
        // ])->render();

        // $tempDir = storage_path('app/mpdf-temp');
        // if (!is_dir($tempDir)) {
        //     mkdir($tempDir, 0775, true);
        // }

        // $mpdf = new Mpdf([
        //     'default_font_size' => 10,
        //     'default_font' => 'dejavusans',
        //     'tempDir' => $tempDir,
        //     'margin_top' => 15,
        //     'margin_bottom' => 15,
        //     'margin_left' => 10,
        //     'margin_right' => 10,
        // ]);

        // $mpdf->SetTitle('Sale #' . $sale->number);
        // $mpdf->WriteHTML($html);

        // return response($mpdf->Output('sale-'.$sale->number.'.pdf', 'S'), 200, [
        //     'Content-Type' => 'application/pdf',
        //     'Content-Disposition' => 'inline; filename="sale-'.$sale->number.'.pdf"',
        // ]);
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
