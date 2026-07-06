<?php

namespace App\Http\Controllers\Sale;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sale\SaleOrderStoreRequest;
use App\Http\Requests\Sale\SaleOrderUpdateRequest;
use App\Http\Resources\Sale\SaleOrderListResource;
use App\Http\Resources\Sale\SaleOrderResource;
use App\Models\Ledger\Ledger;
use App\Models\Sale\SaleOrder;
use App\Models\Sale\SaleOrderItem;
use App\Models\User;
use App\Enums\SaleOrderStatus;
use App\Services\ActivityLogService;
use App\Services\DateConversionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SaleOrderController extends Controller
{
    private $dateConversionService;

    public function __construct(DateConversionService $dateConversionService)
    {
        $this->authorizeResource(SaleOrder::class, 'sale_order');
        $this->dateConversionService = $dateConversionService;
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortField = $request->input('sortField', 'id');
        $sortDirection = strtolower($request->input('sortDirection', 'desc')) === 'asc' ? 'asc' : 'desc';
        $filters = (array) $request->input('filters', []);
        $sortableFields = [
            'id' => 'sale_orders.id',
            'number' => 'sale_orders.number',
            'date' => 'sale_orders.date',
            'delivery_date' => 'sale_orders.delivery_date',
            'amount' => 'items_gross_total',
        ];
        $sortColumn = $sortableFields[$sortField] ?? 'sale_orders.id';

        $itemGrossTotal = SaleOrderItem::query()
            ->selectRaw('COALESCE(SUM(quantity * unit_price), 0)')
            ->whereColumn('sale_order_items.sale_order_id', 'sale_orders.id')
            ->whereNull('sale_order_items.deleted_at');

        $saleOrders = SaleOrder::query()
            ->select([
                'sale_orders.id',
                'sale_orders.number',
                'sale_orders.customer_id',
                'sale_orders.date',
                'sale_orders.delivery_date',
                'sale_orders.status',
            ])
            ->selectSub($itemGrossTotal, 'items_gross_total')
            ->with(['customer:id,name'])
            ->search($request->query('search'))
            ->filter($filters)
            ->orderBy($sortColumn, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Sale/SaleOrders/Index', [
            'saleOrders' => SaleOrderListResource::collection($saleOrders),
            'filterOptions' => [
                'customers' => Ledger::query()->where('type', 'customer')->orderBy('name')->get(['id', 'name']),
                'statuses' => SaleOrderStatus::options(),
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
        $saleOrderNumber = SaleOrder::max('number') ? SaleOrder::max('number') + 1 : 1;

        return inertia('Sale/SaleOrders/Create', [
            'saleOrderNumber' => $saleOrderNumber,
            'ledgers' => \App\Http\Resources\Ledger\LedgerOptionResource::collection(
                Ledger::query()
                    ->select(['id', 'name', 'code', 'type', 'currency_id', 'is_active', 'branch_id'])
                    ->where('type', 'customer')
                    ->where('is_active', true)
                    ->orderBy('created_at', 'desc')
                    ->limit(200)
                    ->get()
            ),
        ]);
    }

    public function store(SaleOrderStoreRequest $request, ActivityLogService $activityLogService)
    {
        $validated = $request->validated();

        $saleOrder = DB::transaction(function () use ($validated, $activityLogService) {
            $postImmediately = (bool) user_preference('transaction.sale_order_post_immediately', true);
            $documentStatus = $postImmediately ? SaleOrderStatus::POSTED->value : SaleOrderStatus::DRAFT->value;
            $date = $validated['date'] ? $this->dateConversionService->toGregorian($validated['date']) : now()->toDateString();
            $deliveryDate = $validated['delivery_date'] ?? null ? $this->dateConversionService->toGregorian($validated['delivery_date']) : null;

            $saleOrder = SaleOrder::create([
                'number' => $validated['number'],
                'date' => $date,
                'delivery_date' => $deliveryDate,
                'customer_id' => $validated['customer_id'],
                'currency_id' => $validated['currency_id'] ?? null,
                'rate' => $validated['rate'] ?? null,
                'warehouse_id' => $validated['warehouse_id'] ?? null,
                'discount' => $validated['discount'] ?? 0,
                'discount_type' => $validated['discount_type'] ?? null,
                'note' => $validated['note'] ?? null,
                'status' => $documentStatus,
            ]);

            $saleOrder->items()->createMany($validated['item_list']);

            $activityLogService->logCreate(
                reference: $saleOrder,
                module: 'sale_order',
                description: "Sale Order #{$saleOrder->number} created.",
                newValues: [
                    'number' => $saleOrder->number,
                    'customer_id' => $saleOrder->customer_id,
                    'date' => $saleOrder->date?->toDateString(),
                    'status' => $saleOrder->status,
                    'item_count' => count($validated['item_list']),
                ],
                metadata: [
                    'action' => 'sale_order_store',
                ],
            );

            return $saleOrder;
        });

        if ((bool) $request->create_and_new) {
            return redirect()->back()->with(
                'success',
                __('general.created_successfully', ['resource' => __('general.resource.sale_order')])
            );
        }

        return redirect()->route('sale-orders.index')->with(
            'success',
            __('general.created_successfully', ['resource' => __('general.resource.sale_order')])
        );
    }

    public function show(Request $request, SaleOrder $saleOrder)
    {
        $saleOrder->load([
            'items.item',
            'items.unitMeasure',
            'items.size',
            'items.category',
            'customer',
            'currency',
            'warehouse',
            'sale:id,number,customer_id',
            'createdBy',
            'updatedBy',
        ]);

        $resource = new SaleOrderResource($saleOrder);

        if ($request->expectsJson()) {
            return response()->json(['data' => $resource]);
        }

        return inertia('Sale/SaleOrders/Show', [
            'saleOrder' => $resource,
        ]);
    }

    public function edit(Request $request, SaleOrder $saleOrder)
    {
        if ($saleOrder->status !== SaleOrderStatus::DRAFT->value) {
            return back()->with('error', 'Only draft documents can be edited.');
        }

        $saleOrder->load(['items.item', 'items.unitMeasure', 'items.size', 'items.category', 'customer', 'currency', 'warehouse']);

        return inertia('Sale/SaleOrders/Edit', [
            'saleOrder' => new SaleOrderResource($saleOrder),
        ]);
    }

    public function update(SaleOrderUpdateRequest $request, SaleOrder $saleOrder, ActivityLogService $activityLogService)
    {
        if ($saleOrder->status !== SaleOrderStatus::DRAFT->value) {
            return back()->with('error', 'Only draft documents can be edited.');
        }

        $beforeState = [
            'number' => $saleOrder->number,
            'customer_id' => $saleOrder->customer_id,
            'date' => $saleOrder->date?->toDateString(),
            'item_count' => $saleOrder->items()->count(),
        ];

        DB::transaction(function () use ($request, $saleOrder, $activityLogService, $beforeState) {
            $validated = $request->validated();
            $date = $validated['date'] ? $this->dateConversionService->toGregorian($validated['date']) : $saleOrder->date;
            $deliveryDate = $validated['delivery_date'] ?? null ? $this->dateConversionService->toGregorian($validated['delivery_date']) : null;

            $saleOrder->update([
                'date' => $date,
                'delivery_date' => $deliveryDate,
                'customer_id' => $validated['customer_id'],
                'currency_id' => $validated['currency_id'] ?? null,
                'rate' => $validated['rate'] ?? null,
                'warehouse_id' => $validated['warehouse_id'] ?? null,
                'discount' => $validated['discount'] ?? 0,
                'discount_type' => $validated['discount_type'] ?? null,
                'note' => $validated['note'] ?? null,
            ]);

            $saleOrder->items()->forceDelete();
            $saleOrder->items()->createMany($validated['item_list']);

            $activityLogService->logUpdate(
                reference: $saleOrder,
                before: $beforeState,
                after: [
                    'number' => $saleOrder->number,
                    'customer_id' => $saleOrder->customer_id,
                    'date' => $saleOrder->date?->toDateString(),
                    'item_count' => count($validated['item_list']),
                ],
                module: 'sale_order',
                description: "Sale Order #{$saleOrder->number} updated.",
                metadata: ['action' => 'sale_order_update'],
            );
        });

        return redirect()->route('sale-orders.index')->with(
            'success',
            __('general.updated_successfully', ['resource' => __('general.resource.sale_order')])
        );
    }

    public function post(SaleOrder $saleOrder, ActivityLogService $activityLogService)
    {
        $this->authorize('update', $saleOrder);

        if ($saleOrder->status !== SaleOrderStatus::DRAFT->value) {
            abort(422, 'Only draft documents can be posted.');
        }

        $saleOrder->update([
            'status' => SaleOrderStatus::POSTED->value,
            'updated_by' => Auth::id(),
        ]);

        $activityLogService->logAction(
            eventType: 'posted',
            reference: $saleOrder,
            module: 'sale_order',
            description: "Sale Order #{$saleOrder->number} posted.",
            newValues: ['status' => $saleOrder->status],
            metadata: ['action' => 'sale_order_post'],
        );

        return redirect()->back()->with('success', __('general.updated_successfully', ['resource' => __('general.resource.sale_order')]));
    }

    public function cancel(SaleOrder $saleOrder, ActivityLogService $activityLogService)
    {
        $this->authorize('update', $saleOrder);

        if (!in_array($saleOrder->status, [SaleOrderStatus::DRAFT->value, SaleOrderStatus::POSTED->value], true)) {
            abort(422, 'Only draft or posted documents can be cancelled.');
        }

        $saleOrder->update([
            'status' => SaleOrderStatus::CANCELLED->value,
            'updated_by' => Auth::id(),
        ]);

        $activityLogService->logAction(
            eventType: 'cancelled',
            reference: $saleOrder,
            module: 'sale_order',
            description: "Sale Order #{$saleOrder->number} cancelled.",
            newValues: ['status' => $saleOrder->status],
            metadata: ['action' => 'sale_order_cancel'],
        );

        return redirect()->back()->with('success', __('general.updated_successfully', ['resource' => __('general.resource.sale_order')]));
    }

    public function destroy(Request $request, SaleOrder $saleOrder, ActivityLogService $activityLogService)
    {
        if ($saleOrder->status !== SaleOrderStatus::DRAFT->value) {
            return back()->with('error', 'Only draft documents can be deleted.');
        }

        DB::transaction(function () use ($saleOrder, $activityLogService) {
            $oldValues = [
                'number' => $saleOrder->number,
                'customer' => $saleOrder->customer?->name,
                'date' => $saleOrder->date?->toDateString(),
                'status' => $saleOrder->status,
                'item_count' => $saleOrder->items()->count(),
            ];

            $saleOrder->items()->delete();
            $saleOrder->delete();

            $activityLogService->logDelete(
                reference: $saleOrder,
                module: 'sale_order',
                description: "Sale Order #{$saleOrder->number} deleted.",
                oldValues: $oldValues,
                metadata: ['action' => 'sale_order_delete'],
            );
        });

        return redirect()->route('sale-orders.index')->with(
            'success',
            __('general.deleted_successfully', ['resource' => __('general.resource.sale_order')])
        );
    }

    public function restore(Request $request, SaleOrder $saleOrder, ActivityLogService $activityLogService)
    {
        DB::transaction(function () use ($saleOrder, $activityLogService) {
            $saleOrder->restore();
            $saleOrder->items()->withTrashed()->restore();

            $activityLogService->logAction(
                eventType: 'restored',
                reference: $saleOrder,
                module: 'sale_order',
                description: "Sale Order #{$saleOrder->number} restored.",
                newValues: ['number' => $saleOrder->number, 'status' => $saleOrder->status],
                metadata: ['action' => 'sale_order_restore'],
            );
        });

        return redirect()->route('sale-orders.index')->with(
            'success',
            __('general.updated_successfully', ['resource' => __('general.resource.sale_order')])
        );
    }

    public function forceDelete(Request $request, SaleOrder $saleOrder)
    {
        app(\App\Services\DeletedRecordService::class)->forceDelete('sale_orders', (string) $saleOrder->id);

        return redirect()->route('sale-orders.index')->with(
            'success',
            __('general.permanently_deleted_successfully', ['resource' => __('general.resource.sale_order')])
        );
    }

    /**
     * List posted sale orders eligible for conversion for a given customer.
     * Feeds the picker dialog on the Sale Create form.
     */
    public function eligibleForLedger(Request $request)
    {
        $this->authorize('viewAny', SaleOrder::class);

        $customerId = $request->query('customer_id');

        if (!$customerId) {
            return response()->json(['sale_orders' => []]);
        }

        $saleOrders = SaleOrder::query()
            ->where('customer_id', $customerId)
            ->where('status', SaleOrderStatus::POSTED->value)
            ->withCount('items')
            ->with('items:id,sale_order_id,quantity,unit_price,discount')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'sale_orders' => $saleOrders->map(fn (SaleOrder $saleOrder) => [
                'id' => $saleOrder->id,
                'number' => $saleOrder->number,
                'date' => $saleOrder->date?->toDateString(),
                'delivery_date' => $saleOrder->delivery_date?->toDateString(),
                'item_count' => $saleOrder->items_count,
                'amount' => $saleOrder->orderTotal(),
            ])->values(),
        ]);
    }

    /**
     * Full header + item detail for a posted sale order, shaped for autofilling the Sale Create form.
     */
    public function forConversion(SaleOrder $saleOrder)
    {
        $this->authorize('view', $saleOrder);

        abort_unless($saleOrder->status === SaleOrderStatus::POSTED->value, 422, 'Only posted sale orders can be converted.');

        $saleOrder->load(['items.item', 'items.unitMeasure', 'customer:id,name']);

        return response()->json([
            'sale_order' => [
                'id' => $saleOrder->id,
                'number' => $saleOrder->number,
                'customer_id' => $saleOrder->customer_id,
                'customer_name' => $saleOrder->customer?->name,
                'currency_id' => $saleOrder->currency_id,
                'rate' => $saleOrder->rate,
                'warehouse_id' => $saleOrder->warehouse_id,
                'discount' => $saleOrder->discount,
                'discount_type' => $saleOrder->discount_type,
                'note' => $saleOrder->note,
            ],
            'items' => $saleOrder->items->map(fn (SaleOrderItem $item) => [
                'item_id' => $item->item_id,
                'item_name' => $item->item?->name,
                'quantity' => (float) $item->quantity,
                'free' => (float) $item->free,
                'unit_price' => (float) $item->unit_price,
                'unit_measure_id' => $item->unit_measure_id,
                'unit_measure_name' => $item->unitMeasure?->name,
                'batch' => $item->batch,
                'expire_date' => $item->expire_date?->toDateString(),
                'size_id' => $item->size_id,
                'category_id' => $item->category_id,
                'discount' => (float) ($item->discount ?? 0),
            ])->values(),
        ]);
    }
}
