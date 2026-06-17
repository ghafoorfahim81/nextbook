<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\LandedCostAllocationMethod;
use App\Enums\LandedCostStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\LandedCostRequest;
use App\Http\Resources\Inventory\LandedCostResource;
use App\Models\Inventory\LandedCost;
use App\Models\Purchase\Purchase;
use App\Services\LandedCostService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LandedCostController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(LandedCost::class, 'landedCost');
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortField = $request->input('sortField', 'date');
        $sortDirection = $request->input('sortDirection', 'desc');
        $filters = (array) $request->input('filters', []);

        $query = LandedCost::with(['purchases.supplier', 'items.purchaseItem.purchase', 'items.item', 'createdBy', 'updatedBy'])
            ->search($request->query('search'))
            ->filter($filters)
            ->orderBy($sortField, $sortDirection);

        if ($request->expectsJson()) {
            return LandedCostResource::collection($query->paginate($perPage)->withQueryString());
        }

        return inertia('Inventories/LandedCosts/Index', [
            'landedCosts' => LandedCostResource::collection($query->paginate($perPage)->withQueryString()),
            'filterOptions' => [
                'purchases' => Purchase::query()
                    ->with('supplier')
                    ->orderByDesc('date')
                    ->limit(100)
                    ->get()
                    ->map(fn (Purchase $purchase) => [
                        'id' => $purchase->id,
                        'name' => sprintf('#%s%s', $purchase->number, $purchase->supplier?->name ? ' - ' . $purchase->supplier?->name : ''),
                    ])
                    ->values(),
                'statuses' => collect(LandedCostStatus::cases())->map(fn ($status) => [
                    'id' => $status->value,
                    'name' => $status->getLabel(),
                ])->values(),
                'allocationMethods' => collect(LandedCostAllocationMethod::cases())->map(fn ($method) => [
                    'id' => $method->value,
                    'name' => $method->getLabel(),
                ])->values(),
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
        return inertia('Inventories/LandedCosts/Create', [
            'allocationMethods' => collect(LandedCostAllocationMethod::cases())->map(fn ($method) => [
                'id' => $method->value,
                'name' => $method->getLabel(),
            ])->values(),
            'purchases' => $this->purchaseOptions(),
        ]);
    }

    public function store(LandedCostRequest $request, LandedCostService $service)
    {
        $landedCost = DB::transaction(function () use ($request, $service) {
            $landedCost = LandedCost::create([
                'date' => $request->validated('date'),
                'purchase_id' => $request->validated('purchase_id'),
                'total_cost' => $request->validated('total_cost'),
                'allocated_total' => 0,
                'allocation_method' => $request->validated('allocation_method'),
                'status' => LandedCostStatus::Draft->value,
                'notes' => $request->validated('notes'),
            ]);

            $service->syncPurchases($landedCost, $this->resolvePurchaseIds($request));
            $service->syncItems($landedCost, $request->input('items', []));

            return $landedCost->fresh(['purchases.supplier', 'items.purchaseItem.purchase', 'items.item', 'createdBy', 'updatedBy']);
        });

        if ($request->expectsJson()) {
            return LandedCostResource::make($landedCost)->response()->setStatusCode(201);
        }

        return redirect()->route('landed-costs.show', $landedCost)->with('success', __('general.created_successfully', ['resource' => __('general.resource.landed_cost')]));
    }

    public function show(Request $request, LandedCost $landedCost)
    {
        $landedCost->load(['purchases.supplier', 'items.purchaseItem.purchase', 'items.item', 'createdBy', 'updatedBy']);
        $payload = LandedCostResource::make($landedCost)->resolve($request);

        if ($request->expectsJson()) {
            return response()->json([
                'data' => LandedCostResource::make($landedCost),
            ]);
        }

        return inertia('Inventories/LandedCosts/Show', [
            'landedCost' => $payload,
        ]);
    }

    public function edit(Request $request, LandedCost $landedCost)
    {
        $landedCost->load(['purchases.supplier', 'items.purchaseItem.purchase', 'items.item', 'createdBy', 'updatedBy']);
        $payload = LandedCostResource::make($landedCost)->resolve($request);

        return inertia('Inventories/LandedCosts/Edit', [
            'landedCost' => $payload,
            'allocationMethods' => collect(LandedCostAllocationMethod::cases())->map(fn ($method) => [
                'id' => $method->value,
                'name' => $method->getLabel(),
            ])->values(),
            'purchases' => $this->purchaseOptions(),
        ]);
    }

    public function update(LandedCostRequest $request, LandedCost $landedCost, LandedCostService $service)
    {
        if (($landedCost->status instanceof LandedCostStatus ? $landedCost->status->value : (string) $landedCost->status) === LandedCostStatus::Posted->value) {
            throw ValidationException::withMessages([
                'landed_cost' => __('general.landed_cost_posted_cannot_be_edited'),
            ]);
        }

        $landedCost = DB::transaction(function () use ($request, $landedCost, $service) {
            $landedCost->update([
                'date' => $request->validated('date'),
                'purchase_id' => $request->validated('purchase_id'),
                'total_cost' => $request->validated('total_cost'),
                'allocated_total' => 0,
                'allocation_method' => $request->validated('allocation_method'),
                'status' => LandedCostStatus::Draft->value,
                'notes' => $request->validated('notes'),
            ]);

            $service->syncPurchases($landedCost, $this->resolvePurchaseIds($request));
            $service->syncItems($landedCost, $request->input('items', []));

            return $landedCost->fresh(['purchases.supplier', 'items.purchaseItem.purchase', 'items.item', 'createdBy', 'updatedBy']);
        });

        if ($request->expectsJson()) {
            return LandedCostResource::make($landedCost);
        }

        return redirect()->route('landed-costs.show', $landedCost)->with('success', __('general.updated_successfully', ['resource' => __('general.resource.landed_cost')]));
    }

    public function destroy(Request $request, LandedCost $landedCost)
    {
        if (($landedCost->status instanceof LandedCostStatus ? $landedCost->status->value : (string) $landedCost->status) === LandedCostStatus::Posted->value) {
            throw ValidationException::withMessages([
                'landed_cost' => __('general.landed_cost_posted_cannot_be_deleted'),
            ]);
        }

        DB::transaction(function () use ($landedCost) {
            $landedCost->items()->delete();
            $landedCost->delete();
        });

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        return redirect()->route('landed-costs.index')->with('success', __('general.deleted_successfully', ['resource' => __('general.resource.landed_cost')]));
    }

    public function allocate(LandedCostRequest $request, LandedCost $landedCost, LandedCostService $service)
    {
        $this->authorize('allocate', $landedCost);

        if (($landedCost->status instanceof LandedCostStatus ? $landedCost->status->value : (string) $landedCost->status) === LandedCostStatus::Posted->value) {
            throw ValidationException::withMessages([
                'landed_cost' => __('general.landed_cost_posted_cannot_be_reallocated'),
            ]);
        }

        $validated = $request->validated();
        $landedCost = $service->allocate($landedCost, $validated);

        return response()->json([
            'data' => new LandedCostResource($landedCost),
        ]);
    }

    public function post(Request $request, LandedCost $landedCost, LandedCostService $service)
    {
        $this->authorize('post', $landedCost);

        $result = $service->post($landedCost);

        return response()->json([
            'data' => new LandedCostResource($result['landed_cost']),
            'journal_entry_id' => $result['journal_entry']->id,
            'transaction_id' => $result['transaction']->id,
        ]);
    }

    private function purchaseOptions()
    {
        return Purchase::query()
            ->with('supplier')
            ->orderByDesc('date')
            ->limit(100)
            ->get()
            ->map(fn (Purchase $purchase) => [
                'id' => $purchase->id,
                'name' => sprintf('#%s%s', $purchase->number, $purchase->supplier?->name ? ' - ' . $purchase->supplier?->name : ''),
            ])
            ->values();
    }

    private function resolvePurchaseIds(Request $request): array
    {
        $purchaseIds = $request->input('purchase_ids', []);

        if (blank($purchaseIds)) {
            $purchaseIds = $request->filled('purchase_id')
                ? [$request->input('purchase_id')]
                : [];
        }

        return collect($purchaseIds)
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();
    }
}
