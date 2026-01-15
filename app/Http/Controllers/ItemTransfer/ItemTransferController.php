<?php

namespace App\Http\Controllers\ItemTransfer;

use App\Enums\TransferStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ItemTransfer\ItemTransferStoreRequest;
use App\Http\Requests\ItemTransfer\ItemTransferUpdateRequest;
use App\Http\Resources\ItemTransfer\ItemTransferResource;
use App\Models\ItemTransfer\ItemTransfer;
use App\Services\DateConversionService;
use App\Services\ItemTransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $perPage = $request->input('perPage', 10);
        $sortField = $request->input('sortField', 'date');
        $sortDirection = $request->input('sortDirection', 'desc');

        $transfers = ItemTransfer::with(['fromStore', 'toStore', 'items.item', 'items.unitMeasure'])
            ->search($request->query('search'))
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('ItemTransfer/ItemTransfers/Index', [
            'transfers' => ItemTransferResource::collection($transfers),
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
    public function store(ItemTransferStoreRequest $request)
    {
        $dateConversionService = app(DateConversionService::class);
        $validated = $request->validated();

        // Convert date properly
        $validated['date'] = $dateConversionService->toGregorian($validated['date']);

        // Convert item expire dates
        if (isset($validated['items'])) {
            $validated['items'] = array_map(function ($item) use ($dateConversionService) {
                if (isset($item['expire_date']) && $item['expire_date']) {
                    $item['expire_date'] = $dateConversionService->toGregorian($item['expire_date']);
                }
                return $item;
            }, $validated['items']);
        }

        $transfer = $this->transferService->createTransfer($validated);

        if ((bool) $request->create_and_new) {
            return redirect()->back()->with('success', 'Item transfer created successfully.');
        }

        return redirect()->route('item-transfers.index')->with('success', 'Item transfer created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, ItemTransfer $itemTransfer)
    {
        $itemTransfer->load(['fromStore', 'toStore', 'items.item', 'items.unitMeasure', 'branch', 'createdBy', 'updatedBy']);

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
            return redirect()->back()->withErrors(['error' => 'Cannot edit a completed or cancelled transfer.']);
        }
        $itemTransfer->load(['fromStore', 'toStore', 'items.item', 'items.unitMeasure']);

        return inertia('ItemTransfer/ItemTransfers/Edit', [
            'transfer' => new ItemTransferResource($itemTransfer),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ItemTransferUpdateRequest $request, ItemTransfer $itemTransfer)
    {
        $dateConversionService = app(DateConversionService::class);
        $validated = $request->validated();

        // Convert date properly if provided
        if (isset($validated['date'])) {
            $validated['date'] = $dateConversionService->toGregorian($validated['date']);
        }

        // Convert item expire dates if items are being updated
        if (isset($validated['items'])) {
            $validated['items'] = array_map(function ($item) use ($dateConversionService) {
                if (isset($item['expire_date']) && $item['expire_date']) {
                    $item['expire_date'] = $dateConversionService->toGregorian($item['expire_date']);
                }
                return $item;
            }, $validated['items']);
        }

        $transfer = $this->transferService->updateTransfer($itemTransfer, $validated);

        return redirect()->route('item-transfers.index')->with('success', 'Item transfer updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, ItemTransfer $itemTransfer)
    {
        // Only allow deletion of pending transfers
        if ($itemTransfer->status === TransferStatus::COMPLETED) {
            return redirect()->back()->withErrors(['error' => 'Cannot delete a completed transfer.']);
        }

        $itemTransfer->items()->delete();
        $itemTransfer->delete();

        return redirect()->route('item-transfers.index')->with('success', 'Item transfer deleted successfully.');
    }

    /**
     * Restore a soft-deleted transfer.
     */
    public function restore(Request $request, ItemTransfer $itemTransfer)
    {
        $itemTransfer->restore();
        $itemTransfer->items()->restore();

        return redirect()->route('item-transfers.index')->with('success', 'Item transfer restored successfully.');
    }

    /**
     * Complete a transfer (trigger stock updates).
     */
    public function complete(Request $request, ItemTransfer $itemTransfer)
    {
        $transfer = $this->transferService->completeTransfer($itemTransfer);

        return redirect()->back()->with('success', 'Item transfer completed successfully.');
    }

    /**
     * Cancel a transfer.
     */
    public function cancel(Request $request, ItemTransfer $itemTransfer)
    {
        $transfer = $this->transferService->cancelTransfer($itemTransfer);

        return redirect()->back()->with('success', 'Item transfer cancelled successfully.');
    }
}
