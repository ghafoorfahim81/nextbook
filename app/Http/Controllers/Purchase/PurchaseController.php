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
use App\Services\AttachmentService;
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
use Carbon\Carbon;
use App\Services\DateConversionService;
use App\Services\ActivityLogService;
use App\Services\SpreadsheetExportService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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
                'paymentStatuses' => collect(\App\Enums\PaymentStatus::cases())->map(fn ($status) => [
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
        $purchaseNumber = Purchase::max('number') ? Purchase::max('number') + 1 : 1;
        $bankAccounts = new Account();
        $bankAccounts = $bankAccounts->getAccountsByAccountTypeSlug('cash-or-bank');
        return inertia('Purchase/Purchases/Create', [
            'purchaseNumber' => $purchaseNumber,
            'bankAccounts' => $bankAccounts,
        ]);
    }

    public function store(
        PurchaseStoreRequest $request,
        TransactionService $transactionService,
        StockService $stockService,
        ActivityLogService $activityLogService,
        AttachmentService $attachmentService
    )
    {
        $validated = $request->validated();
        $purchase = DB::transaction(function () use ($request, $transactionService, $stockService, $activityLogService, $attachmentService) {
            // Create purchase
            $validated = $request->validated();

            $validated['type']  = $validated['purchase_type'] ?? 'cash';
            $postImmediately = (bool) user_preference('transaction.purchase_post_immediately', true);
            $documentStatus = $postImmediately ? TransactionStatus::POSTED->value : TransactionStatus::DRAFT->value;
            $validated['status'] = $documentStatus;

            $validated['date'] = $validated['date'] ? $this->dateConversionService->toGregorian($validated['date']) : null;
            $purchase = Purchase::create($validated);

            if ($request->hasFile('attachments')) {
                $attachmentService->store($purchase, $request->file('attachments'));
            }

            $validated['item_list'] = array_map(function ($item) use ($validated) {
                $item['discount'] = $item['item_discount'] ? $item['item_discount'] : 0;
                $item['warehouse_id'] = $validated['warehouse_id'];
                return $item;
            }, $validated['item_list']);
            $purchase->items()->createMany($validated['item_list']);
            $lines = [];
            $stockPayloads = [];
            foreach ($validated['item_list'] as $item) {
                $quantity = (float) $item['quantity'];
                $unitPrice = (float) $item['unit_price'];
                $itemDiscount = isset($item['discount']) ? (float) $item['discount'] : 0;
                $itemModel = \App\Models\Inventory\Item::find($item['item_id']);

                // if($item['unit_measure_id'] != $itemModel->unit_measure_id) {
                //     $selectedUnit = (float) \App\Models\Administration\UnitMeasure::query()->findOrFail($item['unit_measure_id'])->unit;
                //     $itemUnit = (float) $itemModel->unitMeasure->unit;
                //     // $qty = ($quantity * $selectedUnit) / $itemUnit;
                //     $unitCost = ($selectedUnit * $unitPrice) / $itemUnit;
                //     $totalCost = $unitCost * $quantity;
                // }
                // else{
                //     $unitCost = $unitPrice;
                // }
                $totalCost = $unitPrice * $quantity;
                $stockPayloads[] = [
                    'item_id'         => $item['item_id'],
                    'movement_type'   => StockMovementType::IN->value,
                    'unit_measure_id' => $item['unit_measure_id'], // from item form
                    'quantity'        => $quantity,
                    'source'          => StockSourceType::PURCHASE->value,
                    'unit_cost'       => (float) $item['unit_price'],
                    'status'          => StockStatus::POSTED->value,
                    'batch'           => $item['batch'] ?? null,
                    'color'           => $item['color'] ?? null,
                    'date'            => $validated['date'],
                    'expire_date'     => $item['expire_date'],
                    'size_id'         => $item['size_id'] ?? null,
                    'warehouse_id'    => $validated['warehouse_id'],
                    'branch_id'       => $purchase->branch_id,
                    'reference_type'  => Purchase::class,
                    'reference_id'    => $purchase->id,
                ];

                if ($postImmediately) {
                    $stockService->post($stockPayloads[array_key_last($stockPayloads)]);
                } else {
                    // Draft: record the incoming stock as reserved_in for visibility.
                    $stockService->reserve($stockPayloads[array_key_last($stockPayloads)]);
                }
                $itemModel = \App\Models\Inventory\Item::find($item['item_id']);
                $accountId = $itemModel->asset_account_id;
                $lines[] = [
                    'account_id' => $accountId,
                    'ledger_id'  => null,
                    'debit'      => $totalCost,
                    'credit'     => 0,
                    'remark'     => 'Purchase for '. ' '. $itemModel->name.' #'.$purchase->number,
                    'remark_fa' => 'خرید بابت '. ' '. $itemModel->name.' #'.$purchase->number,
                    'remark_ps' => 'د'. ' '. $itemModel->name.' #'.$purchase->number,
                ];

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
                    'remark_fa' => 'تخفیف برای خرید #' . $purchase->number,
                    'remark_ps' => 'د'. ' '. $purchase->number.' '.'تخفیف اخیستل',
                ];
            }
            if ($validated['type'] === \App\Enums\SalePurchaseType::Cash->value) {

                $lines[] = [
                    'account_id' => $validated['bank_account_id'], // cash/bank
                    'ledger_id'  => null,
                    'debit'      => 0,
                    'credit'     => $validated['transaction_total'],
                    'remark'     => 'Payment for purchase #' . $purchase->number,
                    'remark_fa' => 'پرداخت برای خرید #' . $purchase->number,
                    'remark_ps' => 'د'. '#'. $purchase->number.' '.'پرداخت اخیستل',
                ];
            }
            if ($validated['type'] === \App\Enums\SalePurchaseType::OnLoan->value) {
                $lines[] = [
                    'account_id' => $glAccounts['account-payable'], // cash/bank
                    'ledger_id'  => $validated['supplier_id'],
                    'debit'      => 0,
                    'credit'     => $validated['transaction_total'],
                    'remark'     => 'Purchase on loan for #' . $purchase->number,
                    'remark_fa' => ' بابت خرید قرض #' . $purchase->number,
                    'remark_ps' => 'د'. '#'. $purchase->number.' '.'د پور اخیستلو په اړه',
                ];
            }

            if($validated['type'] === \App\Enums\SalePurchaseType::Credit->value) {
                if($validated['payment']['amount'] > 0) {
                    $amount = (float) $validated['payment']['amount'];
                    $lines[] = [
                        'account_id' => $validated['payment']['account_id'],
                        'debit' => 0,
                        'credit' => $amount,
                        'remark' => 'Partial payment for purchase #' . $purchase->number,
                        'remark_fa' => 'پرداخت جزئی برای خرید #' . $purchase->number,
                        'remark_ps' => 'د'. '#'. $purchase->number.' '.'جزوی تادیه',
                    ];
                    $lines[] = [
                        'account_id' => $glAccounts['account-payable'],
                        'ledger_id' => $validated['supplier_id'],
                        'debit' => 0,
                        'credit' => $validated['transaction_total'] - $amount,
                        'remark'     => 'Purchase on loan for #' . $purchase->number,
                        'remark_fa' => ' بابت خرید قرض #' . $purchase->number,
                        'remark_ps' => 'د'. '#'. $purchase->number.' '.'د پور اخیستلو په اړه',
                    ];
                }
                else{
                    $lines[] = [
                        'account_id' => $glAccounts['account-payable'],
                        'ledger_id' => $validated['supplier_id'],
                        'debit' => 0,
                        'credit' => $validated['transaction_total'],
                        'remark' => 'Purchase for #' . $purchase->number,
                        'remark_fa' => 'خرید #' . $purchase->number,
                        'remark_ps' => 'د'. '#'. $purchase->number.' '.'لخوا اخیستل',
                    ];
                }
            }

            $transaction = $transactionService->post(
                header: [
                    'currency_id'   => $validated['currency_id'],
                    'rate'          => $validated['rate'],
                    'voucher_number' => 'Purchase #' . $purchase->number,
                    'date'          => $validated['date'],
                    'remark'        => 'Purchase #' . $purchase->number,
                    'status'        => $documentStatus,
                    'reference_type'=> Purchase::class,
                    'reference_id'  => $purchase->id,
                    'posting_payload' => [
                        'stock_movements' => $stockPayloads,
                    ],
                ],
                lines: $lines
            );

            app(BillAllocationService::class)->recalculatePurchasePaymentStatuses([$purchase->id]);
            $activityLogService->logCreate(
                reference: $purchase,
                module: 'purchase',
                description: "Purchase #{$purchase->number} created.",
                newValues: [
                    'number' => $purchase->number,
                    'supplier_id' => $purchase->supplier_id,
                    'date' => $purchase->date?->toDateString(),
                    'status' => $purchase->status,
                    'branch_id' => $purchase->branch_id,
                    'warehouse_id' => $validated['warehouse_id'],
                    'currency_id' => $validated['currency_id'],
                    'item_count' => count($validated['item_list']),
                    'transaction_total' => (float) $validated['transaction_total'],
                ],
                metadata: [
                    'action' => 'purchase_store',
                    'purchase_type' => $validated['type'],
                    'transaction_id' => $transaction->id,
                ],
            );


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
        $purchase->load([
            'items.item',
            'items.unitMeasure',
            'supplier',
            'transaction.currency',
            'transaction.originalTransaction',
            'transaction.reversalTransaction',
            'createdBy',
            'updatedBy',
            'attachments',
            'returns.items',
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'data' => new PurchaseResource($purchase),
            ]);
        }

        return inertia('Purchase/Purchases/Show', [
            'purchase' => new PurchaseResource($purchase),
            'reversal' => $purchase->transaction?->reversalTransaction,
            'originalDoc' => $purchase->transaction?->originalTransaction,
        ]);
    }

    public function edit(Request $request, Purchase $purchase)
    {
        if ($purchase->status !== TransactionStatus::DRAFT->value) {
            return back()->with('error', 'Only draft documents can be edited.');
        }

        $bankAccounts = (new Account())->getAccountsByAccountTypeSlug('cash-or-bank');

        return inertia('Purchase/Purchases/Edit', [
            'purchase' => new PurchaseResource($purchase->load([
                'items.item.unitMeasure',
                'items.unitMeasure',
                'items.warehouse',
                'supplier',
                'transaction.currency',
                'transaction.lines.account',
                'transaction.lines.ledger',
                'attachments',
            ])),
            'bankAccounts' => $bankAccounts,
        ]);
    }

    public function update(
        PurchaseUpdateRequest $request,
        Purchase $purchase,
        TransactionService $transactionService,
        StockService $stockService,
        ActivityLogService $activityLogService,
        AttachmentService $attachmentService
    )
    {
        if ($purchase->status !== TransactionStatus::DRAFT->value) {
            return back()->with('error', 'Only draft documents can be edited.');
        }

        $beforeState = [
            'number' => $purchase->number,
            'supplier_id' => $purchase->supplier_id,
            'date' => $purchase->date?->toDateString(),
            'status' => $purchase->status,
            'branch_id' => $purchase->branch_id,
            'warehouse_id' => $purchase->warehouse_id,
            'currency_id' => $purchase->transaction?->currency_id,
            'rate' => $purchase->transaction?->rate,
            'item_count' => $purchase->items()->count(),
            'transaction_total' => (float) ($purchase->transaction_total ?? 0),
        ];

        $purchase = DB::transaction(function () use ($request, $purchase, $transactionService, $stockService, $activityLogService, $attachmentService, $beforeState) {
            $validated = $request->validated();

            if ($request->hasFile('attachments')) {
                $attachmentService->store($purchase, $request->file('attachments'));
            }

            $validated['type'] = $validated['purchase_type'] ?? $purchase->type ?? 'cash';
            $validated['status'] = TransactionStatus::DRAFT->value;

            $validated['date'] = $validated['date'] ? $this->dateConversionService->toGregorian($validated['date']) : $purchase->date;

            // Posted/reversed purchases are immutable, so update no longer needs to
            // rebuild posted stock state or recalculate avg cost.
            // $affectedCombos = $purchase->items()
            //     ->get(['item_id', 'warehouse_id', 'branch_id'])
            //     ->map(fn ($item) => [
            //         'item_id' => $item->item_id,
            //         'warehouse_id' => $item->warehouse_id,
            //         'branch_id' => $item->branch_id ?? $purchase->branch_id,
            //     ])
            //     ->all();

            $validated['item_list'] = array_map(function ($item) use ($validated) {
                $item['discount'] = $item['item_discount'] ?? 0;
                $item['warehouse_id'] = $validated['warehouse_id'];

                return $item;
            }, $validated['item_list']);

            $purchase->update($validated);
            $purchase->items()->forceDelete();

            $purchase->items()->createMany($validated['item_list']);

            $transaction = Transaction::query()
                ->where('reference_type', Purchase::class)
                ->where('reference_id', $purchase->id)
                ->first();

            if ($transaction) {
                // Release the reserved_in held by the previous draft payload before rebuilding.
                foreach ((array) data_get($transaction->posting_payload, 'stock_movements', []) as $oldPayload) {
                    $stockService->release($oldPayload);
                }
                $transaction->lines()->forceDelete();
                $transaction->forceDelete();
            }

            $lines = [];
            $stockPayloads = [];
            $glAccounts = Cache::get('gl_accounts');
            $discountTotal = (float) $request->input('discount_total', 0);

            foreach ($validated['item_list'] as $item) {
                $quantity = (float) $item['quantity'];
                $unitPrice = (float) $item['unit_price'];
                $itemModel = Item::findOrFail($item['item_id']);
                $accountId = $itemModel->asset_account_id ?? $itemModel->cost_account_id;

                $stockPayloads[] = [
                    'item_id'         => $item['item_id'],
                    'movement_type'   => StockMovementType::IN->value,
                    'unit_measure_id' => $item['unit_measure_id'],
                    'quantity'        => $quantity,
                    'source'          => StockSourceType::PURCHASE->value,
                    'unit_cost'       => $unitPrice,
                    'status'          => StockStatus::POSTED->value,
                    'batch'           => $item['batch'] ?? null,
                    'color'           => $item['color'] ?? null,
                    'date'            => $validated['date'],
                    'expire_date'     => $item['expire_date'] ?? null,
                    'size_id'         => $item['size_id'] ?? null,
                    'warehouse_id'    => $validated['warehouse_id'],
                    'branch_id'       => $purchase->branch_id,
                    'reference_type'  => Purchase::class,
                    'reference_id'    => $purchase->id,
                ];

                $lines[] = [
                    'account_id' => $accountId,
                    'ledger_id'  => null,
                    'debit'      => $quantity * $unitPrice,
                    'credit'     => 0,
                    'remark'     => 'Purchase for '. ' '. $itemModel->name.' #'.$purchase->number,
                    'remark_fa' => 'خرید بابت '. ' '. $itemModel->name.' #'.$purchase->number,
                    'remark_ps' => 'د'. ' '. $itemModel->name.' #'.$purchase->number,
                ];
            }

            if ($discountTotal > 0) {
                $lines[] = [
                    'account_id' => $glAccounts['discount-from-supplier'],
                    'ledger_id' => null,
                    'debit' => 0,
                    'credit' => $discountTotal,
                    'remark' => 'Discount for purchase #' . $purchase->number,
                    'remark_fa' => 'تخفیف برای خرید #' . $purchase->number,
                    'remark_ps' => 'د'. ' '. $purchase->number.' '.'تخفیف اخیستل',
                ];
            }

            if ($validated['type'] === \App\Enums\SalePurchaseType::Cash->value) {
                $lines[] = [
                    'account_id' => $validated['bank_account_id'],
                    'ledger_id'  => null,
                    'debit'      => 0,
                    'credit'     => $validated['transaction_total'],
                    'remark'     => 'Payment for purchase #' . $purchase->number,
                    'remark_fa' => 'پرداخت برای خرید #' . $purchase->number,
                    'remark_ps' => 'د'. '#'. $purchase->number.' '.'پرداخت اخیستل',
                ];
            }

            if ($validated['type'] === \App\Enums\SalePurchaseType::OnLoan->value) {
                $lines[] = [
                    'account_id' => $glAccounts['account-payable'],
                    'ledger_id'  => $validated['supplier_id'],
                    'debit'      => 0,
                    'credit'     => $validated['transaction_total'],
                    'remark'     => 'Purchase on loan for #' . $purchase->number,
                    'remark_fa' => ' بابت خرید قرض #' . $purchase->number,
                    'remark_ps' => 'د'. '#'. $purchase->number.' '.'د پور اخیستلو په اړه',
                ];
            }

            if ($validated['type'] === \App\Enums\SalePurchaseType::Credit->value) {
                if ($validated['payment']['amount'] > 0) {
                    $amount = (float) $validated['payment']['amount'];
                    $lines[] = [
                        'account_id' => $validated['payment']['account_id'],
                        'debit' => 0,
                        'credit' => $amount,
                        'remark' => 'Partial payment for purchase #' . $purchase->number,
                        'remark_fa' => 'پرداخت جزئی برای خرید #' . $purchase->number,
                        'remark_ps' => 'د'. '#'. $purchase->number.' '.'جزوی تادیه',
                    ];
                    $lines[] = [
                        'account_id' => $glAccounts['account-payable'],
                        'ledger_id' => $validated['supplier_id'],
                        'debit' => 0,
                        'credit' => $validated['transaction_total'] - $amount,
                        'remark'     => 'Purchase on loan for #' . $purchase->number,
                        'remark_fa' => ' بابت خرید قرض #' . $purchase->number,
                        'remark_ps' => 'د'. '#'. $purchase->number.' '.'د پور اخیستلو په اړه',
                    ];
                } else {
                    $lines[] = [
                        'account_id' => $glAccounts['account-payable'],
                        'ledger_id' => $validated['supplier_id'],
                        'debit' => 0,
                        'credit' => $validated['transaction_total'],
                        'remark' => 'Purchase for #' . $purchase->number,
                        'remark_fa' => 'خرید قرضی بابت #' . $purchase->number,
                        'remark_ps' => 'د'. '#'. $purchase->number.' '.'د پور اخیستلو په اړه',
                    ];
                }
            }

            $transaction = $transactionService->post(
                header: [
                    'currency_id'    => $validated['currency_id'],
                    'rate'           => $validated['rate'],
                    'voucher_number' => 'Purchase #' . $purchase->number,
                    'date'           => $validated['date'],
                    'remark'         => 'Purchase #' . $purchase->number,
                    'status'         => TransactionStatus::DRAFT->value,
                    'reference_type' => Purchase::class,
                    'reference_id'   => $purchase->id,
                    'posting_payload' => [
                        'stock_movements' => $stockPayloads,
                    ],
                ],
                lines: $lines
            );

            // The edited purchase stays a draft, so re-hold the incoming stock as reserved_in.
            foreach ($stockPayloads as $payload) {
                $stockService->reserve($payload);
            }

            app(BillAllocationService::class)->recalculatePurchasePaymentStatuses([$purchase->id]);
            $afterState = [
                'number' => $purchase->number,
                'supplier_id' => $purchase->supplier_id,
                'date' => $purchase->date?->toDateString(),
                'status' => $purchase->status,
                'branch_id' => $purchase->branch_id,
                'warehouse_id' => $validated['warehouse_id'],
                'currency_id' => $validated['currency_id'],
                'rate' => (float) $validated['rate'],
                'item_count' => count($validated['item_list']),
                'transaction_total' => (float) $validated['transaction_total'],
            ];

            $activityLogService->logUpdate(
                reference: $purchase,
                before: $beforeState,
                after: $afterState,
                module: 'purchase',
                description: "Purchase #{$purchase->number} updated.",
                metadata: [
                    'action' => 'purchase_update',
                    'purchase_type' => $validated['type'],
                    'transaction_id' => $transaction->id,
                ],
            );

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

        // Recalculate avg_cost per item by replaying all remaining movements in
        // chronological order. Must run after all warehouse rebuilds since avg_cost
        // is item-wide (not per warehouse).
        $uniqueItemIds = $uniqueCombos->pluck('item_id')->unique()->values();

        foreach ($uniqueItemIds as $itemId) {
            $this->recalculateAvgCostForItem($itemId);
        }
    }

    private function recalculateAvgCostForItem(string $itemId): void
    {
        $item = Item::find($itemId);

        if (!$item) {
            return;
        }

        $movements = StockMovement::query()
            ->where('item_id', $itemId)
            ->orderBy('date')
            ->orderBy('id')
            ->get(['movement_type', 'quantity', 'unit_cost']);

        $avgCost = 0.0;
        $runningQty = 0.0;

        foreach ($movements as $movement) {
            $qty = (float) $movement->quantity;
            if ($movement->movement_type === StockMovementType::IN) {
                $cost = (float) $movement->unit_cost;
                if ($runningQty + $qty > 0) {
                    $avgCost = (($runningQty * $avgCost) + ($qty * $cost)) / ($runningQty + $qty);
                }
                $runningQty += $qty;
            } else {
                $runningQty = max(0.0, $runningQty - $qty);
            }
        }

        if ($runningQty > 0) {
            $item->avg_cost = $avgCost;
            $item->save();
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
            ]);
        }

        $unitFactors = [];

        $inMovements = $movements
            ->filter(fn (StockMovement $movement) => $movement->movement_type === StockMovementType::IN)
            ->values();

        // Reset qty_remaining to item-base-unit quantity (mirrors what StockService::handleIn stores)
        foreach ($inMovements as $movement) {
            $movement->qty_remaining = $this->convertMovementQuantityToItemUnit($movement, $item, $unitFactors);
            $movement->save();
        }

        // Deduct OUT movements from IN layers so FIFO/LIFO qty_remaining is accurate
        $outMovements = $movements
            ->filter(fn (StockMovement $movement) => $movement->movement_type === StockMovementType::OUT)
            ->values();

        foreach ($outMovements as $outMovement) {
            $remaining = $this->convertMovementQuantityToItemUnit($outMovement, $item, $unitFactors);

            foreach ($inMovements as $inMovement) {
                if ($remaining <= 0) {
                    break;
                }

                if ($item->is_batch_tracked && ($inMovement->batch ?? null) !== ($outMovement->batch ?? null)) {
                    continue;
                }

                $inExpireDate  = $inMovement->expire_date  ? Carbon::parse($inMovement->expire_date)->toDateString()  : null;
                $outExpireDate = $outMovement->expire_date ? Carbon::parse($outMovement->expire_date)->toDateString() : null;
                if ($outExpireDate && $inExpireDate !== $outExpireDate) {
                    continue;
                }

                $available = (float) ($inMovement->qty_remaining ?? 0);
                if ($available <= 0) {
                    continue;
                }

                $deduct = min($available, $remaining);
                $inMovement->qty_remaining = $available - $deduct;
                $inMovement->save();
                $remaining -= $deduct;
            }
        }
    }

    private function convertMovementQuantityToItemUnit(StockMovement $movement, Item $item, array &$unitFactors): float
    {
        if ($movement->unit_measure_id === $item->unit_measure_id) {
            return (float) $movement->quantity;
        }

        if (!array_key_exists($movement->unit_measure_id, $unitFactors)) {
            $movementUnit = \App\Models\Administration\UnitMeasure::query()->find($movement->unit_measure_id);
            $itemUnit     = \App\Models\Administration\UnitMeasure::query()->find($item->unit_measure_id);

            if (!$movementUnit || !$itemUnit || (float) $itemUnit->unit === 0.0) {
                $unitFactors[$movement->unit_measure_id] = 1.0;
            } else {
                $unitFactors[$movement->unit_measure_id] = (float) $movementUnit->unit / (float) $itemUnit->unit;
            }
        }

        return (float) $movement->quantity * $unitFactors[$movement->unit_measure_id];
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

    public function post(Purchase $purchase, TransactionService $transactionService, StockService $stockService)
    {
        $this->authorize('update', $purchase);

        if ($purchase->status !== TransactionStatus::DRAFT->value) {
            abort(422, 'Only draft documents can be posted.');
        }

        DB::transaction(function () use ($purchase, $transactionService, $stockService) {
            $transaction = $purchase->transaction()->firstOrFail();

            foreach ((array) data_get($transaction->posting_payload, 'stock_movements', []) as $payload) {
                // The draft's reserved_in becomes a real stock increase.
                $stockService->release($payload);
                $stockService->post($payload);
            }

            $transactionService->postDraft($transaction);
            $purchase->update([
                'status' => TransactionStatus::POSTED->value,
                'updated_by' => auth()->id(),
            ]);
        });

        return redirect()->back()->with('success', __('general.updated_successfully', ['resource' => __('general.resource.purchase')]));
    }

    public function reverse(Request $request, Purchase $purchase, TransactionService $transactionService)
    {
        $this->authorize('update', $purchase);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        if ($purchase->status !== TransactionStatus::POSTED->value) {
            abort(422, 'Only posted documents can be reversed.');
        }

        DB::transaction(function () use ($purchase, $transactionService, $validated) {
            $transaction = $purchase->transaction()->firstOrFail();
            $transactionService->reverse($transaction, $validated['reason'], $purchase->number, Purchase::class);

            $purchase->update([
                'status' => TransactionStatus::REVERSED->value,
                'updated_by' => auth()->id(),
            ]);
        });

        return redirect()->back()->with('success', __('general.updated_successfully', ['resource' => __('general.resource.purchase')]));
    }

    public function destroy(Request $request, Purchase $purchase, ActivityLogService $activityLogService, StockService $stockService)
    {
        if ($purchase->status !== TransactionStatus::DRAFT->value) {
            return back()->with('error', 'Only draft documents can be deleted.');
        }

        $oldValues = [
            'number' => $purchase->number,
            'supplier_id' => $purchase->supplier_id,
            'date' => $purchase->date?->toDateString(),
            'status' => $purchase->status,
            'branch_id' => $purchase->branch_id,
            'warehouse_id' => $purchase->warehouse_id,
            'currency_id' => $purchase->transaction?->currency_id,
            'rate' => $purchase->transaction?->rate,
            'item_count' => $purchase->items()->count(),
            'transaction_total' => (float) ($purchase->transaction_total ?? 0),
        ];

        DB::transaction(function () use ($purchase, $activityLogService, $oldValues, $stockService) {
            $transaction = $purchase->transaction()->first();
            if ($transaction) {
                // Release the reserved_in this draft was holding before removing the transaction.
                foreach ((array) data_get($transaction->posting_payload, 'stock_movements', []) as $payload) {
                    $stockService->release($payload);
                }
                $transaction->lines()->delete();
                $transaction->delete();
            }

            $purchase->items()->delete();
            $purchase->delete();

            $activityLogService->logDelete(
                reference: $purchase,
                module: 'purchase',
                description: "Purchase #{$purchase->number} deleted.",
                oldValues: $oldValues,
                metadata: [
                    'action' => 'purchase_delete',
                ],
            );
        });

        return redirect()->route('purchases.index')->with('success', __('general.purchase_deleted_successfully'));
    }
    public function restore(Request $request, Purchase $purchase, ActivityLogService $activityLogService)
    {
        DB::transaction(function () use ($purchase) {
            $purchase->restore();
            $purchase->items()->withTrashed()->restore();

            // StockMovement::withTrashed()
            //     ->where('reference_type', Purchase::class)
            //     ->where('reference_id', $purchase->id)
            //     ->restore();

            // $transaction = Transaction::withTrashed()
            //     ->where('reference_type', Purchase::class)
            //     ->where('reference_id', $purchase->id)
            //     ->first();

            // if ($transaction) {
            //     $transaction->restore();
            //     $transaction->lines()->withTrashed()->restore();
            // }

            // $affectedCombos = $purchase->items()
            //     ->withTrashed()
            //     ->get(['item_id', 'warehouse_id', 'branch_id'])
            //     ->map(fn ($item) => [
            //         'item_id' => $item->item_id,
            //         'warehouse_id' => $item->warehouse_id,
            //         'branch_id' => $item->branch_id ?? $purchase->branch_id,
            //     ])
            //     ->all();

            // $this->rebuildStockStateForCombos($affectedCombos);
        });

        $purchase->restore();
        $purchase->items()->restore();
        // $purchase->stocks()->restore();
        // $purchase->transaction()->restore();

        $activityLogService->logAction(
            eventType: 'restored',
            reference: $purchase,
            module: 'purchase',
            description: "Purchase #{$purchase->number} restored.",
            newValues: [
                'number' => $purchase->number,
                'status' => $purchase->status,
            ],
            metadata: [
                'action' => 'purchase_restore',
            ],
        );

        return redirect()->route('purchases.index')->with('success', __('general.purchase_restored_successfully'));
    }

    public function forceDelete(Request $request, Purchase $purchase)
    {
        app(\App\Services\DeletedRecordService::class)->forceDelete('purchases', (string) $purchase->id);

        return redirect()->route('purchases.index')->with('success', __('general.permanently_deleted_successfully', ['resource' => __('general.resource.purchase')]));
    }

    public function updatePurchaseStatus(Request $request, Purchase $purchase, ActivityLogService $activityLogService)
    {
        $oldStatus = $purchase->status;
        $purchase->update(['status' => $request->status]);

        $activityLogService->logAction(
            eventType: in_array($request->status, ['posted', 'unposted', 'approved', 'rejected', 'cancelled', 'completed'], true)
                ? $request->status
                : 'status_changed',
            reference: $purchase,
            module: 'purchase',
            description: "Purchase #{$purchase->number} status changed from {$oldStatus} to {$request->status}.",
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => $purchase->status],
            metadata: [
                'action' => 'purchase_status_update',
            ],
        );

        return back()->with('success', __('general.purchase_status_updated_successfully'));
    }

    public function exportDetail(Request $request, Purchase $purchase, SpreadsheetExportService $exporter): BinaryFileResponse
    {
        $this->authorize('view', $purchase);

        $purchase->load(['supplier', 'items.item', 'items.unitMeasure', 'transaction.currency', 'createdBy', 'updatedBy']);

        $rtl = in_array(app()->getLocale(), ['fa', 'ps'], true);
        $company = $request->user()?->company;
        $companyName = match (app()->getLocale()) {
            'fa'    => $company?->name_fa ?: $company?->name_en ?: $company?->abbreviation ?: config('app.name'),
            'ps'    => $company?->name_pa ?: $company?->name_en ?: $company?->abbreviation ?: config('app.name'),
            default => $company?->name_en ?: $company?->abbreviation ?: $company?->name_fa ?: $company?->name_pa ?: config('app.name'),
        };
        $currencySymbol = $purchase->transaction?->currency?->symbol ?? '';
        $t = fn (string $group, string $key, string $fallback = '') => $exporter->localeTranslation($group, $key, $fallback);

        $title = $t('purchase', 'purchase', 'Purchase') . ' #' . $purchase->number;

        $purchaseTotal = $purchase->items->sum(function ($item) use ($purchase) {
            $rowTotal     = (float) $item->quantity * (float) $item->unit_price;
            $itemDiscount = (float) ($item->discount ?? 0);
            $saleDiscount = $purchase->discount_type === 'percentage'
                ? $rowTotal * ((float) $purchase->discount / 100)
                : (float) ($purchase->discount ?? 0);
            return $rowTotal - $itemDiscount - $saleDiscount;
        });

        $typeStr   = $purchase->type   instanceof \BackedEnum ? $purchase->type->value   : (string) ($purchase->type   ?? '-');
        $statusStr = $purchase->status instanceof \BackedEnum ? $purchase->status->value : (string) ($purchase->status ?? '-');

        $summaryFields = [
            ['label' => $t('general',  'date',       'Date'),       'value' => $purchase->date?->format('Y-m-d') ?? '-'],
            ['label' => $t('general',  'supplier',   'Supplier'),   'value' => $purchase->supplier?->name ?? '-'],
            ['label' => $t('general',  'type',        'Type'),       'value' => ucfirst($typeStr)],
            ['label' => $t('general',  'status',      'Status'),     'value' => ucfirst($statusStr)],
            ['label' => $t('general',  'amount',      'Amount'),     'value' => trim($currencySymbol . ' ' . number_format($purchaseTotal, 2))],
            ['label' => $t('general',  'created_by',  'Created By'), 'value' => $purchase->createdBy?->name ?? '-'],
            ['label' => $t('general',  'updated_by',  'Updated By'), 'value' => $purchase->updatedBy?->name ?? '-'],
        ];

        $rows = $purchase->items->map(function ($item) {
            $qty      = (float) $item->quantity;
            $price    = (float) $item->unit_price;
            $discount = (float) ($item->discount ?? 0);
            $free     = (float) ($item->free ?? 0);
            $tax      = (float) ($item->tax ?? 0);
            $subtotal = ($qty * $price) - $discount + $tax;

            return [
                'item_name'         => $item->item?->name ?? '-',
                'item_code'         => $item->item?->code ?? '-',
                'batch'             => $item->batch ?? '-',
                'expire_date'       => $item->expire_date?->format('Y-m-d') ?? '-',
                'quantity'          => $qty,
                'unit_measure_name' => $item->unitMeasure?->name ?? '-',
                'unit_price'        => $price,
                'discount'          => $discount,
                'free'              => $free,
                'tax'               => $tax,
                'subtotal'          => $subtotal,
            ];
        })->all();

        $columns = [
            ['key' => 'item_name',         'label' => $t('item',    'item',        'Item')],
            ['key' => 'item_code',         'label' => $t('item',    'code',        'Code')],
            ['key' => 'batch',             'label' => $t('general', 'batch',       'Batch')],
            ['key' => 'expire_date',       'label' => $t('general', 'expire_date', 'Expiry')],
            ['key' => 'quantity',          'label' => $t('general', 'qty',         'Qty'),      'type' => 'number', 'align' => 'right'],
            ['key' => 'unit_measure_name', 'label' => $t('general', 'unit',        'Unit')],
            ['key' => 'unit_price',        'label' => $t('general', 'price',       'Price'),    'type' => 'money',  'align' => 'right'],
            ['key' => 'discount',          'label' => $t('general', 'discount',    'Discount'), 'type' => 'money',  'align' => 'right'],
            ['key' => 'free',              'label' => $t('general', 'free',        'Free'),     'type' => 'number', 'align' => 'right'],
            ['key' => 'tax',               'label' => $t('general', 'tax',         'Tax'),      'type' => 'money',  'align' => 'right'],
            ['key' => 'subtotal',          'label' => $t('general', 'total',       'Total'),    'type' => 'money',  'align' => 'right'],
        ];

        return $exporter->download([
            'filename'           => 'purchase-' . $purchase->number . '-' . now()->format('Ymd-His') . '.xlsx',
            'sheet_name'         => $title,
            'sheet_title'        => $title,
            'title'              => $title,
            'company_name'       => $companyName,
            'exported_on'        => now()->format('Y m d'),
            'rtl'                => $rtl,
            'include_row_number' => true,
            'row_number_label'   => $t('report', 'columns.no', '#'),
            'summary_fields'     => $summaryFields,
            'columns'            => $columns,
            'rows'               => $rows,
        ]);
    }

    public function exportList(Request $request, SpreadsheetExportService $exporter)
    {
        $this->authorize('viewAny', Purchase::class);

        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'desc');
        $filters = (array) $request->input('filters', []);

        $purchases = Purchase::with(['supplier', 'items', 'transaction.currency'])
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

        $rows = $purchases->map(fn ($p) => [
            'number'         => $p->number,
            'supplier_name'  => $p->supplier?->name ?? '-',
            'payment_status' => \App\Enums\PaymentStatus::tryFrom((string) $p->payment_status)?->getLabel() ?? (string) $p->payment_status,
            'amount'         => (float) $p->items->sum(fn ($item) => ((float) $item->quantity * (float) $item->unit_price) - (float) ($item->discount ?? 0)),
            'date'           => $p->date ? $this->dateConversionService->toDisplay($p->date) : '-',
            'type'           => \App\Enums\SalePurchaseType::tryFrom((string) $p->type)?->getLabel() ?? (string) $p->type,
            'status'         => (string) $p->status,
        ])->all();

        $label = $t('purchase', 'purchases', 'Purchases');

        return $exporter->download([
            'filename'           => 'purchases-' . now()->format('Ymd-His') . '.xlsx',
            'sheet_name'         => $label,
            'sheet_title'        => $label,
            'title'              => $label,
            'company_name'       => $companyName,
            'exported_on'        => now()->format('Y m d'),
            'rtl'                => $rtl,
            'include_row_number' => true,
            'row_number_label'   => $t('report', 'columns.no', 'No.'),
            'columns' => [
                ['key' => 'number',         'label' => $t('general', 'number', 'Number'), 'width' => 10],
                ['key' => 'supplier_name',  'label' => $t('ledger', 'supplier.supplier', 'Supplier'), 'width' => 20],
                ['key' => 'payment_status', 'label' => $t('general', 'payment_status', 'Payment Status'), 'width' => 16],
                ['key' => 'amount',         'label' => $t('general', 'amount', 'Amount'), 'type' => 'money', 'align' => 'right', 'width' => 14],
                ['key' => 'date',           'label' => $t('general', 'date', 'Date'), 'width' => 14],
                ['key' => 'type',           'label' => $t('general', 'type', 'Type'), 'width' => 10],
                ['key' => 'status',         'label' => $t('general', 'status', 'Status'), 'width' => 12],
            ],
            'rows' => $rows,
        ]);
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
