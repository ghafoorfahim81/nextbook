<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\PurchaseQuotationStoreRequest;
use App\Http\Requests\Purchase\PurchaseQuotationUpdateRequest;
use App\Http\Resources\Purchase\PurchaseQuotationListResource;
use App\Http\Resources\Purchase\PurchaseQuotationResource;
use App\Models\Ledger\Ledger;
use App\Models\Purchase\PurchaseQuotation;
use App\Models\Purchase\PurchaseQuotationItem;
use App\Models\User;
use App\Enums\PurchaseQuotationStatus;
use App\Services\ActivityLogService;
use App\Services\DateConversionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseQuotationController extends Controller
{
    private $dateConversionService;

    public function __construct(DateConversionService $dateConversionService)
    {
        $this->authorizeResource(PurchaseQuotation::class, 'purchase_quotation');
        $this->dateConversionService = $dateConversionService;
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortField = $request->input('sortField', 'id');
        $sortDirection = strtolower($request->input('sortDirection', 'desc')) === 'asc' ? 'asc' : 'desc';
        $filters = (array) $request->input('filters', []);
        $sortableFields = [
            'id' => 'purchase_quotations.id',
            'number' => 'purchase_quotations.number',
            'date' => 'purchase_quotations.date',
            'valid_until' => 'purchase_quotations.valid_until',
            'amount' => 'items_gross_total',
        ];
        $sortColumn = $sortableFields[$sortField] ?? 'purchase_quotations.id';

        $itemGrossTotal = PurchaseQuotationItem::query()
            ->selectRaw('COALESCE(SUM(quantity * unit_price), 0)')
            ->whereColumn('purchase_quotation_items.purchase_quotation_id', 'purchase_quotations.id')
            ->whereNull('purchase_quotation_items.deleted_at');

        $purchaseQuotations = PurchaseQuotation::query()
            ->select([
                'purchase_quotations.id',
                'purchase_quotations.number',
                'purchase_quotations.supplier_id',
                'purchase_quotations.date',
                'purchase_quotations.valid_until',
                'purchase_quotations.status',
            ])
            ->selectSub($itemGrossTotal, 'items_gross_total')
            ->with(['supplier:id,name'])
            ->search($request->query('search'))
            ->filter($filters)
            ->orderBy($sortColumn, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Purchase/PurchaseQuotations/Index', [
            'purchaseQuotations' => PurchaseQuotationListResource::collection($purchaseQuotations),
            'filterOptions' => [
                'suppliers' => Ledger::query()->where('type', 'supplier')->orderBy('name')->get(['id', 'name']),
                'statuses' => PurchaseQuotationStatus::options(),
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
        $purchaseQuotationNumber = PurchaseQuotation::max('number') ? PurchaseQuotation::max('number') + 1 : 1;

        return inertia('Purchase/PurchaseQuotations/Create', [
            'purchaseQuotationNumber' => $purchaseQuotationNumber,
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

    public function store(PurchaseQuotationStoreRequest $request, ActivityLogService $activityLogService)
    {
        $validated = $request->validated();

        $purchaseQuotation = DB::transaction(function () use ($validated, $activityLogService) {
            $postImmediately = (bool) user_preference('transaction.purchase_quotation_post_immediately', false);
            $documentStatus = $postImmediately ? PurchaseQuotationStatus::POSTED->value : PurchaseQuotationStatus::DRAFT->value;
            $date = $validated['date'] ? $this->dateConversionService->toGregorian($validated['date']) : now()->toDateString();
            $validUntil = $validated['valid_until'] ?? null ? $this->dateConversionService->toGregorian($validated['valid_until']) : null;

            $purchaseQuotation = PurchaseQuotation::create([
                'number' => $validated['number'],
                'date' => $date,
                'valid_until' => $validUntil,
                'supplier_id' => $validated['supplier_id'],
                'currency_id' => $validated['currency_id'] ?? null,
                'rate' => $validated['rate'] ?? null,
                'warehouse_id' => $validated['warehouse_id'] ?? null,
                'discount' => $validated['discount'] ?? 0,
                'discount_type' => $validated['discount_type'] ?? null,
                'note' => $validated['note'] ?? null,
                'status' => $documentStatus,
            ]);

            $purchaseQuotation->items()->createMany($validated['item_list']);

            $activityLogService->logCreate(
                reference: $purchaseQuotation,
                module: 'purchase_quotation',
                description: "Purchase Quotation #{$purchaseQuotation->number} created.",
                newValues: [
                    'number' => $purchaseQuotation->number,
                    'supplier_id' => $purchaseQuotation->supplier_id,
                    'date' => $purchaseQuotation->date?->toDateString(),
                    'status' => $purchaseQuotation->status,
                    'item_count' => count($validated['item_list']),
                ],
                metadata: [
                    'action' => 'purchase_quotation_store',
                ],
            );

            return $purchaseQuotation;
        });

        if ((bool) $request->create_and_new) {
            return redirect()->back()->with(
                'success',
                __('general.created_successfully', ['resource' => __('general.resource.purchase_quotation')])
            );
        }

        return redirect()->route('purchase-quotations.index')->with(
            'success',
            __('general.created_successfully', ['resource' => __('general.resource.purchase_quotation')])
        );
    }

    public function show(Request $request, PurchaseQuotation $purchaseQuotation)
    {
        $purchaseQuotation->load([
            'items.item',
            'items.unitMeasure',
            'items.size',
            'items.category',
            'supplier',
            'currency',
            'warehouse',
            'createdBy',
            'updatedBy',
        ]);

        $resource = new PurchaseQuotationResource($purchaseQuotation);

        if ($request->expectsJson()) {
            return response()->json(['data' => $resource]);
        }

        return inertia('Purchase/PurchaseQuotations/Show', [
            'purchaseQuotation' => $resource,
        ]);
    }

    public function edit(Request $request, PurchaseQuotation $purchaseQuotation)
    {
        if ($purchaseQuotation->status !== PurchaseQuotationStatus::DRAFT->value) {
            return back()->with('error', 'Only draft documents can be edited.');
        }

        $purchaseQuotation->load(['items.item', 'items.unitMeasure', 'items.size', 'items.category', 'supplier', 'currency', 'warehouse']);

        return inertia('Purchase/PurchaseQuotations/Edit', [
            'purchaseQuotation' => new PurchaseQuotationResource($purchaseQuotation),
        ]);
    }

    public function update(PurchaseQuotationUpdateRequest $request, PurchaseQuotation $purchaseQuotation, ActivityLogService $activityLogService)
    {
        if ($purchaseQuotation->status !== PurchaseQuotationStatus::DRAFT->value) {
            return back()->with('error', 'Only draft documents can be edited.');
        }

        $beforeState = [
            'number' => $purchaseQuotation->number,
            'supplier_id' => $purchaseQuotation->supplier_id,
            'date' => $purchaseQuotation->date?->toDateString(),
            'item_count' => $purchaseQuotation->items()->count(),
        ];

        DB::transaction(function () use ($request, $purchaseQuotation, $activityLogService, $beforeState) {
            $validated = $request->validated();
            $date = $validated['date'] ? $this->dateConversionService->toGregorian($validated['date']) : $purchaseQuotation->date;
            $validUntil = $validated['valid_until'] ?? null ? $this->dateConversionService->toGregorian($validated['valid_until']) : null;

            $purchaseQuotation->update([
                'date' => $date,
                'valid_until' => $validUntil,
                'supplier_id' => $validated['supplier_id'],
                'currency_id' => $validated['currency_id'] ?? null,
                'rate' => $validated['rate'] ?? null,
                'warehouse_id' => $validated['warehouse_id'] ?? null,
                'discount' => $validated['discount'] ?? 0,
                'discount_type' => $validated['discount_type'] ?? null,
                'note' => $validated['note'] ?? null,
            ]);

            $purchaseQuotation->items()->forceDelete();
            $purchaseQuotation->items()->createMany($validated['item_list']);

            $activityLogService->logUpdate(
                reference: $purchaseQuotation,
                before: $beforeState,
                after: [
                    'number' => $purchaseQuotation->number,
                    'supplier_id' => $purchaseQuotation->supplier_id,
                    'date' => $purchaseQuotation->date?->toDateString(),
                    'item_count' => count($validated['item_list']),
                ],
                module: 'purchase_quotation',
                description: "Purchase Quotation #{$purchaseQuotation->number} updated.",
                metadata: ['action' => 'purchase_quotation_update'],
            );
        });

        return redirect()->route('purchase-quotations.index')->with(
            'success',
            __('general.updated_successfully', ['resource' => __('general.resource.purchase_quotation')])
        );
    }

    public function post(PurchaseQuotation $purchaseQuotation, ActivityLogService $activityLogService)
    {
        $this->authorize('update', $purchaseQuotation);

        if ($purchaseQuotation->status !== PurchaseQuotationStatus::DRAFT->value) {
            abort(422, 'Only draft documents can be posted.');
        }

        $purchaseQuotation->update([
            'status' => PurchaseQuotationStatus::POSTED->value,
            'updated_by' => Auth::id(),
        ]);

        $activityLogService->logAction(
            eventType: 'posted',
            reference: $purchaseQuotation,
            module: 'purchase_quotation',
            description: "Purchase Quotation #{$purchaseQuotation->number} posted.",
            newValues: ['status' => $purchaseQuotation->status],
            metadata: ['action' => 'purchase_quotation_post'],
        );

        return redirect()->back()->with('success', __('general.updated_successfully', ['resource' => __('general.resource.purchase_quotation')]));
    }

    public function cancel(PurchaseQuotation $purchaseQuotation, ActivityLogService $activityLogService)
    {
        $this->authorize('update', $purchaseQuotation);

        if ($purchaseQuotation->status === PurchaseQuotationStatus::CANCELLED->value) {
            abort(422, 'Document is already cancelled.');
        }

        $purchaseQuotation->update([
            'status' => PurchaseQuotationStatus::CANCELLED->value,
            'updated_by' => Auth::id(),
        ]);

        $activityLogService->logAction(
            eventType: 'cancelled',
            reference: $purchaseQuotation,
            module: 'purchase_quotation',
            description: "Purchase Quotation #{$purchaseQuotation->number} cancelled.",
            newValues: ['status' => $purchaseQuotation->status],
            metadata: ['action' => 'purchase_quotation_cancel'],
        );

        return redirect()->back()->with('success', __('general.updated_successfully', ['resource' => __('general.resource.purchase_quotation')]));
    }

    public function print(PurchaseQuotation $purchaseQuotation, ActivityLogService $activityLogService)
    {
        $this->authorize('view', $purchaseQuotation);

        $company = auth()->user()?->company;

        $purchaseQuotation->load([
            'items.item',
            'items.unitMeasure',
            'items.size',
            'supplier',
            'currency',
            'warehouse',
        ]);

        $activityLogService->logAction(
            eventType: 'print',
            reference: $purchaseQuotation,
            module: 'purchase_quotation',
            description: "Purchase Quotation #{$purchaseQuotation->number} printed.",
            metadata: ['action' => 'purchase_quotation_print'],
        );

        return inertia('Purchase/PurchaseQuotations/Print', [
            'quotation' => new PurchaseQuotationResource($purchaseQuotation),
            'company' => $company,
        ]);
    }

    public function destroy(Request $request, PurchaseQuotation $purchaseQuotation, ActivityLogService $activityLogService)
    {
        if ($purchaseQuotation->status !== PurchaseQuotationStatus::DRAFT->value) {
            return back()->with('error', 'Only draft documents can be deleted.');
        }

        DB::transaction(function () use ($purchaseQuotation, $activityLogService) {
            $oldValues = [
                'number' => $purchaseQuotation->number,
                'supplier' => $purchaseQuotation->supplier?->name,
                'date' => $purchaseQuotation->date?->toDateString(),
                'status' => $purchaseQuotation->status,
                'item_count' => $purchaseQuotation->items()->count(),
            ];

            $purchaseQuotation->items()->delete();
            $purchaseQuotation->delete();

            $activityLogService->logDelete(
                reference: $purchaseQuotation,
                module: 'purchase_quotation',
                description: "Purchase Quotation #{$purchaseQuotation->number} deleted.",
                oldValues: $oldValues,
                metadata: ['action' => 'purchase_quotation_delete'],
            );
        });

        return redirect()->route('purchase-quotations.index')->with(
            'success',
            __('general.deleted_successfully', ['resource' => __('general.resource.purchase_quotation')])
        );
    }

    public function restore(Request $request, PurchaseQuotation $purchaseQuotation, ActivityLogService $activityLogService)
    {
        DB::transaction(function () use ($purchaseQuotation, $activityLogService) {
            $purchaseQuotation->restore();
            $purchaseQuotation->items()->withTrashed()->restore();

            $activityLogService->logAction(
                eventType: 'restored',
                reference: $purchaseQuotation,
                module: 'purchase_quotation',
                description: "Purchase Quotation #{$purchaseQuotation->number} restored.",
                newValues: ['number' => $purchaseQuotation->number, 'status' => $purchaseQuotation->status],
                metadata: ['action' => 'purchase_quotation_restore'],
            );
        });

        return redirect()->route('purchase-quotations.index')->with(
            'success',
            __('general.updated_successfully', ['resource' => __('general.resource.purchase_quotation')])
        );
    }

    public function forceDelete(Request $request, PurchaseQuotation $purchaseQuotation)
    {
        app(\App\Services\DeletedRecordService::class)->forceDelete('purchase_quotations', (string) $purchaseQuotation->id);

        return redirect()->route('purchase-quotations.index')->with(
            'success',
            __('general.permanently_deleted_successfully', ['resource' => __('general.resource.purchase_quotation')])
        );
    }
}
