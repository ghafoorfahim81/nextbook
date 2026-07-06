<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\StockAdjustmentReason;
use App\Enums\StockMovementType;
use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StockAdjustmentStoreRequest;
use App\Http\Requests\Inventory\StockAdjustmentUpdateRequest;
use App\Http\Resources\Inventory\StockAdjustmentResource;
use App\Models\Administration\Warehouse;
use App\Models\Inventory\Item;
use App\Models\Inventory\StockAdjustment;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\AttachmentService;
use App\Services\StockAdjustmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockAdjustmentController extends Controller
{
    public function __construct(
        private StockAdjustmentService $adjustmentService
    ) {
        $this->authorizeResource(StockAdjustment::class, 'stockAdjustment');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortField = $request->input('sortField', 'date');
        $sortDirection = strtolower($request->input('sortDirection', 'desc')) === 'asc' ? 'asc' : 'desc';
        $filters = (array) $request->input('filters', []);

        $adjustments = StockAdjustment::with(['warehouse', 'items', 'createdBy', 'updatedBy'])
            ->search($request->query('search'))
            ->filter($filters)
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Inventory/StockAdjustments/Index', [
            'adjustments' => StockAdjustmentResource::collection($adjustments),
            'filterOptions' => [
                'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
                'items' => Item::orderBy('name')->get(['id', 'name']),
                'reasons' => StockAdjustmentReason::options(),
                'types' => collect(StockMovementType::cases())->map(fn ($type) => [
                    'id' => $type->value,
                    'name' => $type->getLabel(),
                ])->values(),
                'statuses' => collect([
                    TransactionStatus::DRAFT,
                    TransactionStatus::POSTED,
                    TransactionStatus::REVERSED,
                ])->map(fn ($status) => [
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

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        return inertia('Inventory/StockAdjustments/Create', [
            'reasons' => StockAdjustmentReason::options(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StockAdjustmentStoreRequest $request, AttachmentService $attachmentService)
    {
        $validated = $request->validated();

        $adjustment = $this->adjustmentService->create($validated);

        if ($request->hasFile('attachments')) {
            $attachmentService->store($adjustment, $request->file('attachments'));
        }

        if ((bool) $request->create_and_new) {
            return redirect()->back()->with(
                'success',
                __('general.created_successfully', ['resource' => __('general.resource.stock_adjustment')])
            );
        }

        return redirect()->route('stock-adjustments.index')->with(
            'success',
            __('general.created_successfully', ['resource' => __('general.resource.stock_adjustment')])
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, StockAdjustment $stockAdjustment)
    {
        $stockAdjustment->load([
            'warehouse',
            'items.item',
            'items.unitMeasure',
            'branch',
            'transaction.currency',
            'transaction.originalTransaction',
            'transaction.reversalTransaction',
            'createdBy',
            'updatedBy',
            'attachments',
        ]);

        $resource = new StockAdjustmentResource($stockAdjustment);

        if ($request->expectsJson()) {
            return response()->json(['data' => $resource]);
        }

        return inertia('Inventory/StockAdjustments/Show', [
            'adjustment' => $resource,
            'reversal' => $stockAdjustment->transaction?->reversalTransaction,
            'originalDoc' => $stockAdjustment->transaction?->originalTransaction,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, StockAdjustment $stockAdjustment)
    {
        if ($stockAdjustment->status !== TransactionStatus::DRAFT->value) {
            return back()->with('error', 'Only draft documents can be edited.');
        }

        $stockAdjustment->load([
            'warehouse',
            'items.item.unitMeasure',
            'items.unitMeasure',
            'attachments',
        ]);

        return inertia('Inventory/StockAdjustments/Edit', [
            'adjustment' => new StockAdjustmentResource($stockAdjustment),
            'reasons' => StockAdjustmentReason::options(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StockAdjustmentUpdateRequest $request, StockAdjustment $stockAdjustment, AttachmentService $attachmentService)
    {
        if ($stockAdjustment->status !== TransactionStatus::DRAFT->value) {
            return back()->with('error', 'Only draft documents can be edited.');
        }

        $this->adjustmentService->update($stockAdjustment, $request->validated());

        if ($request->hasFile('attachments')) {
            $attachmentService->store($stockAdjustment, $request->file('attachments'));
        }

        return redirect()->route('stock-adjustments.index')->with(
            'success',
            __('general.updated_successfully', ['resource' => __('general.resource.stock_adjustment')])
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, StockAdjustment $stockAdjustment, ActivityLogService $activityLogService)
    {
        if ($stockAdjustment->status !== TransactionStatus::DRAFT->value) {
            return back()->with('error', 'Only draft documents can be deleted.');
        }

        DB::transaction(function () use ($stockAdjustment, $activityLogService) {
            // Release the stock this draft was holding as reserved.
            $this->adjustmentService->releaseDraftReservations($stockAdjustment);

            $oldValues = [
                'reference' => $stockAdjustment->reference,
                'date' => $stockAdjustment->date?->toDateString(),
                'type' => $stockAdjustment->type?->value,
                'reason' => $stockAdjustment->reason?->value,
                'status' => $stockAdjustment->status,
                'warehouse' => $stockAdjustment->warehouse?->name,
                'item_count' => $stockAdjustment->items()->count(),
            ];

            $stockAdjustment->items()->delete();
            $stockAdjustment->delete();

            $activityLogService->logDelete(
                reference: $stockAdjustment,
                module: 'stock_adjustment',
                description: "Stock adjustment {$stockAdjustment->reference} deleted.",
                oldValues: $oldValues,
                metadata: [
                    'action' => 'stock_adjustment_delete',
                ],
            );
        });

        return redirect()->route('stock-adjustments.index')->with(
            'success',
            __('general.deleted_successfully', ['resource' => __('general.resource.stock_adjustment')])
        );
    }

    /**
     * Post a draft adjustment.
     */
    public function post(Request $request, StockAdjustment $stockAdjustment)
    {
        $this->authorize('update', $stockAdjustment);

        try {
            $this->adjustmentService->post($stockAdjustment);
        } catch (ValidationException $e) {
            return redirect()->back()->with('error', $e->validator->errors()->first() ?: $e->getMessage());
        }

        return redirect()->back()->with(
            'success',
            __('general.updated_successfully', ['resource' => __('general.resource.stock_adjustment')])
        );
    }

    /**
     * Reverse a posted adjustment (audit-safe, no hard delete).
     */
    public function reverse(Request $request, StockAdjustment $stockAdjustment)
    {
        $this->authorize('update', $stockAdjustment);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        try {
            $this->adjustmentService->reverse($stockAdjustment, $validated['reason']);
        } catch (ValidationException $e) {
            return redirect()->back()->with('error', $e->validator->errors()->first() ?: $e->getMessage());
        }

        return redirect()->back()->with(
            'success',
            __('general.updated_successfully', ['resource' => __('general.resource.stock_adjustment')])
        );
    }

    /**
     * Restore a soft-deleted adjustment.
     */
    public function restore(Request $request, StockAdjustment $stockAdjustment, ActivityLogService $activityLogService)
    {
        $stockAdjustment->restore();
        $stockAdjustment->items()->withTrashed()->restore();

        $activityLogService->logAction(
            eventType: 'restored',
            reference: $stockAdjustment,
            module: 'stock_adjustment',
            description: "Stock adjustment {$stockAdjustment->reference} restored.",
            newValues: [
                'reference' => $stockAdjustment->reference,
                'status' => $stockAdjustment->status,
            ],
            metadata: [
                'action' => 'stock_adjustment_restore',
            ],
        );

        return redirect()->route('stock-adjustments.index')->with(
            'success',
            __('general.restored_successfully', ['resource' => __('general.resource.stock_adjustment')])
        );
    }

    public function forceDelete(Request $request, StockAdjustment $stockAdjustment)
    {
        app(\App\Services\DeletedRecordService::class)->forceDelete('stock_adjustments', (string) $stockAdjustment->id);

        return redirect()->route('stock-adjustments.index')->with(
            'success',
            __('general.permanently_deleted_successfully', ['resource' => __('general.resource.stock_adjustment')])
        );
    }

    public function export(Request $request, \App\Services\SpreadsheetExportService $exporter)
    {
        $this->authorize('viewAny', StockAdjustment::class);

        $sortField = $request->input('sortField', 'date');
        $sortDirection = strtolower($request->input('sortDirection', 'desc')) === 'asc' ? 'asc' : 'desc';
        $filters = (array) $request->input('filters', []);

        $adjustments = StockAdjustment::with(['warehouse', 'items'])
            ->search($request->query('search'))
            ->filter($filters)
            ->orderBy($sortField, $sortDirection)
            ->get();

        $rtl = in_array(app()->getLocale(), ['fa', 'ps'], true);
        $company = $request->user()?->company;
        $companyName = match (app()->getLocale()) {
            'fa'    => $company?->name_fa ?: $company?->name_en ?: $company?->abbreviation ?: config('app.name'),
            'ps'    => $company?->name_pa ?: $company?->name_en ?: $company?->abbreviation ?: config('app.name'),
            default => $company?->name_en ?: $company?->abbreviation ?: $company?->name_fa ?: $company?->name_pa ?: config('app.name'),
        };
        $t = fn (string $group, string $key, string $fallback = '') => $exporter->localeTranslation($group, $key, $fallback);
        $dateService = app(\App\Services\DateConversionService::class);

        $rows = $adjustments->map(fn ($adjustment) => [
            'reference' => $adjustment->reference,
            'date' => $adjustment->date ? $dateService->toDisplay($adjustment->date) : '-',
            'type' => $adjustment->type?->getLabel() ?? '-',
            'reason' => $adjustment->reason?->getLabel() ?? '-',
            'warehouse' => $adjustment->warehouse?->name ?? '-',
            'status' => TransactionStatus::tryFrom((string) $adjustment->status)?->getLabel() ?? (string) $adjustment->status,
            'total_cost' => $adjustment->items->sum(fn ($item) => (float) $item->quantity * (float) ($item->unit_cost ?? 0)),
        ])->all();

        $label = $t('adjustment', 'stock_adjustments', 'Stock Adjustments');

        return $exporter->download([
            'filename'           => 'stock-adjustments-' . now()->format('Ymd-His') . '.xlsx',
            'sheet_name'         => $label,
            'sheet_title'        => $label,
            'title'              => $label,
            'company_name'       => $companyName,
            'exported_on'        => now()->format('Y m d'),
            'rtl'                => $rtl,
            'include_row_number' => true,
            'row_number_label'   => $t('report', 'columns.no', 'No.'),
            'columns' => [
                ['key' => 'reference', 'label' => $t('adjustment', 'reference', 'Reference'), 'width' => 18],
                ['key' => 'date',      'label' => $t('general', 'date', 'Date'), 'width' => 14],
                ['key' => 'type',      'label' => $t('adjustment', 'type', 'Type'), 'width' => 10],
                ['key' => 'reason',    'label' => $t('adjustment', 'reason', 'Reason'), 'width' => 22],
                ['key' => 'warehouse', 'label' => $t('adjustment', 'warehouse', 'Warehouse'), 'width' => 20],
                ['key' => 'status',    'label' => $t('general', 'status', 'Status'), 'width' => 14],
                ['key' => 'total_cost','label' => $t('adjustment', 'total_cost', 'Total Cost'), 'type' => 'money', 'align' => 'right', 'width' => 16],
            ],
            'rows' => $rows,
        ]);
    }
}
