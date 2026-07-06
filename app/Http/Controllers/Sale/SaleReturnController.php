<?php

namespace App\Http\Controllers\Sale;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sale\SaleReturnStoreRequest;
use App\Http\Requests\Sale\SaleReturnUpdateRequest;
use App\Http\Resources\Sale\SaleReturnListResource;
use App\Http\Resources\Sale\SaleReturnResource;
use App\Models\Inventory\Item;
use App\Models\Ledger\Ledger;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleItem;
use App\Models\Sale\SaleReturn;
use App\Models\Sale\SaleReturnItem;
use App\Models\User;
use App\Enums\SaleReturnReason;
use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Enums\TransactionStatus;
use App\Services\ActivityLogService;
use App\Services\BillAllocationService;
use App\Services\DateConversionService;
use App\Services\StockService;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleReturnController extends Controller
{
    private $dateConversionService;

    public function __construct(DateConversionService $dateConversionService)
    {
        $this->authorizeResource(SaleReturn::class, 'sale_return');
        $this->dateConversionService = $dateConversionService;
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortField = $request->input('sortField', 'id');
        $sortDirection = strtolower($request->input('sortDirection', 'desc')) === 'asc' ? 'asc' : 'desc';
        $filters = (array) $request->input('filters', []);
        $sortableFields = [
            'id' => 'sale_returns.id',
            'number' => 'sale_returns.number',
            'date' => 'sale_returns.date',
            'amount' => 'items_gross_total',
        ];
        $sortColumn = $sortableFields[$sortField] ?? 'sale_returns.id';

        $itemGrossTotal = SaleReturnItem::query()
            ->selectRaw('COALESCE(SUM(quantity * unit_price), 0)')
            ->whereColumn('sale_return_items.sale_return_id', 'sale_returns.id')
            ->whereNull('sale_return_items.deleted_at');

        $saleReturns = SaleReturn::query()
            ->select([
                'sale_returns.id',
                'sale_returns.number',
                'sale_returns.sale_id',
                'sale_returns.customer_id',
                'sale_returns.date',
                'sale_returns.reason',
                'sale_returns.status',
            ])
            ->selectSub($itemGrossTotal, 'items_gross_total')
            ->with(['customer:id,name', 'sale:id,number'])
            ->search($request->query('search'))
            ->filter($filters)
            ->orderBy($sortColumn, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Sale/SaleReturns/Index', [
            'saleReturns' => SaleReturnListResource::collection($saleReturns),
            'filterOptions' => [
                'customers' => Ledger::query()->where('type', 'customer')->orderBy('name')->get(['id', 'name']),
                'reasons' => SaleReturnReason::options(),
                'statuses' => collect(TransactionStatus::cases())->map(fn ($status) => [
                    'id' => $status->value,
                    'name' => $status->getLabel(),
                ])->values(),
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
        $saleReturnNumber = SaleReturn::max('number') ? SaleReturn::max('number') + 1 : 1;

        $sales = Sale::query()
            ->with('customer:id,name')
            ->where('status', TransactionStatus::POSTED->value)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get(['id', 'number', 'customer_id'])
            ->map(fn (Sale $sale) => [
                'id' => $sale->id,
                'number' => $sale->number,
                'customer_name' => $sale->customer?->name,
                'label' => '#' . $sale->number . ($sale->customer ? ' - ' . $sale->customer->name : ''),
            ])
            ->values();

        return inertia('Sale/SaleReturns/Create', [
            'saleReturnNumber' => $saleReturnNumber,
            'saleId' => $request->query('sale_id'),
            'reasons' => SaleReturnReason::options(),
            'sales' => $sales,
        ]);
    }

    public function returnableItems(Request $request)
    {
        $this->authorize('create', SaleReturn::class);

        $sale = Sale::with(['items.item', 'items.unitMeasure', 'items.warehouse', 'transaction', 'customer:id,name'])
            ->findOrFail($request->query('sale_id'));

        abort_unless($sale->status === TransactionStatus::POSTED->value, 422, 'Only posted sales can be returned against.');

        $excludeSaleReturnId = $request->query('exclude_sale_return_id');

        return response()->json([
            'sale' => [
                'id' => $sale->id,
                'number' => $sale->number,
                'customer_id' => $sale->customer_id,
                'customer_name' => $sale->customer?->name,
                'currency_id' => $sale->transaction?->currency_id,
                'rate' => $sale->transaction?->rate,
            ],
            'items' => $sale->items->map(fn (SaleItem $item) => [
                'sale_item_id' => $item->id,
                'item_id' => $item->item_id,
                'item_name' => $item->item?->name,
                'batch' => $item->batch,
                'expire_date' => $item->expire_date?->toDateString(),
                'unit_measure_id' => $item->unit_measure_id,
                'unit_measure_name' => $item->unitMeasure?->name,
                'warehouse_id' => $item->warehouse_id,
                'warehouse_name' => $item->warehouse?->name,
                'unit_price' => (float) $item->unit_price,
                'net_unit_cost' => (float) $item->net_unit_cost,
                'original_quantity' => (float) $item->quantity,
                'returned_quantity' => $item->returnedQuantity($excludeSaleReturnId),
                'remaining_quantity' => $item->remainingReturnableQuantity($excludeSaleReturnId),
            ])->values(),
        ]);
    }

    public function store(
        SaleReturnStoreRequest $request,
        TransactionService $transactionService,
        StockService $stockService,
        BillAllocationService $billAllocationService,
        ActivityLogService $activityLogService
    ) {
        $validated = $request->validated();

        $saleReturn = DB::transaction(function () use ($validated, $transactionService, $stockService, $billAllocationService, $activityLogService) {
            $sale = Sale::with('transaction')->findOrFail($validated['sale_id']);

            abort_unless($sale->status === TransactionStatus::POSTED->value, 422, 'Only posted sales can be returned against.');

            $postImmediately = (bool) user_preference('transaction.sale_return_post_immediately', true);
            $documentStatus = $postImmediately ? TransactionStatus::POSTED->value : TransactionStatus::DRAFT->value;
            $date = $validated['date'] ? $this->dateConversionService->toGregorian($validated['date']) : now()->toDateString();

            $saleReturn = SaleReturn::create([
                'number' => $validated['number'],
                'sale_id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'date' => $date,
                'reason' => $validated['reason'] ?? null,
                'description' => $validated['description'] ?? null,
                'status' => $documentStatus,
            ]);

            [$lines, $stockPayloads, $totalReturnedValue] = $this->buildReturnItemsAndLines(
                saleReturn: $saleReturn,
                sale: $sale,
                itemList: $validated['item_list'],
                postImmediately: $postImmediately,
                date: $date,
                stockService: $stockService,
            );

            $transaction = $transactionService->post(
                header: [
                    'currency_id' => $sale->transaction->currency_id,
                    'rate' => $sale->transaction->rate,
                    'date' => $date,
                    'voucher_number' => 'Sale Return #' . $saleReturn->number,
                    'remark' => 'Sale return for sale number: ' . $sale->number,
                    'status' => $documentStatus,
                    'reference_type' => SaleReturn::class,
                    'reference_id' => $saleReturn->id,
                    'posting_payload' => [
                        'stock_movements' => $stockPayloads,
                    ],
                ],
                lines: $lines
            );

            if ($postImmediately) {
                $billAllocationService->recalculateSalePaymentStatuses([$sale->id]);
            }

            $activityLogService->logCreate(
                reference: $saleReturn,
                module: 'sale_return',
                description: "Sale Return #{$saleReturn->number} created against Sale #{$sale->number}.",
                newValues: [
                    'number' => $saleReturn->number,
                    'sale_id' => $sale->id,
                    'sale_number' => $sale->number,
                    'customer_id' => $saleReturn->customer_id,
                    'date' => $saleReturn->date?->toDateString(),
                    'status' => $saleReturn->status,
                    'item_count' => count($validated['item_list']),
                    'returned_total' => $totalReturnedValue,
                ],
                metadata: [
                    'action' => 'sale_return_store',
                    'transaction_id' => $transaction->id,
                ],
            );

            return $saleReturn;
        });

        if ((bool) $request->create_and_new) {
            return redirect()->back()->with(
                'success',
                __('general.created_successfully', ['resource' => __('general.resource.sale_return')])
            );
        }

        return redirect()->route('sale-returns.index')->with(
            'success',
            __('general.created_successfully', ['resource' => __('general.resource.sale_return')])
        );
    }

    public function show(Request $request, SaleReturn $saleReturn)
    {
        $saleReturn->load([
            'items.item',
            'items.unitMeasure',
            'items.warehouse',
            'sale:id,number,customer_id',
            'customer',
            'transaction.currency',
            'transaction.lines.account',
            'createdBy',
            'updatedBy',
        ]);

        $resource = new SaleReturnResource($saleReturn);

        if ($request->expectsJson()) {
            return response()->json(['data' => $resource]);
        }

        return inertia('Sale/SaleReturns/Show', [
            'saleReturn' => $resource,
        ]);
    }

    public function edit(Request $request, SaleReturn $saleReturn)
    {
        if ($saleReturn->status !== TransactionStatus::DRAFT->value) {
            return back()->with('error', 'Only draft documents can be edited.');
        }

        $saleReturn->load([
            'items.item',
            'items.unitMeasure',
            'items.warehouse',
            'sale:id,number,customer_id',
            'customer:id,name',
            'transaction',
        ]);

        return inertia('Sale/SaleReturns/Edit', [
            'saleReturn' => new SaleReturnResource($saleReturn),
            'reasons' => SaleReturnReason::options(),
        ]);
    }

    public function update(
        SaleReturnUpdateRequest $request,
        SaleReturn $saleReturn,
        TransactionService $transactionService,
        StockService $stockService,
        BillAllocationService $billAllocationService,
        ActivityLogService $activityLogService
    ) {
        if ($saleReturn->status !== TransactionStatus::DRAFT->value) {
            return back()->with('error', 'Only draft documents can be edited.');
        }

        $beforeState = [
            'number' => $saleReturn->number,
            'sale_id' => $saleReturn->sale_id,
            'date' => $saleReturn->date?->toDateString(),
            'status' => $saleReturn->status,
            'item_count' => $saleReturn->items()->count(),
        ];

        $saleReturn = DB::transaction(function () use ($request, $saleReturn, $transactionService, $stockService, $billAllocationService, $activityLogService, $beforeState) {
            $validated = $request->validated();
            $sale = Sale::with('transaction')->findOrFail($validated['sale_id']);

            abort_unless($sale->status === TransactionStatus::POSTED->value, 422, 'Only posted sales can be returned against.');

            $date = $validated['date'] ? $this->dateConversionService->toGregorian($validated['date']) : $saleReturn->date;

            $saleReturn->update([
                'sale_id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'date' => $date,
                'reason' => $validated['reason'] ?? null,
                'description' => $validated['description'] ?? null,
            ]);

            // Release the reservations held by the previous version before rebuilding.
            $transaction = $saleReturn->transaction()->first();
            if ($transaction) {
                foreach ((array) data_get($transaction->posting_payload, 'stock_movements', []) as $oldPayload) {
                    $stockService->release($oldPayload);
                }
                $transaction->lines()->forceDelete();
                $transaction->forceDelete();
            }
            $saleReturn->items()->forceDelete();

            [$lines, $stockPayloads, $totalReturnedValue] = $this->buildReturnItemsAndLines(
                saleReturn: $saleReturn,
                sale: $sale,
                itemList: $validated['item_list'],
                postImmediately: false,
                date: $date,
                stockService: $stockService,
                excludingSaleReturnId: $saleReturn->id,
            );

            $transaction = $transactionService->post(
                header: [
                    'currency_id' => $sale->transaction->currency_id,
                    'rate' => $sale->transaction->rate,
                    'date' => $date,
                    'voucher_number' => 'Sale Return #' . $saleReturn->number,
                    'remark' => 'Sale return for sale number: ' . $sale->number,
                    'status' => TransactionStatus::DRAFT->value,
                    'reference_type' => SaleReturn::class,
                    'reference_id' => $saleReturn->id,
                    'posting_payload' => [
                        'stock_movements' => $stockPayloads,
                    ],
                ],
                lines: $lines
            );

            $afterState = [
                'number' => $saleReturn->number,
                'sale_id' => $saleReturn->sale_id,
                'date' => $saleReturn->date?->toDateString(),
                'status' => $saleReturn->status,
                'item_count' => count($validated['item_list']),
            ];

            $activityLogService->logUpdate(
                reference: $saleReturn,
                before: $beforeState,
                after: $afterState,
                module: 'sale_return',
                description: "Sale Return #{$saleReturn->number} updated.",
                metadata: [
                    'action' => 'sale_return_update',
                    'transaction_id' => $transaction->id,
                    'returned_total' => $totalReturnedValue,
                ],
            );

            return $saleReturn;
        });

        return redirect()->route('sale-returns.index')->with(
            'success',
            __('general.updated_successfully', ['resource' => __('general.resource.sale_return')])
        );
    }

    /**
     * Build the return line items, GL lines, and stock payloads shared by store()/update().
     * Also creates the SaleReturnItem rows and posts/reserves stock as it goes.
     *
     * @return array{0: array, 1: array, 2: float}
     */
    private function buildReturnItemsAndLines(
        SaleReturn $saleReturn,
        Sale $sale,
        array $itemList,
        bool $postImmediately,
        string $date,
        StockService $stockService,
        ?string $excludingSaleReturnId = null,
    ): array {
        $saleItemIds = collect($itemList)->pluck('sale_item_id')->unique()->values();
        $itemIds = SaleItem::whereIn('id', $saleItemIds)->pluck('item_id')->unique()->values();
        $itemModelsById = Item::query()
            ->whereIn('id', $itemIds)
            ->get(['id', 'name', 'income_account_id', 'cost_account_id', 'asset_account_id'])
            ->keyBy('id');
        $glAccounts = Cache::get('gl_accounts');

        $lines = [];
        $stockPayloads = [];
        $totalReturnedValue = 0.0;

        foreach ($itemList as $row) {
            /** @var SaleItem|null $saleItem */
            $saleItem = SaleItem::where('id', $row['sale_item_id'])->lockForUpdate()->first();

            if (!$saleItem || $saleItem->sale_id !== $sale->id) {
                throw ValidationException::withMessages([
                    'item_list' => __('The selected sale item does not belong to this sale.'),
                ]);
            }

            $quantity = (float) $row['quantity'];
            $alreadyReturned = $saleItem->returnedQuantity($excludingSaleReturnId);

            if ($alreadyReturned + $quantity > (float) $saleItem->quantity + 0.0001) {
                throw ValidationException::withMessages([
                    'item_list' => __('The return quantity exceeds the remaining returnable quantity.'),
                ]);
            }

            SaleReturnItem::create([
                'sale_return_id' => $saleReturn->id,
                'sale_item_id' => $saleItem->id,
                'item_id' => $saleItem->item_id,
                'batch' => $saleItem->batch,
                'expire_date' => $saleItem->expire_date,
                'quantity' => $quantity,
                'unit_measure_id' => $saleItem->unit_measure_id,
                'unit_price' => $saleItem->unit_price,
                'net_unit_cost' => $saleItem->net_unit_cost,
                'size_id' => $saleItem->size_id,
                'warehouse_id' => $saleItem->warehouse_id,
            ]);

            $itemModel = $itemModelsById->get($saleItem->item_id);
            if (!$itemModel) {
                throw (new \Illuminate\Database\Eloquent\ModelNotFoundException())->setModel(Item::class, [$saleItem->item_id]);
            }

            $unitCost = (float) $saleItem->net_unit_cost;
            $lineGrossTotal = $quantity * (float) $saleItem->unit_price;
            $totalCost = $unitCost * $quantity;
            $totalReturnedValue += $lineGrossTotal;

            $stockPayload = [
                'item_id' => $saleItem->item_id,
                'movement_type' => StockMovementType::IN->value,
                'unit_measure_id' => $saleItem->unit_measure_id,
                'quantity' => $quantity,
                'source' => StockSourceType::SALE_RETURN->value,
                'unit_cost' => $unitCost,
                'unit_cost_override' => $unitCost,
                'status' => $postImmediately ? StockStatus::POSTED->value : StockStatus::DRAFT->value,
                'batch' => $saleItem->batch,
                'date' => $date,
                'expire_date' => $saleItem->expire_date,
                'size_id' => $saleItem->size_id,
                'warehouse_id' => $saleItem->warehouse_id,
                'branch_id' => $saleReturn->branch_id,
                'reference_type' => SaleReturn::class,
                'reference_id' => $saleReturn->id,
            ];
            $stockPayloads[] = $stockPayload;

            if ($postImmediately) {
                $stockService->post($stockPayload);
            } else {
                $stockService->reserve($stockPayload);
            }

            $lines[] = [
                'account_id' => $itemModel->income_account_id,
                'ledger_id' => null,
                'debit' => $lineGrossTotal,
                'credit' => 0,
                'remark' => 'Sale return revenue reduction for item: ' . $itemModel->name . ' #' . $saleReturn->number,
            ];
            $lines[] = [
                'account_id' => $itemModel->cost_account_id,
                'ledger_id' => null,
                'debit' => 0,
                'credit' => $totalCost,
                'remark' => 'Sale return COGS reversal for item: ' . $itemModel->name . ' #' . $saleReturn->number,
            ];
            $lines[] = [
                'account_id' => $itemModel->asset_account_id,
                'ledger_id' => null,
                'debit' => $totalCost,
                'credit' => 0,
                'remark' => 'Sale return inventory restock for item: ' . $itemModel->name . ' #' . $saleReturn->number,
            ];
        }

        $lines[] = [
            'account_id' => $glAccounts['account-receivable'],
            'ledger_id' => $sale->customer_id,
            'debit' => 0,
            'credit' => $totalReturnedValue,
            'remark' => 'Sale return credit for return #' . $saleReturn->number . ' (Sale #' . $sale->number . ')',
        ];

        return [$lines, $stockPayloads, $totalReturnedValue];
    }

    public function post(SaleReturn $saleReturn, TransactionService $transactionService, StockService $stockService, BillAllocationService $billAllocationService)
    {
        $this->authorize('update', $saleReturn);

        if ($saleReturn->status !== TransactionStatus::DRAFT->value) {
            abort(422, 'Only draft documents can be posted.');
        }

        try {
            DB::transaction(function () use ($saleReturn, $transactionService, $stockService, $billAllocationService) {
                $transaction = $saleReturn->transaction()->firstOrFail();

                foreach ((array) data_get($transaction->posting_payload, 'stock_movements', []) as $payload) {
                    $stockService->release($payload);
                    $stockService->post($payload);
                }

                $transactionService->postDraft($transaction);
                $saleReturn->update([
                    'status' => TransactionStatus::POSTED->value,
                    'updated_by' => Auth::id(),
                ]);

                $billAllocationService->recalculateSalePaymentStatuses([$saleReturn->sale_id]);
            });
        } catch (ValidationException $e) {
            return redirect()->back()->with('error', $e->validator->errors()->first('stock') ?: $e->getMessage());
        }

        return redirect()->back()->with(
            'success',
            __('general.updated_successfully', ['resource' => __('general.resource.sale_return')])
        );
    }

    public function reverse(Request $request, SaleReturn $saleReturn, TransactionService $transactionService, BillAllocationService $billAllocationService)
    {
        $this->authorize('update', $saleReturn);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        if ($saleReturn->status !== TransactionStatus::POSTED->value) {
            abort(422, 'Only posted documents can be reversed.');
        }

        DB::transaction(function () use ($saleReturn, $transactionService, $billAllocationService, $validated) {
            $transaction = $saleReturn->transaction()->firstOrFail();
            $transactionService->reverse($transaction, $validated['reason'], $saleReturn->number, SaleReturn::class);

            $saleReturn->update([
                'status' => TransactionStatus::REVERSED->value,
                'updated_by' => Auth::id(),
            ]);

            $billAllocationService->recalculateSalePaymentStatuses([$saleReturn->sale_id]);
        });

        return redirect()->back()->with(
            'success',
            __('general.updated_successfully', ['resource' => __('general.resource.sale_return')])
        );
    }

    public function destroy(Request $request, SaleReturn $saleReturn, ActivityLogService $activityLogService, StockService $stockService)
    {
        if ($saleReturn->status !== TransactionStatus::DRAFT->value) {
            return back()->with('error', 'Only draft documents can be deleted.');
        }

        DB::transaction(function () use ($saleReturn, $activityLogService, $stockService) {
            foreach ((array) data_get($saleReturn->transaction?->posting_payload, 'stock_movements', []) as $payload) {
                $stockService->release($payload);
            }

            $oldValues = [
                'number' => $saleReturn->number,
                'sale_id' => $saleReturn->sale_id,
                'customer' => $saleReturn->customer?->name,
                'date' => $saleReturn->date?->toDateString(),
                'status' => $saleReturn->status,
                'item_count' => $saleReturn->items()->count(),
            ];

            $saleReturn->items()->delete();
            $saleReturn->delete();

            $activityLogService->logDelete(
                reference: $saleReturn,
                module: 'sale_return',
                description: "Sale Return #{$saleReturn->number} deleted.",
                oldValues: $oldValues,
                metadata: [
                    'action' => 'sale_return_delete',
                ],
            );
        });

        return redirect()->route('sale-returns.index')->with(
            'success',
            __('general.deleted_successfully', ['resource' => __('general.resource.sale_return')])
        );
    }

    public function restore(Request $request, SaleReturn $saleReturn, ActivityLogService $activityLogService)
    {
        DB::transaction(function () use ($saleReturn, $activityLogService) {
            $saleReturn->restore();
            $saleReturn->items()->withTrashed()->restore();

            $activityLogService->logAction(
                eventType: 'restored',
                reference: $saleReturn,
                module: 'sale_return',
                description: "Sale Return #{$saleReturn->number} restored.",
                newValues: [
                    'number' => $saleReturn->number,
                    'status' => $saleReturn->status,
                ],
                metadata: [
                    'action' => 'sale_return_restore',
                ],
            );
        });

        return redirect()->route('sale-returns.index')->with(
            'success',
            __('general.updated_successfully', ['resource' => __('general.resource.sale_return')])
        );
    }

    public function forceDelete(Request $request, SaleReturn $saleReturn)
    {
        app(\App\Services\DeletedRecordService::class)->forceDelete('sale_returns', (string) $saleReturn->id);

        return redirect()->route('sale-returns.index')->with(
            'success',
            __('general.permanently_deleted_successfully', ['resource' => __('general.resource.sale_return')])
        );
    }
}
