<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\PurchaseStoreRequest;
use App\Http\Requests\Purchase\PurchaseUpdateRequest;
use App\Http\Resources\Purchase\PurchaseResource;
use App\Models\Purchase\Purchase;
use App\Services\BillAllocationService;
use App\Models\Ledger\Ledger;
use App\Models\Administration\Currency;
use App\Models\Administration\Warehouse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\TransactionService;
use App\Models\Account\Account;
use App\Services\StockService;
use App\Models\Transaction\Transaction;
use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Enums\TransactionStatus;
use App\Models\Inventory\Item;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use App\Services\DateConversionService;
class PurchaseController extends Controller
{
    private $dateConversionService;
    public function __construct(DateConversionService $dateConversionService)
    {
        $this->authorizeResource(Purchase::class, 'purchase');
        $this->dateConversionService = $dateConversionService;
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'desc');
        $filters = (array) $request->input('filters', []);

        $purchases = Purchase::with(['supplier', 'transaction.currency', 'stocks.warehouse'])
            ->search($request->query('search'))
            ->filter($filters)
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
        return inertia('Purchase/Purchases/Index', [
            'purchases' => PurchaseResource::collection($purchases),
            'filterOptions' => [
                'suppliers' => Ledger::query()->where('type', 'supplier')->orderBy('name')->get(['id', 'name']),
                'currencies' => Currency::orderBy('code')->get(['id', 'code', 'name']),
                'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
                'types' => [
                    ['id' => 'cash', 'name' => 'Cash'],
                    ['id' => 'credit', 'name' => 'Credit'],
                ],
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
        $purchaseNumber = Purchase::max('number') ? Purchase::max('number') + 1 : 1;
        $bankAccounts = new Account();
        $bankAccounts = $bankAccounts->getAccountsByAccountTypeSlug('cash-or-bank');
        return inertia('Purchase/Purchases/Create', [
            'purchaseNumber' => $purchaseNumber,
            'bankAccounts' => $bankAccounts,
        ]);
    }

    public function store(PurchaseStoreRequest $request, TransactionService $transactionService, StockService $stockService)
    {
        $validated = $request->validated();
        $purchase = DB::transaction(function () use ($request, $transactionService, $stockService) {
            // Create purchase
            $validated = $request->validated();

            $validated['type']  = $validated['purchase_type'] ?? 'cash';
            $validated['status'] = TransactionStatus::POSTED->value;

            $date = $validated['date'] ? $this->dateConversionService->toGregorian($validated['date']) : null;
            $purchase = Purchase::create($validated);
            $validated['item_list'] = array_map(function ($item) use ($validated) {
                $item['discount'] = $item['item_discount'] ? $item['item_discount'] : 0;
                $item['warehouse_id'] = $validated['warehouse_id'];
                return $item;
            }, $validated['item_list']);
            $purchase->items()->createMany($validated['item_list']);
            $lines = [];
            foreach ($validated['item_list'] as $item) {
                $quantity = (float) $item['quantity'];
                $unitPrice = (float) $item['unit_price'];
                $itemDiscount = isset($item['discount']) ? (float) $item['discount'] : 0;
                $itemModel = \App\Models\Inventory\Item::find($item['item_id']);
                $avgCost = (float) ($itemModel->stockBalances()->avg('average_cost') ?? 0);
                // if($item['unit_measure_id'] != $itemModel->unit_measure_id) {
                //     $selectedUnit = (float) \App\Models\Administration\UnitMeasure::query()->findOrFail($item['unit_measure_id'])->unit;
                //     $itemUnit = (float) $itemModel->unitMeasure->unit;
                //     // $qty = ($quantity * $selectedUnit) / $itemUnit;
                //     $unitCost = ($selectedUnit * $avgCost) / $itemUnit;
                //     $totalCost = $unitCost * $quantity;
                // }
                // else{
                //     $unitCost = $avgCost;
                // }
                $totalCost = $unitPrice * $quantity;


                $stock = $stockService->post([
                    'item_id'         => $item['item_id'],
                    'movement_type'   => StockMovementType::IN->value,
                    'unit_measure_id' => $item['unit_measure_id'], // from item form
                    'quantity'        => $quantity,
                    'source'          => StockSourceType::PURCHASE->value,
                    'unit_cost'       => (float) $item['unit_price'],
                    'status'          => StockStatus::DRAFT->value,
                    'batch'           => $item['batch'] ?? null,
                    'date'            => $date,
                    'expire_date'     => $item['expire_date'],
                    'size_id'         => $validated['size_id'] ?? null,
                    'warehouse_id'    => $validated['warehouse_id'],
                    'branch_id'       => $purchase->branch_id,
                    'reference_type'  => Purchase::class,
                    'reference_id'    => $purchase->id,
                ]);
                $itemModel = \App\Models\Inventory\Item::find($item['item_id']);
                $accountId = $itemModel->asset_account_id ?? $itemModel->cost_account_id;
                $lines[] = [
                    'account_id' => $accountId,
                    'ledger_id'  => null,
                    'debit'      => $totalCost,
                    'credit'     => 0,
                    'remark'     => 'Purchase item: '.$itemModel->name,
                ];

                // $stockService->addStock($item, $validated['warehouse_id'], Purchase::class, $purchase->id, $validated['date']);
            }
            $glAccounts = Cache::get('gl_accounts');
            $discountTotal = $request->input('discount_total', 0);
            if($discountTotal > 0) {
                $lines[] = [
                    'account_id' => $glAccounts['discount-from-supplier'],
                    'ledger_id' => null,
                    'debit' => 0,
                    'credit' => $discountTotal,
                    'remark' => 'Discount for purchase #' . $purchase->number,
                ];
            }
            if ($validated['type'] === \App\Enums\SalePurchaseType::Cash->value) {

                $lines[] = [
                    'account_id' => $validated['bank_account_id'], // cash/bank
                    'ledger_id'  => null,
                    'debit'      => 0,
                    'credit'     => $validated['transaction_total'],
                    'remark'     => 'Payment for purchase #' . $purchase->number,
                ];
            }
            if ($validated['type'] === \App\Enums\SalePurchaseType::OnLoan->value) {
                $lines[] = [
                    'account_id' => $glAccounts['account-payable'], // cash/bank
                    'ledger_id'  => $validated['supplier_id'],
                    'debit'      => 0,
                    'credit'     => $validated['transaction_total'],
                    'remark'     => 'Payment for purchase #' . $purchase->number,
                ];
            }

            if($validated['type'] === \App\Enums\SalePurchaseType::Credit->value) {
                if($validated['payment']['amount'] > 0) {
                    $amount = (float) $validated['payment']['amount'];
                    $lines[] = [
                        'account_id' => $validated['payment']['account_id'],
                        'debit' => 0,
                        'credit' => $amount,
                    ];
                    $lines[] = [
                        'account_id' => $glAccounts['account-payable'],
                        'ledger_id' => $validated['supplier_id'],
                        'debit' => 0,
                        'credit' => $validated['transaction_total'] - $amount,
                        'remark' => 'Payment for purchase #' . $purchase->number,
                    ];
                }
                else{
                    $lines[] = [
                        'account_id' => $glAccounts['account-payable'],
                        'ledger_id' => $validated['supplier_id'],
                        'debit' => 0,
                        'credit' => $validated['transaction_total'],
                        'remark' => 'Payment for purchase #' . $purchase->number,
                    ];
                }
            }

            $transactionService->post(
                header: [
                    'currency_id'   => $validated['currency_id'],
                    'rate'          => $validated['rate'],
                    'date'          => $date,
                    'remark'        => 'Purchase #' . $purchase->number,
                    'status'        => TransactionStatus::POSTED->value,
                    'reference_type'=> Purchase::class,
                    'reference_id'  => $purchase->id,
                ],
                lines: $lines
            );

            app(BillAllocationService::class)->recalculatePurchasePaymentStatuses([$purchase->id]);


            // Create accounting transactions


            return $purchase;
        });

        if ((bool) $request->create_and_new) {
            // Stay on the same page; frontend will reset form and increment number
            return redirect()->back()->with('success', __('general.created_successfully', ['resource' => __('general.resource.purchase')]));
        }

        return redirect()->route('purchases.index')->with('success', __('general.created_successfully', ['resource' => __('general.resource.purchase')]));
    }


    public function show(Request $request, Purchase $purchase)
    {
        $purchase->load(['items.item', 'items.unitMeasure', 'supplier', 'transaction.currency', 'createdBy', 'updatedBy']);

        return response()->json([
            'data' => new PurchaseResource($purchase),
        ]);
    }

    public function edit(Request $request, Purchase $purchase)
    {
        $bankAccounts = (new Account())->getAccountsByAccountTypeSlug('cash-or-bank');
        $stockLocked = $this->purchaseHasPostedStock($purchase);

        return inertia('Purchase/Purchases/Edit', [
            'purchase' => new PurchaseResource($purchase->load([
                'items.item.unitMeasure',
                'items.unitMeasure',
                'items.warehouse',
                'supplier',
                'transaction.currency',
                'transaction.lines.account',
                'transaction.lines.ledger',
            ])),
            'bankAccounts' => $bankAccounts,
            'stockLocked' => $stockLocked,
        ]);
    }

    public function update(PurchaseUpdateRequest $request, Purchase $purchase, TransactionService $transactionService, StockService $stockService)
    {
        if ($this->purchaseHasPostedStock($purchase)) {
            throw ValidationException::withMessages([
                'purchase' => 'This purchase contains posted stock and can no longer be edited.',
            ]);
        }

        $purchase = DB::transaction(function () use ($request, $purchase, $transactionService, $stockService) {
            $validated = $request->validated();
            $validated['type'] = $validated['purchase_type'] ?? $purchase->type ?? 'cash';
            $validated['status'] = TransactionStatus::POSTED->value;

            $date = $validated['date'] ? $this->dateConversionService->toGregorian($validated['date']) : $purchase->date;
            $affectedCombos = $purchase->items()
                ->get(['item_id', 'warehouse_id', 'branch_id'])
                ->map(fn ($item) => [
                    'item_id' => $item->item_id,
                    'warehouse_id' => $item->warehouse_id,
                    'branch_id' => $item->branch_id ?? $purchase->branch_id,
                ])
                ->all();

            $validated['item_list'] = array_map(function ($item) use ($validated, $purchase, &$affectedCombos) {
                $item['discount'] = $item['item_discount'] ?? 0;
                $item['warehouse_id'] = $validated['warehouse_id'];

                $affectedCombos[] = [
                    'item_id' => $item['item_id'],
                    'warehouse_id' => $validated['warehouse_id'],
                    'branch_id' => $purchase->branch_id,
                ];

                return $item;
            }, $validated['item_list']);

            $purchase->update($validated);
            $purchase->items()->forceDelete();

            StockMovement::query()
                ->where('reference_type', Purchase::class)
                ->where('reference_id', $purchase->id)
                ->forceDelete();

            $this->rebuildStockStateForCombos($affectedCombos);

            $purchase->items()->createMany($validated['item_list']);

            $transaction = Transaction::query()
                ->where('reference_type', Purchase::class)
                ->where('reference_id', $purchase->id)
                ->first();

            if ($transaction) {
                $transaction->lines()->forceDelete();
                $transaction->forceDelete();
            }

            $lines = [];
            $glAccounts = Cache::get('gl_accounts');
            $discountTotal = (float) $request->input('discount_total', 0);

            foreach ($validated['item_list'] as $item) {
                $quantity = (float) $item['quantity'];
                $unitPrice = (float) $item['unit_price'];
                $itemModel = Item::findOrFail($item['item_id']);
                $accountId = $itemModel->asset_account_id ?? $itemModel->cost_account_id;

                $stockService->post([
                    'item_id'         => $item['item_id'],
                    'movement_type'   => StockMovementType::IN->value,
                    'unit_measure_id' => $item['unit_measure_id'],
                    'quantity'        => $quantity,
                    'source'          => StockSourceType::PURCHASE->value,
                    'unit_cost'       => $unitPrice,
                    'status'          => StockStatus::DRAFT->value,
                    'batch'           => $item['batch'] ?? null,
                    'date'            => $date,
                    'expire_date'     => $item['expire_date'] ?? null,
                    'size_id'         => $validated['size_id'] ?? null,
                    'warehouse_id'    => $validated['warehouse_id'],
                    'branch_id'       => $purchase->branch_id,
                    'reference_type'  => Purchase::class,
                    'reference_id'    => $purchase->id,
                ]);

                $lines[] = [
                    'account_id' => $accountId,
                    'ledger_id'  => null,
                    'debit'      => $quantity * $unitPrice,
                    'credit'     => 0,
                    'remark'     => 'Purchase item: ' . $itemModel->name,
                ];
            }

            if ($discountTotal > 0) {
                $lines[] = [
                    'account_id' => $glAccounts['discount-from-supplier'],
                    'ledger_id'  => null,
                    'debit'      => 0,
                    'credit'     => $discountTotal,
                    'remark'     => 'Discount for purchase #' . $purchase->number,
                ];
            }

            if ($validated['type'] === \App\Enums\SalePurchaseType::Cash->value) {
                $lines[] = [
                    'account_id' => $validated['bank_account_id'],
                    'ledger_id'  => null,
                    'debit'      => 0,
                    'credit'     => $validated['transaction_total'],
                    'remark'     => 'Payment for purchase #' . $purchase->number,
                ];
            }

            if ($validated['type'] === \App\Enums\SalePurchaseType::OnLoan->value) {
                $lines[] = [
                    'account_id' => $glAccounts['account-payable'],
                    'ledger_id'  => $validated['supplier_id'],
                    'debit'      => 0,
                    'credit'     => $validated['transaction_total'],
                    'remark'     => 'Payment for purchase #' . $purchase->number,
                ];
            }

            if ($validated['type'] === \App\Enums\SalePurchaseType::Credit->value) {
                $paidAmount = (float) ($validated['payment']['amount'] ?? 0);

                if ($paidAmount > 0) {
                    $lines[] = [
                        'account_id' => $validated['payment']['account_id'],
                        'ledger_id'  => null,
                        'debit'      => 0,
                        'credit'     => $paidAmount,
                        'remark'     => 'Partial payment for purchase #' . $purchase->number,
                    ];

                    $remaining = $validated['transaction_total'] - $paidAmount;

                    if ($remaining > 0) {
                        $lines[] = [
                            'account_id' => $glAccounts['account-payable'],
                            'ledger_id'  => $validated['supplier_id'],
                            'debit'      => 0,
                            'credit'     => $remaining,
                            'remark'     => 'Remaining payable for purchase #' . $purchase->number,
                        ];
                    }
                } else {
                    $lines[] = [
                        'account_id' => $glAccounts['account-payable'],
                        'ledger_id'  => $validated['supplier_id'],
                        'debit'      => 0,
                        'credit'     => $validated['transaction_total'],
                        'remark'     => 'Payable for purchase #' . $purchase->number,
                    ];
                }
            }

            $transactionService->post(
                header: [
                    'currency_id'    => $validated['currency_id'],
                    'rate'           => $validated['rate'],
                    'date'           => $date,
                    'remark'         => 'Purchase #' . $purchase->number,
                    'status'         => TransactionStatus::POSTED->value,
                    'reference_type' => Purchase::class,
                    'reference_id'   => $purchase->id,
                ],
                lines: $lines
            );

            app(BillAllocationService::class)->recalculatePurchasePaymentStatuses([$purchase->id]);

            return $purchase;
        });

        return redirect()->route('purchases.index')->with('success', __('general.updated_successfully', ['resource' => __('general.resource.purchase')]));
    }

    private function rebuildStockStateForCombos(array $combos): void
    {
        $uniqueCombos = collect($combos)
            ->filter(fn ($combo) => !empty($combo['item_id']) && !empty($combo['warehouse_id']) && !empty($combo['branch_id']))
            ->unique(fn ($combo) => implode('|', [
                $combo['branch_id'],
                $combo['warehouse_id'],
                $combo['item_id'],
            ]))
            ->values();

        foreach ($uniqueCombos as $combo) {
            $this->rebuildStockStateForItemWarehouse(
                branchId: $combo['branch_id'],
                warehouseId: $combo['warehouse_id'],
                itemId: $combo['item_id'],
            );
        }
    }

    private function rebuildStockStateForItemWarehouse(string $branchId, string $warehouseId, string $itemId): void
    {
        $item = Item::find($itemId);

        if (!$item) {
            return;
        }

        $movements = StockMovement::query()
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->where('item_id', $itemId)
            ->orderBy('date')
            ->orderBy('id')
            ->get();
        /** @var \Illuminate\Database\Eloquent\Collection<int, StockMovement> $movements */

        StockBalance::query()
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->where('item_id', $itemId)
            ->forceDelete();

        if ($movements->isEmpty()) {
            return;
        }

        $balanceBuckets = [];

        foreach ($movements as $movement) {
            $expireDate = $movement->expire_date?->toDateString();
            $bucketKey = implode('|', [
                $movement->batch ?? '',
                $expireDate ?? '',
            ]);

            if (!isset($balanceBuckets[$bucketKey])) {
                $balanceBuckets[$bucketKey] = [
                    'branch_id' => $branchId,
                    'item_id' => $itemId,
                    'warehouse_id' => $warehouseId,
                    'batch' => $movement->batch,
                    'expire_date' => $expireDate,
                    'status' => $this->stockStatusValue($movement->status),
                    'quantity' => 0,
                    'in_quantity' => 0,
                    'in_value' => 0,
                ];
            }

            if ($this->stockStatusValue($movement->status) === StockStatus::POSTED->value) {
                $balanceBuckets[$bucketKey]['status'] = StockStatus::POSTED->value;
            }

            if ($movement->movement_type === StockMovementType::IN) {
                $balanceBuckets[$bucketKey]['quantity'] += (float) $movement->quantity;
                $balanceBuckets[$bucketKey]['in_quantity'] += (float) $movement->quantity;
                $balanceBuckets[$bucketKey]['in_value'] += (float) $movement->quantity * (float) $movement->unit_cost;
            } else {
                $balanceBuckets[$bucketKey]['quantity'] -= (float) $movement->quantity;
            }
        }

        foreach ($balanceBuckets as $bucket) {
            if ($bucket['quantity'] <= 0) {
                continue;
            }

            StockBalance::create([
                'branch_id' => $bucket['branch_id'],
                'item_id' => $bucket['item_id'],
                'warehouse_id' => $bucket['warehouse_id'],
                'batch' => $bucket['batch'],
                'expire_date' => $bucket['expire_date'],
                'status' => $bucket['status'],
                'quantity' => $bucket['quantity'],
                'average_cost' => $bucket['in_quantity'] > 0
                    ? $bucket['in_value'] / $bucket['in_quantity']
                    : 0,
            ]);
        }

        $inMovements = $movements
            ->filter(fn (StockMovement $movement) => $movement->movement_type === StockMovementType::IN)
            ->values();

        foreach ($inMovements as $movement) {
            $movement->qty_remaining = (float) $movement->quantity;
            $movement->save();
        }
    }

    private function purchaseHasPostedStock(Purchase $purchase): bool
    {
        return StockMovement::query()
            ->where('reference_type', Purchase::class)
            ->where('reference_id', $purchase->id)
            ->where('status', StockStatus::POSTED->value)
            ->exists();
    }

    private function stockStatusValue(mixed $status): string
    {
        return $status instanceof StockStatus ? $status->value : (string) $status;
    }

    public function destroy(Request $request, Purchase $purchase)
    {
        DB::transaction(function () use ($purchase) {
            $affectedCombos = $purchase->items()
                ->get(['item_id', 'warehouse_id', 'branch_id'])
                ->map(fn ($item) => [
                    'item_id' => $item->item_id,
                    'warehouse_id' => $item->warehouse_id,
                    'branch_id' => $item->branch_id ?? $purchase->branch_id,
                ])
                ->all();

            $stockMovementCombos = StockMovement::query()
                ->where('reference_type', Purchase::class)
                ->where('reference_id', $purchase->id)
                ->get(['item_id', 'warehouse_id', 'branch_id'])
                ->map(fn ($movement) => [
                    'item_id' => $movement->item_id,
                    'warehouse_id' => $movement->warehouse_id,
                    'branch_id' => $movement->branch_id,
                ])
                ->all();

            $transaction = Transaction::query()
                ->where('reference_type', Purchase::class)
                ->where('reference_id', $purchase->id)
                ->first();

            if ($transaction) {
                $transaction->lines()->delete();
                $transaction->delete();
            }

            StockMovement::query()
                ->where('reference_type', Purchase::class)
                ->where('reference_id', $purchase->id)
                ->delete();

            $purchase->items()->delete();
            $purchase->delete();

            $this->rebuildStockStateForCombos([
                ...$affectedCombos,
                ...$stockMovementCombos,
            ]);
        });

        return redirect()->route('purchases.index')->with('success', __('general.purchase_deleted_successfully'));
    }

    public function restore(Request $request, Purchase $purchase)
    {
        DB::transaction(function () use ($purchase) {
            $purchase->restore();
            $purchase->items()->withTrashed()->restore();

            StockMovement::withTrashed()
                ->where('reference_type', Purchase::class)
                ->where('reference_id', $purchase->id)
                ->restore();

            $transaction = Transaction::withTrashed()
                ->where('reference_type', Purchase::class)
                ->where('reference_id', $purchase->id)
                ->first();

            if ($transaction) {
                $transaction->restore();
                $transaction->lines()->withTrashed()->restore();
            }

            $affectedCombos = $purchase->items()
                ->withTrashed()
                ->get(['item_id', 'warehouse_id', 'branch_id'])
                ->map(fn ($item) => [
                    'item_id' => $item->item_id,
                    'warehouse_id' => $item->warehouse_id,
                    'branch_id' => $item->branch_id ?? $purchase->branch_id,
                ])
                ->all();

            $this->rebuildStockStateForCombos($affectedCombos);
        });

        return redirect()->route('purchases.index')->with('success', __('general.purchase_restored_successfully'));
    }

    public function updatePurchaseStatus(Request $request, Purchase $purchase)
    {
        $purchase->update(['status' => $request->status]);
        return back()->with('success', __('general.purchase_status_updated_successfully'));
    }

    public function openBills(Request $request, BillAllocationService $billAllocationService)
    {
        $ledgerId = (string) $request->query('ledger_id', '');
        $excludePaymentId = (string) $request->query('exclude_payment_id', '');

        return response()->json([
            'data' => $ledgerId ? $billAllocationService->openPurchasesForSupplier($ledgerId, $excludePaymentId ?: null) : [],
        ]);
    }
}
