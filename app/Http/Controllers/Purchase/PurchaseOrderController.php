<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\PurchaseOrderStoreRequest;
use App\Http\Requests\Purchase\PurchaseOrderUpdateRequest;
use App\Http\Resources\Purchase\PurchaseOrderListResource;
use App\Http\Resources\Purchase\PurchaseOrderResource;
use App\Models\Ledger\Ledger;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Purchase\PurchaseOrderItem;
use App\Models\User;
use App\Enums\PurchaseOrderStatus;
use App\Services\ActivityLogService;
use App\Services\DateConversionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    private $dateConversionService;

    public function __construct(DateConversionService $dateConversionService)
    {
        $this->authorizeResource(PurchaseOrder::class, 'purchase_order');
        $this->dateConversionService = $dateConversionService;
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortField = $request->input('sortField', 'id');
        $sortDirection = strtolower($request->input('sortDirection', 'desc')) === 'asc' ? 'asc' : 'desc';
        $filters = (array) $request->input('filters', []);
        $sortableFields = [
            'id' => 'purchase_orders.id',
            'number' => 'purchase_orders.number',
            'date' => 'purchase_orders.date',
            'delivery_date' => 'purchase_orders.delivery_date',
            'amount' => 'items_gross_total',
        ];
        $sortColumn = $sortableFields[$sortField] ?? 'purchase_orders.id';

        $itemGrossTotal = PurchaseOrderItem::query()
            ->selectRaw('COALESCE(SUM(quantity * unit_price), 0)')
            ->whereColumn('purchase_order_items.purchase_order_id', 'purchase_orders.id')
            ->whereNull('purchase_order_items.deleted_at');

        $purchaseOrders = PurchaseOrder::query()
            ->select([
                'purchase_orders.id',
                'purchase_orders.number',
                'purchase_orders.supplier_id',
                'purchase_orders.date',
                'purchase_orders.delivery_date',
                'purchase_orders.status',
            ])
            ->selectSub($itemGrossTotal, 'items_gross_total')
            ->with(['supplier:id,name'])
            ->search($request->query('search'))
            ->filter($filters)
            ->orderBy($sortColumn, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Purchase/PurchaseOrders/Index', [
            'purchaseOrders' => PurchaseOrderListResource::collection($purchaseOrders),
            'filterOptions' => [
                'suppliers' => Ledger::query()->where('type', 'supplier')->orderBy('name')->get(['id', 'name']),
                'statuses' => PurchaseOrderStatus::options(),
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

    public function create()
    {
        $purchaseOrderNumber = PurchaseOrder::max('number') ? PurchaseOrder::max('number') + 1 : 1;

        return inertia('Purchase/PurchaseOrders/Create', [
            'purchaseOrderNumber' => $purchaseOrderNumber,
            'ledgers' => \App\Http\Resources\Ledger\LedgerOptionResource::collection(
                Ledger::query()
                    ->select(['id', 'name', 'code', 'type', 'currency_id', 'is_active', 'branch_id'])
                    ->where('type', 'supplier')
                    ->where('is_active', true)
                    ->orderBy('created_at', 'desc')
                    ->limit(200)
                    ->get()
            ),
        ]);
    }

    public function store(PurchaseOrderStoreRequest $request, ActivityLogService $activityLogService)
    {
        $validated = $request->validated();

        $purchaseOrder = DB::transaction(function () use ($validated, $activityLogService) {
            $postImmediately = (bool) user_preference('transaction.purchase_order_post_immediately', true);
            $documentStatus = $postImmediately ? PurchaseOrderStatus::POSTED->value : PurchaseOrderStatus::DRAFT->value;
            $date = $validated['date'] ? $this->dateConversionService->toGregorian($validated['date']) : now()->toDateString();
            $deliveryDate = $validated['delivery_date'] ?? null ? $this->dateConversionService->toGregorian($validated['delivery_date']) : null;

            $purchaseOrder = PurchaseOrder::create([
                'number' => $validated['number'],
                'date' => $date,
                'delivery_date' => $deliveryDate,
                'supplier_id' => $validated['supplier_id'],
                'currency_id' => $validated['currency_id'] ?? null,
                'rate' => $validated['rate'] ?? null,
                'warehouse_id' => $validated['warehouse_id'] ?? null,
                'discount' => $validated['discount'] ?? 0,
                'discount_type' => $validated['discount_type'] ?? null,
                'note' => $validated['note'] ?? null,
                'status' => $documentStatus,
            ]);

            $purchaseOrder->items()->createMany($validated['item_list']);

            $activityLogService->logCreate(
                reference: $purchaseOrder,
                module: 'purchase_order',
                description: "Purchase Order #{$purchaseOrder->number} created.",
                newValues: [
                    'number' => $purchaseOrder->number,
                    'supplier_id' => $purchaseOrder->supplier_id,
                    'date' => $purchaseOrder->date?->toDateString(),
                    'status' => $purchaseOrder->status,
                    'item_count' => count($validated['item_list']),
                ],
                metadata: [
                    'action' => 'purchase_order_store',
                ],
            );

            return $purchaseOrder;
        });

        if ((bool) $request->create_and_new) {
            return redirect()->back()->with(
                'success',
                __('general.created_successfully', ['resource' => __('general.resource.purchase_order')])
            );
        }

        return redirect()->route('purchase-orders.index')->with(
            'success',
            __('general.created_successfully', ['resource' => __('general.resource.purchase_order')])
        );
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load([
            'items.item',
            'items.unitMeasure',
            'items.size',
            'items.category',
            'supplier',
            'currency',
            'warehouse',
            'purchase:id,number,supplier_id',
            'createdBy',
            'updatedBy',
        ]);

        $resource = new PurchaseOrderResource($purchaseOrder);

        if ($request->expectsJson()) {
            return response()->json(['data' => $resource]);
        }

        return inertia('Purchase/PurchaseOrders/Show', [
            'purchaseOrder' => $resource,
        ]);
    }

    public function edit(Request $request, PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status !== PurchaseOrderStatus::DRAFT->value) {
            return back()->with('error', 'Only draft documents can be edited.');
        }

        $purchaseOrder->load(['items.item', 'items.unitMeasure', 'items.size', 'items.category', 'supplier', 'currency', 'warehouse']);

        return inertia('Purchase/PurchaseOrders/Edit', [
            'purchaseOrder' => new PurchaseOrderResource($purchaseOrder),
        ]);
    }

    public function update(PurchaseOrderUpdateRequest $request, PurchaseOrder $purchaseOrder, ActivityLogService $activityLogService)
    {
        if ($purchaseOrder->status !== PurchaseOrderStatus::DRAFT->value) {
            return back()->with('error', 'Only draft documents can be edited.');
        }

        $beforeState = [
            'number' => $purchaseOrder->number,
            'supplier_id' => $purchaseOrder->supplier_id,
            'date' => $purchaseOrder->date?->toDateString(),
            'item_count' => $purchaseOrder->items()->count(),
        ];

        DB::transaction(function () use ($request, $purchaseOrder, $activityLogService, $beforeState) {
            $validated = $request->validated();
            $date = $validated['date'] ? $this->dateConversionService->toGregorian($validated['date']) : $purchaseOrder->date;
            $deliveryDate = $validated['delivery_date'] ?? null ? $this->dateConversionService->toGregorian($validated['delivery_date']) : null;

            $purchaseOrder->update([
                'date' => $date,
                'delivery_date' => $deliveryDate,
                'supplier_id' => $validated['supplier_id'],
                'currency_id' => $validated['currency_id'] ?? null,
                'rate' => $validated['rate'] ?? null,
                'warehouse_id' => $validated['warehouse_id'] ?? null,
                'discount' => $validated['discount'] ?? 0,
                'discount_type' => $validated['discount_type'] ?? null,
                'note' => $validated['note'] ?? null,
            ]);

            $purchaseOrder->items()->forceDelete();
            $purchaseOrder->items()->createMany($validated['item_list']);

            $activityLogService->logUpdate(
                reference: $purchaseOrder,
                before: $beforeState,
                after: [
                    'number' => $purchaseOrder->number,
                    'supplier_id' => $purchaseOrder->supplier_id,
                    'date' => $purchaseOrder->date?->toDateString(),
                    'item_count' => count($validated['item_list']),
                ],
                module: 'purchase_order',
                description: "Purchase Order #{$purchaseOrder->number} updated.",
                metadata: ['action' => 'purchase_order_update'],
            );
        });

        return redirect()->route('purchase-orders.index')->with(
            'success',
            __('general.updated_successfully', ['resource' => __('general.resource.purchase_order')])
        );
    }

    public function post(PurchaseOrder $purchaseOrder, ActivityLogService $activityLogService)
    {
        $this->authorize('update', $purchaseOrder);

        if ($purchaseOrder->status !== PurchaseOrderStatus::DRAFT->value) {
            abort(422, 'Only draft documents can be posted.');
        }

        $purchaseOrder->update([
            'status' => PurchaseOrderStatus::POSTED->value,
            'updated_by' => Auth::id(),
        ]);

        $activityLogService->logAction(
            eventType: 'posted',
            reference: $purchaseOrder,
            module: 'purchase_order',
            description: "Purchase Order #{$purchaseOrder->number} posted.",
            newValues: ['status' => $purchaseOrder->status],
            metadata: ['action' => 'purchase_order_post'],
        );

        return redirect()->back()->with('success', __('general.updated_successfully', ['resource' => __('general.resource.purchase_order')]));
    }

    public function cancel(PurchaseOrder $purchaseOrder, ActivityLogService $activityLogService)
    {
        $this->authorize('update', $purchaseOrder);

        if ($purchaseOrder->status !== PurchaseOrderStatus::DRAFT->value) {
            abort(422, 'Only draft documents can be cancelled.');
        }

        $purchaseOrder->update([
            'status' => PurchaseOrderStatus::CANCELLED->value,
            'updated_by' => Auth::id(),
        ]);

        $activityLogService->logAction(
            eventType: 'cancelled',
            reference: $purchaseOrder,
            module: 'purchase_order',
            description: "Purchase Order #{$purchaseOrder->number} cancelled.",
            newValues: ['status' => $purchaseOrder->status],
            metadata: ['action' => 'purchase_order_cancel'],
        );

        return redirect()->back()->with('success', __('general.updated_successfully', ['resource' => __('general.resource.purchase_order')]));
    }

    public function destroy(Request $request, PurchaseOrder $purchaseOrder, ActivityLogService $activityLogService)
    {
        if ($purchaseOrder->status !== PurchaseOrderStatus::DRAFT->value) {
            return back()->with('error', 'Only draft documents can be deleted.');
        }

        DB::transaction(function () use ($purchaseOrder, $activityLogService) {
            $oldValues = [
                'number' => $purchaseOrder->number,
                'supplier' => $purchaseOrder->supplier?->name,
                'date' => $purchaseOrder->date?->toDateString(),
                'status' => $purchaseOrder->status,
                'item_count' => $purchaseOrder->items()->count(),
            ];

            $purchaseOrder->items()->delete();
            $purchaseOrder->delete();

            $activityLogService->logDelete(
                reference: $purchaseOrder,
                module: 'purchase_order',
                description: "Purchase Order #{$purchaseOrder->number} deleted.",
                oldValues: $oldValues,
                metadata: ['action' => 'purchase_order_delete'],
            );
        });

        return redirect()->route('purchase-orders.index')->with(
            'success',
            __('general.deleted_successfully', ['resource' => __('general.resource.purchase_order')])
        );
    }

    public function restore(Request $request, PurchaseOrder $purchaseOrder, ActivityLogService $activityLogService)
    {
        DB::transaction(function () use ($purchaseOrder, $activityLogService) {
            $purchaseOrder->restore();
            $purchaseOrder->items()->withTrashed()->restore();

            $activityLogService->logAction(
                eventType: 'restored',
                reference: $purchaseOrder,
                module: 'purchase_order',
                description: "Purchase Order #{$purchaseOrder->number} restored.",
                newValues: ['number' => $purchaseOrder->number, 'status' => $purchaseOrder->status],
                metadata: ['action' => 'purchase_order_restore'],
            );
        });

        return redirect()->route('purchase-orders.index')->with(
            'success',
            __('general.updated_successfully', ['resource' => __('general.resource.purchase_order')])
        );
    }

    public function forceDelete(Request $request, PurchaseOrder $purchaseOrder)
    {
        app(\App\Services\DeletedRecordService::class)->forceDelete('purchase_orders', (string) $purchaseOrder->id);

        return redirect()->route('purchase-orders.index')->with(
            'success',
            __('general.permanently_deleted_successfully', ['resource' => __('general.resource.purchase_order')])
        );
    }

    /**
     * List posted purchase orders eligible for conversion for a given supplier.
     * Feeds the picker dialog on the Purchase Create form.
     */
    public function eligibleForLedger(Request $request)
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $supplierId = $request->query('supplier_id');

        if (!$supplierId) {
            return response()->json(['purchase_orders' => []]);
        }

        $purchaseOrders = PurchaseOrder::query()
            ->where('supplier_id', $supplierId)
            ->where('status', PurchaseOrderStatus::POSTED->value)
            ->withCount('items')
            ->with('items:id,purchase_order_id,quantity,unit_price,discount')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'purchase_orders' => $purchaseOrders->map(fn (PurchaseOrder $purchaseOrder) => [
                'id' => $purchaseOrder->id,
                'number' => $purchaseOrder->number,
                'date' => $purchaseOrder->date?->toDateString(),
                'delivery_date' => $purchaseOrder->delivery_date?->toDateString(),
                'item_count' => $purchaseOrder->items_count,
                'amount' => $purchaseOrder->orderTotal(),
            ])->values(),
        ]);
    }

    /**
     * Full header + item detail for a posted purchase order, shaped for autofilling the Purchase Create form.
     */
    public function forConversion(PurchaseOrder $purchaseOrder)
    {
        $this->authorize('view', $purchaseOrder);

        abort_unless($purchaseOrder->status === PurchaseOrderStatus::POSTED->value, 422, 'Only posted purchase orders can be converted.');

        $purchaseOrder->load(['items.item', 'items.unitMeasure', 'supplier:id,name']);

        return response()->json([
            'purchase_order' => [
                'id' => $purchaseOrder->id,
                'number' => $purchaseOrder->number,
                'supplier_id' => $purchaseOrder->supplier_id,
                'supplier_name' => $purchaseOrder->supplier?->name,
                'currency_id' => $purchaseOrder->currency_id,
                'rate' => $purchaseOrder->rate,
                'warehouse_id' => $purchaseOrder->warehouse_id,
                'discount' => $purchaseOrder->discount,
                'discount_type' => $purchaseOrder->discount_type,
                'note' => $purchaseOrder->note,
            ],
            'items' => $purchaseOrder->items->map(fn (PurchaseOrderItem $item) => [
                'item_id' => $item->item_id,
                'item_name' => $item->item?->name,
                'quantity' => (float) $item->quantity,
                'free' => (float) $item->free,
                'unit_price' => (float) $item->unit_price,
                'unit_measure_id' => $item->unit_measure_id,
                'unit_measure_name' => $item->unitMeasure?->name,
                'batch' => $item->batch,
                'color' => $item->color,
                'expire_date' => $item->expire_date?->toDateString(),
                'size_id' => $item->size_id,
                'size_name' => $item->size?->name,
                'category_id' => $item->category_id,
                'discount' => (float) ($item->discount ?? 0),
            ])->values(),
        ]);
    }
}
