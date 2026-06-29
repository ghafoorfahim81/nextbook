<?php

namespace App\Http\Controllers\ItemTransfer;

use App\Enums\TransferStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ItemTransfer\ItemTransferStoreRequest;
use App\Http\Requests\ItemTransfer\ItemTransferUpdateRequest;
use App\Http\Resources\ItemTransfer\ItemTransferResource;
use App\Models\ItemTransfer\ItemTransfer;
use App\Models\Administration\Warehouse;
use App\Models\Inventory\Item;
use App\Models\User;
use App\Services\DateConversionService;
use App\Services\ItemTransferService;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\TransactionService;
use App\Services\AttachmentService;
use Illuminate\Support\Facades\Cache;
class ItemTransferController extends Controller
{
    public function __construct(
        private ItemTransferService $transferService
    ) {
        $this->authorizeResource(ItemTransfer::class, 'itemTransfer');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortField = $request->input('sortField', 'date');
        $sortDirection = $request->input('sortDirection', 'desc');
        $filters = (array) $request->input('filters', []);

        $transfers = ItemTransfer::with(['fromWarehouse', 'toWarehouse', 'items.item', 'items.unitMeasure', 'createdBy', 'updatedBy'])
            ->search($request->query('search'))
            ->filter($filters)
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('ItemTransfer/ItemTransfers/Index', [
            'transfers' => ItemTransferResource::collection($transfers),
            'filterOptions' => [
                'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
                'items' => Item::orderBy('name')->get(['id', 'name']),
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
        return inertia('ItemTransfer/ItemTransfers/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ItemTransferStoreRequest $request, AttachmentService $attachmentService)
    {
        $validated = $request->validated();

        // Convert item expire dates
        if (isset($validated['items'])) {
            $validated['items'] = array_map(function ($item) {
                return $item;
            }, $validated['items']);
        }
        $transfer = $this->transferService->createTransfer($validated);
        if ($request->hasFile('attachments')) {
            $attachmentService->store($transfer, $request->file('attachments'));
        }
        if ((bool) user_preference('transaction.item_transfer_post_immediately', true)) {
            $transfer = $this->transferService->completeTransfer($transfer);
        }
        if ((bool) $request->create_and_new) {
            return redirect()->back()->with('success', __('general.created_successfully', ['resource' => __('general.resource.item_transfer')]));
        }

        return redirect()->route('item-transfers.index')->with('success', __('general.created_successfully', ['resource' => __('general.resource.item_transfer')]));
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, ItemTransfer $itemTransfer)
    {
        $itemTransfer->load(['fromWarehouse', 'toWarehouse', 'items.item', 'items.unitMeasure', 'branch', 'createdBy', 'updatedBy', 'attachments']);

        return response()->json([
            'data' => new ItemTransferResource($itemTransfer),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, ItemTransfer $itemTransfer)
    {
        if ($itemTransfer->status === TransferStatus::COMPLETED || $itemTransfer->status === TransferStatus::CANCELLED) {
            return redirect()->back()->withErrors(['error' => __('general.cannot_edit_completed_or_cancelled_transfer')]);
        }
        $itemTransfer->load(['fromWarehouse', 'toWarehouse', 'items.item', 'items.unitMeasure', 'attachments']);

        return inertia('ItemTransfer/ItemTransfers/Edit', [
            'transfer' => new ItemTransferResource($itemTransfer),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ItemTransferUpdateRequest $request, ItemTransfer $itemTransfer, AttachmentService $attachmentService)
    {
        if ($itemTransfer->status !== TransferStatus::PENDING) {
            abort(403, 'Only draft documents can be edited.');
        }

        $validated = $request->validated();

        if ($request->hasFile('attachments')) {
            $attachmentService->store($itemTransfer, $request->file('attachments'));
        }

        // Convert item expire dates if items are being updated
        if (isset($validated['items'])) {
            $validated['items'] = array_map(function ($item) {
                return $item;
            }, $validated['items']);
        }

        $transfer = $this->transferService->updateTransfer($itemTransfer, $validated);

        return redirect()->route('item-transfers.index')->with('success', __('general.updated_successfully', ['resource' => __('general.resource.item_transfer')]));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, ItemTransfer $itemTransfer, ActivityLogService $activityLogService)
    {
        // Only allow deletion of pending transfers
        if ($itemTransfer->status === TransferStatus::COMPLETED) {
            return redirect()->back()->withErrors(['error' => __('general.cannot_delete_completed_transfer')]);
        }

        $oldValues = [
            'date' => $itemTransfer->date?->toDateString(),
            'status' => $itemTransfer->status?->value ?? $itemTransfer->status,
            'from_warehouse_id' => $itemTransfer->from_warehouse_id,
            'to_warehouse_id' => $itemTransfer->to_warehouse_id,
            'transfer_cost' => (float) ($itemTransfer->transfer_cost ?? 0),
            'item_count' => $itemTransfer->items()->count(),
        ];

        $itemTransfer->items()->delete();
        $itemTransfer->delete();

        $activityLogService->logDelete(
            reference: $itemTransfer,
            module: 'item_transfer',
            description: "Item transfer #{$itemTransfer->id} deleted.",
            oldValues: $oldValues,
            metadata: [
                'action' => 'item_transfer_delete',
            ],
        );

        return redirect()->route('item-transfers.index')->with('success', __('general.deleted_successfully', ['resource' => __('general.resource.item_transfer')]));
    }

    /**
     * Restore a soft-deleted transfer.
     */
    public function restore(Request $request, ItemTransfer $itemTransfer, ActivityLogService $activityLogService)
    {
        $itemTransfer->restore();
        $itemTransfer->items()->restore();

        $activityLogService->logAction(
            eventType: 'restored',
            reference: $itemTransfer,
            module: 'item_transfer',
            description: "Item transfer #{$itemTransfer->id} restored.",
            newValues: [
                'status' => $itemTransfer->status?->value ?? $itemTransfer->status,
            ],
            metadata: [
                'action' => 'item_transfer_restore',
            ],
        );

        return redirect()->route('item-transfers.index')->with('success', __('general.restored_successfully', ['resource' => __('general.resource.item_transfer')]));
    }

    public function forceDelete(Request $request, ItemTransfer $itemTransfer)
    {
        app(\App\Services\DeletedRecordService::class)->forceDelete('item_transfers', (string) $itemTransfer->id);

        return redirect()->route('item-transfers.index')->with('success', __('general.permanently_deleted_successfully', ['resource' => __('general.resource.item_transfer')]));
    }

    /**
     * Complete a transfer (trigger stock updates).
     */
    public function complete(Request $request, ItemTransfer $itemTransfer)
    {
        $transfer = $this->transferService->completeTransfer($itemTransfer);

        return redirect()->back()->with('success', __('general.completed_successfully', ['resource' => __('general.resource.item_transfer')]));
    }

    public function post(Request $request, ItemTransfer $itemTransfer)
    {
        return $this->complete($request, $itemTransfer);
    }

    public function reverse(Request $request, ItemTransfer $itemTransfer)
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        return $this->cancel($request, $itemTransfer);
    }

    /**
     * Cancel a transfer.
     */
    public function cancel(Request $request, ItemTransfer $itemTransfer)
    {
        $transfer = $this->transferService->cancelTransfer($itemTransfer);

        return redirect()->back()->with('success', __('general.cancelled_successfully', ['resource' => __('general.resource.item_transfer')]));
    }

    public function export(Request $request, \App\Services\SpreadsheetExportService $exporter)
    {
        $this->authorize('viewAny', ItemTransfer::class);

        $sortField = $request->input('sortField', 'date');
        $sortDirection = $request->input('sortDirection', 'desc');
        $filters = (array) $request->input('filters', []);

        $transfers = ItemTransfer::with(['fromWarehouse', 'toWarehouse'])
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

        $rows = $transfers->map(fn ($tr) => [
            'date'          => $tr->date ? $dateService->toDisplay($tr->date) : '-',
            'from_warehouse'=> $tr->fromWarehouse?->name ?? '-',
            'to_warehouse'  => $tr->toWarehouse?->name ?? '-',
            'status'        => $tr->status instanceof \App\Enums\TransferStatus ? $tr->status->getLabel() : (string) $tr->status,
            'transfer_cost' => (float) ($tr->transfer_cost ?? 0),
        ])->all();

        $label = $t('item_transfer', 'item_transfers', 'Item Transfers');

        return $exporter->download([
            'filename'           => 'item-transfers-' . now()->format('Ymd-His') . '.xlsx',
            'sheet_name'         => $label,
            'sheet_title'        => $label,
            'title'              => $label,
            'company_name'       => $companyName,
            'exported_on'        => now()->format('Y m d'),
            'rtl'                => $rtl,
            'include_row_number' => true,
            'row_number_label'   => $t('report', 'columns.no', 'No.'),
            'columns' => [
                ['key' => 'date',           'label' => $t('general', 'date', 'Date'), 'width' => 14],
                ['key' => 'from_warehouse', 'label' => $t('item_transfer', 'from_warehouse', 'From Warehouse'), 'width' => 20],
                ['key' => 'to_warehouse',   'label' => $t('item_transfer', 'to_warehouse', 'To Warehouse'), 'width' => 20],
                ['key' => 'status',         'label' => $t('general', 'status', 'Status'), 'width' => 14],
                ['key' => 'transfer_cost',  'label' => $t('item_transfer', 'transfer_cost', 'Transfer Cost'), 'type' => 'money', 'align' => 'right', 'width' => 14],
            ],
            'rows' => $rows,
        ]);
    }
}
