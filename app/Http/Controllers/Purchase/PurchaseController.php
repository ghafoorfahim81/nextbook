<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Concerns\ResolvesLineVariant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\PurchaseStoreRequest;
use App\Http\Requests\Purchase\PurchaseUpdateRequest;
use App\Http\Resources\Purchase\PurchaseResource;
use App\Models\Purchase\Purchase;
use App\Models\Purchase\PurchaseOrder;
use App\Enums\PurchaseOrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\SalePurchaseType;
use App\Services\Accounting\SettlementService;
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
use Illuminate\Validation\ValidationException;
use App\Services\DateConversionService;
use App\Services\ActivityLogService;
use App\Services\SpreadsheetExportService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Support\CompanyContext;
class PurchaseController extends Controller
{
    use ResolvesLineVariant;

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
        $sortDirection = strtolower($request->input('sortDirection', 'desc')) === 'asc' ? 'asc' : 'desc';
        $filters = (array) $request->input('filters', []);

        // Everything the list resource reads, loaded once rather than per row.
        $purchases = Purchase::with(['supplier', 'transaction.currency', 'transaction.lines', 'items.warehouse'])
            ->search($request->query('search'))
            ->filter($filters)
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
        return inertia('Purchase/Purchases/Index', [
            'purchases' => PurchaseResource::collection($purchases),
            'filterOptions' => [
                'suppliers' => Ledger::query()->where('type', 'supplier')->orderBy('name')->get(['id', 'name']),
                'currencies' => Currency::orderBy('code')->get(['id', 'code', 'name', 'local_name']),
                'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
                'types' => collect(SalePurchaseType::cases())->map(fn ($type) => [
                    'id' => $type->value,
                    'name' => $type->getLabel(),
                ])->values(),
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
        $purchaseNumber = Purchase::nextNumber();
        $bankAccounts = new Account();
        $bankAccounts = $bankAccounts->getAccountsByAccountTypeSlug('cash-or-bank');
        return inertia('Purchase/Purchases/Create', [
            'purchaseNumber' => $purchaseNumber,
            'bankAccounts' => $bankAccounts,
            'purchaseOrderId' => $request->query('purchase_order_id'),
            // When navigating here from a supplier's page (?supplier_id=...), preselect
            // that supplier even if it falls outside the capped ledger option list.
            'preselectedLedger' => $this->resolvePreselectedLedger($request->query('supplier_id')),
        ]);
    }

    /**
     * Load a single ledger by id (if given) as a select option, so a purchase form
     * opened from a supplier page can preselect it regardless of the capped option
     * list. Returns null when no/invalid id is supplied.
     *
     * @return array<string, mixed>|null
     */
    private function resolvePreselectedLedger(?string $ledgerId): ?array
    {
        if (! $ledgerId) {
            return null;
        }

        $ledger = Ledger::query()
            ->select([
                'id',
                'name',
                'code',
                'type',
                'counterpart_ledger_id',
                'email',
                'phone_no',
                'address',
                'currency_id',
                'discount',
                'credit_limit',
                'credit_limit_enabled',
                'credit_terms',
                'status',
                'company_id',
            ])
            ->withStatementTotals()
            ->find($ledgerId);

        return $ledger
            ? \App\Http\Resources\Ledger\LedgerOptionResource::make($ledger)->resolve()
            : null;
    }

    public function store(
        PurchaseStoreRequest $request,
        TransactionService $transactionService,
        StockService $stockService,
        ActivityLogService $activityLogService,
        AttachmentService $attachmentService
    )
    {
        DB::transaction(function () use ($request, $transactionService, $stockService, $activityLogService, $attachmentService) {
            $validated = $this->preparePurchaseHeader($request->validated());
            $postImmediately = (bool) user_preference('transaction.purchase_post_immediately', true);
            $validated['status'] = $postImmediately ? TransactionStatus::POSTED->value : TransactionStatus::DRAFT->value;

            if (!empty($validated['purchase_order_id'])) {
                $purchaseOrder = PurchaseOrder::where('id', $validated['purchase_order_id'])->lockForUpdate()->first();

                abort_unless(
                    $purchaseOrder
                        && $purchaseOrder->status === PurchaseOrderStatus::POSTED->value
                        && $purchaseOrder->supplier_id === $validated['supplier_id'],
                    422,
                    'The selected purchase order is no longer available for conversion.'
                );

                // A whole order becomes one bill. A second bill raised from it
                // while the first is still a draft would receive the order twice.
                $claimedBy = Purchase::query()
                    ->where('purchase_order_id', $purchaseOrder->id)
                    ->where('status', '!=', TransactionStatus::REVERSED->value)
                    ->value('number');

                if ($claimedBy) {
                    throw ValidationException::withMessages([
                        'purchase_order_id' => __('general.purchase_order_already_billed', ['number' => $claimedBy]),
                    ]);
                }
            }

            $validated['item_list'] = $this->preparePurchaseLines($validated);
            $totals = $this->computePurchaseTotals($validated);
            $this->assertPurchasePayment($validated, $totals);

            $purchase = Purchase::create($validated);

            if ($request->hasFile('attachments')) {
                $attachmentService->store($purchase, $request->file('attachments'));
            }

            $transaction = $this->writePurchaseDocument($purchase, $validated, $totals, $postImmediately, $transactionService, $stockService);

            $activityLogService->logCreate(
                reference: $purchase,
                module: 'purchase',
                description: $postImmediately
                    ? "Purchase #{$purchase->number} created and posted."
                    : "Purchase #{$purchase->number} created as a draft.",
                newValues: [
                    'number' => $purchase->number,
                    'supplier_id' => $purchase->supplier_id,
                    'date' => $purchase->date?->toDateString(),
                    'status' => $purchase->status,
                    'branch_id' => $purchase->branch_id,
                    'warehouse_id' => $validated['warehouse_id'],
                    'currency_id' => $validated['currency_id'],
                    'item_count' => count($validated['item_list']),
                    'transaction_total' => $totals['total'],
                ],
                metadata: [
                    'action' => 'purchase_store',
                    'purchase_type' => $validated['type'],
                    'transaction_id' => $transaction->id,
                ],
            );

            if ($postImmediately) {
                $this->markPurchaseOrderCompleted($validated['purchase_order_id'] ?? null);
            }

            return $purchase;
        });

        if ((bool) $request->create_and_new) {
            // Stay on the same page; frontend will reset form and increment number
            return redirect()->back()->with('success', __('general.created_successfully', ['resource' => __('general.resource.purchase')]));
        }

        return redirect()->route('purchases.index')->with('success', __('general.created_successfully', ['resource' => __('general.resource.purchase')]));
    }

    /**
     * Header fields as the purchase row stores them: both dates in Gregorian,
     * and the type under its column name.
     */
    private function preparePurchaseHeader(array $validated, ?Purchase $purchase = null): array
    {
        $validated['date'] = ! empty($validated['date'])
            ? $this->dateConversionService->toGregorian($validated['date'])
            : $purchase?->date?->toDateString();
        $validated['due_date'] = ! empty($validated['due_date'])
            ? $this->dateConversionService->toGregorian($validated['due_date'])
            : null;
        $validated['type'] = $validated['purchase_type'] ?? $purchase?->type?->value ?? SalePurchaseType::Cash->value;
        // Only a cash purchase is paid from a named account on the bill itself.
        if ($validated['type'] !== SalePurchaseType::Cash->value) {
            $validated['bank_account_id'] = null;
        }

        return $validated;
    }

    /**
     * Lines as purchase_items stores them. The form posts the line discount as
     * item_discount; the column is `discount`. The expiry date is brought to
     * Gregorian here so the line and the stock movement hold the same date —
     * under the Jalali calendar the line used to keep the raw Jalali string.
     *
     * @return array<int, array<string, mixed>>
     */
    private function preparePurchaseLines(array $validated): array
    {
        return array_values(array_map(function ($item) use ($validated) {
            $item['discount'] = (float) ($item['item_discount'] ?? 0);
            $item['free'] = (float) ($item['free'] ?? 0);
            $item['tax'] = (float) ($item['tax'] ?? 0);
            $item['batch'] = filled($item['batch'] ?? null) ? trim((string) $item['batch']) : null;
            $item['expire_date'] = filled($item['expire_date'] ?? null)
                ? $this->dateConversionService->toGregorian((string) $item['expire_date'])
                : null;
            $item['warehouse_id'] = $validated['warehouse_id'];
            $item['variant_id'] = $this->resolveLineVariantId($item);

            return $item;
        }, $validated['item_list']));
    }

    /**
     * What the bill comes to, worked out here rather than taken from the form.
     *
     * The browser used to send the total and the discount and the ledger was
     * posted with whatever arrived. Each line's net value (its own discount and
     * its share of the bill discount off) is what its stock is received at.
     *
     * @return array{nets: array<int, float>, gross: float, discount: float, tax: float, total: float}
     */
    private function computePurchaseTotals(array $validated): array
    {
        $nets = Purchase::lineNetValues($validated['item_list'], $validated['discount'] ?? 0, $validated['discount_type'] ?? 'percentage');

        foreach ($nets as $net) {
            if ($net < 0) {
                throw ValidationException::withMessages([
                    'discount' => __('general.discount_exceeds_bill_total'),
                ]);
            }
        }

        $gross = 0.0;
        $tax = 0.0;
        foreach ($validated['item_list'] as $item) {
            $gross += money_round((float) $item['quantity'] * (float) $item['unit_price']);
            $tax += (float) ($item['tax'] ?? 0);
        }

        $netTotal = array_sum($nets);

        return [
            'nets' => $nets,
            'gross' => money_round($gross),
            'discount' => money_round($gross - $netTotal),
            'tax' => money_round($tax),
            'total' => money_round($netTotal + $tax),
        ];
    }

    /**
     * A payment made with the purchase has to fit the bill and name where the
     * money came from. Both used to reach the ledger unchecked and fail there.
     */
    private function assertPurchasePayment(array $validated, array $totals): void
    {
        if ($validated['type'] === SalePurchaseType::Cash->value && empty($validated['bank_account_id'])) {
            throw ValidationException::withMessages([
                'bank_account_id' => __('general.cash_account_required'),
            ]);
        }

        if ($validated['type'] !== SalePurchaseType::Credit->value) {
            return;
        }

        $paid = (float) ($validated['payment']['amount'] ?? 0);

        if ($paid > $totals['total'] + 0.0001) {
            throw ValidationException::withMessages([
                'payment.amount' => __('general.payment_exceeds_bill_total'),
            ]);
        }

        if ($paid > 0 && empty($validated['payment']['account_id'])) {
            throw ValidationException::withMessages([
                'payment.account_id' => __('general.cash_account_required'),
            ]);
        }
    }

    /**
     * Write the purchase's lines, its stock and its voucher — the one path for a
     * new bill and an edited draft.
     *
     * Stock is received at what it really cost: the line's net value spread
     * over the units bought AND the free units that came with them. A purchase
     * discount therefore lowers the cost of the goods (it is not booked as
     * income), and free units arrive on the shelf instead of being ignored.
     */
    private function writePurchaseDocument(
        Purchase $purchase,
        array $validated,
        array $totals,
        bool $postNow,
        TransactionService $transactionService,
        StockService $stockService,
    ): Transaction {
        // Document amounts (purchase_items.unit_price, GL lines) stay in the
        // transaction currency — reports convert them with transactions.rate.
        // Stock costing has no currency of its own: unit_cost is home currency.
        $rate = $this->transactionRate($validated);
        $itemModels = Item::query()
            ->whereIn('id', collect($validated['item_list'])->pluck('item_id')->unique())
            ->get(['id', 'name', 'asset_account_id', 'cost_account_id'])
            ->keyBy('id');

        $lines = [];
        $stockPayloads = [];
        $number = $purchase->number;

        foreach ($validated['item_list'] as $index => $item) {
            $itemModel = $itemModels->get($item['item_id']);
            if (! $itemModel) {
                throw (new \Illuminate\Database\Eloquent\ModelNotFoundException())->setModel(Item::class, [$item['item_id']]);
            }

            // Tax paid on a purchase cannot be reclaimed here, so it is part of
            // what the goods cost. Left out of every line it unbalanced the bill.
            $net = money_round((float) $totals['nets'][$index] + (float) ($item['tax'] ?? 0));
            $stockQuantity = (float) $item['quantity'] + max(0.0, (float) ($item['free'] ?? 0));
            // Home currency, per unit of the line's own measure.
            $unitCost = $stockQuantity > 0 ? ($net * $rate) / $stockQuantity : 0.0;

            $purchase->items()->create([...$item, 'net_unit_cost' => $unitCost]);

            $payload = [
                'item_id'         => $item['item_id'],
                'movement_type'   => StockMovementType::IN->value,
                'unit_measure_id' => $item['unit_measure_id'],
                'quantity'        => $stockQuantity,
                'source'          => StockSourceType::PURCHASE->value,
                'unit_cost'       => $unitCost,
                'status'          => $postNow ? StockStatus::POSTED->value : StockStatus::DRAFT->value,
                'batch'           => $item['batch'] ?? null,
                'date'            => $validated['date'],
                'expire_date'     => $item['expire_date'] ?? null,
                'variant_id'      => $item['variant_id'] ?? null,
                'warehouse_id'    => $validated['warehouse_id'],
                'reference_type'  => Purchase::class,
                'reference_id'    => $purchase->id,
            ];

            if ($postNow) {
                $stockService->post($payload);
            } else {
                // Draft: record the incoming stock as reserved_in for visibility.
                $stockService->reserve($payload);
            }

            $stockPayloads[] = $payload;

            $name = $itemModel->name;
            $lines[] = [
                'account_id' => $itemModel->asset_account_id ?? $itemModel->cost_account_id,
                'ledger_id'  => null,
                'debit'      => $net,
                'credit'     => 0,
                'remark'     => "Purchase of {$name} #{$number}",
                'remark_fa'  => "خرید {$name} #{$number}",
                'remark_ps'  => "د {$name} پېرود #{$number}",
            ];
        }

        [$settlementLines, $paymentStatus] = $this->purchaseSettlementLines($purchase, $validated, $totals);
        array_push($lines, ...$settlementLines);

        $purchase->update(['payment_status' => $paymentStatus->value]);

        return $transactionService->post(
            header: [
                'currency_id'    => $validated['currency_id'],
                'rate'           => $validated['rate'],
                'voucher_number' => 'Purchase #' . $number,
                'date'           => $validated['date'],
                'remark'         => 'Purchase #' . $number,
                'status'         => $postNow ? TransactionStatus::POSTED->value : TransactionStatus::DRAFT->value,
                'reference_type' => Purchase::class,
                'reference_id'   => $purchase->id,
                'posting_payload' => [
                    'stock_movements' => $stockPayloads,
                    // How the bill was paid. A draft keeps its lines off the
                    // ledger, so this is where the edit form reads them back.
                    'purchase' => [
                        'bank_account_id' => $validated['bank_account_id'] ?? null,
                        'payment' => [
                            'method' => $validated['payment']['method'] ?? null,
                            'amount' => (float) ($validated['payment']['amount'] ?? 0),
                            'account_id' => $validated['payment']['account_id'] ?? null,
                            'note' => $validated['payment']['note'] ?? null,
                        ],
                    ],
                ],
            ],
            lines: $lines
        );
    }

    /**
     * The side of the voucher that says who is owed what, and the payment status
     * that follows from it. A draft is given its status here: the service that
     * recalculates it reads ledger lines, which a draft does not have yet, and
     * called every unpaid draft "paid".
     *
     * @return array{0: array<int, array<string, mixed>>, 1: PaymentStatus}
     */
    private function purchaseSettlementLines(Purchase $purchase, array $validated, array $totals): array
    {
        $glAccounts = CompanyContext::glAccounts();
        $number = $purchase->number;
        $total = $totals['total'];
        $lines = [];

        $payable = fn (float $amount, string $en, string $fa, string $ps) => [
            'account_id' => $glAccounts['account-payable'],
            'ledger_id'  => $validated['supplier_id'],
            'debit'      => 0,
            'credit'     => $amount,
            'remark'     => $en,
            'remark_fa'  => $fa,
            'remark_ps'  => $ps,
        ];

        if ($validated['type'] === SalePurchaseType::Cash->value) {
            $lines[] = [
                'account_id' => $validated['bank_account_id'],
                'ledger_id'  => null,
                'debit'      => 0,
                'credit'     => $total,
                'remark'     => "Payment for purchase #{$number}",
                'remark_fa'  => "پرداخت بابت خرید #{$number}",
                'remark_ps'  => "د پېرود #{$number} تادیه",
            ];

            return [$lines, PaymentStatus::Paid];
        }

        if ($validated['type'] === SalePurchaseType::OnLoan->value) {
            $lines[] = $payable($total, "Purchase on loan #{$number}", "خرید قرضی #{$number}", "پور پېرود #{$number}");

            return [$lines, PaymentStatus::Unpaid];
        }

        // Credit: part of it may be paid now, the rest stays owed.
        $paid = (float) ($validated['payment']['amount'] ?? 0);
        $remaining = money_round($total - $paid);

        if ($paid > 0) {
            $inFull = $remaining <= 0;
            $lines[] = [
                'account_id' => $validated['payment']['account_id'],
                'ledger_id'  => null,
                'debit'      => 0,
                'credit'     => $paid,
                'remark'     => ($inFull ? 'Payment' : 'Partial payment') . " for purchase #{$number}",
                'remark_fa'  => ($inFull ? 'پرداخت کامل' : 'پرداخت قسمتی') . " بابت خرید #{$number}",
                'remark_ps'  => "د پېرود #{$number} " . ($inFull ? 'بشپړه تادیه' : 'برخه‌ییزه تادیه'),
            ];
        }

        if ($remaining > 0) {
            $lines[] = $paid > 0
                ? $payable($remaining, "Remaining payable for purchase #{$number}", "باقی‌ماندهٔ قرض خرید #{$number}", "د پېرود #{$number} پاتې پور")
                : $payable($remaining, "Payable for credit purchase #{$number}", "قرض بابت خرید اعتباری #{$number}", "د اعتباري پېرود #{$number} پور");
        }

        $status = match (true) {
            $remaining <= 0 => PaymentStatus::Paid,
            $paid > 0 => PaymentStatus::PartiallyPaid,
            default => PaymentStatus::Unpaid,
        };

        return [$lines, $status];
    }


    public function show(Request $request, Purchase $purchase)
    {
        $purchase->load([
            'items.item',
            'items.variant',
            'items.unitMeasure',
            'supplier',
            'purchaseOrder:id,number',
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
            return back()->with('error', __('messages.document.only_draft_can_be_edited'));
        }

        $bankAccounts = (new Account())->getAccountsByAccountTypeSlug('cash-or-bank');

        return inertia('Purchase/Purchases/Edit', [
            'purchase' => new PurchaseResource($purchase->load([
                'items.item.unitMeasure',
                'items.item.variants',
                'items.variant',
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
            return back()->with('error', __('messages.document.only_draft_can_be_edited'));
        }

        $purchase->loadMissing('items');

        $beforeState = [
            'number' => $purchase->number,
            'supplier_id' => $purchase->supplier_id,
            'date' => $purchase->date?->toDateString(),
            'status' => $purchase->status,
            'branch_id' => $purchase->branch_id,
            'warehouse_id' => $purchase->items->first()?->warehouse_id,
            'currency_id' => $purchase->transaction?->currency_id,
            'rate' => $purchase->transaction?->rate,
            'item_count' => $purchase->items->count(),
            'transaction_total' => $purchase->purchaseTotal(),
        ];

        DB::transaction(function () use ($request, $purchase, $transactionService, $stockService, $activityLogService, $attachmentService, $beforeState) {
            $validated = $this->preparePurchaseHeader($request->validated(), $purchase);
            $validated['status'] = TransactionStatus::DRAFT->value;
            $validated['item_list'] = $this->preparePurchaseLines($validated);
            $totals = $this->computePurchaseTotals($validated);
            $this->assertPurchasePayment($validated, $totals);

            if ($request->hasFile('attachments')) {
                $attachmentService->store($purchase, $request->file('attachments'));
            }

            $purchase->update($validated);

            // Hand back what the previous version of the draft was holding.
            $transaction = Transaction::query()
                ->where('reference_type', Purchase::class)
                ->where('reference_id', $purchase->id)
                ->first();

            if ($transaction) {
                foreach ((array) data_get($transaction->posting_payload, 'stock_movements', []) as $oldPayload) {
                    $stockService->release($oldPayload);
                }
                $transaction->lines()->forceDelete();
                $transaction->forceDelete();
            }

            $purchase->items()->forceDelete();

            $transaction = $this->writePurchaseDocument($purchase, $validated, $totals, false, $transactionService, $stockService);

            $activityLogService->logUpdate(
                reference: $purchase,
                before: $beforeState,
                after: [
                    'number' => $purchase->number,
                    'supplier_id' => $purchase->supplier_id,
                    'date' => $purchase->date?->toDateString(),
                    'status' => $purchase->status,
                    'branch_id' => $purchase->branch_id,
                    'warehouse_id' => $validated['warehouse_id'],
                    'currency_id' => $validated['currency_id'],
                    'rate' => (float) $validated['rate'],
                    'item_count' => count($validated['item_list']),
                    'transaction_total' => $totals['total'],
                ],
                module: 'purchase',
                description: "Purchase #{$purchase->number} updated.",
                metadata: [
                    'action' => 'purchase_update',
                    'purchase_type' => $validated['type'],
                    'transaction_id' => $transaction->id,
                ],
            );
        });

        return redirect()->route('purchases.index')->with('success', __('general.updated_successfully', ['resource' => __('general.resource.purchase')]));
    }

    /**
     * Home-currency multiplier for the transaction: 1 unit of the selected currency
     * equals `rate` units of the company currency. Falls back to 1 so a missing or
     * zeroed rate can never wipe out stock cost.
     */
    private function transactionRate(array $validated): float
    {
        $rate = (float) ($validated['rate'] ?? 1);

        return $rate > 0 ? $rate : 1.0;
    }

    public function post(Purchase $purchase, TransactionService $transactionService, StockService $stockService)
    {
        $this->authorize('update', $purchase);

        if ($purchase->status !== TransactionStatus::DRAFT->value) {
            abort(422, __('messages.document.only_draft_can_be_posted'));
        }

        try {
            DB::transaction(function () use ($purchase, $transactionService, $stockService) {
                $transaction = $purchase->transaction()->firstOrFail();

                foreach ((array) data_get($transaction->posting_payload, 'stock_movements', []) as $payload) {
                    // The draft's reserved_in becomes a real stock increase. The payload
                    // was frozen when the draft was written, so it still says "draft" —
                    // the movement this creates belongs to a posted document.
                    $stockService->release($payload);
                    $stockService->post([...$payload, 'status' => StockStatus::POSTED->value]);
                }

                $transactionService->postDraft($transaction);
                $purchase->update([
                    'status' => TransactionStatus::POSTED->value,
                    'updated_by' => auth()->id(),
                ]);

                $this->markPurchaseOrderCompleted($purchase->purchase_order_id);
            });
        } catch (ValidationException $e) {
            // Rolled back: the purchase stays a draft and stock is untouched.
            return redirect()->back()->with('error', collect($e->errors())->flatten()->first() ?: $e->getMessage());
        }

        return redirect()->back()->with('success', __('general.updated_successfully', ['resource' => __('general.resource.purchase')]));
    }

    /**
     * When a Purchase created from a Purchase Order gets posted, the order is fully consumed
     * (whole-order conversion only) so it flips to Completed to prevent reuse.
     */
    private function markPurchaseOrderCompleted(?string $purchaseOrderId): void
    {
        if (!$purchaseOrderId) {
            return;
        }

        PurchaseOrder::where('id', $purchaseOrderId)->update([
            'status' => PurchaseOrderStatus::COMPLETED->value,
            'updated_by' => auth()->id(),
        ]);
    }

    public function reverse(Request $request, Purchase $purchase, TransactionService $transactionService)
    {
        $this->authorize('update', $purchase);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        if ($purchase->status !== TransactionStatus::POSTED->value) {
            abort(422, __('messages.document.only_posted_can_be_reversed'));
        }

        // A return is raised against this document and takes its quantities and
        // costs from it. Reversing the document underneath would leave the
        // return standing on something that no longer happened — the goods it
        // sent back were never received, and its ledger entries reference a
        // voucher that has been undone. The return has to go first.
        $standingReturns = $purchase->returns()
            ->where('status', '!=', TransactionStatus::REVERSED->value)
            ->pluck('number');

        if ($standingReturns->isNotEmpty()) {
            throw ValidationException::withMessages([
                'status' => __('general.cannot_reverse_document_with_returns', [
                    'numbers' => $standingReturns->implode(', '),
                ]),
            ]);
        }

        // Money taken against this document on a SEPARATE voucher. A receipt
        // allocated here was taken against this debt specifically; undoing the
        // debt would leave the receipt allocated to nothing and the party's
        // balance would stop adding up. (A cash purchase settles itself inside
        // its own voucher and writes no settlement row, so it is untouched by
        // this.)
        $paidBy = DB::table('settlements as s')
            ->join('transaction_lines as tl', 'tl.id', '=', 's.target_line_id')
            ->join('transactions as target', 'target.id', '=', 'tl.transaction_id')
            ->join('transactions as settling', 'settling.id', '=', 's.transaction_id')
            ->where('target.reference_type', Purchase::class)
            ->where('target.reference_id', $purchase->id)
            ->whereNull('s.deleted_at')
            ->distinct()
            ->pluck('settling.voucher_number');

        if ($paidBy->isNotEmpty()) {
            throw ValidationException::withMessages([
                'status' => __('general.cannot_reverse_document_with_payments', [
                    'numbers' => $paidBy->filter()->implode(', '),
                ]),
            ]);
        }

        // A landed cost was added onto this bill's stock. Undoing the bill would
        // leave that cost on goods that were never received.
        $landedCosts = $purchase->landedCosts()
            ->where('landed_costs.status', '!=', \App\Enums\LandedCostStatus::Reversed->value)
            ->pluck('landed_costs.number');

        if ($landedCosts->isNotEmpty()) {
            throw ValidationException::withMessages([
                'status' => __('general.cannot_reverse_purchase_with_landed_cost', [
                    'numbers' => $landedCosts->implode(', '),
                ]),
            ]);
        }

        DB::transaction(function () use ($purchase, $transactionService, $validated) {
            $transaction = $purchase->transaction()->firstOrFail();
            $transactionService->reverse($transaction, $validated['reason'], $purchase->number, Purchase::class);

            $purchase->update([
                'status' => TransactionStatus::REVERSED->value,
                'updated_by' => auth()->id(),
            ]);

            // Nothing was received after all: the order it delivered is open again.
            if ($purchase->purchase_order_id) {
                PurchaseOrder::where('id', $purchase->purchase_order_id)
                    ->where('status', PurchaseOrderStatus::COMPLETED->value)
                    ->update([
                        'status' => PurchaseOrderStatus::POSTED->value,
                        'updated_by' => auth()->id(),
                    ]);
            }
        });

        return redirect()->back()->with('success', __('general.updated_successfully', ['resource' => __('general.resource.purchase')]));
    }

    public function destroy(Request $request, Purchase $purchase, ActivityLogService $activityLogService, StockService $stockService)
    {
        if ($purchase->status !== TransactionStatus::DRAFT->value) {
            return back()->with('error', __('messages.document.only_draft_can_be_deleted'));
        }

        $purchase->loadMissing(['items', 'transaction']);

        $oldValues = [
            'number' => $purchase->number,
            'supplier_id' => $purchase->supplier_id,
            'date' => $purchase->date?->toDateString(),
            'status' => $purchase->status,
            'branch_id' => $purchase->branch_id,
            'warehouse_id' => $purchase->items->first()?->warehouse_id,
            'currency_id' => $purchase->transaction?->currency_id,
            'rate' => $purchase->transaction?->rate,
            'item_count' => $purchase->items->count(),
            'transaction_total' => $purchase->purchaseTotal(),
        ];

        DB::transaction(function () use ($purchase, $activityLogService, $oldValues, $stockService) {
            $transaction = $purchase->transaction()->first();
            if ($transaction) {
                // Release the reserved_in this draft was holding.
                foreach ((array) data_get($transaction->posting_payload, 'stock_movements', []) as $payload) {
                    $stockService->release($payload);
                }
                // To the bin with the purchase, so restoring it brings it back.
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

    public function restore(Request $request, Purchase $purchase, ActivityLogService $activityLogService, StockService $stockService)
    {
        DB::transaction(function () use ($purchase, $activityLogService, $stockService) {
            $purchase->restore();
            $purchase->items()->withTrashed()->restore();

            // Deleting the draft binned its voucher too; without it back the
            // restored draft could never be posted.
            $transaction = Transaction::withTrashed()
                ->where('reference_type', Purchase::class)
                ->where('reference_id', $purchase->id)
                ->first();

            if ($transaction?->trashed()) {
                $transaction->restore();
            }

            // A restored draft is expecting its goods again.
            if ($purchase->status === TransactionStatus::DRAFT->value) {
                foreach ((array) data_get($transaction?->posting_payload, 'stock_movements', []) as $payload) {
                    $stockService->reserve($payload);
                }
            }

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
        });

        return redirect()->route('purchases.index')->with('success', __('general.purchase_restored_successfully'));
    }

    public function forceDelete(Request $request, Purchase $purchase)
    {
        app(\App\Services\DeletedRecordService::class)->forceDelete('purchases', (string) $purchase->id);

        return redirect()->route('purchases.index')->with('success', __('general.permanently_deleted_successfully', ['resource' => __('general.resource.purchase')]));
    }

    public function exportDetail(Request $request, Purchase $purchase, SpreadsheetExportService $exporter): BinaryFileResponse
    {
        $this->authorize('view', $purchase);

        $purchase->load(['supplier', 'items.item', 'items.unitMeasure', 'transaction.currency', 'createdBy', 'updatedBy']);

        $rtl = in_array(app()->getLocale(), ['fa', 'ps'], true);
        $company = CompanyContext::company();
        $companyName = match (app()->getLocale()) {
            'fa'    => $company?->name_fa ?: $company?->name_en ?: $company?->abbreviation ?: config('app.name'),
            'ps'    => $company?->name_pa ?: $company?->name_en ?: $company?->abbreviation ?: config('app.name'),
            default => $company?->name_en ?: $company?->abbreviation ?: $company?->name_fa ?: $company?->name_pa ?: config('app.name'),
        };
        $currencySymbol = $purchase->transaction?->currency?->symbol ?? '';
        $t = fn (string $group, string $key, string $fallback = '') => $exporter->localeTranslation($group, $key, $fallback);

        $title = $t('purchase', 'purchase', 'Purchase') . ' #' . $purchase->number;

        $purchaseTotal = $purchase->purchaseTotal();

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
                'item_name'         => $item->snapshotItemName() ?? '-',
                'item_code'         => $item->snapshotItemCode() ?? '-',
                'batch'             => $item->batch ?? '-',
                'expire_date'       => $item->expire_date?->format('Y-m-d') ?? '-',
                'quantity'          => $qty,
                'unit_measure_name' => $item->snapshotUnitName() ?? '-',
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
        $company = CompanyContext::company();
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
            'amount'         => $p->purchaseTotal(),
            'currency'       => $p->transaction?->currency?->code ?? '-',
            'rate'           => $p->transaction?->rate !== null ? (float) $p->transaction->rate : '-',
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
                ['key' => 'currency',       'label' => $t('admin', 'currency.currency', 'Currency'), 'width' => 10],
                ['key' => 'rate',           'label' => $t('general', 'rate', 'Rate'), 'type' => 'money', 'align' => 'right', 'width' => 10],
                ['key' => 'date',           'label' => $t('general', 'date', 'Date'), 'width' => 14],
                ['key' => 'type',           'label' => $t('general', 'type', 'Type'), 'width' => 10],
                ['key' => 'status',         'label' => $t('general', 'status', 'Status'), 'width' => 12],
            ],
            'rows' => $rows,
        ]);
    }

    /**
     * Open payables for a supplier. Mirror of SaleController::openBills().
     */
    public function openBills(Request $request, SettlementService $settlements)
    {
        $ledgerId = (string) $request->query('ledger_id', '');
        $currencyId = (string) $request->query('currency_id', '');

        return response()->json([
            'data' => $ledgerId
                ? $settlements->openItems($ledgerId, $currencyId ?: null)
                : [],
        ]);
    }
}
