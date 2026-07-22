<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\PurchaseReturnStoreRequest;
use App\Http\Requests\Purchase\PurchaseReturnUpdateRequest;
use App\Http\Resources\Purchase\PurchaseReturnListResource;
use App\Http\Resources\Purchase\PurchaseReturnResource;
use App\Models\Inventory\Item;
use App\Models\Ledger\Ledger;
use App\Models\Purchase\Purchase;
use App\Models\Purchase\PurchaseItem;
use App\Models\Purchase\PurchaseReturn;
use App\Models\Purchase\PurchaseReturnItem;
use App\Models\User;
use App\Enums\PurchaseReturnReason;
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

class PurchaseReturnController extends Controller
{
    private $dateConversionService;

    public function __construct(DateConversionService $dateConversionService)
    {
        $this->authorizeResource(PurchaseReturn::class, 'purchase_return');
        $this->dateConversionService = $dateConversionService;
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortField = $request->input('sortField', 'id');
        $sortDirection = strtolower($request->input('sortDirection', 'desc')) === 'asc' ? 'asc' : 'desc';
        $filters = (array) $request->input('filters', []);
        $sortableFields = [
            'id' => 'purchase_returns.id',
            'number' => 'purchase_returns.number',
            'date' => 'purchase_returns.date',
            'amount' => 'items_gross_total',
        ];
        $sortColumn = $sortableFields[$sortField] ?? 'purchase_returns.id';

        $itemGrossTotal = PurchaseReturnItem::query()
            ->selectRaw('COALESCE(SUM(quantity * unit_price), 0)')
            ->whereColumn('purchase_return_items.purchase_return_id', 'purchase_returns.id')
            ->whereNull('purchase_return_items.deleted_at');

        $purchaseReturns = PurchaseReturn::query()
            ->select([
                'purchase_returns.id',
                'purchase_returns.number',
                'purchase_returns.purchase_id',
                'purchase_returns.supplier_id',
                'purchase_returns.date',
                'purchase_returns.reason',
                'purchase_returns.status',
            ])
            ->selectSub($itemGrossTotal, 'items_gross_total')
            ->with(['supplier:id,name', 'purchase:id,number'])
            ->search($request->query('search'))
            ->filter($filters)
            ->orderBy($sortColumn, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Purchase/PurchaseReturns/Index', [
            'purchaseReturns' => PurchaseReturnListResource::collection($purchaseReturns),
            'filterOptions' => [
                'suppliers' => Ledger::query()->where('type', 'supplier')->orderBy('name')->get(['id', 'name']),
                'reasons' => PurchaseReturnReason::options(),
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
        $purchaseReturnNumber = PurchaseReturn::max('number') ? PurchaseReturn::max('number') + 1 : 1;

        $purchases = Purchase::query()
            ->with('supplier:id,name')
            ->where('status', TransactionStatus::POSTED->value)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get(['id', 'number', 'supplier_id'])
            ->map(fn (Purchase $purchase) => [
                'id' => $purchase->id,
                'number' => $purchase->number,
                'supplier_name' => $purchase->supplier?->name,
                'label' => '#' . $purchase->number . ($purchase->supplier ? ' - ' . $purchase->supplier->name : ''),
            ])
            ->values();

        return inertia('Purchase/PurchaseReturns/Create', [
            'purchaseReturnNumber' => $purchaseReturnNumber,
            'purchaseId' => $request->query('purchase_id'),
            'reasons' => PurchaseReturnReason::options(),
            'purchases' => $purchases,
        ]);
    }

    public function returnableItems(Request $request)
    {
        $this->authorize('create', PurchaseReturn::class);

        $purchase = Purchase::with(['items.item', 'items.unitMeasure', 'items.warehouse', 'items.size', 'transaction', 'supplier:id,name'])
            ->findOrFail($request->query('purchase_id'));

        abort_unless($purchase->status === TransactionStatus::POSTED->value, 422, 'Only posted purchases can be returned against.');

        $excludePurchaseReturnId = $request->query('exclude_purchase_return_id');

        return response()->json([
            'purchase' => [
                'id' => $purchase->id,
                'number' => $purchase->number,
                'supplier_id' => $purchase->supplier_id,
                'supplier_name' => $purchase->supplier?->name,
                'currency_id' => $purchase->transaction?->currency_id,
                'rate' => $purchase->transaction?->rate,
            ],
            'items' => $purchase->items->map(fn (PurchaseItem $item) => [
                'purchase_item_id' => $item->id,
                'item_id' => $item->item_id,
                'item_name' => $item->item?->name,
                'batch' => $item->batch,
                'color' => $item->color,
                'size_id' => $item->size_id,
                'size_name' => $item->size?->name,
                'expire_date' => $item->expire_date?->toDateString(),
                'unit_measure_id' => $item->unit_measure_id,
                'unit_measure_name' => $item->unitMeasure?->name,
                'warehouse_id' => $item->warehouse_id,
                'warehouse_name' => $item->warehouse?->name,
                'unit_price' => (float) $item->unit_price,
                'original_quantity' => (float) $item->quantity,
                'returned_quantity' => $item->returnedQuantity($excludePurchaseReturnId),
                'remaining_quantity' => $item->remainingReturnableQuantity($excludePurchaseReturnId),
            ])->values(),
        ]);
    }

    public function store(
        PurchaseReturnStoreRequest $request,
        TransactionService $transactionService,
        StockService $stockService,
        BillAllocationService $billAllocationService,
        ActivityLogService $activityLogService
    ) {
        $validated = $request->validated();

        $purchaseReturn = DB::transaction(function () use ($validated, $transactionService, $stockService, $billAllocationService, $activityLogService) {
            $purchase = Purchase::with('transaction')->findOrFail($validated['purchase_id']);

            abort_unless($purchase->status === TransactionStatus::POSTED->value, 422, 'Only posted purchases can be returned against.');

            $postImmediately = (bool) user_preference('transaction.purchase_return_post_immediately', true);
            $documentStatus = $postImmediately ? TransactionStatus::POSTED->value : TransactionStatus::DRAFT->value;
            $date = $validated['date'] ? $this->dateConversionService->toGregorian($validated['date']) : now()->toDateString();

            $purchaseReturn = PurchaseReturn::create([
                'number' => $validated['number'],
                'purchase_id' => $purchase->id,
                'supplier_id' => $purchase->supplier_id,
                'date' => $date,
                'reason' => $validated['reason'] ?? null,
                'description' => $validated['description'] ?? null,
                'status' => $documentStatus,
            ]);

            [$lines, $stockPayloads, $totalReturnedValue] = $this->buildReturnItemsAndLines(
                purchaseReturn: $purchaseReturn,
                purchase: $purchase,
                itemList: $validated['item_list'],
                postImmediately: $postImmediately,
                date: $date,
                stockService: $stockService,
            );

            $transaction = $transactionService->post(
                header: [
                    'currency_id' => $purchase->transaction->currency_id,
                    'rate' => $purchase->transaction->rate,
                    'date' => $date,
                    'voucher_number' => 'Purchase Return #' . $purchaseReturn->number,
                    'remark' => 'Purchase return for purchase number: ' . $purchase->number,
                    'status' => $documentStatus,
                    'reference_type' => PurchaseReturn::class,
                    'reference_id' => $purchaseReturn->id,
                    'posting_payload' => [
                        'stock_movements' => $stockPayloads,
                    ],
                ],
                lines: $lines
            );

            if ($postImmediately) {
                $billAllocationService->recalculatePurchasePaymentStatuses([$purchase->id]);
            }

            $activityLogService->logCreate(
                reference: $purchaseReturn,
                module: 'purchase_return',
                description: "Purchase Return #{$purchaseReturn->number} created against Purchase #{$purchase->number}.",
                newValues: [
                    'number' => $purchaseReturn->number,
                    'purchase_id' => $purchase->id,
                    'purchase_number' => $purchase->number,
                    'supplier_id' => $purchaseReturn->supplier_id,
                    'date' => $purchaseReturn->date?->toDateString(),
                    'status' => $purchaseReturn->status,
                    'item_count' => count($validated['item_list']),
                    'returned_total' => $totalReturnedValue,
                ],
                metadata: [
                    'action' => 'purchase_return_store',
                    'transaction_id' => $transaction->id,
                ],
            );

            return $purchaseReturn;
        });

        if ((bool) $request->create_and_new) {
            return redirect()->back()->with(
                'success',
                __('general.created_successfully', ['resource' => __('general.resource.purchase_return')])
            );
        }

        return redirect()->route('purchase-returns.index')->with(
            'success',
            __('general.created_successfully', ['resource' => __('general.resource.purchase_return')])
        );
    }

    public function show(Request $request, PurchaseReturn $purchaseReturn)
    {
        $purchaseReturn->load([
            'items.item',
            'items.unitMeasure',
            'items.warehouse',
            'purchase:id,number,supplier_id',
            'supplier',
            'transaction.currency',
            'transaction.lines.account',
            'createdBy',
            'updatedBy',
        ]);

        $resource = new PurchaseReturnResource($purchaseReturn);

        if ($request->expectsJson()) {
            return response()->json(['data' => $resource]);
        }

        return inertia('Purchase/PurchaseReturns/Show', [
            'purchaseReturn' => $resource,
        ]);
    }

    public function edit(Request $request, PurchaseReturn $purchaseReturn)
    {
        if ($purchaseReturn->status !== TransactionStatus::DRAFT->value) {
            return back()->with('error', 'Only draft documents can be edited.');
        }

        $purchaseReturn->load([
            'items.item',
            'items.unitMeasure',
            'items.warehouse',
            'purchase:id,number,supplier_id',
            'supplier:id,name',
            'transaction',
        ]);

        return inertia('Purchase/PurchaseReturns/Edit', [
            'purchaseReturn' => new PurchaseReturnResource($purchaseReturn),
            'reasons' => PurchaseReturnReason::options(),
        ]);
    }

    public function update(
        PurchaseReturnUpdateRequest $request,
        PurchaseReturn $purchaseReturn,
        TransactionService $transactionService,
        StockService $stockService,
        BillAllocationService $billAllocationService,
        ActivityLogService $activityLogService
    ) {
        if ($purchaseReturn->status !== TransactionStatus::DRAFT->value) {
            return back()->with('error', 'Only draft documents can be edited.');
        }

        $beforeState = [
            'number' => $purchaseReturn->number,
            'purchase_id' => $purchaseReturn->purchase_id,
            'date' => $purchaseReturn->date?->toDateString(),
            'status' => $purchaseReturn->status,
            'item_count' => $purchaseReturn->items()->count(),
        ];

        $purchaseReturn = DB::transaction(function () use ($request, $purchaseReturn, $transactionService, $stockService, $billAllocationService, $activityLogService, $beforeState) {
            $validated = $request->validated();
            $purchase = Purchase::with('transaction')->findOrFail($validated['purchase_id']);

            abort_unless($purchase->status === TransactionStatus::POSTED->value, 422, 'Only posted purchases can be returned against.');

            $date = $validated['date'] ? $this->dateConversionService->toGregorian($validated['date']) : $purchaseReturn->date;

            $purchaseReturn->update([
                'purchase_id' => $purchase->id,
                'supplier_id' => $purchase->supplier_id,
                'date' => $date,
                'reason' => $validated['reason'] ?? null,
                'description' => $validated['description'] ?? null,
            ]);

            // Release the reservations held by the previous version before rebuilding.
            $transaction = $purchaseReturn->transaction()->first();
            if ($transaction) {
                foreach ((array) data_get($transaction->posting_payload, 'stock_movements', []) as $oldPayload) {
                    $stockService->release($oldPayload);
                }
                $transaction->lines()->forceDelete();
                $transaction->forceDelete();
            }
            $purchaseReturn->items()->forceDelete();

            [$lines, $stockPayloads, $totalReturnedValue] = $this->buildReturnItemsAndLines(
                purchaseReturn: $purchaseReturn,
                purchase: $purchase,
                itemList: $validated['item_list'],
                postImmediately: false,
                date: $date,
                stockService: $stockService,
                excludingPurchaseReturnId: $purchaseReturn->id,
            );

            $transaction = $transactionService->post(
                header: [
                    'currency_id' => $purchase->transaction->currency_id,
                    'rate' => $purchase->transaction->rate,
                    'date' => $date,
                    'voucher_number' => 'Purchase Return #' . $purchaseReturn->number,
                    'remark' => 'Purchase return for purchase number: ' . $purchase->number,
                    'status' => TransactionStatus::DRAFT->value,
                    'reference_type' => PurchaseReturn::class,
                    'reference_id' => $purchaseReturn->id,
                    'posting_payload' => [
                        'stock_movements' => $stockPayloads,
                    ],
                ],
                lines: $lines
            );

            $afterState = [
                'number' => $purchaseReturn->number,
                'purchase_id' => $purchaseReturn->purchase_id,
                'date' => $purchaseReturn->date?->toDateString(),
                'status' => $purchaseReturn->status,
                'item_count' => count($validated['item_list']),
            ];

            $activityLogService->logUpdate(
                reference: $purchaseReturn,
                before: $beforeState,
                after: $afterState,
                module: 'purchase_return',
                description: "Purchase Return #{$purchaseReturn->number} updated.",
                metadata: [
                    'action' => 'purchase_return_update',
                    'transaction_id' => $transaction->id,
                    'returned_total' => $totalReturnedValue,
                ],
            );

            return $purchaseReturn;
        });

        return redirect()->route('purchase-returns.index')->with(
            'success',
            __('general.updated_successfully', ['resource' => __('general.resource.purchase_return')])
        );
    }

    /**
     * Build the return line items, GL lines, and stock payloads shared by store()/update().
     * Also creates the PurchaseReturnItem rows and posts/reserves stock as it goes.
     *
     * @return array{0: array, 1: array, 2: float}
     */
    private function buildReturnItemsAndLines(
        PurchaseReturn $purchaseReturn,
        Purchase $purchase,
        array $itemList,
        bool $postImmediately,
        string $date,
        StockService $stockService,
        ?string $excludingPurchaseReturnId = null,
    ): array {
        $purchaseItemIds = collect($itemList)->pluck('purchase_item_id')->unique()->values();
        $itemIds = PurchaseItem::whereIn('id', $purchaseItemIds)->pluck('item_id')->unique()->values();
        $itemModelsById = Item::query()
            ->whereIn('id', $itemIds)
            ->get(['id', 'name', 'asset_account_id'])
            ->keyBy('id');
        $glAccounts = Cache::get('gl_accounts');

        $lines = [];
        $stockPayloads = [];
        $totalReturnedValue = 0.0;

        foreach ($itemList as $row) {
            /** @var PurchaseItem|null $purchaseItem */
            $purchaseItem = PurchaseItem::where('id', $row['purchase_item_id'])->lockForUpdate()->first();

            if (!$purchaseItem || $purchaseItem->purchase_id !== $purchase->id) {
                throw ValidationException::withMessages([
                    'item_list' => __('The selected purchase item does not belong to this purchase.'),
                ]);
            }

            $quantity = (float) $row['quantity'];
            $alreadyReturned = $purchaseItem->returnedQuantity($excludingPurchaseReturnId);

            if ($alreadyReturned + $quantity > (float) $purchaseItem->quantity + 0.0001) {
                throw ValidationException::withMessages([
                    'item_list' => __('The return quantity exceeds the remaining returnable quantity.'),
                ]);
            }

            PurchaseReturnItem::create([
                'purchase_return_id' => $purchaseReturn->id,
                'purchase_item_id' => $purchaseItem->id,
                'item_id' => $purchaseItem->item_id,
                'batch' => $purchaseItem->batch,
                'color' => $purchaseItem->color,
                'expire_date' => $purchaseItem->expire_date,
                'quantity' => $quantity,
                'unit_measure_id' => $purchaseItem->unit_measure_id,
                'unit_price' => $purchaseItem->unit_price,
                'size_id' => $purchaseItem->size_id,
                'warehouse_id' => $purchaseItem->warehouse_id,
            ]);

            $itemModel = $itemModelsById->get($purchaseItem->item_id);
            if (!$itemModel) {
                throw (new \Illuminate\Database\Eloquent\ModelNotFoundException())->setModel(Item::class, [$purchaseItem->item_id]);
            }

            $unitPrice = (float) $purchaseItem->unit_price;
            $totalCost = $unitPrice * $quantity;
            $totalReturnedValue += $totalCost;

            $stockPayload = [
                'item_id' => $purchaseItem->item_id,
                'movement_type' => StockMovementType::OUT->value,
                'unit_measure_id' => $purchaseItem->unit_measure_id,
                'quantity' => $quantity,
                'source' => StockSourceType::PURCHASE_RETURN->value,
                'unit_cost' => $unitPrice,
                'unit_cost_override' => $unitPrice,
                'status' => $postImmediately ? StockStatus::POSTED->value : StockStatus::DRAFT->value,
                'batch' => $purchaseItem->batch,
                'color' => $purchaseItem->color,
                'date' => $date,
                'expire_date' => $purchaseItem->expire_date,
                'size_id' => $purchaseItem->size_id,
                'warehouse_id' => $purchaseItem->warehouse_id,
                'branch_id' => $purchaseReturn->branch_id,
                'reference_type' => PurchaseReturn::class,
                'reference_id' => $purchaseReturn->id,
            ];
            $stockPayloads[] = $stockPayload;

            if ($postImmediately) {
                $stockService->post($stockPayload);
            } else {
                $stockService->reserve($stockPayload);
            }

            $lines[] = [
                'account_id' => $itemModel->asset_account_id,
                'ledger_id' => null,
                'debit' => 0,
                'credit' => $totalCost,
                'remark' => 'Purchase return inventory reduction for item: ' . $itemModel->name . ' #' . $purchaseReturn->number,
            ];
        }

        $lines[] = [
            'account_id' => $glAccounts['account-payable'],
            'ledger_id' => $purchase->supplier_id,
            'debit' => $totalReturnedValue,
            'credit' => 0,
            'remark' => 'Purchase return debit for return #' . $purchaseReturn->number . ' (Purchase #' . $purchase->number . ')',
        ];

        return [$lines, $stockPayloads, $totalReturnedValue];
    }

    public function post(PurchaseReturn $purchaseReturn, TransactionService $transactionService, StockService $stockService, BillAllocationService $billAllocationService)
    {
        $this->authorize('update', $purchaseReturn);

        if ($purchaseReturn->status !== TransactionStatus::DRAFT->value) {
            abort(422, 'Only draft documents can be posted.');
        }

        try {
            DB::transaction(function () use ($purchaseReturn, $transactionService, $stockService, $billAllocationService) {
                $transaction = $purchaseReturn->transaction()->firstOrFail();

                foreach ((array) data_get($transaction->posting_payload, 'stock_movements', []) as $payload) {
                    $stockService->release($payload);
                    $stockService->post($payload);
                }

                $transactionService->postDraft($transaction);
                $purchaseReturn->update([
                    'status' => TransactionStatus::POSTED->value,
                    'updated_by' => Auth::id(),
                ]);

                $billAllocationService->recalculatePurchasePaymentStatuses([$purchaseReturn->purchase_id]);
            });
        } catch (ValidationException $e) {
            return redirect()->back()->with('error', $e->validator->errors()->first('stock') ?: $e->getMessage());
        }

        return redirect()->back()->with(
            'success',
            __('general.updated_successfully', ['resource' => __('general.resource.purchase_return')])
        );
    }

    public function reverse(Request $request, PurchaseReturn $purchaseReturn, TransactionService $transactionService, BillAllocationService $billAllocationService)
    {
        $this->authorize('update', $purchaseReturn);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        if ($purchaseReturn->status !== TransactionStatus::POSTED->value) {
            abort(422, 'Only posted documents can be reversed.');
        }

        DB::transaction(function () use ($purchaseReturn, $transactionService, $billAllocationService, $validated) {
            $transaction = $purchaseReturn->transaction()->firstOrFail();
            $transactionService->reverse($transaction, $validated['reason'], $purchaseReturn->number, PurchaseReturn::class);

            $purchaseReturn->update([
                'status' => TransactionStatus::REVERSED->value,
                'updated_by' => Auth::id(),
            ]);

            $billAllocationService->recalculatePurchasePaymentStatuses([$purchaseReturn->purchase_id]);
        });

        return redirect()->back()->with(
            'success',
            __('general.updated_successfully', ['resource' => __('general.resource.purchase_return')])
        );
    }

    public function destroy(Request $request, PurchaseReturn $purchaseReturn, ActivityLogService $activityLogService, StockService $stockService)
    {
        if ($purchaseReturn->status !== TransactionStatus::DRAFT->value) {
            return back()->with('error', 'Only draft documents can be deleted.');
        }

        DB::transaction(function () use ($purchaseReturn, $activityLogService, $stockService) {
            foreach ((array) data_get($purchaseReturn->transaction?->posting_payload, 'stock_movements', []) as $payload) {
                $stockService->release($payload);
            }

            $oldValues = [
                'number' => $purchaseReturn->number,
                'purchase_id' => $purchaseReturn->purchase_id,
                'supplier' => $purchaseReturn->supplier?->name,
                'date' => $purchaseReturn->date?->toDateString(),
                'status' => $purchaseReturn->status,
                'item_count' => $purchaseReturn->items()->count(),
            ];

            $purchaseReturn->items()->delete();
            $purchaseReturn->delete();

            $activityLogService->logDelete(
                reference: $purchaseReturn,
                module: 'purchase_return',
                description: "Purchase Return #{$purchaseReturn->number} deleted.",
                oldValues: $oldValues,
                metadata: [
                    'action' => 'purchase_return_delete',
                ],
            );
        });

        return redirect()->route('purchase-returns.index')->with(
            'success',
            __('general.deleted_successfully', ['resource' => __('general.resource.purchase_return')])
        );
    }

    public function restore(Request $request, PurchaseReturn $purchaseReturn, ActivityLogService $activityLogService)
    {
        DB::transaction(function () use ($purchaseReturn, $activityLogService) {
            $purchaseReturn->restore();
            $purchaseReturn->items()->withTrashed()->restore();

            $activityLogService->logAction(
                eventType: 'restored',
                reference: $purchaseReturn,
                module: 'purchase_return',
                description: "Purchase Return #{$purchaseReturn->number} restored.",
                newValues: [
                    'number' => $purchaseReturn->number,
                    'status' => $purchaseReturn->status,
                ],
                metadata: [
                    'action' => 'purchase_return_restore',
                ],
            );
        });

        return redirect()->route('purchase-returns.index')->with(
            'success',
            __('general.updated_successfully', ['resource' => __('general.resource.purchase_return')])
        );
    }

    public function forceDelete(Request $request, PurchaseReturn $purchaseReturn)
    {
        app(\App\Services\DeletedRecordService::class)->forceDelete('purchase_returns', (string) $purchaseReturn->id);

        return redirect()->route('purchase-returns.index')->with(
            'success',
            __('general.permanently_deleted_successfully', ['resource' => __('general.resource.purchase_return')])
        );
    }
}
