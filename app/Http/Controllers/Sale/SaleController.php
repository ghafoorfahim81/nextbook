<?php

namespace App\Http\Controllers\Sale;

use App\Http\Controllers\Concerns\ResolvesLineVariant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sale\SaleStoreRequest;
use App\Http\Requests\Sale\SaleUpdateRequest;
use App\Http\Resources\Sale\SaleListResource;
use App\Http\Resources\Sale\SaleResource;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleItem;
use App\Models\Sale\SaleOrder;
use App\Enums\SaleOrderStatus;
use App\Services\Accounting\PaymentStatusService;
use App\Services\Accounting\SettlementService;
use App\Models\Ledger\Ledger;
use App\Models\Administration\Currency;
use App\Models\Administration\UnitMeasure;
use App\Models\Administration\Warehouse;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\TransactionService;
use App\Models\Account\Account;
use App\Services\StockService;
use App\Services\DiscountRuleResolver;
use App\Services\AttachmentService;
use App\Models\Transaction\Transaction;
use App\Enums\TransactionStatus;
use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Support\Preferences\InvoiceThemeOptions;
use App\Models\Sale\InvoiceFormat;
use Illuminate\Support\Facades\Auth;
use App\Enums\CostingMethod;
use App\Enums\PaymentStatus;
use App\Enums\SalePurchaseType;
use App\Models\Inventory\Item;
use App\Models\Inventory\ItemVariant;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Services\DateConversionService;
use App\Services\ActivityLogService;
use App\Services\SpreadsheetExportService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Validation\ValidationException;
use App\Support\CompanyContext;

class SaleController extends Controller
{
    use ResolvesLineVariant;

    private $dateConversionService;
    public function __construct(DateConversionService $dateConversionService)
    {
        $this->authorizeResource(Sale::class, 'sale');
        $this->dateConversionService = $dateConversionService;
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortField = $request->input('sortField', 'id');
        $sortDirection = strtolower($request->input('sortDirection', 'desc')) === 'asc' ? 'asc' : 'desc';
        $filters = (array) $request->input('filters', []);
        $sortableFields = [
            'id' => 'sales.id',
            'number' => 'sales.number',
            'date' => 'sales.date',
            'type' => 'sales.type',
            'amount' => 'items_gross_total',
        ];
        $sortColumn = $sortableFields[$sortField] ?? 'sales.id';

        $itemGrossTotal = SaleItem::query()
            ->selectRaw('COALESCE(SUM(quantity * unit_price), 0)')
            ->whereColumn('sale_items.sale_id', 'sales.id')
            ->whereNull('sale_items.deleted_at');
        $itemDiscountTotal = SaleItem::query()
            ->selectRaw('COALESCE(SUM(discount), 0)')
            ->whereColumn('sale_items.sale_id', 'sales.id')
            ->whereNull('sale_items.deleted_at');
        $itemTaxTotal = SaleItem::query()
            ->selectRaw('COALESCE(SUM(tax), 0)')
            ->whereColumn('sale_items.sale_id', 'sales.id')
            ->whereNull('sale_items.deleted_at');

        $sales = Sale::query()
            ->select([
                'sales.id',
                'sales.number',
                'sales.customer_id',
                'sales.date',
                'sales.discount',
                'sales.discount_type',
                'sales.type',
                'sales.status',
                'sales.payment_status',
            ])
            ->selectSub($itemGrossTotal, 'items_gross_total')
            ->selectSub($itemDiscountTotal, 'items_discount_total')
            ->selectSub($itemTaxTotal, 'items_tax_total')
            ->with(['customer:id,name', 'transaction.currency'])
            ->search($request->query('search'))
            ->filter($filters)
            ->orderBy($sortColumn, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
        return inertia('Sale/Sales/Index', [
            'sales' => SaleListResource::collection($sales),
            'filterOptions' => [
                'customers' => Ledger::query()->where('type', 'customer')->orderBy('name')->get(['id', 'name']),
                'currencies' => Currency::orderBy('code')->get(['id', 'code', 'name', 'local_name']),
                'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
                'types' => collect(SalePurchaseType::cases())->map(fn ($type) => [
                    'id' => $type->value,
                    'name' => $type->getLabel(),
                ])->values(),
                'paymentStatuses' => collect(PaymentStatus::cases())->map(fn ($status) => [
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
        $saleNumber = Sale::nextNumber();
        $bankAccounts = new Account();
        $bankAccounts = $bankAccounts->getAccountsByAccountTypeSlug('cash-or-bank');

        return inertia('Sale/Sales/Create', [
            'saleNumber' => $saleNumber,
            'bankAccounts' => $bankAccounts,
            'saleOrderId' => $request->query('sale_order_id'),
            // When navigating here from a customer's page (?customer_id=...), preselect
            // that customer even if it falls outside the capped ledger option list.
            'preselectedLedger' => $this->resolvePreselectedLedger($request->query('customer_id')),
            'ledgers' => \App\Http\Resources\Ledger\LedgerOptionResource::collection(
                Ledger::query()
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
                        'is_active',
                        'company_id',
                    ])
                    ->withStatementTotals()
                    ->where('type', 'customer')
                    ->where('is_active', true)
                    ->availableInBranch(CompanyContext::branchId())
                    ->orderByRaw("CASE WHEN code = 'CASH-CUST' THEN 0 ELSE 1 END")
                    ->orderBy('created_at','desc')
                    ->limit(200)
                    ->get()
            ),
        ]);
    }

    /**
     * Load a single ledger by id (if given) as a select option, so a transaction
     * form opened from a customer/supplier page can preselect it regardless of the
     * capped option list. Returns null when no/invalid id is supplied.
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
                'is_active',
                'company_id',
            ])
            ->withStatementTotals()
            ->find($ledgerId);

        return $ledger
            ? \App\Http\Resources\Ledger\LedgerOptionResource::make($ledger)->resolve()
            : null;
    }

    public function store(
        SaleStoreRequest $request,
        TransactionService $transactionService,
        StockService $stockService,
        ActivityLogService $activityLogService,
        AttachmentService $attachmentService
    )
    {
        $sale = DB::transaction(function () use ($request, $transactionService, $stockService, $activityLogService, $attachmentService) {
            $validated = $this->prepareSaleHeader($request->validated());
            $postImmediately = (bool) user_preference('transaction.sale_post_immediately', true);
            $documentStatus = $postImmediately ? TransactionStatus::POSTED->value : TransactionStatus::DRAFT->value;
            $validated['status'] = $documentStatus;

            if (!empty($validated['sale_order_id'])) {
                $saleOrder = SaleOrder::where('id', $validated['sale_order_id'])->lockForUpdate()->first();

                abort_unless(
                    $saleOrder
                        && $saleOrder->status === SaleOrderStatus::POSTED->value
                        && $saleOrder->customer_id === $validated['customer_id'],
                    422,
                    'The selected sale order is no longer available for conversion.'
                );

                // A whole order becomes one sale. A second bill raised from it
                // while the first is still a draft would deliver the order twice.
                $claimedBy = Sale::query()
                    ->where('sale_order_id', $saleOrder->id)
                    ->where('status', '!=', TransactionStatus::REVERSED->value)
                    ->value('number');

                if ($claimedBy) {
                    throw ValidationException::withMessages([
                        'sale_order_id' => __('general.sale_order_already_billed', ['number' => $claimedBy]),
                    ]);
                }
            }

            $sale = Sale::create($validated);

            if ($request->hasFile('attachments')) {
                $attachmentService->store($sale, $request->file('attachments'));
            }

            $validated['item_list'] = $this->prepareSaleLines($validated, $sale->company_id);
            $totals = $this->computeSaleTotals($validated);
            $this->assertSalePayment($validated, $totals);

            $transaction = $this->writeSaleDocument($sale, $validated, $totals, $postImmediately, $transactionService, $stockService);

            $activityLogService->logCreate(
                reference: $sale,
                module: 'sale',
                description: $postImmediately
                    ? "Sale #{$sale->number} created and posted."
                    : "Sale #{$sale->number} created as a draft.",
                newValues: [
                    'number' => $sale->number,
                    'customer_id' => $sale->customer_id,
                    'date' => $sale->date?->toDateString(),
                    'status' => $sale->status,
                    'branch_id' => $sale->branch_id,
                    'warehouse_id' => $validated['warehouse_id'],
                    'currency_id' => $validated['currency_id'],
                    'item_count' => count($validated['item_list']),
                    'transaction_total' => $totals['total'],
                ],
                metadata: [
                    'action' => 'sale_store',
                    'sale_type' => $validated['type'],
                    'transaction_id' => $transaction->id,
                ],
            );

            if ($postImmediately) {
                $this->markSaleOrderCompleted($validated['sale_order_id'] ?? null);
            }

            return $sale;
        });

        if ((bool) $request->create_and_new) {
            return redirect()->back()->with(
                'success',
                __('general.created_successfully', ['resource' => __('general.resource.sale')])
            );
        }

        $redirect = redirect()->route('sales.index')->with(
            'success',
            __('general.created_successfully', ['resource' => __('general.resource.sale')])
        );

        if ((bool) $request->create_and_print) {
            $redirect->with('print_url', route('sales.print', $sale));
        }

        return $redirect;
    }

    /**
     * Header fields as the sale row stores them: both dates in Gregorian, and
     * the type under its column name.
     */
    private function prepareSaleHeader(array $validated, ?Sale $sale = null): array
    {
        $validated['date'] = ! empty($validated['date'])
            ? $this->dateConversionService->toGregorian($validated['date'])
            : $sale?->date?->toDateString();
        // The due date arrives in the company calendar like the bill date does;
        // left unconverted, a Jalali due date was stored as a Gregorian one
        // six centuries early.
        $validated['due_date'] = ! empty($validated['due_date'])
            ? $this->dateConversionService->toGregorian($validated['due_date'])
            : null;
        $validated['type'] = $validated['sale_type'] ?? $sale?->type ?? SalePurchaseType::Cash->value;

        return $validated;
    }

    /**
     * Lines as sale_items stores them.
     *
     * The form posts the line discount as item_discount; the column is
     * `discount`. Without the mapping the value is dropped, so the GL records
     * the discount while the sale_items row stores 0 and the sales report
     * cannot subtract it.
     *
     * @return array<int, array<string, mixed>>
     */
    private function prepareSaleLines(array $validated, ?string $companyId): array
    {
        $items = $this->normalizeLineExpiryDates($validated['item_list'], $validated['warehouse_id']);

        return array_map(function ($item) use ($validated, $companyId) {
            $item['discount'] = $this->resolveLineDiscount($item, $validated, $companyId);
            $item['warehouse_id'] = $validated['warehouse_id'];
            $item['variant_id'] = $this->resolveLineVariantId($item);

            return $item;
        }, $items);
    }

    /**
     * What the bill comes to, worked out here rather than taken from the form.
     *
     * The browser used to send the bill total and the discount total and the
     * ledger was posted with whatever arrived — a stale page or a hand-made
     * request could book a receivable that had nothing to do with the lines.
     * The formula is the form's own: a percentage bill discount is taken on
     * the value of goods, before line discounts.
     *
     * @return array{gross: float, line_discount: float, bill_discount: float, discount: float, tax: float, total: float}
     */
    private function computeSaleTotals(array $validated): array
    {
        $gross = 0.0;
        $lineDiscount = 0.0;
        $tax = 0.0;

        foreach ($validated['item_list'] as $item) {
            $gross += (float) $item['quantity'] * (float) $item['unit_price'];
            $lineDiscount += (float) ($item['discount'] ?? 0);
            $tax += (float) ($item['tax'] ?? 0);
        }

        $billDiscountInput = max(0.0, (float) ($validated['discount'] ?? 0));
        $billDiscount = ($validated['discount_type'] ?? 'percentage') === 'percentage'
            ? $gross * ($billDiscountInput / 100)
            : $billDiscountInput;

        $discount = round($lineDiscount + $billDiscount, 4);
        $total = round($gross - $discount + $tax, 4);

        if ($total < 0) {
            throw ValidationException::withMessages([
                'discount' => __('general.discount_exceeds_bill_total'),
            ]);
        }

        return [
            'gross' => round($gross, 4),
            'line_discount' => round($lineDiscount, 4),
            'bill_discount' => round($billDiscount, 4),
            'discount' => $discount,
            'tax' => round($tax, 4),
            'total' => $total,
        ];
    }

    /**
     * A payment taken with the sale has to fit the bill and name where the
     * money went. Both used to reach the ledger unchecked and fail there as an
     * unbalanced voucher.
     */
    private function assertSalePayment(array $validated, array $totals): void
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
     * Write the sale's lines, its stock and its voucher — once, for a new sale,
     * an edited draft and everything in between.
     *
     * A posted sale takes its stock now and is costed from the movements that
     * actually left the shelf. A draft only reserves, and carries an estimate
     * that post() replaces with the real figure on the day it is posted.
     */
    private function writeSaleDocument(
        Sale $sale,
        array $validated,
        array $totals,
        bool $postNow,
        TransactionService $transactionService,
        StockService $stockService,
    ): Transaction {
        [$itemModelsById, $averageCostsByItemId, $unitValuesById, $averageCostsByVariantId] = $this->buildSaleItemCostLookup($validated['item_list']);

        // Every transaction_line is expressed in the transaction's currency; reports
        // multiply by transactions.rate to get home currency. Selling prices already
        // come in that currency, but stock cost is always home currency, so COGS and
        // the inventory credit have to be divided back down by the rate.
        $rate = $this->transactionRate($validated);
        $lines = [];
        $stockPayloads = [];

        foreach (array_values($validated['item_list']) as $index => $item) {
            $itemModel = $itemModelsById[$item['item_id']] ?? null;
            if (! $itemModel) {
                throw (new \Illuminate\Database\Eloquent\ModelNotFoundException())->setModel(Item::class, [$item['item_id']]);
            }

            // Free units leave the shelf too: they cost what the sold ones cost,
            // and carry no revenue.
            $stockQuantity = $this->lineStockQuantity($item);
            $unitCost = $this->resolveItemCostByMethod(
                avgCost: $this->resolveLineBaseCost(
                    $item['variant_id'] ?? null,
                    $item['item_id'],
                    $averageCostsByVariantId,
                    $averageCostsByItemId,
                ),
                selectedUnitMeasureId: $item['unit_measure_id'],
                itemUnitMeasureId: $itemModel->unit_measure_id,
                unitValuesById: $unitValuesById,
                itemId: $item['item_id'],
                warehouseId: $validated['warehouse_id'],
                quantity: $stockQuantity,
                costingMethod: $itemModel->effectiveCostingMethod()->value,
                variantId: $item['variant_id'] ?? null,
                batch: $item['batch'] ?? null,
                expireDate: $item['expire_date'] ?? null,
            );
            $totalCost = $unitCost * $stockQuantity;

            $saleItem = $sale->items()->create([...$item, 'net_unit_cost' => $unitCost]);

            $payload = [
                'item_id'              => $item['item_id'],
                'movement_type'        => StockMovementType::OUT->value,
                'unit_measure_id'      => $item['unit_measure_id'],
                'quantity'             => $stockQuantity,
                'source'               => StockSourceType::SALE->value,
                'unit_cost'            => $unitCost,
                'unit_cost_override'   => $unitCost,
                'status'               => $postNow ? StockStatus::POSTED->value : StockStatus::DRAFT->value,
                'batch'                => $item['batch'] ?? null,
                'date'                 => $validated['date'],
                'expire_date'          => $item['expire_date'] ?? null,
                'variant_id'           => $item['variant_id'] ?? null,
                'warehouse_id'         => $validated['warehouse_id'],
                'reference_type'       => Sale::class,
                'reference_id'         => $sale->id,
                // Not a stock column: lets post() find the line this movement
                // belongs to when it re-costs a draft.
                'sale_item_id'         => $saleItem->id,
            ];

            if ($postNow) {
                [$unitCost, $totalCost] = $this->postSaleStock($payload, $stockService);
                $payload['unit_cost'] = $unitCost;
                $payload['unit_cost_override'] = $unitCost;
                $saleItem->update(['net_unit_cost' => $unitCost]);
            } else {
                // Draft: hold the stock as reserved_out so it is visible to other users.
                $stockService->reserve($this->stockPayloadOnly($payload));
            }

            $stockPayloads[] = $payload;

            array_push($lines, ...$this->saleItemLines(
                sale: $sale,
                itemModel: $itemModel,
                index: $index,
                grossTotal: (float) $item['quantity'] * (float) $item['unit_price'],
                cost: $totalCost / $rate,
            ));
        }

        [$settlementLines, $paymentStatus] = $this->saleSettlementLines($sale, $validated, $totals);
        array_push($lines, ...$settlementLines);

        $sale->update(['payment_status' => $paymentStatus->value]);

        return $transactionService->post(
            header: [
                'currency_id'    => $validated['currency_id'],
                'rate'           => $validated['rate'],
                'voucher_number' => 'Sale #' . $sale->number,
                'date'           => $validated['date'],
                'remark'         => 'Sale number: ' . $sale->number,
                'status'         => $postNow ? TransactionStatus::POSTED->value : TransactionStatus::DRAFT->value,
                'reference_type' => Sale::class,
                'reference_id'   => $sale->id,
                'posting_payload' => [
                    'stock_movements' => $stockPayloads,
                    // How the sale was paid. A draft keeps its lines off the
                    // ledger, so this is the only place the edit form can read
                    // the chosen account and the amount taken back from.
                    'sale' => [
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
     * The three ledger lines one sale line writes. `line_role` and
     * `line_index` are not ledger columns; they let post() find the cost
     * lines of a draft again and replace the estimate.
     *
     * @return array<int, array<string, mixed>>
     */
    private function saleItemLines(Sale $sale, Item $itemModel, int $index, float $grossTotal, float $cost): array
    {
        $name = $itemModel->name;
        $number = $sale->number;

        return [
            [
                'account_id' => $itemModel->income_account_id,
                'ledger_id'  => null,
                'debit'      => 0,
                'credit'     => $grossTotal,
                'remark'     => "Sale income for item {$name} #{$number}",
                'remark_fa'  => "عاید فروش {$name} #{$number}",
                'remark_ps'  => "د {$name} د پلور عاید #{$number}",
                'line_role'  => 'income',
                'line_index' => $index,
            ],
            [
                'account_id' => $itemModel->cost_account_id,
                'ledger_id'  => null,
                'debit'      => $cost,
                'credit'     => 0,
                'remark'     => "Cost of goods sold for item {$name} #{$number}",
                'remark_fa'  => "قیمت تمام‌شدهٔ جنس فروخته‌شده {$name} #{$number}",
                'remark_ps'  => "د {$name} د پلورل شویو توکو لګښت #{$number}",
                'line_role'  => 'cogs',
                'line_index' => $index,
            ],
            [
                'account_id' => $itemModel->asset_account_id,
                'ledger_id'  => null,
                'debit'      => 0,
                'credit'     => $cost,
                'remark'     => "Inventory out for item {$name} #{$number}",
                'remark_fa'  => "خروج جنس {$name} از گدام #{$number}",
                'remark_ps'  => "د {$name} وتل له ګودام څخه #{$number}",
                'line_role'  => 'inventory',
                'line_index' => $index,
            ],
        ];
    }

    /**
     * The discount line and the side of the voucher that says who owes what,
     * with the payment status that follows from it.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: PaymentStatus}
     */
    private function saleSettlementLines(Sale $sale, array $validated, array $totals): array
    {
        $glAccounts = CompanyContext::glAccounts();
        $number = $sale->number;
        $total = $totals['total'];
        $lines = [];

        if ($totals['discount'] > 0) {
            $lines[] = [
                'account_id' => $glAccounts['discount-to-customer'],
                'ledger_id'  => null,
                'debit'      => $totals['discount'],
                'credit'     => 0,
                'remark'     => "Sales discount for sale #{$number}",
                'remark_fa'  => "تخفیف فروش #{$number}",
                'remark_ps'  => "د پلور تخفیف #{$number}",
            ];
        }

        $receivable = fn (float $amount, string $en, string $fa, string $ps) => [
            'account_id' => $glAccounts['account-receivable'],
            'ledger_id'  => $validated['customer_id'],
            'debit'      => $amount,
            'credit'     => 0,
            'remark'     => $en,
            'remark_fa'  => $fa,
            'remark_ps'  => $ps,
        ];

        if ($validated['type'] === SalePurchaseType::Cash->value) {
            $lines[] = [
                'account_id' => $validated['bank_account_id'],
                'ledger_id'  => null,
                'debit'      => $total,
                'credit'     => 0,
                'remark'     => "Cash received from sale #{$number}",
                'remark_fa'  => "دریافت نقدی بابت فروش #{$number}",
                'remark_ps'  => "د پلور #{$number} نغدي رسید",
            ];

            return [$lines, PaymentStatus::Paid];
        }

        if ($validated['type'] === SalePurchaseType::OnLoan->value) {
            $lines[] = $receivable(
                $total,
                "Sale on loan #{$number}",
                "فروش قرضی #{$number}",
                "پور پلور #{$number}",
            );

            return [$lines, PaymentStatus::Unpaid];
        }

        // Credit: part of it may be taken now, the rest stays owed.
        $paid = (float) ($validated['payment']['amount'] ?? 0);
        $remaining = round($total - $paid, 4);

        if ($paid > 0) {
            $inFull = $remaining <= 0;
            $lines[] = [
                'account_id' => $validated['payment']['account_id'],
                'ledger_id'  => null,
                'debit'      => $paid,
                'credit'     => 0,
                'remark'     => ($inFull ? 'Payment received' : 'Partial payment received') . " for sale #{$number}",
                'remark_fa'  => ($inFull ? 'دریافت کامل' : 'دریافت قسمتی') . " بابت فروش #{$number}",
                'remark_ps'  => "د پلور #{$number} " . ($inFull ? 'بشپړ رسید' : 'برخه‌ییز رسید'),
            ];
        }

        if ($remaining > 0) {
            $lines[] = $paid > 0
                ? $receivable(
                    $remaining,
                    "Remaining receivable for sale #{$number}",
                    "باقی‌ماندهٔ طلب فروش #{$number}",
                    "د پلور #{$number} پاتې پور",
                )
                : $receivable(
                    $remaining,
                    "Receivable for credit sale #{$number}",
                    "طلب بابت فروش اعتباری #{$number}",
                    "د اعتباري پلور #{$number} پور",
                );
        }

        // Paid in full at the counter is paid, not "partially paid".
        $status = match (true) {
            $remaining <= 0 => PaymentStatus::Paid,
            $paid > 0 => PaymentStatus::PartiallyPaid,
            default => PaymentStatus::Unpaid,
        };

        return [$lines, $status];
    }

    /** A stock payload without the keys that are ours rather than the stock ledger's. */
    private function stockPayloadOnly(array $payload): array
    {
        unset($payload['sale_item_id']);

        return $payload;
    }

    /**
     * Take a sale line's stock and report what it really cost.
     *
     * FIFO and LIFO write one movement per layer they consume, each at that
     * layer's own cost, so the cost of the line is the sum of those movements —
     * not a figure peeked at beforehand, which went wrong whenever a batch or
     * expiry date narrowed the layers or two lines drew on the same item.
     *
     * @return array{0: float, 1: float}  Unit cost (in the line's unit) and total cost, home currency.
     */
    private function postSaleStock(array $payload, StockService $stockService): array
    {
        $result = $stockService->post($this->stockPayloadOnly($payload));

        $totalCost = 0.0;
        $movementIds = [];

        foreach ($result as $row) {
            if ($row instanceof StockMovement) {
                $totalCost += (float) $row->quantity * (float) $row->unit_cost;
            } elseif (is_array($row) && ! empty($row['out_movement_id'])) {
                $movementIds[] = $row['out_movement_id'];
            }
        }

        if ($movementIds !== []) {
            $totalCost += StockMovement::query()
                ->whereIn('id', $movementIds)
                ->get(['id', 'quantity', 'unit_cost'])
                ->sum(fn (StockMovement $movement) => (float) $movement->quantity * (float) $movement->unit_cost);
        }

        $quantity = (float) $payload['quantity'];

        return [$quantity > 0 ? $totalCost / $quantity : (float) $payload['unit_cost'], $totalCost];
    }


    public function show(Request $request, Sale $sale)
    {
        // transaction.lines is required: SaleResource derives receivable_amount / old_balance
        // from the customer ledger lines of this sale's transaction.
        $sale->load([
            'items.item',
            'items.variant',
            'items.unitMeasure',
            'customer',
            'saleOrder:id,number',
            'transaction.currency',
            'transaction.originalTransaction',
            'transaction.reversalTransaction',
            'transaction.lines',
            'createdBy',
            'updatedBy',
            'attachments',
            'returns.items',
        ]);

        $resource = new SaleResource($sale);

        if ($request->expectsJson()) {
            return response()->json(['data' => $resource]);
        }

        return inertia('Sale/Sales/Show', [
            'sale' => $resource,
            'reversal' => $sale->transaction?->reversalTransaction,
            'originalDoc' => $sale->transaction?->originalTransaction,
        ]);
    }

    public function edit(Request $request, Sale $sale)
    {
        if ($sale->status !== TransactionStatus::DRAFT->value) {
            return back()->with('error', __('messages.document.only_draft_can_be_edited'));
        }

        $bankAccounts = (new Account())->getAccountsByAccountTypeSlug('cash-or-bank');

        // Load only what the edit form needs.
        // transaction.lines is needed to find the payment line (account_id + debit).
        // We do NOT load lines.account or lines.ledger — those are not used by the edit form.
        // customer is loaded with minimal columns; the heavy `statement` accessor is not
        // triggered because we don't access it here.
        $sale->load([
            'items.item.unitMeasure',
            'items.item.variants',
            'items.variant',
            'items.unitMeasure',
            'items.warehouse',
            'customer:id,name,type',
            'transaction',
            'transaction.currency:id,code,symbol,is_base_currency,exchange_rate',
            'transaction.lines:id,transaction_id,account_id,ledger_id,debit,credit',
            'attachments',
        ]);

        return inertia('Sale/Sales/Edit', [
            'sale' => new SaleResource($sale),
            'bankAccounts' => $bankAccounts,
            'ledgers' => \App\Http\Resources\Ledger\LedgerOptionResource::collection(
                Ledger::query()
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
                        'is_active',
                        'company_id',
                    ])
                    ->withStatementTotals()
                    ->where('type', 'customer')
                    ->where('is_active', true)
                    // Its own customer stays selectable even if no longer open here.
                    ->where(fn ($q) => $q->availableInBranch(CompanyContext::branchId())->orWhere('id', $sale->customer_id))
                    ->orderByRaw("CASE WHEN code = 'CASH-CUST' THEN 0 ELSE 1 END")
                    ->orderBy('created_at','desc')
                    ->limit(200)
                    ->get()
            ),
        ]);
    }

    public function update(
        SaleUpdateRequest $request,
        Sale $sale,
        TransactionService $transactionService,
        StockService $stockService,
        ActivityLogService $activityLogService,
        AttachmentService $attachmentService
    )
    {
        if ($sale->status !== TransactionStatus::DRAFT->value) {
            return back()->with('error', __('messages.document.only_draft_can_be_edited'));
        }

        $sale->loadMissing('items');

        $beforeState = [
            'number' => $sale->number,
            'customer_id' => $sale->customer_id,
            'date' => $sale->date?->toDateString(),
            'status' => $sale->status,
            'branch_id' => $sale->branch_id,
            'warehouse_id' => $sale->items->first()?->warehouse_id,
            'currency_id' => $sale->transaction?->currency_id,
            'rate' => $sale->transaction?->rate,
            'item_count' => $sale->items->count(),
            'transaction_total' => (float) $sale->saleTotal(),
        ];

        $sale = DB::transaction(function () use ($request, $sale, $transactionService, $stockService, $activityLogService, $attachmentService, $beforeState) {
            $validated = $this->prepareSaleHeader($request->validated(), $sale);
            $validated['status'] = TransactionStatus::DRAFT->value;

            if ($request->hasFile('attachments')) {
                $attachmentService->store($sale, $request->file('attachments'));
            }

            $sale->update($validated);

            // Hand back what the previous version of the draft was holding
            // before anything is rebuilt.
            $transaction = Transaction::query()
                ->where('reference_type', Sale::class)
                ->where('reference_id', $sale->id)
                ->first();

            if ($transaction) {
                foreach ((array) data_get($transaction->posting_payload, 'stock_movements', []) as $oldPayload) {
                    $stockService->release($this->stockPayloadOnly($oldPayload));
                }
                $transaction->lines()->forceDelete();
                $transaction->forceDelete();
            }

            $sale->items()->forceDelete();

            // A draft has moved nothing yet, so its lines are costed afresh on
            // every save: holding on to the figure from the day it was first
            // written booked the goods at a cost the shelf no longer carries.
            $validated['item_list'] = $this->prepareSaleLines($validated, $sale->company_id);
            $totals = $this->computeSaleTotals($validated);
            $this->assertSalePayment($validated, $totals);

            $transaction = $this->writeSaleDocument($sale, $validated, $totals, false, $transactionService, $stockService);

            $activityLogService->logUpdate(
                reference: $sale,
                before: $beforeState,
                after: [
                    'number' => $sale->number,
                    'customer_id' => $sale->customer_id,
                    'date' => $sale->date?->toDateString(),
                    'status' => $sale->status,
                    'branch_id' => $sale->branch_id,
                    'warehouse_id' => $validated['warehouse_id'],
                    'currency_id' => $validated['currency_id'],
                    'rate' => (float) $validated['rate'],
                    'item_count' => count($validated['item_list']),
                    'transaction_total' => $totals['total'],
                ],
                module: 'sale',
                description: "Sale #{$sale->number} updated.",
                metadata: [
                    'action' => 'sale_update',
                    'sale_type' => $validated['type'],
                    'transaction_id' => $transaction->id,
                ],
            );

            return $sale;
        });

        $redirect = redirect()->route('sales.index')->with('success', __('general.updated_successfully', ['resource' => __('general.resource.sale')]));

        if ($request->boolean('save_and_print')) {
            $redirect->with('print_url', route('sales.print', $sale));
        }

        return $redirect;
    }

    /**
     * Home-currency multiplier for the transaction: 1 unit of the selected currency
     * equals `rate` units of the company currency. Falls back to 1 so a missing or
     * zeroed rate can never blow up the COGS conversion.
     */
    private function transactionRate(array $validated): float
    {
        $rate = (float) ($validated['rate'] ?? 1);

        return $rate > 0 ? $rate : 1.0;
    }

    /**
     * The discount stored on a sale line.
     *
     * The figure that arrives with the line always wins — the salesperson
     * standing in front of the customer overrules any rule, and the sale form
     * has already shown them what the rules offered. The resolver is only a
     * backstop for callers that send no discount at all. The bill-level
     * discount is a separate concept and is not consulted here.
     */
    private function resolveLineDiscount(array $item, array $validated, ?string $companyId): float
    {
        // The sale form resolves the rule itself and shows the figure in the
        // line's discount box, so the presence of the field — whatever it holds
        // — is a decision someone made while looking at it. An emptied box is
        // "no discount", not "choose one for me": it reaches here as null,
        // because ConvertEmptyStringsToNull rewrites '' on the way in and the
        // validation rule is nullable. Treating that as zero is what stops a
        // deleted discount from quietly coming back.
        //
        // Only a caller that omits the field entirely (an import, an API
        // client) still has the rule applied on its behalf.
        if (array_key_exists('item_discount', $item)) {
            return (float) $item['item_discount'];
        }

        return app(DiscountRuleResolver::class)->discountFor(
            itemId: $item['item_id'],
            quantity: (float) ($item['quantity'] ?? 0),
            unitPrice: (float) ($item['unit_price'] ?? 0),
            ledgerId: $validated['customer_id'] ?? null,
            companyId: $companyId,
            onDate: $validated['date'] ?? null,
        );
    }

    /**
     * An expiry date on a sale line has to be one the warehouse actually holds.
     *
     * The form only offers dates read from stock, so this is for everything
     * that is not the form: a stale page, an import, an API client. Dates are
     * also brought to Gregorian here, so the sale line stores the same value
     * the stock movement will.
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizeLineExpiryDates(array $items, string $warehouseId): array
    {
        foreach ($items as $index => $item) {
            if (empty($item['expire_date'])) {
                $items[$index]['expire_date'] = null;
                continue;
            }

            $expireDate = $this->dateConversionService->toGregorian((string) $item['expire_date']);
            $variantId = $item['variant_id'] ?? null;

            $inStock = StockBalance::query()
                ->where('item_id', $item['item_id'])
                ->where('warehouse_id', $warehouseId)
                ->whereDate('expire_date', $expireDate)
                ->where('quantity', '>', 0)
                ->when(! empty($item['batch']), fn ($q) => $q->where('batch', $item['batch']))
                // Stock from before variants existed carries none and counts
                // for whichever variant the line names.
                ->when($variantId, fn ($q) => $q->where(
                    fn ($w) => $w->where('variant_id', $variantId)->orWhereNull('variant_id')
                ))
                ->exists();

            if (! $inStock) {
                throw ValidationException::withMessages([
                    "item_list.{$index}.expire_date" => __('general.expiry_not_in_stock'),
                ]);
            }

            $items[$index]['expire_date'] = $expireDate;
        }

        return $items;
    }

    /**
     * What a sale line takes off the shelf: the units sold plus the units given
     * away with them. Revenue is charged on `quantity` alone, but the free units
     * are just as gone from the warehouse — leaving them out kept stock (and the
     * inventory account) overstated by every giveaway.
     */
    private function lineStockQuantity(array $item): float
    {
        return (float) ($item['quantity'] ?? 0) + max(0.0, (float) ($item['free'] ?? 0));
    }

    private function buildSaleItemCostLookup(array $items): array
    {
        $itemIds = collect($items)
            ->pluck('item_id')
            ->filter()
            ->unique()
            ->values();

        if ($itemIds->isEmpty()) {
            return [[], [], [], []];
        }

        $itemModelsById = Item::query()
            ->whereIn('id', $itemIds)
            // costing_method must be in the select: without it the attribute is
            // null and effectiveCostingMethod() silently falls back to the
            // company default, costing the line by the wrong method.
            ->get(['id', 'name', 'unit_measure_id', 'costing_method', 'income_account_id', 'cost_account_id', 'asset_account_id'])
            ->keyBy('id')
            ->all();
        $averageCostsByItemId = Item::query()
            ->whereIn('id', $itemIds)
            ->pluck('avg_cost', 'id')
            ->map(fn ($value) => (float) $value)
            ->all();

        // A variant carries its own average, blended only from the receipts of
        // that variant. Selling "Red / XL" has to relieve inventory at what the
        // Red / XL stock cost, not at an average smeared across every colour —
        // otherwise COGS and the inventory credit are both wrong, and by
        // opposite amounts on the cheap and the expensive variants.
        $averageCostsByVariantId = ItemVariant::query()
            ->whereIn('item_id', $itemIds)
            ->pluck('avg_cost', 'id')
            ->map(fn ($value) => (float) $value)
            ->all();

        $itemUnitMeasureIds = collect($itemModelsById)
            ->pluck('unit_measure_id');
        $selectedUnitMeasureIds = collect($items)
            ->pluck('unit_measure_id')
            ->filter();
        $allUnitMeasureIds = $itemUnitMeasureIds
            ->merge($selectedUnitMeasureIds)
            ->unique()
            ->values();

        $unitValuesById = UnitMeasure::query()
            ->whereIn('id', $allUnitMeasureIds)
            ->pluck('unit', 'id')
            ->map(fn ($value) => (float) $value)
            ->all();

        return [$itemModelsById, $averageCostsByItemId, $unitValuesById, $averageCostsByVariantId];
    }

    /**
     * The per-base-unit cost a sale line should relieve inventory at.
     *
     * The variant's own average wins whenever it carries one. It can legitimately
     * be zero — a variant whose stock arrived before the column was maintained,
     * or one that has never been received — and pricing goods at nothing would
     * report the whole sale as profit, so a zero falls back to the item figure
     * rather than being trusted.
     */
    private function resolveLineBaseCost(
        ?string $variantId,
        string $itemId,
        array $averageCostsByVariantId,
        array $averageCostsByItemId,
    ): float {
        $variantCost = $variantId ? (float) ($averageCostsByVariantId[$variantId] ?? 0.0) : 0.0;

        if ($variantCost > 0) {
            return $variantCost;
        }

        return (float) ($averageCostsByItemId[$itemId] ?? 0.0);
    }

    private function resolveItemCostByMethod(
        float $avgCost,
        string $selectedUnitMeasureId,
        string $itemUnitMeasureId,
        array $unitValuesById,
        string $itemId,
        string $warehouseId,
        float $quantity,
        ?string $costingMethod = null,
        ?string $variantId = null,
        ?string $batch = null,
        ?string $expireDate = null,
    ): float {
        // The ITEM's method, not the company's. An item may override the
        // company default (Item::effectiveCostingMethod), and StockService
        // deducts the stock on that override — so reading the company default
        // here issued the goods at one cost and booked them at another.
        $method = $costingMethod ?? CompanyContext::costingMethod();

        if ($method !== CostingMethod::FIFO->value && $method !== CostingMethod::LIFO->value) {
            return $this->resolveUnitCost($avgCost, $selectedUnitMeasureId, $itemUnitMeasureId, $unitValuesById);
        }

        $order = $method === CostingMethod::FIFO->value ? 'asc' : 'desc';

        // Convert requested quantity to item base units for layer peeking
        $selectedUomVal = (float) ($unitValuesById[$selectedUnitMeasureId] ?? 1.0) ?: 1.0;
        $itemUomVal     = (float) ($unitValuesById[$itemUnitMeasureId] ?? 1.0) ?: 1.0;
        $convFactor     = $selectedUomVal / $itemUomVal; // selectedUnit / itemUnit
        $qtyInItemUnits = $quantity * $convFactor;

        // For FIFO: mirror the exact ordering used by StockService::deductFIFO()
        // — non-null expiry first, then nearest expiry, then oldest receipt date.
        // For LIFO: reverse only the receipt-date axes; expiry preference stays the same
        // (near-expiry stock should still be moved before distant-expiry stock).
        $layers = StockMovement::query()
            ->where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->where('movement_type', StockMovementType::IN->value)
            ->where('qty_remaining', '>', 0)
            // Voided layers are not consumable (StockService::consumeLayers
            // skips them), so peeking at them costs the line off stock the
            // business never ended up owning — the ledger then disagrees with
            // the movement the document actually posted.
            ->whereNotIn('status', [StockStatus::VOIDED->value, StockStatus::CANCELLED->value])
            // Peek at the SAME layers StockService::deductFIFO() will consume —
            // it filters by variant, so leaving this unfiltered costed the line
            // from another variant's receipts and left the GL disagreeing with
            // the movement the sale actually posted.
            ->when(
                $variantId !== null && $variantId !== '',
                fn ($q) => $q->where('variant_id', $variantId),
                fn ($q) => $q->whereNull('variant_id'),
            )
            // A line that names a batch or an expiry date only draws on those
            // layers (StockService::consumeLayers), so only those are peeked at.
            ->when(! empty($batch), fn ($q) => $q->where('batch', $batch))
            ->when(! empty($expireDate), fn ($q) => $q->whereDate('expire_date', $expireDate))
            ->orderByRaw('CASE WHEN expire_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expire_date', 'asc')
            ->orderBy('date', $order)
            ->orderBy('created_at', $order)
            ->orderBy('id', $order)
            ->get(['qty_remaining', 'unit_cost', 'unit_measure_id']);

        if ($layers->isEmpty()) {
            return $this->resolveUnitCost($avgCost, $selectedUnitMeasureId, $itemUnitMeasureId, $unitValuesById);
        }

        // Preload any unit measure values not already in the lookup
        $missingUomIds = $layers->pluck('unit_measure_id')->unique()->filter()
            ->filter(fn ($id) => !isset($unitValuesById[$id]))->values()->all();
        if (!empty($missingUomIds)) {
            $extra = UnitMeasure::whereIn('id', $missingUomIds)->pluck('unit', 'id')
                ->map(fn ($v) => (float) $v)->all();
            $unitValuesById = array_merge($unitValuesById, $extra);
        }

        $remaining           = $qtyInItemUnits;
        $totalCostItemUnit   = 0.0;
        $totalQtyConsumed    = 0.0;

        foreach ($layers as $layer) {
            if ($remaining <= 0) {
                break;
            }
            $chunk = min((float) $layer->qty_remaining, $remaining);

            // layer.unit_cost is stored in the layer's purchase unit; convert to item base unit
            $layerUomVal      = (float) ($unitValuesById[$layer->unit_measure_id] ?? 1.0) ?: 1.0;
            $layerConvFactor  = $layerUomVal / $itemUomVal; // purchaseUnit / itemUnit
            $costPerItemUnit  = $layerConvFactor > 0
                ? (float) $layer->unit_cost / $layerConvFactor
                : (float) $layer->unit_cost;

            $totalCostItemUnit += $chunk * $costPerItemUnit;
            $totalQtyConsumed  += $chunk;
            $remaining         -= $chunk;
        }

        if ($totalQtyConsumed <= 0) {
            return $this->resolveUnitCost($avgCost, $selectedUnitMeasureId, $itemUnitMeasureId, $unitValuesById);
        }

        $costPerItemUnit = $totalCostItemUnit / $totalQtyConsumed;

        return $this->resolveUnitCost($costPerItemUnit, $selectedUnitMeasureId, $itemUnitMeasureId, $unitValuesById);
    }

    private function resolveUnitCost(
        float $avgCost,
        string $selectedUnitMeasureId,
        string $itemUnitMeasureId,
        array $unitValuesById
    ): float {
        if ($selectedUnitMeasureId === $itemUnitMeasureId) {
            return $avgCost;
        }

        $selectedUnit = (float) ($unitValuesById[$selectedUnitMeasureId] ?? 0);
        $itemUnit = (float) ($unitValuesById[$itemUnitMeasureId] ?? 0);
        if ($itemUnit === 0.0) {
            return $avgCost;
        }

        return ($selectedUnit * $avgCost) / $itemUnit;
    }

    public function post(Sale $sale, TransactionService $transactionService, StockService $stockService)
    {
        $this->authorize('update', $sale);

        if ($sale->status !== TransactionStatus::DRAFT->value) {
            abort(422, __('messages.document.only_draft_can_be_posted'));
        }

        $enforceReservation = (bool) user_preference('sale.enforce_sale_stock_reservation', false);

        try {
            DB::transaction(function () use ($sale, $transactionService, $stockService, $enforceReservation) {
                $transaction = $sale->transaction()->firstOrFail();
                $payloads = array_values((array) data_get($transaction->posting_payload, 'stock_movements', []));
                $lines = array_values((array) data_get($transaction->posting_payload, 'lines', []));
                $rate = (float) $transaction->rate > 0 ? (float) $transaction->rate : 1.0;

                foreach ($payloads as $index => $payload) {
                    try {
                        // When enforcement is on, block posting if another draft already
                        // holds the stock. Checked before releasing this draft's own
                        // reservation so a document is never blocked by itself.
                        if ($enforceReservation) {
                            $stockService->ensureReservedAvailability($this->stockPayloadOnly($payload));
                        }

                        // The reservation goes back under the keys it was made with.
                        $stockService->release($this->stockPayloadOnly($payload));

                        // The payload was frozen when the draft was written — its
                        // status still says "draft" and its cost is that day's. The
                        // goods leave today, at what the shelf holds them at today.
                        $payload['status'] = StockStatus::POSTED->value;
                        $freshCost = $this->freshUnitCostFor($payload);
                        $payload['unit_cost'] = $freshCost;
                        $payload['unit_cost_override'] = $freshCost;

                        [$unitCost, $totalCost] = $this->postSaleStock($payload, $stockService);
                    } catch (ValidationException $e) {
                        // Stock was sufficient when this draft was created, but another
                        // posted invoice has since consumed it. Surface a clear, item-named
                        // message instead of the raw "Insufficient stock." validation error.
                        $itemName = Item::find($payload['item_id'] ?? null)?->name ?? ($payload['item_id'] ?? '');
                        throw ValidationException::withMessages([
                            'stock' => __('general.cannot_post_insufficient_stock', ['item' => $itemName]),
                        ]);
                    }

                    $payload['unit_cost'] = $unitCost;
                    $payload['unit_cost_override'] = $unitCost;
                    $payloads[$index] = $payload;

                    $lines = $this->recostDraftLines($lines, $index, count($payloads), $totalCost / $rate);

                    if (! empty($payload['sale_item_id'])) {
                        SaleItem::whereKey($payload['sale_item_id'])->update(['net_unit_cost' => $unitCost]);
                    }
                }

                $transaction->update([
                    'posting_payload' => array_merge((array) $transaction->posting_payload, [
                        'stock_movements' => $payloads,
                        'lines' => $lines,
                    ]),
                ]);

                $transactionService->postDraft($transaction, $lines);
                $sale->update([
                    'status' => TransactionStatus::POSTED->value,
                    'updated_by' => Auth::id(),
                ]);

                $this->markSaleOrderCompleted($sale->sale_order_id);
            });
        } catch (ValidationException $e) {
            // Transaction rolled back, so the sale stays a draft and stock is untouched.
            return redirect()->back()->with('error', $e->validator->errors()->first('stock') ?: $e->getMessage());
        }

        return redirect()->back()->with('success', __('general.updated_successfully', ['resource' => __('general.resource.sale')]));
    }

    /** What a stored stock payload's line costs per unit as the shelf stands now. */
    private function freshUnitCostFor(array $payload): float
    {
        [$itemModelsById, $averageCostsByItemId, $unitValuesById, $averageCostsByVariantId] = $this->buildSaleItemCostLookup([$payload]);
        $itemModel = $itemModelsById[$payload['item_id']] ?? null;

        if (! $itemModel) {
            return (float) ($payload['unit_cost'] ?? 0);
        }

        return $this->resolveItemCostByMethod(
            avgCost: $this->resolveLineBaseCost(
                $payload['variant_id'] ?? null,
                $payload['item_id'],
                $averageCostsByVariantId,
                $averageCostsByItemId,
            ),
            selectedUnitMeasureId: $payload['unit_measure_id'],
            itemUnitMeasureId: $itemModel->unit_measure_id,
            unitValuesById: $unitValuesById,
            itemId: $payload['item_id'],
            warehouseId: $payload['warehouse_id'],
            quantity: (float) $payload['quantity'],
            costingMethod: $itemModel->effectiveCostingMethod()->value,
            variantId: $payload['variant_id'] ?? null,
            batch: $payload['batch'] ?? null,
            expireDate: $payload['expire_date'] ?? null,
        );
    }

    /**
     * Put a line's real cost on the two ledger lines that carry it.
     *
     * Lines written by writeSaleDocument() say which sale line and which role
     * they are. A draft saved before that only has their order to go by: three
     * per sale line — income, cost of goods, inventory — ahead of everything else.
     */
    private function recostDraftLines(array $lines, int $index, int $lineCount, float $cost): array
    {
        $tagged = collect($lines)->contains(fn ($line) => isset($line['line_role']));

        if (! $tagged && count($lines) < $lineCount * 3) {
            return $lines;
        }

        foreach ($lines as $position => $line) {
            if ($tagged) {
                $role = $line['line_role'] ?? null;
                $lineIndex = $line['line_index'] ?? null;
            } else {
                $lineIndex = $position < $lineCount * 3 ? intdiv($position, 3) : null;
                $role = [0 => 'income', 1 => 'cogs', 2 => 'inventory'][$position % 3];
            }

            if ($lineIndex !== $index) {
                continue;
            }

            if ($role === 'cogs') {
                $lines[$position]['debit'] = $cost;
            } elseif ($role === 'inventory') {
                $lines[$position]['credit'] = $cost;
            }
        }

        return $lines;
    }

    /**
     * When a Sale created from a Sales Order gets posted, the order is fully consumed
     * (whole-order conversion only) so it flips to Completed to prevent reuse.
     */
    private function markSaleOrderCompleted(?string $saleOrderId): void
    {
        if (!$saleOrderId) {
            return;
        }

        SaleOrder::where('id', $saleOrderId)->update([
            'status' => SaleOrderStatus::COMPLETED->value,
            'updated_by' => Auth::id(),
        ]);
    }

    public function reverse(Request $request, Sale $sale, TransactionService $transactionService)
    {
        $this->authorize('update', $sale);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        if ($sale->status !== TransactionStatus::POSTED->value) {
            abort(422, __('messages.document.only_posted_can_be_reversed'));
        }

        // A return is raised against this document and takes its quantities and
        // costs from it. Reversing the document underneath would leave the
        // return standing on something that no longer happened — the goods it
        // sent back were never received, and its ledger entries reference a
        // voucher that has been undone. The return has to go first.
        $standingReturns = $sale->returns()
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
        // balance would stop adding up. (A cash sale settles itself inside
        // its own voucher and writes no settlement row, so it is untouched by
        // this.)
        $paidBy = DB::table('settlements as s')
            ->join('transaction_lines as tl', 'tl.id', '=', 's.target_line_id')
            ->join('transactions as target', 'target.id', '=', 'tl.transaction_id')
            ->join('transactions as settling', 'settling.id', '=', 's.transaction_id')
            ->where('target.reference_type', Sale::class)
            ->where('target.reference_id', $sale->id)
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

        DB::transaction(function () use ($sale, $transactionService, $validated) {
            $transaction = $sale->transaction()->firstOrFail();
            $transactionService->reverse($transaction, $validated['reason'], $sale->number, Sale::class);

            $sale->update([
                'status' => TransactionStatus::REVERSED->value,
                'updated_by' => Auth::id(),
            ]);

            // The order this sale delivered is open again: nothing was
            // delivered after all, and it can be billed afresh.
            if ($sale->sale_order_id) {
                SaleOrder::where('id', $sale->sale_order_id)
                    ->where('status', SaleOrderStatus::COMPLETED->value)
                    ->update([
                        'status' => SaleOrderStatus::POSTED->value,
                        'updated_by' => Auth::id(),
                    ]);
            }
        });

        return redirect()->back()->with('success', __('general.updated_successfully', ['resource' => __('general.resource.sale')]));
    }

    public function destroy(Request $request, Sale $sale, ActivityLogService $activityLogService, StockService $stockService)
    {
        if ($sale->status !== TransactionStatus::DRAFT->value) {
            return back()->with('error', __('messages.document.only_draft_can_be_deleted'));
        }

        DB::transaction(function () use ($sale, $activityLogService, $stockService) {
            $sale->loadMissing(['items.warehouse', 'transaction.currency', 'customer', 'branch']);
            $transaction = $sale->transaction;

            // Release the stock this draft was holding as reserved_out.
            foreach ((array) data_get($transaction?->posting_payload, 'stock_movements', []) as $payload) {
                $stockService->release($this->stockPayloadOnly($payload));
            }

            $oldValues = [
                'number' => $sale->number,
                'customer' => $sale->customer?->name,
                'date' => $sale->date?->toDateString(),
                'status' => $sale->status,
                'branch' => $sale->branch?->name,
                'warehouse' => $sale->warehouse()?->name,
                'currency' => $transaction?->currency?->name,
                'rate' => $transaction?->rate,
                'item_count' => $sale->items->count(),
                'transaction_total' => (float) $sale->saleTotal(),
            ];

            $sale->items()->delete();
            $sale->delete();
            // The draft voucher goes to the bin with its sale. Left behind it
            // was a voucher for a document nobody could open any more.
            $transaction?->delete();

            $activityLogService->logDelete(
                reference: $sale,
                module: 'sale',
                description: "Sale #{$sale->number} deleted.",
                oldValues: $oldValues,
                metadata: [
                    'action' => 'sale_delete',
                ],
            );
        });

        return redirect()->route('sales.index')->with('success', __('general.sale_deleted_successfully'));
    }

    public function restore(Request $request, Sale $sale, ActivityLogService $activityLogService, StockService $stockService)
    {
        DB::transaction(function () use ($sale, $activityLogService, $stockService) {
            $sale->restore();
            $sale->items()->withTrashed()->restore();

            $transaction = Transaction::withTrashed()
                ->where('reference_type', Sale::class)
                ->where('reference_id', $sale->id)
                ->first();

            if ($transaction?->trashed()) {
                $transaction->restore();
            }

            // Only a draft can be deleted, so what comes back is a draft: it
            // holds its stock as a reservation again, exactly as it did before
            // it was deleted. Nothing else about the warehouse is touched.
            if ($sale->status === TransactionStatus::DRAFT->value) {
                foreach ((array) data_get($transaction?->posting_payload, 'stock_movements', []) as $payload) {
                    $stockService->reserve($this->stockPayloadOnly($payload));
                }
            }

            $activityLogService->logAction(
                eventType: 'restored',
                reference: $sale,
                module: 'sale',
                description: "Sale #{$sale->number} restored.",
                newValues: [
                    'number' => $sale->number,
                    'status' => $sale->status,
                ],
                metadata: [
                    'action' => 'sale_restore',
                ],
            );
        });

        return redirect()->route('sales.index')->with('success', __('general.sale_restored_successfully'));
    }

    public function forceDelete(Request $request, Sale $sale)
    {
        app(\App\Services\DeletedRecordService::class)->forceDelete('sales', (string) $sale->id);

        return redirect()->route('sales.index')->with('success', __('general.permanently_deleted_successfully', ['resource' => __('general.resource.sale')]));
    }

    /**
     * Open receivables for a customer.
     *
     * Kept as an alias of settlements.open-items so existing links do not
     * break. The claims are journal LINES now, not sales — an opening balance
     * or a manual journal debit to AR shows up here on equal footing with an
     * invoice, which is the whole point of settling against lines.
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

    public function print(Request $request, Sale $sale, ActivityLogService $activityLogService)
    {
        $company = CompanyContext::company();
        $sale = $sale->load([
            'customer',
            'items.item',
            'items.unitMeasure',
            'items.warehouse',
            'transaction.currency',
            'transaction.lines',
        ]);

        $activityLogService->logAction(
            eventType: 'print',
            reference: $sale,
            module: 'sale',
            description: "Sale #{$sale->number} printed.",
            metadata: [
                'action' => 'sale_print',
            ],
        );

        $invoiceTheme = $company?->invoice_theme ?: InvoiceThemeOptions::DEFAULT;
        $customFormat = null;

        // If the selected theme is not a built-in format, treat it as a custom InvoiceFormat ULID
        if (!in_array($invoiceTheme, InvoiceThemeOptions::ids()) && $company) {
            $customFormat = InvoiceFormat::where('id', $invoiceTheme)
                ->where('company_id', $company->id)
                ->first();

            // Fall back to default built-in if the custom format doesn't exist
            if (!$customFormat) {
                $invoiceTheme = InvoiceThemeOptions::DEFAULT;
            }
        }

        return inertia('Sale/Sales/Print', [
            'invoice' => new SaleResource($sale),
            'company' => $company,
            'invoiceTheme' => $invoiceTheme,
            'customFormat' => $customFormat,
        ]);

    }

    public function exportDetail(Request $request, Sale $sale, SpreadsheetExportService $exporter): BinaryFileResponse
    {
        $this->authorize('view', $sale);

        $sale->load(['customer', 'items.item', 'items.unitMeasure', 'transaction.currency', 'createdBy', 'updatedBy']);

        $stockMovements = StockMovement::where('reference_id', $sale->id)
            ->where('reference_type', Sale::class)
            ->where('movement_type', StockMovementType::OUT)
            ->get(['item_id', 'unit_cost', 'quantity'])
            ->keyBy('item_id');

        $rtl = in_array(app()->getLocale(), ['fa', 'ps'], true);
        $company = CompanyContext::company();
        $companyName = match (app()->getLocale()) {
            'fa'    => $company?->name_fa ?: $company?->name_en ?: $company?->abbreviation ?: config('app.name'),
            'ps'    => $company?->name_pa ?: $company?->name_en ?: $company?->abbreviation ?: config('app.name'),
            default => $company?->name_en ?: $company?->abbreviation ?: $company?->name_fa ?: $company?->name_pa ?: config('app.name'),
        };
        $currencySymbol = $sale->transaction?->currency?->symbol ?? '';
        $t = fn (string $group, string $key, string $fallback = '') => $exporter->localeTranslation($group, $key, $fallback);

        $title = $t('sale', 'sale', 'Sale') . ' #' . $sale->number;

        $saleTotal = $sale->items->sum(function ($item) use ($sale) {
            $rowTotal      = (float) $item->quantity * (float) $item->unit_price;
            $itemDiscount  = (float) ($item->discount ?? 0);
            $saleDiscount  = $sale->discount_type === 'percentage'
                ? $rowTotal * ((float) $sale->discount / 100)
                : (float) ($sale->discount ?? 0);
            return $rowTotal - $itemDiscount - $saleDiscount;
        });

        $typeStr   = $sale->type   instanceof \BackedEnum ? $sale->type->value   : (string) ($sale->type   ?? '-');
        $statusStr = $sale->status instanceof \BackedEnum ? $sale->status->value : (string) ($sale->status ?? '-');

        $summaryFields = [
            ['label' => $t('general', 'date',       'Date'),       'value' => $sale->date?->format('Y-m-d') ?? '-'],
            ['label' => $t('general', 'customer',   'Customer'),   'value' => $sale->customer?->name ?? '-'],
            ['label' => $t('general', 'type',        'Type'),       'value' => ucfirst($typeStr)],
            ['label' => $t('general', 'status',      'Status'),     'value' => ucfirst($statusStr)],
            ['label' => $t('general', 'amount',      'Amount'),     'value' => trim($currencySymbol . ' ' . number_format($saleTotal, 2))],
            ['label' => $t('general', 'created_by',  'Created By'), 'value' => $sale->createdBy?->name ?? '-'],
            ['label' => $t('general', 'updated_by',  'Updated By'), 'value' => $sale->updatedBy?->name ?? '-'],
        ];

        $rows = $sale->items->map(function ($item) use ($stockMovements) {
            $movement  = $stockMovements->get($item->item_id);
            $unitCost  = $movement ? (float) $movement->unit_cost : 0.0;
            $qty       = (float) $item->quantity;
            $price     = (float) $item->unit_price;
            $discount  = (float) ($item->discount ?? 0);
            $free      = (float) ($item->free ?? 0);
            $tax       = (float) ($item->tax ?? 0);
            $subtotal  = ($qty * $price) - $discount + $tax;
            $lineCost  = $unitCost * $qty;
            $profit    = $subtotal - $lineCost;

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
                'unit_cost'         => $unitCost,
                'line_profit'       => $profit,
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
            ['key' => 'unit_cost',         'label' => $t('general', 'unit_cost',   'Unit Cost'),'type' => 'money',  'align' => 'right'],
            ['key' => 'line_profit',       'label' => $t('general', 'line_profit', 'Profit/Loss'),'type' => 'money','align' => 'right'],
        ];

        return $exporter->download([
            'filename'          => 'sale-' . $sale->number . '-' . now()->format('Ymd-His') . '.xlsx',
            'sheet_name'        => $title,
            'sheet_title'       => $title,
            'title'             => $title,
            'company_name'      => $companyName,
            'exported_on'       => now()->format('Y m d'),
            'rtl'               => $rtl,
            'include_row_number'=> true,
            'row_number_label'  => $t('report', 'columns.no', '#'),
            'summary_fields'    => $summaryFields,
            'columns'           => $columns,
            'rows'              => $rows,
        ]);
    }

    public function exportList(Request $request, SpreadsheetExportService $exporter)
    {
        $this->authorize('viewAny', Sale::class);

        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'desc');
        $filters = (array) $request->input('filters', []);

        $itemGrossTotal = SaleItem::query()
            ->selectRaw('COALESCE(SUM(quantity * unit_price), 0)')
            ->whereColumn('sale_items.sale_id', 'sales.id')
            ->whereNull('sale_items.deleted_at');
        $itemDiscountTotal = SaleItem::query()
            ->selectRaw('COALESCE(SUM(discount), 0)')
            ->whereColumn('sale_items.sale_id', 'sales.id')
            ->whereNull('sale_items.deleted_at');
        $itemTaxTotal = SaleItem::query()
            ->selectRaw('COALESCE(SUM(tax), 0)')
            ->whereColumn('sale_items.sale_id', 'sales.id')
            ->whereNull('sale_items.deleted_at');

        $sales = Sale::query()
            ->select(['sales.id', 'sales.number', 'sales.customer_id', 'sales.date',
                'sales.discount', 'sales.discount_type', 'sales.type', 'sales.status', 'sales.payment_status'])
            ->selectSub($itemGrossTotal, 'items_gross_total')
            ->selectSub($itemDiscountTotal, 'items_discount_total')
            ->selectSub($itemTaxTotal, 'items_tax_total')
            ->with(['customer:id,name', 'transaction.currency'])
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

        $rows = $sales->map(function ($s) {
            $gross = (float) ($s->items_gross_total ?? 0);
            $itemDiscount = (float) ($s->items_discount_total ?? 0);
            $tax = (float) ($s->items_tax_total ?? 0);
            $billDiscount = $s->discount_type === 'percentage'
                ? $gross * ((float) $s->discount / 100)
                : (float) ($s->discount ?? 0);
            return [
                'number'         => $s->number,
                'customer_name'  => $s->customer?->name ?? '-',
                'payment_status' => ($s->payment_status instanceof PaymentStatus ? $s->payment_status : PaymentStatus::tryFrom((string) $s->payment_status))?->getLabel() ?? (string) $s->payment_status,
                'amount'         => $gross - $itemDiscount - $billDiscount + $tax,
                'currency'       => $s->transaction?->currency?->code ?? '-',
                'rate'           => $s->transaction?->rate !== null ? (float) $s->transaction->rate : '-',
                'date'           => $s->date ? app(\App\Services\DateConversionService::class)->toDisplay($s->date) : '-',
                'type'           => ($s->type instanceof SalePurchaseType ? $s->type : SalePurchaseType::tryFrom((string) $s->type))?->getLabel() ?? (string) $s->type,
                'status'         => $s->status instanceof \BackedEnum ? $s->status->value : (string) $s->status,
            ];
        })->all();

        $label = $t('sale', 'sales', 'Sales');

        return $exporter->download([
            'filename'           => 'sales-' . now()->format('Ymd-His') . '.xlsx',
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
                ['key' => 'customer_name',  'label' => $t('ledger', 'customer.customer', 'Customer'), 'width' => 20],
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
}
