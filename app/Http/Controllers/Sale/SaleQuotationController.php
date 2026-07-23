<?php

namespace App\Http\Controllers\Sale;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sale\SaleQuotationStoreRequest;
use App\Http\Requests\Sale\SaleQuotationUpdateRequest;
use App\Http\Resources\Sale\SaleQuotationListResource;
use App\Http\Resources\Sale\SaleQuotationResource;
use App\Models\Ledger\Ledger;
use App\Models\Sale\SaleQuotation;
use App\Models\Sale\SaleQuotationItem;
use App\Models\User;
use App\Enums\SaleQuotationStatus;
use App\Services\ActivityLogService;
use App\Services\DateConversionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SaleQuotationController extends Controller
{
    private $dateConversionService;

    public function __construct(DateConversionService $dateConversionService)
    {
        $this->authorizeResource(SaleQuotation::class, 'sale_quotation');
        $this->dateConversionService = $dateConversionService;
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortField = $request->input('sortField', 'id');
        $sortDirection = strtolower($request->input('sortDirection', 'desc')) === 'asc' ? 'asc' : 'desc';
        $filters = (array) $request->input('filters', []);
        $sortableFields = [
            'id' => 'sale_quotations.id',
            'number' => 'sale_quotations.number',
            'date' => 'sale_quotations.date',
            'valid_until' => 'sale_quotations.valid_until',
            'amount' => 'items_gross_total',
        ];
        $sortColumn = $sortableFields[$sortField] ?? 'sale_quotations.id';

        $itemGrossTotal = SaleQuotationItem::query()
            ->selectRaw('COALESCE(SUM(quantity * unit_price), 0)')
            ->whereColumn('sale_quotation_items.sale_quotation_id', 'sale_quotations.id')
            ->whereNull('sale_quotation_items.deleted_at');

        $saleQuotations = SaleQuotation::query()
            ->select([
                'sale_quotations.id',
                'sale_quotations.number',
                'sale_quotations.customer_id',
                'sale_quotations.date',
                'sale_quotations.valid_until',
                'sale_quotations.status',
            ])
            ->selectSub($itemGrossTotal, 'items_gross_total')
            ->with(['customer:id,name'])
            ->search($request->query('search'))
            ->filter($filters)
            ->orderBy($sortColumn, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Sale/SaleQuotations/Index', [
            'saleQuotations' => SaleQuotationListResource::collection($saleQuotations),
            'filterOptions' => [
                'customers' => Ledger::query()->where('type', 'customer')->orderBy('name')->get(['id', 'name']),
                'statuses' => SaleQuotationStatus::options(),
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
        $saleQuotationNumber = SaleQuotation::max('number') ? SaleQuotation::max('number') + 1 : 1;

        return inertia('Sale/SaleQuotations/Create', [
            'saleQuotationNumber' => $saleQuotationNumber,
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

    public function store(SaleQuotationStoreRequest $request, ActivityLogService $activityLogService)
    {
        $validated = $request->validated();

        $saleQuotation = DB::transaction(function () use ($validated, $activityLogService) {
            $postImmediately = (bool) user_preference('transaction.sale_quotation_post_immediately', false);
            $documentStatus = $postImmediately ? SaleQuotationStatus::POSTED->value : SaleQuotationStatus::DRAFT->value;
            $date = $validated['date'] ? $this->dateConversionService->toGregorian($validated['date']) : now()->toDateString();
            $validUntil = $validated['valid_until'] ?? null ? $this->dateConversionService->toGregorian($validated['valid_until']) : null;

            $saleQuotation = SaleQuotation::create([
                'number' => $validated['number'],
                'date' => $date,
                'valid_until' => $validUntil,
                'customer_id' => $validated['customer_id'],
                'currency_id' => $validated['currency_id'] ?? null,
                'rate' => $validated['rate'] ?? null,
                'warehouse_id' => $validated['warehouse_id'] ?? null,
                'discount' => $validated['discount'] ?? 0,
                'discount_type' => $validated['discount_type'] ?? null,
                'note' => $validated['note'] ?? null,
                'status' => $documentStatus,
            ]);

            $saleQuotation->items()->createMany($validated['item_list']);

            $activityLogService->logCreate(
                reference: $saleQuotation,
                module: 'sale_quotation',
                description: "Sale Quotation #{$saleQuotation->number} created.",
                newValues: [
                    'number' => $saleQuotation->number,
                    'customer_id' => $saleQuotation->customer_id,
                    'date' => $saleQuotation->date?->toDateString(),
                    'status' => $saleQuotation->status,
                    'item_count' => count($validated['item_list']),
                ],
                metadata: [
                    'action' => 'sale_quotation_store',
                ],
            );

            return $saleQuotation;
        });

        if ((bool) $request->create_and_new) {
            return redirect()->back()->with(
                'success',
                __('general.created_successfully', ['resource' => __('general.resource.sale_quotation')])
            );
        }

        return redirect()->route('sale-quotations.index')->with(
            'success',
            __('general.created_successfully', ['resource' => __('general.resource.sale_quotation')])
        );
    }

    public function show(Request $request, SaleQuotation $saleQuotation)
    {
        $saleQuotation->load([
            'items.item',
            'items.unitMeasure',
            'items.size',
            'items.category',
            'customer',
            'currency',
            'warehouse',
            'createdBy',
            'updatedBy',
        ]);

        $resource = new SaleQuotationResource($saleQuotation);

        if ($request->expectsJson()) {
            return response()->json(['data' => $resource]);
        }

        return inertia('Sale/SaleQuotations/Show', [
            'saleQuotation' => $resource,
        ]);
    }

    public function edit(Request $request, SaleQuotation $saleQuotation)
    {
        if ($saleQuotation->status !== SaleQuotationStatus::DRAFT->value) {
            return back()->with('error', 'Only draft documents can be edited.');
        }

        $saleQuotation->load(['items.item', 'items.unitMeasure', 'items.size', 'items.category', 'customer', 'currency', 'warehouse']);

        return inertia('Sale/SaleQuotations/Edit', [
            'saleQuotation' => new SaleQuotationResource($saleQuotation),
        ]);
    }

    public function update(SaleQuotationUpdateRequest $request, SaleQuotation $saleQuotation, ActivityLogService $activityLogService)
    {
        if ($saleQuotation->status !== SaleQuotationStatus::DRAFT->value) {
            return back()->with('error', 'Only draft documents can be edited.');
        }

        $beforeState = [
            'number' => $saleQuotation->number,
            'customer_id' => $saleQuotation->customer_id,
            'date' => $saleQuotation->date?->toDateString(),
            'item_count' => $saleQuotation->items()->count(),
        ];

        DB::transaction(function () use ($request, $saleQuotation, $activityLogService, $beforeState) {
            $validated = $request->validated();
            $date = $validated['date'] ? $this->dateConversionService->toGregorian($validated['date']) : $saleQuotation->date;
            $validUntil = $validated['valid_until'] ?? null ? $this->dateConversionService->toGregorian($validated['valid_until']) : null;

            $saleQuotation->update([
                'date' => $date,
                'valid_until' => $validUntil,
                'customer_id' => $validated['customer_id'],
                'currency_id' => $validated['currency_id'] ?? null,
                'rate' => $validated['rate'] ?? null,
                'warehouse_id' => $validated['warehouse_id'] ?? null,
                'discount' => $validated['discount'] ?? 0,
                'discount_type' => $validated['discount_type'] ?? null,
                'note' => $validated['note'] ?? null,
            ]);

            $saleQuotation->items()->forceDelete();
            $saleQuotation->items()->createMany($validated['item_list']);

            $activityLogService->logUpdate(
                reference: $saleQuotation,
                before: $beforeState,
                after: [
                    'number' => $saleQuotation->number,
                    'customer_id' => $saleQuotation->customer_id,
                    'date' => $saleQuotation->date?->toDateString(),
                    'item_count' => count($validated['item_list']),
                ],
                module: 'sale_quotation',
                description: "Sale Quotation #{$saleQuotation->number} updated.",
                metadata: ['action' => 'sale_quotation_update'],
            );
        });

        return redirect()->route('sale-quotations.index')->with(
            'success',
            __('general.updated_successfully', ['resource' => __('general.resource.sale_quotation')])
        );
    }

    public function post(SaleQuotation $saleQuotation, ActivityLogService $activityLogService)
    {
        $this->authorize('update', $saleQuotation);

        if ($saleQuotation->status !== SaleQuotationStatus::DRAFT->value) {
            abort(422, 'Only draft documents can be posted.');
        }

        $saleQuotation->update([
            'status' => SaleQuotationStatus::POSTED->value,
            'updated_by' => Auth::id(),
        ]);

        $activityLogService->logAction(
            eventType: 'posted',
            reference: $saleQuotation,
            module: 'sale_quotation',
            description: "Sale Quotation #{$saleQuotation->number} posted.",
            newValues: ['status' => $saleQuotation->status],
            metadata: ['action' => 'sale_quotation_post'],
        );

        return redirect()->back()->with('success', __('general.updated_successfully', ['resource' => __('general.resource.sale_quotation')]));
    }

    public function cancel(SaleQuotation $saleQuotation, ActivityLogService $activityLogService)
    {
        $this->authorize('update', $saleQuotation);

        if ($saleQuotation->status === SaleQuotationStatus::CANCELLED->value) {
            abort(422, 'Document is already cancelled.');
        }

        $saleQuotation->update([
            'status' => SaleQuotationStatus::CANCELLED->value,
            'updated_by' => Auth::id(),
        ]);

        $activityLogService->logAction(
            eventType: 'cancelled',
            reference: $saleQuotation,
            module: 'sale_quotation',
            description: "Sale Quotation #{$saleQuotation->number} cancelled.",
            newValues: ['status' => $saleQuotation->status],
            metadata: ['action' => 'sale_quotation_cancel'],
        );

        return redirect()->back()->with('success', __('general.updated_successfully', ['resource' => __('general.resource.sale_quotation')]));
    }

    public function print(SaleQuotation $saleQuotation, ActivityLogService $activityLogService)
    {
        $this->authorize('view', $saleQuotation);

        $company = auth()->user()?->company;

        $saleQuotation->load([
            'items.item',
            'items.unitMeasure',
            'items.size',
            'customer',
            'currency',
            'warehouse',
        ]);

        $activityLogService->logAction(
            eventType: 'print',
            reference: $saleQuotation,
            module: 'sale_quotation',
            description: "Sale Quotation #{$saleQuotation->number} printed.",
            metadata: ['action' => 'sale_quotation_print'],
        );

        return inertia('Sale/SaleQuotations/Print', [
            'quotation' => new SaleQuotationResource($saleQuotation),
            'company' => $company,
        ]);
    }

    public function destroy(Request $request, SaleQuotation $saleQuotation, ActivityLogService $activityLogService)
    {
        if ($saleQuotation->status !== SaleQuotationStatus::DRAFT->value) {
            return back()->with('error', 'Only draft documents can be deleted.');
        }

        DB::transaction(function () use ($saleQuotation, $activityLogService) {
            $oldValues = [
                'number' => $saleQuotation->number,
                'customer' => $saleQuotation->customer?->name,
                'date' => $saleQuotation->date?->toDateString(),
                'status' => $saleQuotation->status,
                'item_count' => $saleQuotation->items()->count(),
            ];

            $saleQuotation->items()->delete();
            $saleQuotation->delete();

            $activityLogService->logDelete(
                reference: $saleQuotation,
                module: 'sale_quotation',
                description: "Sale Quotation #{$saleQuotation->number} deleted.",
                oldValues: $oldValues,
                metadata: ['action' => 'sale_quotation_delete'],
            );
        });

        return redirect()->route('sale-quotations.index')->with(
            'success',
            __('general.deleted_successfully', ['resource' => __('general.resource.sale_quotation')])
        );
    }

    public function restore(Request $request, SaleQuotation $saleQuotation, ActivityLogService $activityLogService)
    {
        DB::transaction(function () use ($saleQuotation, $activityLogService) {
            $saleQuotation->restore();
            $saleQuotation->items()->withTrashed()->restore();

            $activityLogService->logAction(
                eventType: 'restored',
                reference: $saleQuotation,
                module: 'sale_quotation',
                description: "Sale Quotation #{$saleQuotation->number} restored.",
                newValues: ['number' => $saleQuotation->number, 'status' => $saleQuotation->status],
                metadata: ['action' => 'sale_quotation_restore'],
            );
        });

        return redirect()->route('sale-quotations.index')->with(
            'success',
            __('general.updated_successfully', ['resource' => __('general.resource.sale_quotation')])
        );
    }

    public function forceDelete(Request $request, SaleQuotation $saleQuotation)
    {
        app(\App\Services\DeletedRecordService::class)->forceDelete('sale_quotations', (string) $saleQuotation->id);

        return redirect()->route('sale-quotations.index')->with(
            'success',
            __('general.permanently_deleted_successfully', ['resource' => __('general.resource.sale_quotation')])
        );
    }
}
